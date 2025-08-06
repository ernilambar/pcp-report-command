<?php
/**
 * Command
 *
 * @package PCP_Report_Command
 */

use Nilambar\PCP_Report_Command\Report_Command;

if ( ! class_exists( 'WP_CLI', false ) ) {
	return;
}

$wpcli_pcp_report_autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_pcp_report_autoload ) ) {
	require_once $wpcli_pcp_report_autoload;
}

WP_CLI::add_command( 'pcp-report', Report_Command::class );
