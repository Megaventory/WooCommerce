<?php
/**
 * Integration Updates controller.
 *
 * @package megaventory
 * @since 2.3.1
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
 * Model for Integration updates.
 */
class Integration_Updates {

	/**
	 * Remove Integration update.
	 *
	 * @param int $id as integer.
	 */
	public static function remove_integration_update( $id ) {

		$data = array(
			'IntegrationUpdateIDToDelete' => $id,
		);

		$url = \Megaventory\API::get_url_for_call( MV_Constants::INTEGRATION_UPDATE_DELETE );

		\Megaventory\API::send_request_to_megaventory( $url, $data );
	}

	/**
	 * Pull Product changes.
	 */
	public static function get_integration_updates() {

		$url  = \Megaventory\API::get_url_for_call( MV_Constants::INTEGRATION_UPDATE_GET );
		$data = array(
			'Filters' => array(
				'FieldName'      => 'Application',
				'SearchOperator' => 'Equals',
				'SearchValue'    => 'Woocommerce',
			),
		);

		$response = \Megaventory\API::send_request_to_megaventory( $url, $data );

		return $response;
	}
}
