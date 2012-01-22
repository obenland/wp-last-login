<?php
//Don't uninstall unless you absolutely want to!
if ( ! defined( 'WP_UNINSTALL_PLUGIN' )){
	wp_die('WP_UNINSTALL_PLUGIN undefined.');
}

$users = get_users(array(
	'blog_id'	=>	'',
	'fields'	=>	'ID'
));

foreach ( $users as $user ) {
	delete_user_meta( $user->ID, 'wp-last-login' );
}

/* Goodbye! Thank you for having me! */


/* End of file uninstall.php */
/* Location: ./wp-content/plugins/wp-last-login/uninstall.php */