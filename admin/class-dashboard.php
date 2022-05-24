<?php
/**
 * Admin panel page.
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

namespace Megaventory\Admin;

/**
 * Megaventory dashboard
 */
class Dashboard {

	/**
	 * Generates admin's page.
	 *
	 * @return void
	 */
	public static function generate_megaventory_admin_dashboard() {
		// check if user is authorized.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get the active tab.
		$default_tab = null;
		$tab         = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $default_tab; // phpcs:ignore.

		?>
		<!-- Use a wrapper -->
		<div class="wrap">
		<!-- Get title from configuration -->
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<!-- Provide the tabs -->
		<nav class="nav-tab-wrapper">
			<a href="?page=megaventory-plugin" class="nav-tab <?php if ( null === $tab ) : ?>
				nav-tab-active<?php endif; ?>">Settings</a>
			<a href="?page=megaventory-plugin&tab=orders" class="nav-tab <?php if ( 'orders' === $tab ) : ?>
				nav-tab-active<?php endif; ?>">Orders</a>	
			<a href="?page=megaventory-plugin&tab=logs" class="nav-tab <?php if ( 'logs' === $tab ) : ?>
				nav-tab-active<?php endif; ?>">Logs</a>
		</nav>

		<div class="tab-content">
		<?php
		switch ( $tab ) :
			case 'logs':
				\Megaventory\Admin\Template_Partials\Logs::generate_page();
				break;
			case 'orders':
				\Megaventory\Admin\Template_Partials\Order_Settings::generate_page();
				break;
			default:
				\Megaventory\Admin\Template_Partials\Settings::generate_page();
				break;
		endswitch;
		?>
		</div>
		</div>
		<div id="loading" class="none">
			<div id="InnerLoading"></div>

			<h1>This may take some time..</h1>

			<div class="InnerloadingBox">
				<span>.</span><span>.</span><span>.</span><br>
			</div>
		</div>

		<div id="loading_operation" class="none">
			<div id="InnerLoading"></div>

			<h1>This may take some time..</h1>

			<div class="InnerloadingBox">
				<span>.</span><span>.</span><span>.</span><br>
			</div>
		</div>
		<?php
	}
}
