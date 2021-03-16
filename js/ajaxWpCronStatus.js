/**
 * Javascript functions for changing alternate_wp_cron status.
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
 * Change alternate_wp_cron status function.
 */

function changeWpCronStatus() {
	window.event.preventDefault();
	jQuery( '#loading' ).show();
	var checbox_value = jQuery( '#enable_alternate_wp_cron' ).prop( 'checked' );
	var initial_value = ! checbox_value;

	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'alternateWpCronStatus',
				'newStatus': checbox_value,
				'async-nonce': ajax_object.nonce
			},
			success: function (data) { // This outputs the result of the ajax request.
				jQuery( '#enable_alternate_wp_cron' ).prop( 'checked', checbox_value );
				jQuery( '#loading h1' ).html( "Progress: 100%" );
				setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
			},

			error: function (errorThrown) {
				jQuery( '#enable_alternate_wp_cron' ).prop( 'checked', initial_value );
				alert( 'Error, unable to change the status, try again!' );
			}
		}
	);
}
