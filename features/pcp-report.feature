Feature: Check report

  Scenario: Plugin Check is ready
    Given a WP install
    And these installed and active plugins:
      """
      plugin-check
      """
    And a wp-content/plugins/foo-sample/foo-sample.php file:
      """
      <?php
      /**
       * Plugin Name: Foo Sample
       * Plugin URI: https://foo-sample.com
       * Description: Custom plugin.
       * Version: 1.0.0
       * Requires at least: 6.3
       * Requires PHP: 7.2
       * Author: John Doe
       * Author URI: https://johndoe.com/
       * License: GPL-2.0+
       * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
       * Text Domain: foo-sample
       */

      add_action(
          'init',
          function () {
            $number = mt_rand( 10, 100 );
            echo $number;
          }
        );
      """

    When I run `wp pcp-report foo-sample`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Report file:
      """
    And STDOUT should contain:
      """
      PCP report generated successfully.
      """
