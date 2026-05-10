<?php
/**
 * WP Last Login tests.
 *
 * @package wp-last-login
 */

/**
 * Tests for the wpll_* functions in wp-last-login.php.
 */
class Test_WP_Last_Login extends WP_UnitTestCase {

	/**
	 * Tests that wpll_wp_login writes a timestamp within the call window
	 * to user meta.
	 */
	public function test_wp_login_updates_meta() {
		$user_id = self::factory()->user->create( array( 'user_login' => 'wpll_login_user' ) );

		$before = time();
		wpll_wp_login( 'wpll_login_user' );
		$after = time();

		$stored = (int) get_user_meta( $user_id, 'wp-last-login', true );
		$this->assertGreaterThanOrEqual( $before, $stored );
		$this->assertLessThanOrEqual( $after, $stored );
	}

	/**
	 * Tests that the wp_login action — not just direct callback invocation
	 * — writes the timestamp. Guards against the action registration being
	 * removed or the callback's parameter signature drifting.
	 */
	public function test_wp_login_action_updates_meta() {
		$user = self::factory()->user->create_and_get( array( 'user_login' => 'wpll_action_user' ) );

		$before = time();
		do_action( 'wp_login', 'wpll_action_user', $user );
		$after = time();

		$stored = (int) get_user_meta( $user->ID, 'wp-last-login', true );
		$this->assertGreaterThanOrEqual( $before, $stored );
		$this->assertLessThanOrEqual( $after, $stored );
	}

	/**
	 * Tests that wpll_two_factor_user_authenticated writes a timestamp
	 * within the call window to user meta. Required because Two Factor
	 * halts wp_login.
	 */
	public function test_two_factor_user_authenticated_updates_meta() {
		$user = self::factory()->user->create_and_get();

		$before = time();
		wpll_two_factor_user_authenticated( $user );
		$after = time();

		$stored = (int) get_user_meta( $user->ID, 'wp-last-login', true );
		$this->assertGreaterThanOrEqual( $before, $stored );
		$this->assertLessThanOrEqual( $after, $stored );
	}

	/**
	 * Tests that wpll_user_register seeds a 0 timestamp so new users
	 * appear in meta_value_num sorts.
	 */
	public function test_user_register_seeds_zero() {
		$user_id = self::factory()->user->create();
		delete_user_meta( $user_id, 'wp-last-login' );

		wpll_user_register( $user_id );

		$this->assertSame( 0, (int) get_user_meta( $user_id, 'wp-last-login', true ) );
	}

	/**
	 * Tests that wpll_add_column adds the Last Login column.
	 */
	public function test_add_column_adds_key() {
		$cols = wpll_add_column( array( 'username' => 'Username' ) );
		$this->assertArrayHasKey( 'wp-last-login', $cols );
	}

	/**
	 * Tests that wpll_manage_users_custom_column renders an em-dash placeholder
	 * with an explanatory tooltip when the user has no recorded login (meta is 0).
	 */
	public function test_manage_users_custom_column_renders_placeholder() {
		$user_id = self::factory()->user->create();

		$value = wpll_manage_users_custom_column( '', 'wp-last-login', $user_id );
		$this->assertStringContainsString( '>—</span>', $value );
		$this->assertStringContainsString( 'title="No login recorded since the plugin was activated."', $value );
		$this->assertStringContainsString( 'aria-label="No login recorded since the plugin was activated."', $value );
	}

	/**
	 * Tests that wpll_manage_users_custom_column renders a <time> element
	 * with a UTC `datetime` attribute when the user has a login timestamp.
	 */
	public function test_manage_users_custom_column_renders_time() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'wp-last-login', 1_700_000_000 );

		$value = wpll_manage_users_custom_column( '', 'wp-last-login', $user_id );
		$this->assertStringContainsString( '<time', $value );
		$this->assertStringContainsString( 'title=', $value );
		$this->assertStringContainsString( 'datetime="' . gmdate( 'c', 1_700_000_000 ) . '"', $value );
	}

	/**
	 * Tests that the wpll_date_format filter overrides the site's
	 * date_format option. Pins the timezone to UTC and asserts a literal
	 * date so the test fails on a real formatting bug instead of
	 * re-deriving the buggy value.
	 *
	 * 1_700_000_000 = 2023-11-14T22:13:20Z.
	 */
	public function test_manage_users_custom_column_honours_date_format_filter() {
		$original_tz = get_option( 'timezone_string' );
		update_option( 'timezone_string', 'UTC' );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'wp-last-login', 1_700_000_000 );

		$filter = static function () {
			return 'Y-m-d';
		};
		add_filter( 'wpll_date_format', $filter );

		try {
			$value = wpll_manage_users_custom_column( '', 'wp-last-login', $user_id );
		} finally {
			remove_filter( 'wpll_date_format', $filter );
			update_option( 'timezone_string', $original_tz );
		}

		$this->assertStringContainsString( '>2023-11-14</time>', $value );
	}

	/**
	 * Tests that wpll_manage_users_custom_column leaves other columns alone.
	 */
	public function test_manage_users_custom_column_passes_through() {
		$user_id = self::factory()->user->create();
		$value   = wpll_manage_users_custom_column( 'original', 'email', $user_id );
		$this->assertSame( 'original', $value );
	}

	/**
	 * Tests that wpll_add_sortable registers the column as sortable.
	 */
	public function test_add_sortable_registers_column() {
		$sortable = wpll_add_sortable( array() );
		$this->assertArrayHasKey( 'wp-last-login', $sortable );
		$this->assertSame( 'wp-last-login', $sortable['wp-last-login'] );
	}

	/**
	 * Tests that wpll_pre_get_users rewrites orderby to meta_value_num and
	 * adds an EXISTS/NOT EXISTS meta_query so users with no meta row still
	 * appear in sorted results.
	 */
	public function test_pre_get_users_rewrites_orderby() {
		$query             = new WP_User_Query();
		$query->query_vars = array( 'orderby' => 'wp-last-login' );

		wpll_pre_get_users( $query );

		$this->assertSame( 'meta_value_num', $query->query_vars['orderby'] );
		$this->assertSame( 'OR', $query->query_vars['meta_query']['relation'] );
		$this->assertSame( 'wp-last-login', $query->query_vars['meta_query'][0]['key'] );
		$this->assertSame( 'EXISTS', $query->query_vars['meta_query'][0]['compare'] );
		$this->assertSame( 'NOT EXISTS', $query->query_vars['meta_query'][1]['compare'] );
	}

	/**
	 * Tests that wpll_pre_get_users wraps an upstream meta_query with AND
	 * instead of overwriting it, so other pre_get_users callbacks that add
	 * meta_query constraints continue to apply when sorting by last login.
	 */
	public function test_pre_get_users_preserves_existing_meta_query() {
		$existing          = array(
			array(
				'key'     => 'role_capability',
				'value'   => 'editor',
				'compare' => '=',
			),
		);
		$query             = new WP_User_Query();
		$query->query_vars = array(
			'orderby'    => 'wp-last-login',
			'meta_query' => $existing, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		wpll_pre_get_users( $query );

		$this->assertSame( 'AND', $query->query_vars['meta_query']['relation'] );
		$this->assertSame( $existing, $query->query_vars['meta_query'][0] );
		$this->assertSame( 'OR', $query->query_vars['meta_query'][1]['relation'] );
		$this->assertSame( 'EXISTS', $query->query_vars['meta_query'][1][0]['compare'] );
		$this->assertSame( 'NOT EXISTS', $query->query_vars['meta_query'][1][1]['compare'] );
	}

	/**
	 * Tests that wpll_pre_get_users ignores unrelated orderby values.
	 */
	public function test_pre_get_users_ignores_other_orderby() {
		$query             = new WP_User_Query();
		$query->query_vars = array( 'orderby' => 'login' );

		wpll_pre_get_users( $query );

		$this->assertSame( 'login', $query->query_vars['orderby'] );
		$this->assertArrayNotHasKey( 'meta_query', $query->query_vars );
	}

	/**
	 * Issue #4 regression guard: a real WP_User_Query sorted by
	 * wp-last-login must include users with no `wp-last-login` meta row.
	 *
	 * The pre-fix INNER JOIN dropped them silently. This is the
	 * unit-level companion to the Playwright e2e test and runs in
	 * <100ms with no browser/wp-env coordination.
	 */
	public function test_pre_get_users_includes_users_without_meta() {
		$with_login = self::factory()->user->create();
		update_user_meta( $with_login, 'wp-last-login', 1_700_000_000 );

		$seeded_zero = self::factory()->user->create();
		// user_register seeded 0; leave it.

		$no_meta = self::factory()->user->create();
		delete_user_meta( $no_meta, 'wp-last-login' );

		$query = new WP_User_Query(
			array(
				'orderby' => 'wp-last-login',
				'order'   => 'DESC',
				'fields'  => 'ID',
				'include' => array( $with_login, $seeded_zero, $no_meta ),
			)
		);
		$ids   = array_map( 'intval', $query->get_results() );

		$this->assertContains( $with_login, $ids );
		$this->assertContains( $seeded_zero, $ids );
		$this->assertContains( $no_meta, $ids );
		$this->assertSame( $with_login, $ids[0], 'Descending sort must put the timestamped user first.' );
	}

	/**
	 * Tests that wpll_load_textdomain points WordPress at the plugin's
	 * `lang/` subdirectory for translations. Catches a typo in the path
	 * argument to load_plugin_textdomain (the only mistake the function
	 * is plausibly going to introduce). The custom path is on a
	 * protected property; reflection is the only public-API-free way
	 * to read it back across WP versions.
	 */
	public function test_load_textdomain_registers_path() {
		global $wp_textdomain_registry;

		wpll_load_textdomain();

		$prop = new ReflectionProperty( $wp_textdomain_registry, 'custom_paths' );
		$prop->setAccessible( true );
		$custom_paths = $prop->getValue( $wp_textdomain_registry );

		$this->assertArrayHasKey( 'wp-last-login', $custom_paths );
		$this->assertStringEndsWith(
			'wp-last-login/lang',
			rtrim( (string) $custom_paths['wp-last-login'], '/' )
		);
	}

	/**
	 * Tests the exact CSS string emitted by wpll_column_style. The exact
	 * width is part of the contract — a regression that tweaks `14%` to
	 * something that breaks the layout in a CI run wouldn't be caught by
	 * a substring match.
	 */
	public function test_column_style_outputs_css() {
		ob_start();
		wpll_column_style();
		$output = ob_get_clean();

		$this->assertSame( '<style>.column-wp-last-login { width: 14%; }</style>', $output );
	}

	/**
	 * Tests that uninstall.php deletes all wp-last-login user meta when
	 * WP_UNINSTALL_PLUGIN is defined.
	 */
	public function test_uninstall_deletes_meta() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'wp-last-login', 1_700_000_000 );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-last-login/wp-last-login.php' );
		}

		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertSame( '', get_user_meta( $user_id, 'wp-last-login', true ) );
	}
}
