# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer format        # Auto-fix code style
composer phpcs         # PHP CodeSniffer lint
composer phpstan       # Static analysis
composer phpunit       # Unit tests
composer behat         # Integration tests (requires WP env)
composer test          # Full suite (phpcs + phpstan + phpunit + behat)
composer prepare-tests # Set up WP test environment before behat
composer readme        # Regenerate README.md from doc blocks
```

Run a single PHPUnit test file:
```bash
./vendor/bin/phpunit tests/Unit/Report_CommandTest.php
```

Run a single Behat scenario by line number:
```bash
./vendor/bin/behat features/pcp-report.feature:42
```

## Architecture

**WP-CLI package** that wraps the Plugin Check (`wp plugin check`) command and renders its JSON output as an HTML report.

**Flow:**
1. `command.php` — registers the `wp pcp-report` command
2. `src/Report_Command.php` — main command class; `__invoke()` runs Plugin Check, parses JSON, calls templates
3. `src/Utils/File_Utils.php` — file I/O (cache dir resolution, write, open-in-browser)
4. `src/Utils/Template_Utils.php` — renders PHP templates to string
5. `templates/default.php` / `templates/grouped.php` — HTML output templates; `default` = flat list, `grouped` = categorized by `ernilambar/classifier`
6. `data/groups.json` — default issue grouping config; validated against `data/groups-schema.json` (JSON Schema Draft 7)

**Classifier** (`ernilambar/classifier` ^1) — external package that maps Plugin Check issue codes to named groups using the JSON config.

**Coding standards:** WordPress Core + Extra, Slevomat v8, PHPCompatibility 7.4+. Configured in `phpcs.xml.dist`. Namespace: `Nilambar\PCP_Report_Command`.

## Integration Tests

Behat features live in `features/`. They spin up a real WP install via WP-CLI scaffolding. Run `composer prepare-tests` once before the first `composer behat` on a fresh environment. Matrix: PHP 8.0–8.3 × WP 6.5–trunk (see `.github/workflows/behat-test.yml`).
