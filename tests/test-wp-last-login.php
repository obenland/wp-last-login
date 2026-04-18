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
	 * Tests that wpll_activate seeds a 0 timestamp on existing users that
	 * don't yet have the meta key. Keeps never-logged-in users in sort
	 * results after a plugin activation.
	 */
	public function test_activate_seeds_existing_users() {
		$user_id = self::factory()->user->create();
		delete_user_meta( $user_id, 'wp-last-login' );

		wpll_activate();

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
	 * Tests that wpll_manage_users_custom_column returns "Never." when the
	 * user has never logged in (meta is 0).
	 */
	public function test_manage_users_custom_column_never() {
		$user_id = self::factory()->user->create();

		$value = wpll_manage_users_custom_column( '', 'wp-last-login', $user_id );
		$this->assertSame( 'Never.', $value );
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
	 * date_format option.
	 */
	public function test_manage_users_custom_column_honours_date_format_filter() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'wp-last-login', 1_700_000_000 );

		$filter = static function () {
			return 'Y|m|d';
		};
		add_filter( 'wpll_date_format', $filter );

		$value = wpll_manage_users_custom_column( '', 'wp-last-login', $user_id );

		remove_filter( 'wpll_date_format', $filter );

		$expected = date_i18n(
			'Y|m|d',
			get_date_from_gmt( gmdate( 'Y-m-d H:i:s', 1_700_000_000 ), 'U' )
		);
		$this->assertStringContainsString( '>' . $expected . '</time>', $value );
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
	 * Tests that wpll_pre_get_users rewrites orderby to meta_value_num.
	 */
	public function test_pre_get_users_rewrites_orderby() {
		$query             = new WP_User_Query();
		$query->query_vars = array( 'orderby' => 'wp-last-login' );

		wpll_pre_get_users( $query );

		$this->assertSame( 'meta_value_num', $query->query_vars['orderby'] );
		$this->assertSame( 'wp-last-login', $query->query_vars['meta_key'] );
	}

	/**
	 * Tests that wpll_pre_get_users ignores unrelated orderby values.
	 */
	public function test_pre_get_users_ignores_other_orderby() {
		$query             = new WP_User_Query();
		$query->query_vars = array( 'orderby' => 'login' );

		wpll_pre_get_users( $query );

		$this->assertSame( 'login', $query->query_vars['orderby'] );
		$this->assertArrayNotHasKey( 'meta_key', $query->query_vars );
	}
}
