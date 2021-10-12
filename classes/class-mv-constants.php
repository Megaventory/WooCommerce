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
	 * Megaventory default host
	 */
	const MV_DEFAULT_HOST = 'https://api.megaventory.com/v2017a/';

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
	const PUSH_STOCK_BATCH_COUNT = 40;

	/**
	 * We use it to update the administrator about the progress of the export.
	 */
	const PUSH_STOCK_ADMIN_UPDATE_COUNT = 20;

	const SYNC_STOCK_FROM_MEGAVENTORY = 40;

	const DOCUMENT_UPDATE = 'DocumentUpdate';

	const INVENTORY_LOCATION_STOCK_GET = 'InventoryLocationStockGet';

	const CURRENCY_GET = 'CurrencyGet';

	const INTEGRATION_UPDATE_GET = 'IntegrationUpdateGet';

	const INTEGRATION_UPDATE_DELETE = 'IntegrationUpdateDelete';

	const PRODUCT_GET = 'ProductGet';

	const PRODUCT_UPDATE = 'ProductUpdate';

	const PRODUCT_DELETE = 'ProductDelete';

	const SUPPLIER_CLIENT_DELETE = 'SupplierClientDelete';

	const SALES_ORDER_GET = 'SalesOrderGet';

	const SALES_ORDER_UPDATE = 'SalesOrderUpdate';

	const SALES_ORDER_CANCEL = 'SalesOrderCancel';

	const MV_RECORD_ACTION = array(
		'Insert'                       => 'Insert',
		'Update'                       => 'Update',
		'InsertOrUpdate'               => 'InsertOrUpdate',
		'InsertOrUpdateNonEmptyFields' => 'InsertOrUpdateNonEmptyFields',
	);

	const MV_PRODUCT_TYPE = array(
		'BuyFromSupplier'                           => 'BuyFromSupplier',
		'Service'                                   => 'Service',
		'ManufactureFromWorkOrder'                  => 'ManufactureFromWorkOrder',
		'BuyFromSupplierOrManufactureFromWorkOrder' => 'BuyFromSupplierOrManufactureFromWorkOrder',
		'TimeRestrictedService'                     => 'TimeRestrictedService',
		'Undefined'                                 => 'Undefined',
	);

	const MAX_FAILED_CONNECTION_ATTEMPTS = 100;

	const ADJ_PLUS_DEFAULT_TRANS_ID = -99;

	const ADJ_MINUS_DEFAULT_TRANS_ID = -98;

	const INTERNAL_SUPPLIER_CLIENT_FOR_ADJUSTMENTS_AND_OTHER_OPERATIONS = -1;

	const MV_DOCUMENT_STATUS_MAPPINGS = array(
		0  => 'ValidStatus',
		10 => 'Pending',
		20 => 'ApprovalInProcess',
		30 => 'Verified',
		35 => 'Picked',
		36 => 'Packed',
		40 => 'PartiallyShipped',
		41 => 'PartiallyShippedInvoiced',
		42 => 'FullyShipped',
		43 => 'PartiallyReceived',
		44 => 'PartiallyReceivedInvoiced',
		45 => 'FullyReceived',
		46 => 'PartiallyInvoiced',
		47 => 'FullyInvoiced',
		48 => 'PartiallyPaid',
		49 => 'FullyPaid',
		50 => 'Closed',
		70 => 'ClosedWO',
		99 => 'Cancelled',
	);

	const MV_DOCUMENT_STATUS_TO_WC_ORDER_STATUS_MAPPINGS = array(
		'Pending'                  => 'on-hold',
		'Verified'                 => 'processing',
		'PartiallyShipped'         => 'processing',
		'PartiallyShippedInvoiced' => 'processing',
		'FullyShipped'             => 'processing',
		'Closed'                   => 'completed',
		// Received is only for purchase orders.
		'FullyInvoiced'            => 'completed',
		'Cancelled'                => 'cancelled',
	);

	const MAX_REQUEST_ATTEMPTS = 3;

	const REQUEST_TIMEOUT_LIMIT_IN_SECONDS = 20;

	const MV_DEFAULT_SALES_ORDER_TEMPLATE = 3;

	const SECONDS_TO_MICROSECONDS_CONVERSION_RATE = 1E6;

	const CHECK_STATUS_VALUE = 5;

	const RANDOM_NUMBER_MIN = 0;

	const RANDOM_NUMBER_MAX = 30;
}
