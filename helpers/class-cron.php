<?php
/**
 * Crons.
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
 * Cron operations.
 */
class Cron {

	/**
	 * The activation hook.
	 */
	public static function cron_activation() {
		if ( ! wp_next_scheduled( 'pull_integration_updates_from_megaventory_event' ) ) {
			wp_schedule_event( time(), '1min', 'pull_integration_updates_from_megaventory_event' );
		}

		if ( (bool) get_option( \Megaventory\Models\MV_Constants::MV_ORDER_DELAY_CHOICE_OPT, false ) ) {
			self::order_sync_cron_activation();
		}
	}

	/**
	 * The deactivation hook.
	 */
	public static function cron_deactivation() {

		if ( wp_next_scheduled( 'pull_integration_updates_from_megaventory_event' ) ) {
			wp_clear_scheduled_hook( 'pull_integration_updates_from_megaventory_event' );
		}

		self::order_sync_cron_deactivation();
	}

	/**
	 * The order sync activation hook.
	 */
	public static function order_sync_cron_activation() {

		if ( ! wp_next_scheduled( \Megaventory\Models\MV_Constants::MV_ORDER_SYNC_EVENT ) ) {

			wp_schedule_event( time(), '1min', \Megaventory\Models\MV_Constants::MV_ORDER_SYNC_EVENT );
		}
	}

	/**
	 * The order sync deactivation hook.
	 */
	public static function order_sync_cron_deactivation() {

		if ( wp_next_scheduled( \Megaventory\Models\MV_Constants::MV_ORDER_SYNC_EVENT ) ) {

			wp_clear_scheduled_hook( \Megaventory\Models\MV_Constants::MV_ORDER_SYNC_EVENT );
		}
	}

	/**
	 * Every 1 min check.
	 *
	 * @param array $schedules as tasks.
	 * @return array
	 */
	public static function add_cron_schedules( $schedules ) {

		$schedules['1min'] = array(
			'interval' => 1 * 60, /* 1 * 60, //1min */
			'display'  => __( 'Every Minute', 'textdomain' ),
		);

		$schedules['10min'] = array(
			'interval' => 10 * 60, /* 10 * 60, //10min */
			'display'  => __( 'Every 10 Minutes', 'textdomain' ),
		);

		return $schedules;
	}
}
