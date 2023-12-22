<?php
/**
 * Plugin Name: WP Last Login
 * Plugin URI:  http://en.wp.obenland.it/wp-last-login/#utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-last-login
 * Description: Displays the date of the last login in user lists.
 * Version:     7
 * Author:      Konstantin Obenland
 * Author URI:  http://en.wp.obenland.it/#utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-last-login
 * Text Domain: wp-last-login
 * Domain Path: /lang
 * License:     GPLv2
 *
 * @package wp-last-login
 */

/**
 * Sets a default meta value for all users.
 *
 * Allows sorting users by last login to work, even though some might not have
 * recorded login time.
 *
 * @see https://wordpress.org/support/topic/wp-40-sorting-by-date-doesnt-work
 */
function wpll_activate() {
	$user_ids = get_users(
		array(
			'blog_id' => '',
			'fields'  => 'ID',
		)
	);

	foreach ( $user_ids as $user_id ) {
		add_user_meta( $user_id, 'wp-last-login', 0 );
	}
}
register_activation_hook( __FILE__, 'wpll_activate' );

/**
 * Loads the plugin's translated strings.
 */
function wpll_load_textdomain() {
	load_plugin_textdomain( 'wp-last-login', false, 'wp-last-login/lang' );
}
add_action( 'init', 'wpll_load_textdomain' );

/**
 * Update the login timestamp.
 *
 * @param string $user_login The user's login name.
 */
function wpll_wp_login( $user_login ) {
	$user = get_user_by( 'login', $user_login );
	update_user_meta( $user->ID, 'wp-last-login', time() );
}
add_action( 'wp_login', 'wpll_wp_login' );

/**
 * Update the login timestamp for two-factor authentication.
 *
 * The Two Factor plugin halts execution during the wp_login hook and the above
 * callback is never fired.
 * This callback is fired after the user has been authenticated with 2FA.
 *
 * @see https://github.com/WordPress/two-factor/blob/2b0d9bc964f9d9f7b86eac4eb0c4dbfb43c25c2c/class-two-factor-core.php#L567.
 *
 * @param WP_User $user The user object.
 */
function wpll_two_factor_user_authenticated( $user ) {
	update_user_meta( $user->ID, 'wp-last-login', time() );
}
add_action( 'two_factor_user_authenticated', 'wpll_two_factor_user_authenticated' );

/**
 * Set default data for new users.
 *
 * @param int $user_id The user ID.
 */
function wpll_user_register( $user_id ) {
	update_user_meta( $user_id, 'wp-last-login', 0 );
}
add_action( 'user_register', 'wpll_user_register' );

/**
 * Programmers:
 * To limit this information to certain user roles, add a filter to
 * 'wpll_current_user_can' and check for user permissions, returning
 * true or false!
 *
 * Example:
 *
 * function prefix_wpll_visibility( $bool ) {
 *     return current_user_can( 'manage_options' ); // Only for Admins
 * }
 * add_filter( 'wpll_current_user_can', 'prefix_wpll_visibility' );
 */
if ( ! apply_filters( 'wpll_current_user_can', true ) ) {
	return;
}

/**
 * Adds the last login column to the network admin user list.
 *
 * @param array $cols The default columns.
 * @return array
 */
function wpll_add_column( $cols ) {
	$cols['wp-last-login'] = __( 'Last Login', 'wp-last-login' );

	return $cols;
}
add_filter( 'manage_site-users-network_columns', 'wpll_add_column', 1 );
add_filter( 'manage_users_columns', 'wpll_add_column', 1 );
add_filter( 'wpmu_users_columns', 'wpll_add_column', 1 );

/**
 * Defines the width of the column.
 */
function wpll_column_style() {
	echo '<style>.column-wp-last-login { width: 14%; }</style>';
}
add_filter( 'admin_print_styles-users.php', 'wpll_column_style' );
add_filter( 'admin_print_styles-site-users.php', 'wpll_column_style' );

/**
 * Adds the last login column to the network admin user list.
 *
 * @param string $value       Value of the custom column.
 * @param string $column_name The name of the column.
 * @param int    $user_id     The user's id.
 * @return string
 */
function wpll_manage_users_custom_column( $value, $column_name, $user_id ) {
	if ( 'wp-last-login' === $column_name ) {
		$value      = __( 'Never.', 'wp-last-login' );
		$last_login = (int) get_user_meta( $user_id, 'wp-last-login', true );

		if ( $last_login ) {
			/**
			 * Date format to use with last login date.
			 *
			 * @param string $format Date format. Default: `date_format` option value.
			 */
			$format     = apply_filters( 'wpll_date_format', get_option( 'date_format' ) );
			$last_login = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $last_login ), 'U' );
			$value      = sprintf(
				'<time title="%1$s" datetime="%2$s">%3$s</time>',
				esc_attr( date_i18n( get_option( 'time_format' ), $last_login ) ),
				esc_attr( gmdate( 'c', $last_login ) ),
				esc_html( date_i18n( $format, $last_login ) )
			);
		}
	}

	return $value;
}
add_filter( 'manage_users_custom_column', 'wpll_manage_users_custom_column', 10, 3 );

/**
 * Register the column as sortable.
 *
 * @param array $columns User table columns.
 * @return array
 */
function wpll_add_sortable( $columns ) {
	$columns['wp-last-login'] = 'wp-last-login';

	return $columns;
}
add_filter( 'manage_users_sortable_columns', 'wpll_add_sortable' );
add_filter( 'manage_users-network_sortable_columns', 'wpll_add_sortable' );

/**
 * Handle ordering by last login.
 *
 * @param WP_User_Query $user_query Request arguments.
 * @return WP_User_Query
 */
function wpll_pre_get_users( $user_query ) {
	if ( isset( $user_query->query_vars['orderby'] ) && 'wp-last-login' === $user_query->query_vars['orderby'] ) {
		$user_query->query_vars = array_merge(
			$user_query->query_vars,
			array(
				'meta_key' => 'wp-last-login', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'  => 'meta_value_num',
			)
		);
	}

	return $user_query;
}
add_filter( 'pre_get_users', 'wpll_pre_get_users' );
