<?php
/**
 * Group_Utils
 *
 * @package PCP_Report_Command
 */

declare(strict_types=1);

namespace Nilambar\PCP_Report_Command\Utils;

use WP_CLI;

/**
 * Group_Utils class.
 *
 * @since 1.0.0
 */
class Group_Utils {

	/**
	 * Maximum number of files to display before showing summary.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	const MAX_DISPLAYED_FILES = 3;

	/**
	 * Gets group details for categorizing plugin check issues.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $rules_file Path to the rules file.
	 * @return array Array of group definitions.
	 */
	public static function get_group_details( string $rules_file ): array {
		// Read and validate the group configuration file.
		$groups = JSON_Utils::read_json( $rules_file );
		if ( is_wp_error( $groups ) ) {
			WP_CLI::error( sprintf( 'Invalid group configuration file: %s', $groups->get_error_message() ) );
		}

		// Validate against schema.
		$schema_file       = dirname( dirname( __DIR__ ) ) . '/data/groups-schema.json';
		$validation_result = JSON_Utils::validate_json_data_with_schema( $groups, $schema_file );
		if ( is_wp_error( $validation_result ) ) {
			WP_CLI::error( sprintf( 'Invalid group configuration file: %s', $validation_result->get_error_message() ) );
		}

		// Process groups and their children.
		$processed_groups = [];

		foreach ( $groups as $group_id => $group_data ) {
			// Skip the $schema property.
			if ( '$schema' === $group_id ) {
				continue;
			}

			// Skip non-array group data.
			if ( ! is_array( $group_data ) ) {
				continue;
			}

			// Add parent group.
			$processed_groups[ $group_id ] = [
				'id'    => $group_data['id'] ?? '',
				'title' => $group_data['title'] ?? '',
			];

			// Add child groups if they exist.
			if ( isset( $group_data['children'] ) && is_array( $group_data['children'] ) ) {
				foreach ( $group_data['children'] as $child_id => $child_data ) {
					if ( is_array( $child_data ) ) {
						$processed_groups[ $child_id ] = [
							'id'     => $child_data['id'] ?? '',
							'title'  => $child_data['title'] ?? '',
							'type'   => $child_data['type'] ?? '',
							'parent' => $child_data['parent'] ?? '',
							'checks' => $child_data['checks'] ?? [],
						];
					}
				}
			} else {
				// Direct group without children.
				if ( isset( $group_data['type'] ) ) {
					$processed_groups[ $group_id ]['type'] = $group_data['type'];
				}
				if ( isset( $group_data['checks'] ) ) {
					$processed_groups[ $group_id ]['checks'] = $group_data['checks'];
				}
			}
		}

		return $processed_groups;
	}


	/**
	 * Gets the category ID for an issue based on its code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code       Issue code.
	 * @param array  $all_groups Array of all groups.
	 * @return string Category ID.
	 */
	public static function get_issue_category_id( string $code, array $all_groups ): string {
		foreach ( $all_groups as $group_id => $group_details ) {
			if ( isset( $group_details['checks'] ) ) {
				foreach ( $group_details['checks'] as $check ) {
					if ( str_starts_with( $code, $check ) || str_contains( $code, $check ) ) {
						// Check if this is a child category and return the parent instead.
						if ( isset( $group_details['parent'] ) && ! empty( $group_details['parent'] ) ) {
							return $group_details['parent'];
						}
						return $group_id;
					}
				}
			}
		}

		// Default category if no match found.
		return 'ungrouped';
	}

	/**
	 * Groups errors based on predefined categories and patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param array $errors     Array of errors to group.
	 * @param array $all_groups Array of all groups.
	 * @return array Grouped errors array.
	 */
	public static function get_grouped_errors( array $errors, array $all_groups ): array {
		$prefix_map   = [];
		$contains_map = [];

		$all_prefixes = array_values( wp_list_filter( $all_groups, [ 'type' => 'prefix' ] ) );
		$all_contains = array_values( wp_list_filter( $all_groups, [ 'type' => 'contains' ] ) );

		if ( ! empty( $all_prefixes ) ) {
			foreach ( $all_prefixes as $item ) {
				$prefix_check = reset( $item['checks'] );

				$prefix_map[ $prefix_check ] = $item['id'];
			}
		}

		if ( ! empty( $all_contains ) ) {
			foreach ( $all_contains as $item ) {
				$all_checks = $item['checks'] ?? [];

				foreach ( $all_checks as $check_string ) {
					$contains_map[ $check_string ] = $item['id'];
				}
			}
		}

		$categorized_errors = [
			'ungrouped' => [],
		];

		foreach ( $errors as $key => $value ) {
			$group = 'ungrouped';

			// Check prefixes first.
			foreach ( $prefix_map as $prefix => $group_name ) {
				if ( str_starts_with( $key, $prefix ) ) {
					$group = $group_name;
					break;
				}
			}

			// Check contains if no prefix match.
			if ( 'ungrouped' === $group ) {
				foreach ( $contains_map as $needle => $group_name ) {
					if ( str_contains( $key, $needle ) ) {
						$group = $group_name;
						break;
					}
				}
			}

			$categorized_errors[ $group ][] = [
				'key'    => $key,
				'type'   => $value[0]['type'],
				'values' => $value,
			];

			if ( ! isset( $categorized_errors[ $group ] ) ) {
				$categorized_errors[ $group ] = []; // Ensure group exists.
			}
		}

		// Maintain order based on array order.
		$ordered_errors = [];
		$ungrouped      = $categorized_errors['ungrouped'] ?? [];

		// Add groups in the order they appear.
		foreach ( $all_groups as $group_id => $group_details ) {
			if ( isset( $categorized_errors[ $group_id ] ) && ! empty( $categorized_errors[ $group_id ] ) ) {
				// Sort errors by type: error first, then warning.
				$group_errors = $categorized_errors[ $group_id ];
				usort(
					$group_errors,
					function ( $a, $b ) {
						$type_order = [
							'error'   => 1,
							'warning' => 2,
						];
						$a_order    = $type_order[ $a['type'] ] ?? 3;
						$b_order    = $type_order[ $b['type'] ] ?? 3;
						return $a_order - $b_order;
					}
				);
				$ordered_errors[ $group_id ] = $group_errors;
			}
		}

		// Add ungrouped at the end if it has items.
		if ( ! empty( $ungrouped ) ) {
			// Sort ungrouped errors by type: error first, then warning.
			usort(
				$ungrouped,
				function ( $a, $b ) {
					$type_order = [
						'error'   => 1,
						'warning' => 2,
					];
					$a_order    = $type_order[ $a['type'] ] ?? 3;
					$b_order    = $type_order[ $b['type'] ] ?? 3;
					return $a_order - $b_order;
				}
			);
			$ordered_errors['ungrouped'] = $ungrouped;
		}

		return $ordered_errors;
	}

	/**
	 * Prepares grouped data for template rendering.
	 *
	 * @since 1.0.0
	 *
	 * @param array $issues     Array of issues from plugin check.
	 * @param array $all_groups Array of all group definitions.
	 * @return array Prepared grouped data array for template rendering.
	 */
	public static function prepare_grouped_data( array $issues, array $all_groups ): array {
		$categories = [];

		// Initialize category arrays with only error and warning types.
		$category_data = [];
		foreach ( $all_groups as $group_id => $group_details ) {
			$category_data[ $group_id ] = [
				'name'     => $group_details['title'],
				'errors'   => [],
				'warnings' => [],
			];
		}
		$category_data['ungrouped'] = [
			'name'     => 'Misc Issues',
			'errors'   => [],
			'warnings' => [],
		];

		// Process each issue and assign to appropriate category and type.
		foreach ( $issues as $issue ) {
			$code = $issue['code'];
			$type = $issue['type'];

			// Normalize the type to handle different case variations.
			$normalized_type = strtolower( $type );

			// Only process error and warning types.
			if ( ! in_array( $normalized_type, [ 'error', 'warning' ], true ) ) {
				continue;
			}

			$category_id = self::get_issue_category_id( $code, $all_groups );

			$file_data = [
				'file'         => esc_html( $issue['file'] ),
				'line'         => $issue['line'],
				'column'       => $issue['column'],
				'has_location' => ( $issue['line'] > 0 ),
			];

			// Add to appropriate type array within the category.
			if ( 'error' === $normalized_type ) {
				if ( ! isset( $category_data[ $category_id ]['errors'][ $code ] ) ) {
					$category_data[ $category_id ]['errors'][ $code ] = [
						'type'    => strtoupper( $normalized_type ),
						'code'    => esc_html( $code ),
						'message' => Template_Utils::format_message( $issue['message'] ),
						'docs'    => $issue['docs'] ?? null,
						'issues'  => [],
					];
				}
				$category_data[ $category_id ]['errors'][ $code ]['issues'][] = $file_data;
			} elseif ( 'warning' === $normalized_type ) {
				if ( ! isset( $category_data[ $category_id ]['warnings'][ $code ] ) ) {
					$category_data[ $category_id ]['warnings'][ $code ] = [
						'type'    => strtoupper( $normalized_type ),
						'code'    => esc_html( $code ),
						'message' => Template_Utils::format_message( $issue['message'] ),
						'docs'    => $issue['docs'] ?? null,
						'issues'  => [],
					];
				}
				$category_data[ $category_id ]['warnings'][ $code ]['issues'][] = $file_data;
			}
		}

		// Build final categories in the correct order with proper sorting.
		foreach ( $all_groups as $group_id => $group_details ) {
			$category = $category_data[ $group_id ];
			$types    = [];

			// Add errors first, then warnings.
			if ( ! empty( $category['errors'] ) ) {
				$processed_errors = self::process_issue_types( $category['errors'] );
				$types            = array_merge( $types, $processed_errors );
			}
			if ( ! empty( $category['warnings'] ) ) {
				$processed_warnings = self::process_issue_types( $category['warnings'] );
				$types              = array_merge( $types, $processed_warnings );
			}

			if ( ! empty( $types ) ) {
				$categories[] = [
					'name'  => $category['name'],
					'types' => $types,
				];
			}
		}

		// Add ungrouped items as "Misc Issues" at the end.
		$misc_category = $category_data['ungrouped'];
		$misc_types    = [];

		// Add errors first, then warnings.
		if ( ! empty( $misc_category['errors'] ) ) {
			$processed_misc_errors = self::process_issue_types( $misc_category['errors'] );
			$misc_types            = array_merge( $misc_types, $processed_misc_errors );
		}
		if ( ! empty( $misc_category['warnings'] ) ) {
			$processed_misc_warnings = self::process_issue_types( $misc_category['warnings'] );
			$misc_types              = array_merge( $misc_types, $processed_misc_warnings );
		}

		if ( ! empty( $misc_types ) ) {
			$categories[] = [
				'name'  => 'Misc Issues',
				'types' => $misc_types,
			];
		}

		return [
			'categories' => $categories,
		];
	}

	/**
	 * Processes issue types for template rendering.
	 *
	 * @since 1.0.0
	 *
	 * @param array $issue_types Array of issue types (errors or warnings).
	 * @return array Processed issue types.
	 */
	private static function process_issue_types( array $issue_types ): array {
		return array_values( $issue_types );
	}
}
