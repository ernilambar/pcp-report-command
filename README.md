ernilambar/pcp-report-command
=============================

Generate PCP results in HTML format.



Quick links: [Using](#using) | [Installing](#installing)

## Using

~~~
wp pcp-report <plugin> [--slug=<slug>] [--checks=<checks>] [--exclude-checks=<checks>] [--ignore-codes=<codes>] [--categories] [--ignore-warnings] [--ignore-errors] [--include-experimental] [--exclude-directories=<directories>] [--exclude-files=<files>] [--severity=<severity>] [--error-severity=<error-severity>] [--warning-severity=<warning-severity>] [--grouped] [--open] [--porcelain] [--rules=<rules>]
~~~

**OPTIONS**

	<plugin>
		The plugin to check.

	[--slug=<slug>]
		Slug to override the default.

	[--checks=<checks>]
		Only runs checks provided as an argument in comma-separated values.

	[--exclude-checks=<checks>]
		Exclude checks provided as an argument in comma-separated values, e.g. i18n_usage, late_escaping.
		Applies after evaluating `--checks`.

	[--ignore-codes=<codes>]
		Ignore error codes provided as an argument in comma-separated values.

	[--categories]
		Limit displayed results to include only specific categories Checks.

	[--ignore-warnings]
		Limit displayed results to exclude warnings.

	[--ignore-errors]
		Limit displayed results to exclude errors.

	[--include-experimental]
		Include experimental checks.

	[--exclude-directories=<directories>]
		Additional directories to exclude from checks.
		By default, `.git`, `vendor` and `node_modules` directories are excluded.

	[--exclude-files=<files>]
		Additional files to exclude from checks.

	[--severity=<severity>]
		Severity level.

	[--error-severity=<error-severity>]
		Error severity level.

	[--warning-severity=<warning-severity>]
		Warning severity level.

	[--grouped]
		Display report in grouped format.

	[--open]
		Open report in default browser.

	[--porcelain]
		Output just the report file path.

	[--rules=<rules>]
		Path to custom rules JSON file for grouping plugin check issues.

**EXAMPLES**

    # Generate report.
    $ wp pcp-report hello-dolly

    # Generate grouped report.
    $ wp pcp-report hello-dolly --grouped

    # Generate report with custom rules file.
    $ wp pcp-report hello-dolly --rules=/path/to/custom-rules.json

    # Get report path only.
    $ wp pcp-report hello-dolly --porcelain

## Installing

Installing this package requires WP-CLI v2.12 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install ernilambar/pcp-report-command:@stable
```

To install the latest development version of this package, use the following command instead:

```bash
wp package install ernilambar/pcp-report-command:dev-main
```


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
