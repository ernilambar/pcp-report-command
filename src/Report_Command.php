<?php
/**
 * Report_Command
 *
 * @package PLG_Command
 */

namespace Nilambar\PCP_Report_Command;

use WP_CLI;

class Report_Command {

	/**
	 * Test command.
	 *
	 * ## EXAMPLES
	 *
	 *     # Run test command.
	 *     $ wp dashmate layout test
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       List of the positional arguments.
	 * @param array $assoc_args List of the associative arguments.
	 *
	 * @when after_wp_load
	 * @subcommand test
	 */
	public function test_( $args, $assoc_args = [] ) {
		WP_CLI::success( 'Test.' );
	}
}
