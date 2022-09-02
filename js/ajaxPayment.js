/**
 * Javascript functions for updating payment method mappings.
 *
 * @package megaventory
 * @since 2.3.2
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

window.megaventory_woocommerce_payment_method_mappings = {};

/** Update payment mapping array */
function megaventory_payment_method_mapping_change( wc_payment_method ) {
    window.megaventory_woocommerce_payment_method_mappings[ wc_payment_method ] = jQuery("#mv_wc_payment_mapping_input_"+wc_payment_method).val();
}

/** Save payment method mappings */
function megaventory_save_payment_method_mappings() {
    jQuery( '#loading' ).show();

    jQuery.ajax(
		{
			url: "admin-ajax.php", // Or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_update_payment_method_mappings',
				'mv_wc_mapping': JSON.stringify( window.megaventory_woocommerce_payment_method_mappings ),
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading' ).hide();

				if ( ! data.success ) {
					alert( "Unable to save the user defined payment method mappings, please try again" );
				}
			},
			error: function (errorThrown) {
				alert( 'Error occurred, try again! If the error persist contact to Megaventory!' );
				jQuery( '#loading' ).hide();
			}
		}
	);
}