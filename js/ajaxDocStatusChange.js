/**
 * Javascript functions for changing the option for adjustment document status.
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
 * Change the option for adjustment document status function.
 */

function changeDocumentStatusOption() {
	jQuery( '#loading' ).show();
	var prefered_status = jQuery( '#mv_adjustment_document_status' ).val();

	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'changeAdjustmentDocumentStatusOption',
				'prefered-status': prefered_status,
				'async-nonce': mv_ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#loading h1' ).html( "Progress: 100%" );
				setTimeout( function () {jQuery( '#loading' ).hide();}, 1000 );
			},

			error: function (errorThrown) {
				alert( 'Error, unable to change the option, try again!' );
			}
		}
	);

	return false;
}
