<?php
/**
 * Admin notices,warnings,errors
 *
 * @package megaventory
 * @since 1.3.1
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory\Helpers;

/**
 * Megaventory static functions.
 */
class Admin_Notifications {

	/**
	 * Registration errors.
	 *
	 * @param string $str1 as string message.
	 * @param string $str2 as string message.
	 * @return void
	 */
	public static function register_error( $str1 = null, $str2 = null ) {

		$session_messages = get_option( 'mv_session_messages' );

		if ( ! isset( $session_messages ) ) {
			$session_messages = array();
		}
		$errs = empty( $session_messages['errors'] ) ? array() : $session_messages['errors'];

		if ( ! is_array( $errs ) ) {
			$errs = array();
		}

		if ( null !== $str1 && ! in_array( $str1, $errs, true ) ) {
			array_push( $errs, $str1 );
		}

		if ( null !== $str2 && ! in_array( $str2, $errs, true ) ) {
			array_push( $errs, $str2 );
		}

		$session_messages['errors'] = $errs;

		update_option( 'mv_session_messages', $session_messages );
	}

	/**
	 * Registration errors.
	 *
	 * @param string $str1 as string message.
	 * @param string $str2 as string message.
	 * @return void
	 */
	public static function register_warning( $str1 = null, $str2 = null ) {

		$session_messages = get_option( 'mv_session_messages' );

		if ( ! isset( $session_messages ) ) {
			$session_messages = array();
		}
		$warns = empty( $session_messages['warnings'] ) ? array() : $session_messages['warnings'];

		if ( ! is_array( $warns ) ) {
			$warns = array();
		}

		if ( null !== $str1 && ! in_array( $str1, $warns, true ) ) {
			array_push( $warns, $str1 );
		}

		if ( null !== $str2 && ! in_array( $str2, $warns, true ) ) {
			array_push( $warns, $str2 );
		}

		$session_messages['warnings'] = $warns;

		update_option( 'mv_session_messages', $session_messages );
	}

	/**
	 * Registration successes.
	 *
	 * @param string $str1 as string message.
	 * @param string $str2 as string message.
	 * @return void
	 */
	public static function register_success( $str1 = null, $str2 = null ) {

		$session_messages = get_option( 'mv_session_messages' );

		if ( ! isset( $session_messages ) ) {
			$session_messages = array();
		}
		$succs = empty( $session_messages['successes'] ) ? array() : $session_messages['successes'];

		if ( ! is_array( $succs ) ) {
			$succs = array();
		}

		if ( null !== $str1 && ! in_array( $str1, $succs, true ) ) {
			array_push( $succs, $str1 );
		}

		if ( null !== $str2 && ! in_array( $str2, $succs, true ) ) {
			array_push( $succs, $str2 );
		}

		$session_messages['successes'] = $succs;

		update_option( 'mv_session_messages', $session_messages );
	}

	/**
	 * Admin errors.
	 *
	 * @return void
	 */
	public static function sample_admin_notice_error() {

		$class = 'notice notice-error';

		global $pagenow;

		if ( 'admin.php' === $pagenow ) {

			$session_messages = get_option( 'mv_session_messages' );

			if ( ! isset( $session_messages ) ) {
				$session_messages = array();
			}

			$errs = ( isset( $session_messages['errors'] ) ? $session_messages['errors'] : array() );

			if ( null !== $errs && count( $errs ) > 0 ) {

				foreach ( $errs as $err ) {

					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $err ) );
				}

				unset( $session_messages['errors'] );

				update_option( 'mv_session_messages', $session_messages );
			}
		}
	}

	/**
	 * Admin warnings.
	 *
	 * @return void
	 */
	public static function sample_admin_notice_warning() {

		$class = 'notice notice-warning';

		global $pagenow;

		if ( 'admin.php' === $pagenow ) {

			$session_messages = get_option( 'mv_session_messages' );

			if ( ! isset( $session_messages ) ) {
				$session_messages = array();
			}

			if ( ! isset( $session_messages['warnings'] ) ) {
				$warns = array();
			}

			$warns = ( isset( $session_messages['warnings'] ) ? $session_messages['warnings'] : array() );

			if ( null !== $warns && count( $warns ) > 0 ) {

				foreach ( $warns as $warn ) {
					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $warn ) );
				}

				unset( $session_messages['warnings'] );

				update_option( 'mv_session_messages', $session_messages );
			}
		}
	}

	/**
	 * Admin successes.
	 *
	 * @return void
	 */
	public static function sample_admin_notice_success() {
		$class = 'notice notice-success';

		global $pagenow;

		if ( 'admin.php' === $pagenow ) {

			$session_messages = get_option( 'mv_session_messages' );

			if ( ! isset( $session_messages ) ) {
				$session_messages = array();
			}

			$succs = ( isset( $session_messages['successes'] ) ? $session_messages['successes'] : array() );

			if ( null !== $succs && count( $succs ) > 0 ) {

				foreach ( $succs as $succ ) {

					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $succ ) );
				}

				unset( $session_messages['successes'] );

				update_option( 'mv_session_messages', $session_messages );
			}
		}
	}

	/**
	 * Set notice in admin panel when plug in is activated.
	 *
	 * @return void
	 */
	public static function plugin_activation_admin_notification() {
		global $plugin_url;

		/* Check transient */
		if ( get_transient( 'plugin_activation_notice' ) ) {
			?>
			<div class="updated notice is-dismissible">
				<p>The Megaventory plugin is now activated! Visit the Megaventory <a href =<?php echo esc_url( $plugin_url ); ?>>plugin section</a> to enter your API key and initialize the synchronization to get started.</p>
			</div>
			<?php
			delete_transient( 'plugin_activation_notice' );
		}
	}

	/**
	 * API call suspended for excessive failed request.
	 */
	public static function register_api_suspension_error() {
		self::register_warning( 'Unable to verify Megaventory API key', 'Please check your API key and try again. All Megaventory synchronization tasks have been disabled due to excessive failed requests. Please ensure your Megaventory account is active and enter a valid API key or disable the plugin if you are not planning on using the integration.' );
	}

	/**
	 * Admin database notices.
	 *
	 * @return void
	 */
	public static function sample_admin_database_notices() {

		$success_class = 'notice notice-success';

		$error_class = 'notice notice-error';

		$notice_class = 'notice notice-info';

		global $wpdb;

		$notices = (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}megaventory_notices_log ORDER BY id ASC LIMIT 50;" ); // phpcs:ignore

		foreach ( $notices as $notice ) {

			if ( 'success' === $notice->type ) {

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $success_class ), esc_html( $notice->message ) );
			}

			if ( 'error' === $notice->type ) {

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $error_class ), esc_html( $notice->message ) );
			}

			if ( 'notice' === $notice->type ) {

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $notice_class ), esc_html( $notice->message ) );
			}
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}megaventory_notices_log" ); // db call ok. no-cache ok.
	}
}
