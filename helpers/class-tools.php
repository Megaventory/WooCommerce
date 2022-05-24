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
}
