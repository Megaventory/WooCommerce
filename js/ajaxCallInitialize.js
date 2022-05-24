/**
 * Javascript functions for initialization.
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
 * Initialize javascript function.
 *
 * @param {string} block                 as the type that will initialized.
 * @param {integer} countOfEntity        as count of entities.
 * @param {integer} numberOfIndToProcess as the end point.
 * @param {string} call                  as the entity code block.
 */
function megaventory_initialize(block, countOfEntity, page, numberOfIndToProcess, call) {
	jQuery( '#loading' ).show();
	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_import',
				'block': block,
				'countOfEntity': countOfEntity,
				'page': page,
				'numberOfIndexesToProcess': numberOfIndToProcess,
				'call': call,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				var obj            = JSON.parse( data.data );
				var block          = obj.block;
				var message        = obj.success_message;
				var countOfEntity  = obj.count_of_entity;
				var percentMessage = obj.percent_message;
				var page           = obj.page;

				if (message.includes( 'continue' )) {

					jQuery( '#loading h1' ).html( percentMessage );
					megaventory_initialize( block, countOfEntity, page, numberOfIndToProcess, call );// new ajax call.

				} else if ( message.includes( 'FinishedSuccessfully' ) ) {

					jQuery( '#loading h1' ).html( "Current Sync Count: 100%" );
					setTimeout( function () {jQuery( '#loading' ).hide(); jQuery( 'body>*' ).css( "filter", "none" );}, 2000 );
					location.reload();

				} else if ( message.includes( 'Error' ) ) {

					alert( 'An error occured during the ' + call + ' process. Please try again. If the error persists, contact Megaventory support.' );
					setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
					location.reload();
				}
			},

			error: function (errorThrown) {
				alert( 'An error occured during the initialization process. Please try again. If the error persists, contact Megaventory support.' );
			}
		}
	);
}

/**
 * Initialize javascript function.
 *
 * @param {string} block                 as the type that will initialized.
 * @param {integer} countOfEntity        as count of entities.
 * @param {integer} numberOfIndToProcess as the end point.
 * @param {string} call                  as the entity code block.
 */
function megaventory_reinitialize(block, countOfEntity, page, numberOfIndToProcess, call) {

	if ( ! confirm( "Are you sure you want to re-initialize the Megaventory plugin? After that you need to synchronize products, clients, coupons and product stock again." ) ) {
		return;
	}

	megaventory_initialize( block, countOfEntity, page, numberOfIndToProcess, call )

}

function megaventory_toggle_order_delay() {
	jQuery( '#loading' ).show();
	var checbox_value = jQuery( '#enable_mv_order_sync_delay' ).prop( 'checked' );
	var initial_value = ! checbox_value;
	var secondsToWait = checbox_value ? Math.floor( jQuery( '#mv_order_delay_input' ).val() ) : 7200;

	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_toggle_order_delay',
				'newStatus': checbox_value,
				'secondsToWait' : secondsToWait,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#enable_mv_order_sync_delay' ).prop( 'checked', checbox_value );
				jQuery( '#loading h1' ).html( "Progress: 100%" );
				setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
			},

			error: function (errorThrown) {
				jQuery( '#enable_mv_order_sync_delay' ).prop( 'checked', initial_value );
				alert( 'Error, unable to change the status, try again!' );
			}
		}
	);

	return false;
}

function megaventory_show_hide_sync_delay_input() {

	var checked = jQuery( '#enable_mv_order_sync_delay' ).prop( 'checked' );

	if (checked) {
		jQuery( '#mv_order_delay_controls' ).show();
	} else {
		jQuery( '#mv_order_delay_controls' ).hide();
	}

}
