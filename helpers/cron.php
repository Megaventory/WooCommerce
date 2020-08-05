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

/**
 * The activation hook.
 */
function cron_activation() {
	if ( ! wp_next_scheduled( 'pull_changes_event' ) ) {
		wp_schedule_event( time(), '1min', 'pull_changes_event' );
	}
}

/**
 * The deactivation hook.
 */
function cron_deactivation() {
	if ( wp_next_scheduled( 'pull_changes_event' ) ) {
		wp_clear_scheduled_hook( 'pull_changes_event' );
	}
}
