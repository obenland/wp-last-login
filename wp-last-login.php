<?php
/** wp-last-login.php
 *
 * Plugin Name:	WP Last Login
 * Plugin URI:	http://en.wp.obenland.it/wp-last-login/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-last-login
 * Description:	Displays the date of the last login in user lists.
 * Version:		1.1.1
 * Author:		Konstantin Obenland
 * Author URI:	http://en.wp.obenland.it/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-last-login
 * Text Domain: wp-last-login
 * Domain Path: /lang
 * License:		GPLv2
 */


if ( ! class_exists('Obenland_Wp_Plugins_v15') ) {
	require_once( 'obenland-wp-plugins.php' );
}


class Obenland_Wp_Last_Login extends Obenland_Wp_Plugins_v15 {
	
	
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
			'plugin_path'		=>	__FILE__,
			'donate_link_id'	=>	'K32M878XHREQC'
		));
		
		load_plugin_textdomain( 'wp-last-login', false, 'wp-last-login/lang' );
		
		$this->hook( 'wp_login' );
		
		/**
		 * Programmers:
		 * To limit this information to certain user roles, add a filter to
		 * 'wpll_current_user_can' and check for user permissions, returning
		 * true or false!
		 *
		 * Example:
		 *
		 * function prefix_wpll_visibility( $bool ) {
		 * 		return current_user_can( 'manage_options' ); // Only for Admins
		 * }
		 * add_filter( 'wpll_current_user_can', 'prefix_wpll_visibility' );
		 *
		 */
		if ( is_admin() AND apply_filters( 'wpll_current_user_can', true ) ) {
			
			$this->hook( 'manage_site-users-network_columns',	'add_column',	1 );
			$this->hook( 'manage_users_columns',				'add_column',	1 );
			$this->hook( 'wpmu_users_columns',					'add_column',	1 );
			$this->hook( 'admin_print_styles-users.php',		'column_style' );
			$this->hook( 'admin_print_styles-site-users.php',	'column_style' );
			$this->hook( 'manage_users_custom_column' );
		}
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
	public function wp_login( $user_login ) {
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
	 * @return	string
	 */
	public function manage_users_custom_column( $empty, $column_name, $user_id ) {
		
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
	public function column_style() {
		?>
		<style type="text/css">
			.column-wp-last-login { width: 9%; }
		</style>
		<?php
	}

}  // End of class Obenland_Wp_Last_Login


new Obenland_Wp_Last_Login;


/* End of file wp-last-login.php */
/* Location: ./wp-content/plugins/wp-last-login/wp-last-login.php */