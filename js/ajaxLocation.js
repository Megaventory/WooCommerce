/**
 * Javascript functions for location.
 *
 * @package megaventory
 * @since 2.3.0
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Changes the default location
 *
 * @param {int} inventory_id as Megaventory location id.
 */
function megaventory_change_default_location(inventory_id) {
	jQuery( '#loading' ).show();
	jQuery( '#loading' ).find( 'h1' ).hide();
	jQuery.ajax(
		{
			url: "admin-ajax.php", // Or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_change_default_location',
				'inventory_id': inventory_id,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading' ).hide();
				location.reload();
			},

			error: function (errorThrown) {
				alert( 'Error occurred, try again! If the error persist contact to Megaventory!' );
				jQuery( '#loading' ).hide();
			}
		}
	);
}

/**
 * Includes a location in stock handling.
 *
 * @param {int} inventory_id as Megaventory location id.
 */
function megaventory_include_location(inventory_id) {
	jQuery( '#loading' ).show();
	jQuery( '#loading' ).find( 'h1' ).hide();
	jQuery.ajax(
		{
			url: "admin-ajax.php", // Or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_include_location',
				'inventory_id': inventory_id,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading' ).hide();
				jQuery( "#massImportInventoryAfterLocationIsIncludedOrExcluded" ).removeClass( 'hidden' );
			},

			error: function (errorThrown) {
				alert( 'Error occurred, try again! If the error persist contact to Megaventory!' );
				jQuery( '#loading' ).hide();
			}
		}
	);
}

/**
 * Exclude a location in stock handling.
 *
 * @param {int} inventory_id as Megaventory location id.
 */
function megaventory_exclude_location(inventory_id) {
	jQuery( '#loading' ).show();
	jQuery( '#loading' ).find( 'h1' ).hide();
	jQuery.ajax(
		{
			url: "admin-ajax.php", // Or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_exclude_location',
				'inventory_id': inventory_id,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading' ).hide();
				jQuery( "#massImportInventoryAfterLocationIsIncludedOrExcluded" ).removeClass( 'hidden' );
			},

			error: function (errorThrown) {
				alert( 'Error occurred, try again! If the error persist contact to Megaventory!' );
				jQuery( '#loading' ).hide();
			}
		}
	);
}
