<?php
/**
 * Uninstall.
 *
 * @package wp-last-login
 */

// Don't uninstall unless you absolutely want to!
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	wp_die( 'WP_UNINSTALL_PLUGIN undefined.' );
}

delete_metadata( 'user', 0, 'wp-last-login', '', true );


/* Goodbye! Thank you for having me! */
