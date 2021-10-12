<?php
/**
 * Address helper.
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
 * Help correctly format the address.
 *
 * @param array $ar is an address object.
 */
function format_address( $ar ) {
	$name     = empty( $ar['name'] ) ? '' : $ar['name'];
	$company  = empty( $ar['company'] ) ? '' : $ar['company'];
	$line_1   = empty( $ar['line_1'] ) ? '' : $ar['line_1'];
	$line_2   = empty( $ar['line_2'] ) ? '' : $ar['line_2'];
	$city     = empty( $ar['city'] ) ? '' : $ar['city'];
	$county   = empty( $ar['county'] ) ? '' : $ar['county'];
	$postcode = empty( $ar['postcode'] ) ? '' : $ar['postcode'];
	$country  = empty( $ar['country'] ) ? '' : $ar['country'];

	$country_states = WC()->countries->get_states( $country );

	if ( is_array( $country_states ) && in_array( $county, array_keys( $country_states ), true ) ) {
		$county = $country_states[ $county ];
	}

	$address = '';
	if ( null !== $name && ! ctype_space( $name ) ) {
		$address .= $name . " \n ";
	}
	if ( null !== $company && ! ctype_space( $company ) ) {
		$address .= $company . " \n ";
	}
	if ( null !== $line_1 && ! ctype_space( $line_1 ) ) {
		$address .= $line_1 . " \n ";
	}
	if ( null !== $line_2 && ! ctype_space( $line_2 ) ) {
		$address .= $line_2 . " \n ";
	}
	if ( null !== $city && ! ctype_space( $city ) ) {
		$address .= $city . " \n ";
	}
	if ( null !== $county && ! ctype_space( $county ) ) {
		$address .= $county . " \n ";
	}
	if ( null !== $postcode && ! ctype_space( $postcode ) ) {
		$address .= $postcode . " \n ";
	}
	if ( null !== $country && ! ctype_space( $country ) ) {
		$address .= $country . " \n ";
	}

	// Add customer phone and email to billing address if order customer is guest.
	if ( array_key_exists( 'phone', $ar ) ) {
		$phone    = $ar['phone'];
		$address .= " \n Phone: " . $phone . " \n ";
	}

	if ( array_key_exists( 'email', $ar ) ) {
		$email    = $ar['email'];
		$address .= " \n Email: " . $email . " \n ";
	}

	return $address;
}
