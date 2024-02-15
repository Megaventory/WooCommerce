<?php
/**
 * Successes class.
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

namespace Megaventory\Models;

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-success.php';

/**
 * Class that contains array of MVWC_Success.
 */
class MVWC_Successes {

	/**
	 * Array of MVWC_Success.
	 *
	 * @var array
	 */
	private $successes = array();

	/**
	 * Log successes.
	 *
	 * @param array $args as success data.
	 * @return void
	 */
	public function log_success( $args = array() ) {
		array_push( $this->successes, new MVWC_Success( $args ) );
	}
}
