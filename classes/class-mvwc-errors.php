<?php
/**
 * Errors class.
 *
 * @package megaventory
 * @since 1.0.0
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
/**
 * Errors should be immutable.
 * There is no point in changing the messages.
 */
class MVWC_Errors {

	/**
	 * Errors as array
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Log errors
	 *
	 * @param array $args as error messages.
	 * @return void
	 */
	public function log_error( $args = array() ) {
		array_push( $this->errors, new MVWC_Error( $args ) );
	}

	/**
	 * Error full message.
	 *
	 * @return string
	 */
	public function full_messages() {
		$msgs = array();
		foreach ( $this->errors as $error ) {
			array_push( $msgs, $error->get_full_message() );
		}

		return $msgs;
	}
}
