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

namespace Megaventory\Models;

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

	const DEFAULT_ERROR_MESSAGE_COUNT_TO_DISPLAY = 50;

	const DEFAULT_SUCCESS_MESSAGE_COUNT_TO_DISPLAY = 50;

	const DOCUMENT_UPDATE = 'DocumentUpdate';

	const INVENTORY_LOCATION_STOCK_GET = 'InventoryLocationStockGet';

	const CURRENCY_GET = 'CurrencyGet';

	const INTEGRATION_UPDATE_GET = 'IntegrationUpdateGet';

	const INTEGRATION_UPDATE_DELETE = 'IntegrationUpdateDelete';

	const PRODUCT_GET = 'ProductGet';

	const PRODUCT_UPDATE = 'ProductUpdate';

	const PRODUCT_DELETE = 'ProductDelete';

	const BUNDLE_GET = 'ProductBundleGet';

	const BUNDLE_UPDATE = 'ProductBundleUpdate';

	const BUNDLE_DELETE = 'ProductBundleDelete';

	const BUNDLE_UNDELETE = 'ProductBundleUndelete';

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

	const MV_AUTO_ASSIGN_BATCH_NUMBERS_OPT = 'megaventory_auto_assign_batch_numbers';

	const AUTO_INSERT_BATCH_NUMBERS_TO_PRODUCT_ROWS = array(
		'Undefined'      => 'Undefined',
		'ByExpiryDate'   => 'ByExpiryDate',
		'ByCreationDate' => 'ByCreationDate',
		'ByName'         => 'ByName',
	);

	const MAX_REQUEST_ATTEMPTS = 3;

	const REQUEST_TIMEOUT_LIMIT_IN_SECONDS = 20;

	const MV_DEFAULT_SALES_ORDER_TEMPLATE = 3;

	const SECONDS_TO_MICROSECONDS_CONVERSION_RATE = 1E6;

	const CHECK_STATUS_VALUE = 5;

	const RANDOM_NUMBER_MIN = 0;

	const RANDOM_NUMBER_MAX = 30;

	const DEF_BUNDLES_PLUGIN_DIR = 'woocommerce-product-bundles/woocommerce-product-bundles.php';

	const MV_RELATED_ORDER_ID_META = 'order_sent_to_megaventory';

	const MV_RELATED_ORDER_NAMES_META = 'megaventory_order_name';

	const MV_ORDER_STATUSES_META = 'megaventory_related_order_statuses';

	const SHIPPING_ZONES_ENABLE_OPT = 'megaventory_enable_shipping_zones';

	const SHIPPING_ZONES_PRIORITY_OPT = 'megaventory_shipping_zone_priority';

	const SHIPPING_ZONES_EXCLUDED_LOCATION_OPT = 'megaventory_shipping_zone_excluded_locations';

	const MV_ORDER_DELAY_CHOICE_OPT = 'megaventory_delay_order_sync';

	const MV_ORDER_DELAY_SECONDS_CHOICE_OPT = 'megaventory_order_delay_seconds';

	const MV_STOCK_UPDATE_NOTICE_OPT = 'megaventory_stock_notify_user';

	const MV_STOCK_UPDATE_NOTICE_EXP_SECS = 3600;

	const MV_ORDERS_TO_SYNC_OPT = 'megaventory_orders_to_sync_queue';

	const MV_EXCLUDED_LOCATION_IDS_OPT = 'megaventory_excluded_location_ids';

	const MV_LOCATION_ID_TO_ABBREVIATION = 'mv_location_id_to_abbr';

	const MV_ORDER_SYNC_EVENT = 'sync_orders_to_megaventory_event';

	const DEFAULT_EXTRA_FEE_SERVICE_SKU = 'extra_fee_service';

	const MV_PAYMENT_METHOD_MAPPING_OPT = 'megaventory_wc_payment_method_mappings';

	const DEFAULT_MEGAVENTORY_PAYMENT_METHOD = 'None';

	/**
	 * The default time to wait before synchronizing a new WC Order to Megaventory.
	 */
	const MV_ORDER_SYNC_DEFAULT_SECONDS_TO_WAIT = 7200;

	/**
	 * The Id of the Rest-Of-World Default Zone of WooCommerce.
	 */
	const SHIPPING_DEFAULT_ZONE_ID = 0;

	const ADDRESS_TYPE_BILLING    = 'Billing';
	const ADDRESS_TYPE_SHIPPING_1 = 'Shipping1';
	const ADDRESS_TYPE_SHIPPING_2 = 'Shipping2';
	const ADDRESS_TYPE_GENERAL    = 'General';

	const DEFAULT_STRING_MAX_LENGTH = 400;
}
