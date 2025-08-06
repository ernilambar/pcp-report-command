<?php
/**
 * Report_Command
 *
 * @package PCP_Report_Command
 */

namespace Nilambar\PCP_Report_Command;

use Nilambar\PCP_Report_Command\Utils\File_Utils;
use Nilambar\PCP_Report_Command\Utils\Group_Utils;
use Nilambar\PCP_Report_Command\Utils\JSON_Utils;
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
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->rules_file       = dirname( __DIR__ ) . '/data/groups.json';
		$this->reports_folder   = $this->get_reports_folder();
		$this->templates_folder = dirname( __DIR__ ) . '/templates';
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
	 * [--open]
	 * : Open report in default browser.
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

		$porcelain_mode  = Utils\get_flag_value( $assoc_args, 'porcelain', false );
		$grouped_mode    = Utils\get_flag_value( $assoc_args, 'grouped', false );
		$open_in_browser = Utils\get_flag_value( $assoc_args, 'open', false );
		unset( $assoc_args['porcelain'] );
		unset( $assoc_args['grouped'] );
		unset( $assoc_args['open'] );

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

		$template_type = $grouped_mode ? 'grouped' : 'default';
		$html_content  = $this->get_html_content( $template_data, $template_type );

		$reports_folder = $this->reports_folder;

		if ( false === $reports_folder ) {
			WP_CLI::error( 'Could not create reports folder.' );
		}

		$report_file = "{$reports_folder}/{$plugin_slug}.html";

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
		return Group_Utils::get_group_details( $this->rules_file );
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
		if ( ! JSON_Utils::is_valid_json( $json_data ) ) {
			return $data;
		}

		$issues = json_decode( $json_data, true );
		if ( empty( $issues ) ) {
			return $data;
		}

		// Prepare data based on mode.
		$data = $grouped
			? Group_Utils::prepare_grouped_data( $issues, $this->get_group_info() )
			: $this->prepare_simple_data( $issues );

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
	}

	/**
	 * Generates HTML content from template and data.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data           Template data array.
	 * @param string   $type        Template type.
	 * @return string Generated HTML content.
	 */
	private function get_html_content( array $data, string $type = 'default' ): string {
		return Utils\mustache_render( "{$this->templates_folder}/{$type}.mustache", $data );
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
