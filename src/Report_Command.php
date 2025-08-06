<?php
/**
 * Report_Command
 *
 * @package PLG_Command
 */

namespace Nilambar\PCP_Report_Command;

use JsonSchema\Validator;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Report Command Class.
 *
 * Generates HTML reports for plugin check command results.
 *
 * @since 1.0.0
 */
class Report_Command {

	/**
	 * Group rules file.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $rules_file;

	/**
	 * JSON Schema validator instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Validator|null
	 */
	private $validator = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->validator  = new Validator();
		$this->rules_file = dirname( __DIR__ ) . '/data/groups.json';
	}

	/**
	 * Generates HTML report for "plugin check" command.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to check.
	 *
	 * [--slug=<slug>]
	 * : Slug to override the default.
	 *
	 * [--checks=<checks>]
	 * : Only runs checks provided as an argument in comma-separated values.
	 *
	 * [--exclude-checks=<checks>]
	 * : Exclude checks provided as an argument in comma-separated values, e.g. i18n_usage, late_escaping.
	 * Applies after evaluating `--checks`.
	 *
	 * [--ignore-codes=<codes>]
	 * : Ignore error codes provided as an argument in comma-separated values.
	 *
	 * [--categories]
	 * : Limit displayed results to include only specific categories Checks.
	 *
	 * [--ignore-warnings]
	 * : Limit displayed results to exclude warnings.
	 *
	 * [--ignore-errors]
	 * : Limit displayed results to exclude errors.
	 *
	 * [--include-experimental]
	 * : Include experimental checks.
	 *
	 * [--exclude-directories=<directories>]
	 * : Additional directories to exclude from checks.
	 * By default, `.git`, `vendor` and `node_modules` directories are excluded.
	 *
	 * [--exclude-files=<files>]
	 * : Additional files to exclude from checks.
	 *
	 * [--severity=<severity>]
	 * : Severity level.
	 *
	 * [--error-severity=<error-severity>]
	 * : Error severity level.
	 *
	 * [--warning-severity=<warning-severity>]
	 * : Warning severity level.
	 *
	 * [--grouped]
	 * : Display report in grouped format.
	 *
	 * [--porcelain]
	 * : Output just the report file path.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate report.
	 *     $ wp pcp-report hello-dolly
	 *
	 *     # Generate grouped report.
	 *     $ wp pcp-report hello-dolly --grouped
	 *
	 *     # Get report path only.
	 *     $ wp pcp-report hello-dolly --porcelain=path
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of options.
	 */
	public function __invoke( $args, $assoc_args = [] ) {
		$plugin_slug = isset( $args[0] ) ? $args[0] : '';

		$flags = [
			'ignore-warnings',
			'ignore-errors',
			'include-experimental',
			'grouped',
		];

		$command_text = sprintf( 'plugin check "%s"', esc_html( $plugin_slug ) );

		$check_args = [
			'format' => 'strict-json',
			'fields' => 'file,line,column,type,code,message,docs',
		];

		$porcelain_mode = Utils\get_flag_value( $assoc_args, 'porcelain', false );
		$grouped_mode   = Utils\get_flag_value( $assoc_args, 'grouped', false );
		unset( $assoc_args['porcelain'] );
		unset( $assoc_args['grouped'] );

		$check_args = array_merge( $assoc_args, $check_args );

		foreach ( $check_args as $key => $val ) {
			if ( in_array( $key, $flags, true ) ) {
				$command_text .= " --{$key}";
			} else {
				$command_text .= " --{$key}={$val}";
			}
		}

		$result_obj = WP_CLI::runcommand(
			$command_text,
			[
				'return'     => 'all',
				'launch'     => true,
				'exit_error' => false,
			]
		);

		// Error occurred.
		if ( 1 === $result_obj->return_code ) {
			WP_CLI::line( $result_obj->stderr );
			WP_CLI::halt( 1 );
		}

		$stdout = $result_obj->stdout;

		if ( empty( $stdout ) || str_contains( $stdout, 'Checks complete. No errors found.' ) ) {
			WP_CLI::success( 'No errors or warnings.' );
			return;
		}

		$template_data = $this->prepare_data( $stdout, $grouped_mode );

		$html_content = $this->get_html_content( $template_data, $grouped_mode );

		$report_file = $this->get_reports_folder() . "/{$plugin_slug}.html";

		$status = $this->create_file( $report_file, $html_content );

		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status->get_error_message() );
		}

		if ( true === $porcelain_mode ) {
			WP_CLI::line( $report_file );
			return;
		}

		WP_CLI::log( 'Report file: ' . $report_file );
		WP_CLI::success( 'PCP report generated successfully.' );
	}

	/**
	 * Gets group details for categorizing plugin check issues.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of group definitions.
	 */
	public function get_group_details(): array {
		$config_file = $this->rules_file;

		if ( ! file_exists( $config_file ) ) {
			return [];
		}

		$json_content = file_get_contents( $config_file );
		$groups       = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [];
		}

		// Validate against schema if available.
		$schema_file = dirname( __DIR__ ) . '/data/groups-schema.json';
		if ( file_exists( $schema_file ) ) {
			$schema = json_decode( file_get_contents( $schema_file ), true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$validation_result = $this->validate_json_schema( $groups, $schema );
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
	 * @param array $data   Data to validate.
	 * @param array $schema Schema to validate against.
	 * @return array Validation result with 'valid' boolean and 'error' string.
	 */
	private function validate_json_schema( array $data, array $schema ): array {
		try {
			// Convert data and schema to objects for jsonrainbow/json-schema.
			$data_object   = json_decode( json_encode( $data ) );
			$schema_object = json_decode( json_encode( $schema ) );

			// Validate data against schema.
			$this->validator->validate( $data_object, $schema_object );

			if ( $this->validator->isValid() ) {
				return [
					'valid' => true,
					'error' => '',
				];
			}

			// Collect validation errors.
			$errors           = [];
			$validator_errors = $this->validator->getErrors();

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

		} catch ( \Exception $e ) {
			return [
				'valid' => false,
				'error' => 'Schema validation error: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Groups errors based on predefined categories and patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param array $errors Array of errors to group.
	 * @return array Grouped errors array.
	 */
	private function get_grouped_errors( array $errors ): array {
		$prefix_map   = [];
		$contains_map = [];

		$all_groups = $this->get_group_details();

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
	 * Prepares data for HTML template rendering.
	 *
	 * @since 1.0.0
	 *
	 * @param string $json_data JSON string containing plugin check results.
	 * @param bool   $grouped   Whether to group the data.
	 * @return array Prepared data array for template rendering.
	 */
	private function prepare_data( string $json_data, bool $grouped = false ): array {
		$issues = json_decode( $json_data, true );

		if ( empty( $issues ) ) {
			return [];
		}

		if ( ! $grouped ) {
			$data = [
				'issues' => array_map(
					function ( $issue ) {
						return [
							'file'         => $issue['file'],
							'type'         => $issue['type'],
							'code'         => $issue['code'],
							'line'         => $issue['line'],
							'column'       => $issue['column'],
							'has_location' => ( $issue['line'] > 0 ),
							'message'      => $issue['message'],
							'docs'         => $issue['docs'] ?? null,
						];
					},
					$issues
				),
			];

			return $data;
		}

		// Group issues by category and type with proper sorting.
		$categories = [];
		$all_groups = $this->get_group_details();

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

			$category_id = $this->get_issue_category_id( $code );

			$issue_data = [
				'type'    => strtoupper( $normalized_type ),
				'code'    => $code,
				'message' => $issue['message'],
				'docs'    => $issue['docs'] ?? null,
				'issues'  => [
					[
						'file'         => $issue['file'],
						'line'         => $issue['line'],
						'column'       => $issue['column'],
						'has_location' => ( $issue['line'] > 0 ),
					],
				],
			];

			// Add to appropriate type array within the category.
			if ( 'error' === $normalized_type ) {
				$category_data[ $category_id ]['errors'][ $code ] = $issue_data;
			} elseif ( 'warning' === $normalized_type ) {
				$category_data[ $category_id ]['warnings'][ $code ] = $issue_data;
			}
		}

		// Build final categories in the correct order with proper sorting.
		foreach ( $all_groups as $group_id => $group_details ) {
			$category = $category_data[ $group_id ];
			$types    = [];

			// Add errors first, then warnings.
			if ( ! empty( $category['errors'] ) ) {
				$types = array_merge( $types, array_values( $category['errors'] ) );
			}
			if ( ! empty( $category['warnings'] ) ) {
				$types = array_merge( $types, array_values( $category['warnings'] ) );
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
			$misc_types = array_merge( $misc_types, array_values( $misc_category['errors'] ) );
		}
		if ( ! empty( $misc_category['warnings'] ) ) {
			$misc_types = array_merge( $misc_types, array_values( $misc_category['warnings'] ) );
		}

		if ( ! empty( $misc_types ) ) {
			$categories[] = [
				'name'  => 'Misc Issues',
				'types' => $misc_types,
			];
		}

		$data = [
			'categories' => $categories,
		];

		return $data;
	}

	/**
	 * Gets the category ID for an issue based on its code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Issue code.
	 * @return string Category ID.
	 */
	private function get_issue_category_id( string $code ): string {
		$all_groups = $this->get_group_details();

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
	 * Generates HTML content from template and data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data      Template data array.
	 * @param bool  $grouped   Whether to use grouped template.
	 * @return string Generated HTML content.
	 */
	private function get_html_content( array $data, bool $grouped = false ): string {
		$template_path = dirname( __DIR__ ) . '/templates/';
		$template_name = $grouped ? 'grouped.mustache' : 'default.mustache';

		return Utils\mustache_render( "{$template_path}/{$template_name}", $data );
	}

	/**
	 * Returns reports folder.
	 *
	 * @since 1.0.0
	 *
	 * @return string Reports folder pull path.
	 */
	public static function get_reports_folder() {
		$report_path = Utils\get_cache_dir() . '/pcp-report';

		if ( ! wp_mkdir_p( $report_path ) ) {
			return false;
		}

		return $report_path;
	}

	/**
	 * Writes content to a file using file_put_contents() with proper WordPress error handling
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Full path to the file to be written
	 * @param string $content Content to write to the file
	 * @param bool $overwrite Whether to overwrite existing files (default: true)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	function create_file( $file_path, $content = '', $overwrite = true ) {
		$file_path = wp_normalize_path( $file_path );

		// Check if file exists and overwrite is disabled.
		if ( ! $overwrite && file_exists( $file_path ) ) {
			return new WP_Error(
				'file_exists',
				'File already exists and overwrite is disabled',
				[ 'file' => $file_path ]
			);
		}

		// Ensure directory exists.
		$dir = dirname( $file_path );
		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error(
					'directory_creation_failed',
					'Could not create directory',
					[ 'directory' => $dir ]
				);
			}
		}

		// Verify directory is writable.
		if ( ! is_writable( $dir ) ) {
			return new WP_Error(
				'directory_not_writable',
				'Directory is not writable',
				[ 'directory' => $dir ]
			);
		}

		// Attempt to write the file.
		$result = file_put_contents( $file_path, $content, LOCK_EX );

		if ( $result === false ) {
			$error = error_get_last();
			return new WP_Error(
				'file_write_failed',
				'Could not write file',
				[
					'file'  => $file_path,
					'error' => $error ? $error['message'] : 'Unknown error',
				]
			);
		}

		return true;
	}
}
