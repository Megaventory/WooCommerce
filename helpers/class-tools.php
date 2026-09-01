<?php
/**
 * API Data Common helper. This helper is used to modify input strings in a
 * form that conforms to the Megaventory API restrictions.
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
class Tools {

	/**
	 * Check if current request is in checkout/order-received flow.
	 *
	 * @return bool
	 */
	public static function is_checkout_phase_request() {

		$request_uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! is_string( $request_uri ) || '' === $request_uri ) {
			return false;
		}

		$query_string = wp_parse_url( $request_uri, PHP_URL_QUERY );

		if ( is_string( $query_string ) && '' !== $query_string ) {

			parse_str( $query_string, $query_params );

			if ( isset( $query_params['wc-ajax'] ) && 'checkout' === sanitize_text_field( (string) $query_params['wc-ajax'] ) ) {

				return true;
			}
		}

		if ( false !== strpos( $request_uri, 'order-received' ) ) {

			return true;
		}

		return false;
	}

	/**
	 * Remove special characters from string.
	 *
	 * @param string $subject as string.
	 * @param array  $characters as array.
	 *
	 * @return string
	 */
	public static function mv_remove_special_chars( $subject, $characters = array( '^', '<', '>', '?', '$', '@', '!', '*', '#' ) ) {

		$subject = str_replace( $characters, ' ', $subject );

		return $subject;
	}

	/**
	 * Trim string to the maximum limited length.
	 *
	 * @param string $subject as string.
	 * @param int    $max_length as int.
	 *
	 * @return string
	 */
	public static function mv_trim_to_max_length( $subject, $max_length = \Megaventory\Models\MV_Constants::DEFAULT_STRING_MAX_LENGTH ) {

		if ( strlen( $subject ) > $max_length ) {
			$subject = mb_substr( $subject, 0, $max_length );
		}

		return $subject;
	}

	/**
	 * This will display a notification to the user to pull all the stock from Megaventory.
	 *
	 * @return void
	 */
	public static function notify_user_for_stock() {
		set_transient( \Megaventory\Models\MV_Constants::MV_STOCK_UPDATE_NOTICE_OPT, true, \Megaventory\Models\MV_Constants::MV_STOCK_UPDATE_NOTICE_EXP_SECS );
	}
}
