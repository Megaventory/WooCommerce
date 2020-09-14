<?php
/**
 * MV_Constants helper.
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

/**
 * Some constants.
 */
class MV_Constants {

	/**
	 * Megaventory Separator.
	 */
	const MV_SEPARATOR = ' - ';

	/**
	 * Megaventory Slash.
	 */
	const MV_SLASH = ' / ';

	/**
	 * Discounts that applied for Document tags.
	 */
	const COUPONS_APPLIED = 'Discounts Applied: ';

	/**
	 * We use it to create adjustments in Megaventory in batches.
	 */
	const STOCK_BATCH_COUNT = 40;

	const SYNC_STOCK_FROM_MEGAVENTORY = 40;

	const DOCUMENT_UPDATE = 'DocumentUpdate';

	const INVENTORY_LOCATION_STOCK_GET = 'InventoryLocationStockGet';

	const CURRENCY_GET = 'CurrencyGet';

	const INTEGRATION_UPDATE_GET = 'IntegrationUpdateGet';

	const INTEGRATION_UPDATE_DELETE = 'IntegrationUpdateDelete';

	const PRODUCT_GET = 'ProductGet';

	const PRODUCT_DELETE = 'ProductDelete';

	const SUPPLIER_CLIENT_DELETE = 'SupplierClientDelete';
}
