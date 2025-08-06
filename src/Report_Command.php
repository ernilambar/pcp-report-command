<?php
/**
 * Report_Command
 *
 * @package PLG_Command
 */

namespace Nilambar\PCP_Report_Command;

use WP_CLI;
use WP_CLI\Utils;

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
	 * [--porcelain=<field>]
	 * : Output a single value.
	 * ---
	 * options:
	 *   - path
	 * ---

	 * ## EXAMPLES
	 *
	 *     # Generate report.
	 *     $ wp pcp-report hello-dolly
	 *
	 *     # Get report path only.
	 *     $ wp pcp-report hello-dolly --porcelain=path
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of options.
	 */
	public function __invoke( $args, $assoc_args = [] ) {
		// Keep backup.
		$original_assoc_args = $assoc_args;

		$plugin_slug = isset( $args[0] ) ? $args[0] : '';

		$flags = [
			'ignore-warnings',
			'ignore-errors',
			'include-experimental',
		];

		$command_text = sprintf( 'plugin check "%s"', esc_html( $plugin_slug ) );

		$check_args = [
			'format' => 'strict-json',
			'fields' => 'file,line,column,type,code,message,docs',
		];

		$porcelain_mode = Utils\get_flag_value( $assoc_args, 'porcelain', '' );
		unset( $assoc_args['porcelain'] );

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

		$template_data = $this->prepare_data( $stdout );

		$html_content = $this->get_html_content( $template_data );
		WP_CLI::line( $html_content );
	}

	private function prepare_data( string $json_data ): array {
		$issues = json_decode( $json_data, true );

		if ( empty( $issues ) ) {
			return [];
		}

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

	private function get_html_content( array $data ): string {
		$template_path = dirname( __DIR__ ) . '/templates/';

		return Utils\mustache_render( "{$template_path}/default.mustache", $data );
	}
}
