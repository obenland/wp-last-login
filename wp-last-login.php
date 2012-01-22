<?php
/** wp-last-login.php
 *
 * Plugin Name:	WP Last Login
 * Plugin URI:	http://www.obenlands.de/en/2012/01/wp-last-login/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-last-login
 * Description:	Displays the date of the last login in user lists.
 * Version:		1.0
 * Author:		Konstantin Obenland
 * Author URI:	http://www.obenlands.de/en/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-last-login
 * Text Domain: wp-last-login
 * Domain Path: /lang
 * License:		GPLv2
 */


if( ! class_exists('Obenland_Wp_Plugins') ) {
	require_once('obenland-wp-plugins.php');
}


class Obenland_Wp_Last_Login extends Obenland_Wp_Plugins {
	
	
	///////////////////////////////////////////////////////////////////////////
	// METHODS, PUBLIC
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * Constructor
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.01.2012
	 * @access	public
	 *
	 * @return	Obenland_Wp_Last_Login
	 */
	public function __construct() {
		
		parent::__construct( array(
			'textdomain'		=>	'wp-last-login',
			'plugin_name'		=>	plugin_basename(__FILE__),
			'donate_link_id'	=>	'K32M878XHREQC'
		));
		
		load_plugin_textdomain( 'wp-last-login', false, 'wp-last-login/lang');
		
		add_action( 'wp_login', array(
			&$this,
			'count_login'
		));
		
		foreach ( array('manage_users_columns', 'wpmu_users_columns') as $hook ) {
			add_filter( $hook, array(
				&$this,
				'add_column'
			), 1);
		}

		add_filter( 'manage_users_custom_column', array(
			&$this,
			'manage_custom_column'
		), 10, 3);
		
		add_action( 'admin_print_styles-users.php', array(
			&$this,
			'column_style'
		));
	}

		
	/**
	 * Update the login timestamp
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.01.2012
	 * @access	public
	 *
	 * @param	string	$user_login	The user's login name
	 *
	 * @return	void
	 */
	public function count_login( $user_login ) {
		$user	=	get_user_by( 'login', $user_login );
		update_user_meta( $user->ID, $this->textdomain, time() );
	}
	
	
	/**
	 * Adds the last login column to the network admin user list
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.01.2012
	 * @access	public
	 *
	 * @param	array	$cols	The default columns
	 *
	 * @return	array
	 */
	public function add_column( $cols ) {
		$cols[$this->textdomain]	=	__( 'Last Login', 'wp-last-login' );
		return $cols;
    }

	
	/**
	 * Adds the last login column to the network admin user list
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.01.2012
	 * @access	public
	 *
	 * @param	string	$empty			An empty string
	 * @param	string	$column_name	The name of the column
	 * @param	int		$user_id		The user's id
	 *
	 * @return	array
	 */
	public function manage_custom_column( $empty, $column_name, $user_id ) {
		
		if ( $this->textdomain == $column_name ) {
			$last_login	=	get_user_meta( $user_id, $this->textdomain, true );
			
			if( $last_login ) {
				$format	=	apply_filters( 'wpll_date_format', get_option('date_format') );
				return date_i18n( $format, $last_login );
			}
			
			return __( 'Never.', 'wp-last-login' );
		}
	}
	
	
	/**
	 * Defines the width of the column
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.01.2012
	 * @access	public
	 *
	 * @return	void
	 */
	public function column_style(){
		$style	=	'<style type="text/css">' . "\n"
				.	'	.column-wp-last-login { width: 8%; }' . "\n"
				.	'</style>'  ."\n";

		echo $style;
	}

}  // End of class Obenland_Wp_Last_Login


new Obenland_Wp_Last_Login;


/* End of file wp-last-login.php */
/* Location: ./wp-content/plugins/wp-last-login/wp-last-login.php */