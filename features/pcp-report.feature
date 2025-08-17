Feature: Check report

  Background:
    Given a WP install
    And these installed and active plugins:
      """
      plugin-check
      """

  Scenario: Report generation for plugin check
    Given a wp-content/plugins/foo-sample/foo-sample.php file:
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

    When I run `wp eval 'echo WP_CLI\Utils\get_cache_dir() . "/pcp-report";'`
    Then STDOUT should contain:
      """
      wp-cli/cache/pcp-report
      """
    And save STDOUT as {PCP_REPORTS_DIR}

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
    And the {PCP_REPORTS_DIR}/foo-sample.html file should exist
    And the {PCP_REPORTS_DIR}/foo-sample.html file should contain:
      """
      ERROR: WordPress.WP.AlternativeFunctions.rand_mt_rand
      """
    And the {PCP_REPORTS_DIR}/foo-sample.html file should contain:
      """
      ERROR: no_plugin_readme
      """

    When I run `wp pcp-report foo-sample --porcelain`
    Then the return code should be 0
    And STDOUT should be:
      """
      {PCP_REPORTS_DIR}/foo-sample.html
      """

    When I run `wp pcp-report foo-sample --slug=my-custom-slug`
    Then the return code should be 0
    And STDOUT should contain:
      """
      wp-cli/cache/pcp-report/my-custom-slug.html
      """
    And STDOUT should contain:
      """
      PCP report generated successfully.
      """

  Scenario: Report generation with custom group configuration
    Given a wp-content/plugins/foo-sample/foo-sample.php file:
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
    And a custom-group-config.json file:
      """
      {
        "security": {
          "id": "security",
          "title": "Security Issues",
          "type": "prefix",
          "checks": [
            "WordPress.Security"
          ]
        },
        "performance": {
          "id": "performance",
          "title": "Performance Issues",
          "type": "contains",
          "checks": [
            "WordPress.WP.AlternativeFunctions"
          ]
        }
      }
      """

    When I run `wp eval 'echo WP_CLI\Utils\get_cache_dir() . "/pcp-report";'`
    Then STDOUT should contain:
      """
      wp-cli/cache/pcp-report
      """
    And save STDOUT as {PCP_REPORTS_DIR}

    When I run `wp pcp-report foo-sample --grouped --group-config=custom-group-config.json`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Report file:
      """
    And STDOUT should contain:
      """
      PCP report generated successfully.
      """
    And the {PCP_REPORTS_DIR}/foo-sample.html file should exist
    And the {PCP_REPORTS_DIR}/foo-sample.html file should contain:
      """
      ## Security Issues
      """
    And the {PCP_REPORTS_DIR}/foo-sample.html file should contain:
      """
      ## Performance Issues
      """

    When I try `wp pcp-report foo-sample --grouped --group-config=non-existent-group-config.json`
    Then STDERR should contain:
      """
      Invalid custom group configuration file: File not found: non-existent-group-config.json
      """

  Scenario: Report generation with invalid custom group configuration
    Given a wp-content/plugins/foo-sample/foo-sample.php file:
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
    And a custom-group-config.json file:
      """
      {
        "NEW_CAT": {
          "id": "New_Cat",
          "title": "New Category",
          "type": "suffix",
          "checks": [
            "New-Cat-"
          ]
        },
        "another": {
          "id": "another",
          "title": "Another Issues",
          "type": "contains",
          "checks": []
        }
      }
      """

    When I try `wp pcp-report foo-sample --grouped --group-config=custom-group-config.json`
    And STDERR should contain:
      """
      Invalid group configuration file:
      """
