/**
 * Javascript functions for Imports.
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
 * Import javascript function.
 */
function ajaxImport(startingIndex, numberOfIndToProcess, countOfEntity, page, successes, errors, call) {
	jQuery( '#loading' ).show();
	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'asyncImport',
				'startingIndex': startingIndex,
				'numberOfIndexesToProcess': numberOfIndToProcess,
				'countOfEntity' : countOfEntity,
				'page': page,
				'successes': successes,
				'errors': errors,
				'call': call,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				var obj              = JSON.parse( data.data );
				var startingIndex    = obj.starting_index;
				var countOfEntity    = obj.count_of_entity;
				var page             = obj.page;
				var CurrentSyncCount = obj.current_sync_count_message;
				var successes        = obj.success_count;
				var errors           = obj.errors_count;
				var message          = obj.success_message;

				if (message.includes( 'continue' )) {

					jQuery( '#loading h1' ).html( CurrentSyncCount );
					ajaxImport( startingIndex, numberOfIndToProcess, countOfEntity, page, successes, errors, call );// new ajax call.

				} else if ( message.includes( 'FinishedSuccessfully' ) ) {

					jQuery( '#loading h1' ).html( "Current Sync Count: 100%" );
					setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
					location.reload();
				} else if ( message.includes( 'Error' ) ) {

					alert( 'Error on ' + call + ', try again! If the error persist contact to Megaventory!' );
					setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
					location.reload();
				}
			},

			error: function (errorThrown) {
				alert( 'Error on import, try again! If the error persist contact to Megaventory!' );
			}
		}
	);
}

/**
 * Pull Integration Updates manually.
 */
function ajaxPullUpdates() {
	jQuery( '#loading_operation' ).show();
	jQuery.ajax(
		{
			url: "admin-ajax.php",
			type: "POST",
			data: {
				'action': 'pull_integration_updates',
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading_operation' ).hide();
			},

			error: function (errorThrown) {
				alert( 'Error on updates synchronization, try again! If the error persists, contact Megaventory support.' );
				jQuery( '#loading_operation' ).hide();
			}
		}
	);
}

/**
 * Pull Integration Updates manually.
 */
function SyncStockToMegaventory(starting_index) {
	jQuery( '#loading_operation' ).show();

	var prefered_status = jQuery( '#mv_adjustment_document_status' ).val();

	jQuery.ajax(
		{
			url: "admin-ajax.php",
			type: "POST",
			data: {
				'action': 'sync_stock_to_megaventory',
				'startingIndex': starting_index,
				'prefered-status': prefered_status,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				var obj = JSON.parse( data.data );

				var startingIndex  = obj.starting_index;
				var next_index     = obj.next_index;
				var error_occurred = obj.error_occurred;
				var finished       = obj.finished;
				var message        = obj.message;

				if ( error_occurred ) {

					setTimeout( function () {jQuery( '#loading_operation' ).hide();}, 2000 );
					location.reload();

				}

				jQuery( '#loading_operation h1' ).html( message );

				if ( ! finished ) {

					SyncStockToMegaventory( next_index );// new ajax call.

				} else {

					setTimeout( function () {jQuery( '#loading_operation' ).hide();}, 2000 );
					location.reload();
				}
			},

			error: function (errorThrown) {
				alert( 'Error on pushing stock, try again! If the error persists, contact Megaventory support.' );
				jQuery( '#loading_operation' ).hide();
			}
		}
	);
}

/**
 * Pull Integration Updates manually.
 */
function SyncStockFromMegaventory(starting_index) {
	jQuery( '#loading_operation' ).show();
	jQuery.ajax(
		{
			url: "admin-ajax.php",
			type: "POST",
			data: {
				'action': 'sync_stock_from_megaventory',
				'startingIndex': starting_index,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				var obj = JSON.parse( data.data );

				var startingIndex  = obj.starting_index;
				var next_index     = obj.next_index;
				var error_occurred = obj.error_occurred;
				var finished       = obj.finished;
				var message        = obj.message;

				if ( error_occurred ) {

					setTimeout( function () {jQuery( '#loading_operation' ).hide();}, 2000 );
					location.reload();

				}

				jQuery( '#loading_operation h1' ).html( message );

				if ( ! finished ) {

					SyncStockFromMegaventory( next_index );// new ajax call.

				} else {

					setTimeout( function () {jQuery( '#loading_operation' ).hide();}, 2000 );
					location.reload();
				}
			},

			error: function (errorThrown) {
				alert( 'Error on pulling stock, try again! If the error persists, contact Megaventory support.' );
				jQuery( '#loading_operation' ).hide();
			}
		}
	);
}

/**
 * Skip stock synchronization.
 */
function SkipStockSynchronization() {
	jQuery( '#loading_operation' ).show();
	jQuery.ajax(
		{
			url: "admin-ajax.php",
			type: "POST",
			data: {
				'action': 'skip_stock_synchronization',
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				setTimeout( function () {jQuery( '#loading_operation' ).hide();}, 2000 );
				location.reload();
			},

			error: function (errorThrown) {
				alert( 'Error occurred, try again! If the error persist contact to Megaventory!' );
				jQuery( '#loading_operation' ).hide();
			}
		}
	);
}

/**
 * Synchronize Order manually.
 */
function SyncOrder(order_Id) {
	jQuery( '#loading_operation' ).show();
	jQuery.ajax(
		{
			url: "admin-ajax.php",
			type: "POST",
			data: {
				'action': 'sync_order',
				'orderId': order_Id,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.

				setTimeout( function () {jQuery( '#loading_operation' ).hide();}, 2000 );
				location.reload();
			},

			error: function (errorThrown) {

				setTimeout( function () {jQuery( '#loading_operation' ).hide();}, 2000 );
				location.reload();
			}
		}
	);
}
