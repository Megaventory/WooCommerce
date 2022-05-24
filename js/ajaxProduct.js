/**
 * Javascript functions for updating extra fee options.
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

/**
 * Changes the generic sku for extra fees.
 */
function megaventory_update_extra_fee_sku() {
	jQuery( '#loading' ).show();

	var sku = jQuery( '#mv_extra_fee_sku_control' ).val();
	jQuery.ajax(
		{
			url: "admin-ajax.php", // Or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_update_extra_fee_sku',
				'extra_fee_sku': sku,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading' ).hide();

				if ( ! data.success && data.data.message ) {
					alert( data.data.message );
				}
			},

			error: function (errorThrown) {
				alert( 'Error occurred, try again! If the error persist contact to Megaventory!' );
				jQuery( '#loading' ).hide();
			}
		}
	);
}
