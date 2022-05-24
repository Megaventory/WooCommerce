/**
 * Javascript functions for changing alternate_wp_cron status.
 *
 * @package megaventory
 * @since 2.2.0
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Deletes Success logs
 */
function megaventory_delete_success_logs() {

	let ids = [];

	jQuery( "#success-log tbody tr td.success_id" ).each(
		( index, element ) =>
		{
			let id = jQuery( element ).text();
			ids.push( id );
		}
	);

	if ( 0 == ids.length ) {
		return;
	}

	jQuery( '#loading' ).show();

	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_delete_success_logs',
				'ids': JSON.stringify( ids ),
				'async-nonce': mv_ajax_object.nonce
			},
			success: function () {

				setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
				location.reload();
			},

			error: function ( errorThrown ) {

				alert( 'Error occurred, unable to delete success logs, try again! If the error persist contact to Megaventory!' );
			}
		}
	);

	return;
}

/**
 * Deletes Error logs
 */
function megaventory_delete_error_logs() {

	let ids = [];

	jQuery( "#error-log tbody tr td.error_id" ).each(
		( index, element ) =>
		{
			let id = jQuery( element ).text();
			ids.push( id );
		}
	);

	if ( 0 == ids.length ) {
		return;
	}

	jQuery( '#loading' ).show();

	jQuery.ajax(
		{
			url: "admin-ajax.php", // or example_ajax_obj.ajaxurl if using on frontend.
			type: "POST",
			data: {
				'action': 'megaventory_delete_error_logs',
				'ids': JSON.stringify( ids ),
				'async-nonce': mv_ajax_object.nonce
			},
			success: function () {

				setTimeout( function () {jQuery( '#loading' ).hide();}, 2000 );
				location.reload();
			},

			error: function ( errorThrown ) {

				alert( 'Error occurred, unable to delete error logs, try again! If the error persist contact to Megaventory!' );
			}
		}
	);

	return;
}
