<?php
/**
 * Obenland_Wp_Last_Login file.
 *
 * @package wp-last-login
 */

/**
 * Class Obenland_Wp_Last_Login.
 */
class Obenland_Wp_Last_Login extends Obenland_Wp_Plugins_V5 {

	/**
	 * Constructor.
	 *
	 * @since 1.0 - 23.01.2012
	 */
	public function __construct() {
		parent::__construct( array(
			'textdomain'     => 'wp-last-login',
			'plugin_path'    => __DIR__ . '/wp-last-login.php',
			'donate_link_id' => 'K32M878XHREQC',
		) );

		load_plugin_textdomain( 'wp-last-login', false, 'wp-last-login/lang' );

		$this->hook( 'wp_login' );
		$this->hook( 'user_register' );

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
		if ( is_admin() && apply_filters( 'wpll_current_user_can', true ) ) {
			$this->hook( 'manage_site-users-network_columns', 'add_column', 1 );
			$this->hook( 'manage_users_columns', 'add_column', 1 );
			$this->hook( 'wpmu_users_columns', 'add_column', 1 );
			$this->hook( 'admin_print_styles-users.php', 'column_style' );
			$this->hook( 'admin_print_styles-site-users.php', 'column_style' );
			$this->hook( 'manage_users_custom_column' );
			$this->hook( 'manage_users_sortable_columns', 'add_sortable' );
			$this->hook( 'manage_users-network_sortable_columns', 'add_sortable' );
			$this->hook( 'pre_get_users' );
		}
	}

	/**
	 * Update the login timestamp.
	 *
	 * @since 1.0 - 23.01.2012
	 *
	 * @param string $user_login The user's login name.
	 */
	public function wp_login( $user_login ) {
		$user = get_user_by( 'login', $user_login );
		update_user_meta( $user->ID, $this->textdomain, time() );
	}

	/**
	 * Set default data for new users.
	 *
	 * @since 3 - 12.09.2019
	 *
	 * @param int $user_id The user ID.
	 */
	public function user_register( $user_id ) {
		update_user_meta( $user_id, $this->textdomain, 0 );
	}

	/**
	 * Adds the last login column to the network admin user list.
	 *
	 * @since 1.0 - 23.01.2012
	 *
	 * @param array $cols The default columns.
	 * @return array
	 */
	public function add_column( $cols ) {
		$cols[ $this->textdomain ] = __( 'Last Login', 'wp-last-login' );
		return $cols;
	}

	/**
	 * Adds the last login column to the network admin user list.
	 *
	 * @since 1.0 - 23.01.2012
	 *
	 * @param string $value       Value of the custom column.
	 * @param string $column_name The name of the column.
	 * @param int    $user_id     The user's id.
	 * @return string
	 */
	public function manage_users_custom_column( $value, $column_name, $user_id ) {
		if ( $this->textdomain === $column_name ) {
			$value      = __( 'Never.', 'wp-last-login' );
			$last_login = (int) get_user_meta( $user_id, $this->textdomain, true );

			if ( $last_login ) {
				/**
				 * Date format to use with last login date.
				 *
				 * @param string $format Date format. Default: `date_format` option value.
				 */
				$format     = apply_filters( 'wpll_date_format', get_option( 'date_format' ) );
				$last_login = get_date_from_gmt( date( 'Y-m-d H:i:s', $last_login ), 'U' );
				$value      = date_i18n( $format, $last_login );
			}
		}

		return $value;
	}

	/**
	 * Register the column as sortable.
	 *
	 * @since 1.2.0 - 11.12.2012
	 *
	 * @param array $columns User table columns.
	 * @return array
	 */
	public function add_sortable( $columns ) {
		$columns[ $this->textdomain ] = $this->textdomain;

		return $columns;
	}

	/**
	 * Handle ordering by last login.
	 *
	 * @since 1.2.0 - 11.12.2012
	 *
	 * @param WP_User_Query $user_query Request arguments.
	 * @return WP_User_Query
	 */
	public function pre_get_users( $user_query ) {
		if ( isset( $user_query->query_vars['orderby'] ) && $this->textdomain === $user_query->query_vars['orderby'] ) {
			$user_query->query_vars = array_merge(
				$user_query->query_vars,
				array(
					'meta_key' => $this->textdomain,
					'orderby'  => 'meta_value_num',
				)
			);
		}

		return $user_query;
	}

	/**
	 * Defines the width of the column
	 *
	 * @since 1.0 - 23.01.2012
	 */
	public function column_style() {
		?>
		<style>.column-wp-last-login { width: 12%; }</style>
		<?php
	}
}
