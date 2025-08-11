Feature: Check report of plugin from ZIP source

  Background:
    Given a WP install
    And these installed and active plugins:
      """
      plugin-check
      """

  Scenario: Report generation
    When I run `wp eval 'echo WP_CLI\Utils\get_cache_dir() . "/pcp-report";'`
    Then STDOUT should contain:
      """
      wp-cli/cache/pcp-report
      """
    And save STDOUT as {PCP_REPORTS_DIR}

    When I run `wp pcp-report https://downloads.wordpress.org/plugin/easy-image-widget.1.1.1.zip`
    Then the return code should be 0
    And STDOUT should contain:
      """
      easy-image-widget.html
      """
    And STDOUT should contain:
      """
      PCP report generated successfully.
      """
    And the {PCP_REPORTS_DIR}/easy-image-widget.html file should exist

    When I run `wp pcp-report https://downloads.wordpress.org/plugin/easy-image-widget.1.1.1.zip --porcelain`
    Then the return code should be 0
    And STDOUT should be:
      """
      {PCP_REPORTS_DIR}/easy-image-widget.html
      """
