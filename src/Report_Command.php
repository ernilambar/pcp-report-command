<?php
/**
 * Report_Command
 *
 * @package PLG_Command
 */

namespace Nilambar\PCP_Report_Command;

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

		// Group issues by category and type.
		$grouped_issues = [];
		foreach ( $issues as $issue ) {
			$category = $this->get_issue_category( $issue['code'] );
			$type     = $issue['type'];
			$code     = $issue['code'];

			if ( ! isset( $grouped_issues[ $category ] ) ) {
				$grouped_issues[ $category ] = [];
			}
			if ( ! isset( $grouped_issues[ $category ][ $code ] ) ) {
				$grouped_issues[ $category ][ $code ] = [
					'type'    => $type,
					'code'    => $code,
					'message' => $issue['message'],
					'docs'    => $issue['docs'] ?? null,
					'issues'  => [],
				];
			}

			$grouped_issues[ $category ][ $code ]['issues'][] = [
				'file'         => $issue['file'],
				'line'         => $issue['line'],
				'column'       => $issue['column'],
				'has_location' => ( $issue['line'] > 0 ),
			];
		}

		$data = [
			'categories' => array_map(
				function ( $category, $category_issues ) {
					return [
						'name'   => $category,
						'types'  => array_values( $category_issues ),
					];
				},
				array_keys( $grouped_issues ),
				array_values( $grouped_issues )
			),
		];

		return $data;
	}

	/**
	 * Gets the category for an issue based on its code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Issue code.
	 * @return string Category name.
	 */
	private function get_issue_category( string $code ): string {
		// Map issue codes to categories based on the reference format.
		$category_map = [
			// Files and folders category.
			'empty_file'                    => 'Files and folders',
			'zip_filename'                  => 'Files and folders',
			'unconventional_main_filename'  => 'Files and folders',

			// Internationalization category.
			'WordPress.WP.I18n.TextDomainMismatch' => 'Internationalization',
			'WordPress.WP.I18n.MissingTextDomain'  => 'Internationalization',
			'WordPress.WP.I18n.NonSingularStringLiteral' => 'Internationalization',

			// Default category for unknown codes.
			'default' => 'Other',
		];

		// Check if the code exists in our map.
		if ( isset( $category_map[ $code ] ) ) {
			return $category_map[ $code ];
		}

		// Check for WordPress coding standards patterns.
		if ( strpos( $code, 'WordPress.' ) === 0 ) {
			$parts = explode( '.', $code );
			if ( count( $parts ) >= 3 ) {
				$section = $parts[1];
				switch ( $section ) {
					case 'WP':
						return 'WordPress Coding Standards';
					case 'CS':
						return 'Coding Standards';
					case 'Security':
						return 'Security';
					default:
						return 'WordPress Coding Standards';
				}
			}
		}

		// Default category.
		return 'Other';
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
