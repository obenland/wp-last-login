# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

WP Last Login is a single-file WordPress plugin that adds a "Last Login" column (sortable) to the Users admin screen and tracks login timestamps per user in user meta.

## Commands

Local development runs inside `@wordpress/env` (Docker). Node `>=24` (pinned in `.nvmrc`) and Composer are required on the host.

- `composer install` — install PHPCS, WPCS, PHPCompatibilityWP, and `yoast/phpunit-polyfills`. PHPUnit itself is provided by the wp-env `tests-cli` container at runtime (it also arrives transitively in `vendor/` via polyfills, but CI runs against the container's copy).
- `npm ci` — install `@wordpress/env` and `@wordpress/scripts`.
- `npm run start` — boot the wp-env container (WP latest, PHP 8.3, this plugin mounted). Once running, the admin is at `http://localhost:8888` (`admin` / `password`).
- `npm run lint` — runs `lint:php`, `lint:md`, `lint:pkg-json` in sequence. Each can be invoked individually; `lint:php` / `lint:php:fix` run PHPCS/PHPCBF **inside the wp-env container** against `phpcs.xml.dist`.
- `npm run format` — `wp-scripts format` (Prettier) over tracked files, honouring `.prettierignore`.
- `npm run test-php` — runs PHPUnit inside the `tests-cli` container against single-site WordPress. The `--env-cwd` is computed from `$(basename "$PWD")` so it works in worktrees with non-canonical directory names too.
- `npm run test-php-multisite` — same, multisite. The plugin registers network columns, so run this before releases.
- `npm run test-e2e` — runs Playwright against the wp-env tests environment at `http://localhost:8889`. Specs live in `tests/e2e/specs/`. Tests seed data by shelling out to `npx wp-env run tests-cli wp …` (see `tests/e2e/specs/last-login-sort.test.js`).
- Need to run tests against a specific combination? Set `WP_ENV_PHP_VERSION` and `WP_ENV_CORE` before `npm run start` (see `.github/workflows/phpunit.yml` for the exact env shape).

CI lives in `.github/workflows/`:

- `wpcs.yml` — PHPCS (PHP 8.3, `phpcs.xml.dist`) + markdownlint + package.json lint (`npm run lint:md` / `lint:pkg-json`).
- `phpunit.yml` — three-row matrix: **PHP 7.4 / WP 6.5** (floor), **PHP 8.3 / WP latest**, **PHP 8.5 / WP trunk** (nightly canary).

## Architecture

The entire plugin is `wp-last-login.php` — a flat, procedural file with `wpll_`-prefixed functions hooked into WordPress actions/filters. `uninstall.php` deletes all `wp-last-login` user meta on plugin uninstall.

### Data model

A single user meta key, `wp-last-login`, stores a Unix timestamp (or `0` for "never"). The `0` default is important: `wpll_activate` (activation hook) and `wpll_user_register` (`user_register` action) seed it so that `ORDER BY meta_value_num` includes users who have never logged in — otherwise they'd be excluded from sorted lists (see the reference to the support thread in `wpll_activate`'s docblock, and changelog entry for v3).

### Login capture

Two hooks write the timestamp:

1. `wp_login` → `wpll_wp_login` — normal logins.
2. `two_factor_user_authenticated` → `wpll_two_factor_user_authenticated` — required because the Two Factor plugin halts execution during `wp_login`, so the first hook never fires for 2FA users. Do not remove this without understanding that interaction.

### Column rendering (conditional)

After the login-capture hooks, the file short-circuits with `apply_filters( 'wpll_current_user_can', true )`. If that filter returns false, none of the column-rendering functions are registered, but login capture still works. This lets site owners gate column visibility by role without disabling tracking.

Column functions are registered on three column filters — `manage_users_columns`, `wpmu_users_columns`, `manage_site-users-network_columns` — plus two sortable filters — `manage_users_sortable_columns` and `manage_users-network_sortable_columns` (note the hyphen; this is the network-admin variant) — and `manage_users_custom_column` for the cell content. Sorting is implemented in `wpll_pre_get_users` by rewriting `orderby` to `meta_value_num` against the `wp-last-login` meta key.

### Public filters

- `wpll_current_user_can` (bool) — gate column visibility.
- `wpll_date_format` (string) — override the date format (defaults to the site's `date_format` option).

## Testing notes

- `tests/bootstrap.php` loads the plugin via `muplugins_loaded` and resolves the WP test library from (in order) `WP_TESTS_DIR`, `WP_DEVELOP_DIR`, a sibling `../../../../tests/phpunit` path, or `/tmp/wordpress-tests-lib`. Under `npm run test-php` this resolves via the wp-env `tests-cli` container — the test library is provided by the container, not Composer.
- Tests extend `WP_UnitTestCase`. Creating a user via the factory triggers `user_register`, which seeds `wp-last-login = 0` — rely on that rather than inserting meta manually when testing the "never logged in" path.
- When asserting "meta was set to now," sample `time()` before and after the call and assert the stored value is within the window, not an exact value (see `test_wp_login_updates_meta`). `time()` can advance mid-test.
- `phpunit.xml` scopes coverage to `./` excluding `tests/`, `vendor/`, `node_modules/` — so `uninstall.php` and the activation/login/column code paths are all measured.

## Conventions

- WordPress Coding Standards (enforced by CI via `phpcs.xml.dist`, which layers `PHPCompatibilityWP` on top of `WordPress`). Yoda conditions, `snake_case`, `wpll_` prefix on every global function.
- Escape on output (`esc_attr`, `esc_html`, `date_i18n`) — follow the pattern in `wpll_manage_users_custom_column` when adding markup.
- Bump both the `Version:` header in `wp-last-login.php` and `Stable tag:` in `readme.txt` together. Add a matching `== Changelog ==` entry in `readme.txt`, and bump `Tested up to:` when verifying against a new WordPress release. `Requires at least:` (WP) and `Requires PHP:` must match what CI's PHPUnit matrix actually exercises.
- Supported PHP range: **7.4 – 8.5**, declared in `composer.json` as `">=7.4"`, in `readme.txt` as `Requires PHP: 7.4`, pinned via `<config name="testVersion" value="7.4-"/>` in `phpcs.xml.dist`, and exercised by the PHPUnit matrix. Avoid language features newer than 7.4 (no `match`, no named args, no enums, no readonly/typed promoted properties) — PHPCompatibilityWP will flag them.

## Release

Pushing a git tag triggers `.github/workflows/deploy.yml`, which deploys to the WordPress.org SVN repo via `10up/action-wordpress-plugin-deploy`. Pushes to `trunk` trigger `push-asset-readme-update.yml` to sync `readme.txt` and `.wordpress-org/` assets to WordPress.org without cutting a release. `.distignore` controls what's excluded from the deployed zip — add new dev-only files (tests, configs, lockfiles, CLAUDE.md) there or they'll ship to users.

## Playground

`.wordpress-org/blueprints/blueprint.json` boots the plugin in WordPress Playground (PHP 8.3) with seeded test users (one with a recent login, one with yesterday's login, one that has never logged in) — useful for manually exercising the column and sort without standing up wp-env.
