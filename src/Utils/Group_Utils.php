<?php
/**
 * Group_Utils
 *
 * @package PCP_Report_Command
 */

declare(strict_types=1);

namespace Nilambar\PCP_Report_Command\Utils;

use Exception;
use JsonSchema\Validator;

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
		if ( ! file_exists( $rules_file ) ) {
			return [];
		}

		$validator = new Validator();

		$json_content = file_get_contents( $rules_file );
		$groups       = json_decode( $json_content, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return [];
		}

		// Validate against schema if available.
		$schema_file = dirname( dirname( $rules_file ) ) . '/data/groups-schema.json';
		if ( file_exists( $schema_file ) ) {
			$schema = json_decode( file_get_contents( $schema_file ), true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$validation_result = self::validate_json_schema( $groups, $schema, $validator );
				if ( ! $validation_result['valid'] ) {
					// Log validation error but don't fail completely - use existing data.
					error_log( 'Groups configuration validation failed: ' . $validation_result['error'] );
					// Continue with existing data even if validation fails.
				}
			}
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
	 * Validates JSON data against a JSON schema using jsonrainbow/json-schema library.
	 *
	 * @since 1.0.0
	 *
	 * @param array     $data      Data to validate.
	 * @param array     $schema    Schema to validate against.
	 * @param Validator $validator JSON Schema validator instance.
	 * @return array Validation result with 'valid' boolean and 'error' string.
	 */
	public static function validate_json_schema( array $data, array $schema, Validator $validator ): array {
		try {
			// Convert data and schema to objects for jsonrainbow/json-schema.
			$data_object   = json_decode( wp_json_encode( $data ) );
			$schema_object = json_decode( wp_json_encode( $schema ) );

			// Validate data against schema.
			$validator->validate( $data_object, $schema_object );

			if ( $validator->isValid() ) {
				return [
					'valid' => true,
					'error' => '',
				];
			}

			// Collect validation errors.
			$errors           = [];
			$validator_errors = $validator->getErrors();

			if ( is_array( $validator_errors ) ) {
				foreach ( $validator_errors as $error ) {
					if ( is_array( $error ) ) {
						$property = $error['property'] ?? '';
						$message  = $error['message'] ?? '';
					} else {
						$property = '';
						$message  = (string) $error;
					}

					if ( ! empty( $property ) ) {
						$errors[] = "{$property}: {$message}";
					} else {
						$errors[] = $message;
					}
				}
			}

			return [
				'valid' => false,
				'error' => implode( '; ', $errors ),
			];

		} catch ( Exception $e ) {
			return [
				'valid' => false,
				'error' => 'Schema validation error: ' . $e->getMessage(),
			];
		}
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
						'message' => esc_html( $issue['message'] ),
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
						'message' => esc_html( $issue['message'] ),
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
				$processed_errors = array_values( $category['errors'] );
				foreach ( $processed_errors as &$error ) {
					$total_files                  = count( $error['issues'] );
					$is_single                    = $total_files === 1;
					$error['has_multiple_files']  = ! $is_single;
					$error['total_files']         = $total_files;
					$error['has_more_than_three'] = $total_files > self::MAX_DISPLAYED_FILES;

					// Limit displayed files to first MAX_DISPLAYED_FILES if more than MAX_DISPLAYED_FILES.
					if ( $total_files > self::MAX_DISPLAYED_FILES ) {
						$error['displayed_issues'] = array_slice( $error['issues'], 0, self::MAX_DISPLAYED_FILES );
					} else {
						$error['displayed_issues'] = $error['issues'];
					}

					foreach ( $error['displayed_issues'] as &$issue ) {
						$issue['is_single_file'] = $is_single;
					}
				}
				$types = array_merge( $types, $processed_errors );
			}
			if ( ! empty( $category['warnings'] ) ) {
				$processed_warnings = array_values( $category['warnings'] );
				foreach ( $processed_warnings as &$warning ) {
					$total_files                    = count( $warning['issues'] );
					$is_single                      = $total_files === 1;
					$warning['has_multiple_files']  = ! $is_single;
					$warning['total_files']         = $total_files;
					$warning['has_more_than_three'] = $total_files > self::MAX_DISPLAYED_FILES;

					// Limit displayed files to first MAX_DISPLAYED_FILES if more than MAX_DISPLAYED_FILES.
					if ( $total_files > self::MAX_DISPLAYED_FILES ) {
						$warning['displayed_issues'] = array_slice( $warning['issues'], 0, self::MAX_DISPLAYED_FILES );
					} else {
						$warning['displayed_issues'] = $warning['issues'];
					}

					foreach ( $warning['displayed_issues'] as &$issue ) {
						$issue['is_single_file'] = $is_single;
					}
				}
				$types = array_merge( $types, $processed_warnings );
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
			$processed_misc_errors = array_values( $misc_category['errors'] );
			foreach ( $processed_misc_errors as &$error ) {
				$total_files                  = count( $error['issues'] );
				$is_single                    = $total_files === 1;
				$error['has_multiple_files']  = ! $is_single;
				$error['total_files']         = $total_files;
				$error['has_more_than_three'] = $total_files > self::MAX_DISPLAYED_FILES;

				// Limit displayed files to first 3 if more than 3.
				if ( $total_files > self::MAX_DISPLAYED_FILES ) {
					$error['displayed_issues'] = array_slice( $error['issues'], 0, self::MAX_DISPLAYED_FILES );
				} else {
					$error['displayed_issues'] = $error['issues'];
				}

				foreach ( $error['displayed_issues'] as &$issue ) {
					$issue['is_single_file'] = $is_single;
				}
			}
			$misc_types = array_merge( $misc_types, $processed_misc_errors );
		}
		if ( ! empty( $misc_category['warnings'] ) ) {
			$processed_misc_warnings = array_values( $misc_category['warnings'] );
			foreach ( $processed_misc_warnings as &$warning ) {
				$total_files                    = count( $warning['issues'] );
				$is_single                      = $total_files === 1;
				$warning['has_multiple_files']  = ! $is_single;
				$warning['total_files']         = $total_files;
				$warning['has_more_than_three'] = $total_files > self::MAX_DISPLAYED_FILES;

				// Limit displayed files to first 3 if more than 3.
				if ( $total_files > self::MAX_DISPLAYED_FILES ) {
					$warning['displayed_issues'] = array_slice( $warning['issues'], 0, self::MAX_DISPLAYED_FILES );
				} else {
					$warning['displayed_issues'] = $warning['issues'];
				}

				foreach ( $warning['displayed_issues'] as &$issue ) {
					$issue['is_single_file'] = $is_single;
				}
			}
			$misc_types = array_merge( $misc_types, $processed_misc_warnings );
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
}
