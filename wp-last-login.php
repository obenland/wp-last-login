<?php
/**
 * Plugin Name: WP Last Login
 * Plugin URI:  http://en.wp.obenland.it/wp-last-login/#utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-last-login
 * Description: Displays the date of the last login in user lists.
 * Version:     5
 * Author:      Konstantin Obenland
 * Author URI:  http://en.wp.obenland.it/#utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-last-login
 * Text Domain: wp-last-login
 * Domain Path: /lang
 * License:     GPLv2
 *
 * @package wp-last-login
 */

if ( ! class_exists( 'Obenland_Wp_Plugins_V5' ) ) {
	require_once 'class-obenland-wp-plugins-v5.php';
}

require_once 'class-obenland-wp-last-login.php';
new Obenland_Wp_Last_Login();

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
		update_user_meta( $user_id, 'wp-last-login', 0 );
	}
}
register_activation_hook( __FILE__, 'wpll_activate' );
