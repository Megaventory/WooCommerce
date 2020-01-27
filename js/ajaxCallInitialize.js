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
 * @param {integer} startingInd          as the starting point.
 * @param {integer} numberOfIndToProcess as the end point.
 * @param {string} call                  as the entity code block.
 */
function ajaxInitialize(block, startingInd, numberOfIndToProcess, call) {
	jQuery( '#loading' ).show();
	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'asyncImport',
				'block': block,
				'startingIndex': startingInd,
				'numberOfIndexesToProcess': numberOfIndToProcess,
				'call': call,
				'async-nonce': ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				var obj            = JSON.parse( data.data );
				var block          = obj.block;
				var startingInd    = obj.starting_index;
				var message        = obj.success_message;
				var percentMessage = obj.percent_message;

				if (message.includes( 'continue' )) {
					jQuery( '#loading h1' ).html( percentMessage );
					ajaxInitialize( block, startingInd, numberOfIndToProcess, call );// new ajax call.
				}

				if (message.includes( 'FinishedSuccessfully' )) {
					jQuery( '#loading h1' ).html( "Current Sync Count: 100%" );
					setTimeout( function () {jQuery( '#loading' ).hide(); jQuery( 'body>*' ).css( "filter", "none" );}, 2000 );
					location.reload();
				}
			},

			error: function (errorThrown) {
				alert( 'error on import' );
			}
		}
	);
}

function changeDefaultInventory(inventory_id) {
	jQuery( '#loading' ).show();
	jQuery( '#loading' ).find( 'h1' ).hide();
	jQuery.ajax(
		{
			url: "admin-ajax.php", // Or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'changeDefaultMegaventoryLocation',
				'inventory_id': inventory_id,
				'async-nonce': ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading' ).hide();
			},

			error: function (errorThrown) {
				alert( 'error on import' );
				jQuery( '#loading' ).hide();
			}
		}
	);
}
