<?php
/**
 * Megaventory Order Settings Tab Content.
 *
 * @package megaventory
 * @since 1.3.1
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Megaventory\Admin\Template_Partials;

/**
 * Order settings template class
 */
class Order_Settings {

	/**
	 * Generates Order Settings Tab Content.
	 *
	 * @return void
	 */
	public static function generate_page() {
		$correct_connection = (bool) get_option( 'correct_connection', true );
		$correct_currency   = (bool) get_option( 'correct_currency', false );
		$correct_key        = (bool) get_option( 'correct_megaventory_apikey', false );

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore

		$section_generators = array(
			''                    => \Megaventory\Admin\Template_Partials\Order_Sections\General_Order_Settings::class,
			'shipping-zones'      => \Megaventory\Admin\Template_Partials\Order_Sections\Shipping_Zone_Settings::class,
			'order-cron-settings' => \Megaventory\Admin\Template_Partials\Order_Sections\Order_Cron_Settings::class,
			'payments'            => \Megaventory\Admin\Template_Partials\Order_Sections\Payment_Method_Mapping_Settings::class,
			'extra-settings'      => \Megaventory\Admin\Template_Partials\Order_Sections\Extra_Settings::class,
		);

		$section_labels = array(
			''                    => 'General',
			'shipping-zones'      => 'Shipping Zones',
			'order-cron-settings' => 'Order Cron Settings',
			'payments'            => 'Payment Method Mappings',
			'extra-settings'      => 'Extra Settings',
		);

		// gets last key of array.
		end( $section_labels );
		$last_key = key( $section_labels );

		if ( $correct_connection && $correct_currency && $correct_key ) : ?>
			<ul class="subsubsub">
				<?php foreach ( $section_labels as $section_id => $section_label ) : ?>
					<?php if ( array_key_exists( $section_id, $section_generators ) && class_exists( $section_generators[ $section_id ] ) ) : // section that cannot be generated should not appear. ?> 
					<li>
						<a class="<?php echo ( $section_id === $section ) ? 'current' : ''; ?>" href="?page=megaventory-plugin&tab=orders<?php echo ( ! empty( $section_id ) ) ? '&section=' . esc_attr( $section_id ) : ''; ?>"><?php echo esc_attr( $section_label ); ?></a>
						<?php echo ( $section_id !== $last_key ) ? '|' : ''; ?>
					</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<br class="clear">
			<?php
			if ( ! array_key_exists( $section, $section_generators ) ) {
				wp_safe_redirect( '?page=megaventory-plugin&tab=orders' );
			}
			try {
				$section_generator = $section_generators[ $section ];
				call_user_func( array( $section_generator, 'generate_page' ) ); // call dynamically the section generator class.
			} catch ( \Error $e ) {
				wp_safe_redirect( '?page=megaventory-plugin&tab=orders' );
			}
		endif;
	}
}
