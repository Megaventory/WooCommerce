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

namespace Megaventory\Helpers;

/**
 * Address helper class.
 */
class Address {

	/**
	 * Help correctly format the address for megaventory multi-field addresses.
	 *
	 * @param array  $ar is an address object.
	 * @param string $address_type Type of megaventory address in string format (Billing,Shipping1,Shipping2,General).
	 * @return array
	 */
	public static function format_multifield_address( $ar, $address_type ) {

		$name         = wp_strip_all_tags( empty( $ar['name'] ) ? '' : $ar['name'] );
		$organization = wp_strip_all_tags( empty( $ar['company'] ) ? '' : $ar['company'] );
		$line_1       = wp_strip_all_tags( empty( $ar['line_1'] ) ? '' : $ar['line_1'] );
		$line_2       = wp_strip_all_tags( empty( $ar['line_2'] ) ? '' : $ar['line_2'] );
		$line_3       = '';
		$city         = wp_strip_all_tags( empty( $ar['city'] ) ? '' : $ar['city'] );
		$state        = wp_strip_all_tags( empty( $ar['county'] ) ? '' : $ar['county'] );
		$postcode     = wp_strip_all_tags( empty( $ar['postcode'] ) ? '' : $ar['postcode'] );
		$country      = wp_strip_all_tags( empty( $ar['country'] ) ? '' : $ar['country'] );
		$tax_id       = ''; // not implemented yet.

		$country_states = WC()->countries->get_states( $country );

		if ( is_array( $country_states ) && in_array( $state, array_keys( $country_states ), true ) ) {
			$state = $country_states[ $state ];
		}

		/** $countryNames = WC()->countries->countries; */

		$address = array();

		$address['AddressType']  = $address_type;
		$address['Organization'] = $organization;

		if ( null !== $name && ! ctype_space( $name ) ) {
			$address['AddressLine1'] = $name . ', ' . $line_1;
		} else {
			$address['AddressLine1'] = $line_1;
		}

		$address['AddressLine2'] = $line_2;
		$address['AddressLine3'] = $line_3;
		$address['TaxIdNumber']  = $tax_id;
		$address['City']         = $city;
		$address['State']        = $state;
		$address['Country']      = $country;
		$address['CountryName']  = '';
		$address['ZipCode']      = $postcode;

		// Add customer phone and email to address line if it exists.
		$address_line_to_append = '' === $address['AddressLine2'] ? 'AddressLine2' : 'AddressLine3';

		if ( array_key_exists( 'phone', $ar ) ) {
			$address[ $address_line_to_append ] .= ' Phone: ' . $ar['phone'];
		}

		if ( array_key_exists( 'email', $ar ) ) {
			$address[ $address_line_to_append ] .= ' Email: ' . $ar['email'];
		}

		return $address;
	}

	/**
	 * Generate an array containing billing and shipping address objects from a WC Order object.
	 *
	 * @param WC_Order $order The WC Order object.
	 * @return array
	 */
	public static function generate_addresses_array_from_order( $order ) {

		$shipping_address['name']     = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
		$shipping_address['company']  = $order->get_shipping_company();
		$shipping_address['line_1']   = $order->get_shipping_address_1();
		$shipping_address['line_2']   = $order->get_shipping_address_2();
		$shipping_address['city']     = $order->get_shipping_city();
		$shipping_address['county']   = $order->get_shipping_state();
		$shipping_address['postcode'] = $order->get_shipping_postcode();
		$shipping_address['country']  = $order->get_shipping_country();

		$billing_address['name']     = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		$billing_address['company']  = $order->get_billing_company();
		$billing_address['line_1']   = $order->get_billing_address_1();
		$billing_address['line_2']   = $order->get_billing_address_2();
		$billing_address['city']     = $order->get_billing_city();
		$billing_address['county']   = $order->get_billing_state();
		$billing_address['postcode'] = $order->get_billing_postcode();
		$billing_address['country']  = $order->get_billing_country();

		if ( ! $order->get_user() ) { // get_user returns false if order customer was guest.
			$billing_address['phone'] = $order->get_billing_phone();
			$billing_address['email'] = $order->get_billing_email();
		}

		$addresses = array();

		$addresses['shipping'] = $shipping_address;
		$addresses['billing']  = $billing_address;

		return $addresses;
	}
}
