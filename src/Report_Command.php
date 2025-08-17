<?php
/**
 * Report_Command
 *
 * @package PCP_Report_Command
 */

namespace Nilambar\PCP_Report_Command;

use Nilambar\Classifier\Classifier;
use Nilambar\Classifier\Utils\JsonUtils;
use Nilambar\PCP_Report_Command\Utils\File_Utils;
use Nilambar\PCP_Report_Command\Utils\Template_Utils;
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
	 * Group configuration file.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $group_config_file;

	/**
	 * Reports folder path.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $reports_folder;

	/**
	 * Templates folder path.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $templates_folder;

	/**
	 * Custom group configuration file path.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	private $custom_group_config_file;

	/**
	 * Report title.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $report_title;

	/**
	 * Classifier instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Classifier|null
	 */
	private $classifier;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->group_config_file        = dirname( __DIR__ ) . '/data/groups.json';
		$this->reports_folder           = $this->get_reports_folder();
		$this->templates_folder         = dirname( __DIR__ ) . '/templates';
		$this->custom_group_config_file = null;
		$this->report_title             = 'Plugin Check Report';
		$this->classifier               = null;
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
	 * [--report-title=<title>]
	 * : Custom title for the report. Use empty string to remove title. Default: 'Plugin Check Report'.
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
	 * [--open]
	 * : Open report in default browser.
	 *
	 * [--porcelain]
	 * : Output just the report file path.
	 *
	 * [--group-config=<group-config>]
	 * : Path to custom group configuration JSON file for grouping plugin check issues.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate report.
	 *     $ wp pcp-report hello-dolly
	 *
	 *     # Generate grouped report.
	 *     $ wp pcp-report hello-dolly --grouped
	 *
	 *     # Generate report with custom group configuration file.
	 *     $ wp pcp-report hello-dolly --group-config=/path/to/custom-groups.json
	 *
	 *     # Get report path only.
	 *     $ wp pcp-report hello-dolly --porcelain
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of options.
	 */
	public function __invoke( $args, $assoc_args = [] ) {
		$plugin_slug = isset( $args[0] ) ? $args[0] : '';

		if ( ! defined( 'WP_PLUGIN_CHECK_VERSION' ) ) {
			WP_CLI::error( 'Plugin Check is not installed/activated.' );
		}

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

		$porcelain_mode    = Utils\get_flag_value( $assoc_args, 'porcelain', false );
		$grouped_mode      = Utils\get_flag_value( $assoc_args, 'grouped', false );
		$open_in_browser   = Utils\get_flag_value( $assoc_args, 'open', false );
		$group_config_file = Utils\get_flag_value( $assoc_args, 'group-config', '' );
		$custom_title      = Utils\get_flag_value( $assoc_args, 'report-title', null );

		if ( null !== $custom_title ) {
			$this->report_title = $custom_title;
		}

		// Set custom group configuration file if provided.
		if ( ! empty( $group_config_file ) ) {
			// Validate that the file exists and is readable.
			try {
				$group_config_data              = JsonUtils::readJson( $group_config_file );
				$this->custom_group_config_file = $group_config_file;
			} catch ( \Exception $e ) {
				WP_CLI::error( sprintf( 'Invalid custom group configuration file: %s', $e->getMessage() ) );
			}
		}

		unset( $assoc_args['porcelain'] );
		unset( $assoc_args['grouped'] );
		unset( $assoc_args['open'] );
		unset( $assoc_args['group-config'] );

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

		if ( '[]' === $stdout || str_contains( $stdout, 'Checks complete. No errors found.' ) ) {
			WP_CLI::success( 'No errors or warnings.' );
			return;
		}

		$template_data = $this->prepare_data( $stdout, $grouped_mode );

		$template_type = $grouped_mode ? 'grouped' : 'default';
		$html_content  = $this->get_html_content( $template_data, $template_type );

		$reports_folder = $this->reports_folder;

		if ( false === $reports_folder ) {
			WP_CLI::error( 'Could not create reports folder.' );
		}

		$target_file_name = '';

		if ( Utils\get_flag_value( $assoc_args, 'slug', null ) ) {
			$target_file_name = Utils\get_flag_value( $assoc_args, 'slug', null );
		}

		if ( empty( $target_file_name ) ) {
			$target_file_name = $this->get_html_file_name( $plugin_slug );
		}

		$report_file = "{$reports_folder}/{$target_file_name}.html";

		$status = File_Utils::create_file( $report_file, $html_content );

		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status->get_error_message() );
		}

		if ( true === $porcelain_mode ) {
			WP_CLI::line( $report_file );
			return;
		}

		if ( true === $open_in_browser ) {
			$this->open_in_browser( $report_file );
		}

		WP_CLI::log( 'Report file: ' . $report_file );
		WP_CLI::success( 'PCP report generated successfully.' );
	}

	/**
	 * Gets group information.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of group definitions.
	 */
	public function get_group_info(): array {
		$group_config_file = $this->custom_group_config_file ? $this->custom_group_config_file : $this->group_config_file;

		try {
			$this->classifier = new Classifier( $group_config_file );
			// Return empty array as the classifier handles the configuration internally.
			return [];
		} catch ( \Exception $e ) {
			WP_CLI::error( sprintf( 'Invalid group configuration file: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Returns reports folder.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false Reports folder full path or false on failure.
	 */
	private function get_reports_folder() {
		$report_path = Utils\get_cache_dir() . '/pcp-report';

		if ( ! wp_mkdir_p( $report_path ) ) {
			return false;
		}

		return $report_path;
	}

	/**
	 * Gets the report title.
	 *
	 * @since 1.0.0
	 *
	 * @return string Report title.
	 */
	private function get_report_title(): string {
		return $this->report_title;
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
		$data = [];

		// Validate JSON and decode.
		if ( ! JsonUtils::isValidJson( $json_data ) ) {
			return $data;
		}

		$issues = json_decode( $json_data, true );
		if ( empty( $issues ) ) {
			return $data;
		}

		// Remove /private prefix from file paths on macOS for all issues.
		$issues = array_map(
			function ( $issue ) {
				$issue['file'] = ltrim( preg_replace( '|^/private|', '', $issue['file'] ), '/' );
				return $issue;
			},
			$issues
		);

		// Prepare data based on mode.
		if ( $grouped ) {
			$data          = $this->prepare_grouped_data( $issues );
			$data['title'] = $this->get_report_title();
		} else {
			$data = $this->prepare_simple_data( $issues );
		}

		return $data;
	}

	/**
	 * Prepares simple data for default template rendering.
	 *
	 * @since 1.0.0
	 *
	 * @param array $issues Array of issues from plugin check.
	 * @return array Prepared simple data array for template rendering.
	 */
	private function prepare_simple_data( array $issues ): array {
		return [
			'title'  => $this->get_report_title(),
			'issues' => array_map(
				function ( $issue ) {
					return [
						'file'         => $issue['file'],
						'type'         => $issue['type'],
						'code'         => $issue['code'],
						'line'         => $issue['line'],
						'column'       => $issue['column'],
						'has_location' => ( $issue['line'] > 0 ),
						'message'      => Template_Utils::format_message( $issue['message'] ),
						'docs'         => $issue['docs'] ?? null,
					];
				},
				$issues
			),
		];
	}

	/**
	 * Prepares grouped data for template rendering using the classifier package.
	 *
	 * @since 1.0.0
	 *
	 * @param array $issues Array of issues from plugin check.
	 * @return array Prepared grouped data array for template rendering.
	 */
	private function prepare_grouped_data( array $issues ): array {
		if ( null === $this->classifier ) {
			$group_config_file = $this->custom_group_config_file ? $this->custom_group_config_file : $this->group_config_file;
			try {
				$this->classifier = new Classifier( $group_config_file );
			} catch ( \Exception $e ) {
				WP_CLI::error( sprintf( 'Invalid group configuration file: %s', $e->getMessage() ) );
			}
		}

		// Use the classifier to group the data.
		$grouped_data = $this->classifier->classify( $issues, 'code' );

		// Transform the grouped data to match the expected template format.
		$categories = [];

		foreach ( $grouped_data as $group_id => $group_issues ) {
			if ( empty( $group_issues ) ) {
				continue;
			}

			$category_data = [
				'errors'   => [],
				'warnings' => [],
			];

			// Process each issue in the group.
			foreach ( $group_issues as $issue ) {
				$code = $issue['code'];
				$type = strtolower( $issue['type'] );

				// Only process error and warning types.
				if ( ! in_array( $type, [ 'error', 'warning' ], true ) ) {
					continue;
				}

				$file_data = [
					'file'         => esc_html( $issue['file'] ),
					'line'         => $issue['line'],
					'column'       => $issue['column'],
					'has_location' => ( $issue['line'] > 0 ),
				];

				$type_key = $type . 's'; // Errors or warnings.

				if ( ! isset( $category_data[ $type_key ][ $code ] ) ) {
					$category_data[ $type_key ][ $code ] = [
						'type'    => strtoupper( $type ),
						'code'    => esc_html( $code ),
						'message' => Template_Utils::format_message( $issue['message'] ),
						'docs'    => $issue['docs'] ?? null,
						'issues'  => [],
					];
				}
				$category_data[ $type_key ][ $code ]['issues'][] = $file_data;
			}

			// Build the category with types.
			$types = [];
			if ( ! empty( $category_data['errors'] ) ) {
				$types = array_merge( $types, array_values( $category_data['errors'] ) );
			}
			if ( ! empty( $category_data['warnings'] ) ) {
				$types = array_merge( $types, array_values( $category_data['warnings'] ) );
			}

			if ( ! empty( $types ) ) {
				$category_name = 'ungrouped' === $group_id ? 'Misc Issues' : ucfirst( str_replace( '_', ' ', $group_id ) );
				$categories[]  = [
					'name'  => $category_name,
					'types' => $types,
				];
			}
		}

		return [
			'categories' => $categories,
		];
	}

	/**
	 * Generates HTML content from template and data.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data Template data array.
	 * @param string $type Template type.
	 * @return string Generated HTML content.
	 */
	private function get_html_content( array $data, string $type = 'default' ): string {
		$template_path = "{$this->templates_folder}/{$type}.php";
		return Template_Utils::render( $template_path, $data );
	}

	/**
	 * Gets HTML file name from plugin slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_slug Plugin slug or URL.
	 * @return string Sanitized file name.
	 */
	private function get_html_file_name( $plugin_slug ) {
		$slug = $plugin_slug;

		if ( filter_var( $slug, FILTER_VALIDATE_URL ) ) {
			// Handle URLs.
			if ( false !== strpos( $slug, 'downloads.wordpress.org/plugin/' ) ) {
				// WordPress.org download URLs.
				$path = wp_parse_url( $slug, PHP_URL_PATH );

				if ( $path ) {
					$filename = basename( $path );
					$slug     = preg_replace( '/\.\d+(\.\d+)*\.zip$/', '', $filename );
				}
			} elseif ( false !== strpos( $slug, '#wporgapi' ) ) {
				// Special URLs with #wporgapi.
				$path = wp_parse_url( $slug, PHP_URL_PATH );

				if ( $path ) {
					$filename = basename( $path );
					$slug     = preg_replace( '/^\d+_\d+-\d+-\d+_/', '', $filename );
					$slug     = preg_replace( '/\.zip$/', '', $slug );
				}
			} elseif ( false !== strpos( $slug, '.zip' ) ) {
				$path = wp_parse_url( $slug, PHP_URL_PATH );

				if ( $path ) {
					$filename = basename( $path );
					$slug     = preg_replace( '/\.zip$/', '', $filename );
				}
			}
		} elseif ( false !== strpos( $slug, '/' ) ) {
			// Handle full file paths.
			$slug = basename( rtrim( $slug, '/' ) );
		}

		return sanitize_file_name( strtolower( $slug ) );
	}

	/**
	 * Opens URL in default browser.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL.
	 */
	public static function open_in_browser( $url ) {
		switch ( strtoupper( substr( PHP_OS, 0, 3 ) ) ) {
			case 'DAR':
				$exec = 'open';
				break;
			case 'WIN':
				$exec = 'start ""';
				break;
			default:
				$exec = 'xdg-open';
		}

		passthru( $exec . ' ' . escapeshellarg( $url ) );
	}
}
