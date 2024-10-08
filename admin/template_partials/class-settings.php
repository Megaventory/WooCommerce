<?php
/**
 * Megaventory Settings Tab Content.
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

namespace Megaventory\Admin\Template_Partials;

/**
 * Megaventory general settings template class
 */
class Settings {

	/**
	 * Generates Settings Tab Content.
	 *
	 * @return void
	 */
	public static function generate_page() {

		if ( ! wp_next_scheduled( 'pull_integration_updates_from_megaventory_event' ) ) {
			wp_schedule_event( time(), '1min', 'pull_integration_updates_from_megaventory_event' );
		}

		$taxes_objects = \Megaventory\Models\Tax::wc_all();

		$correct_connection         = (bool) get_option( 'correct_connection', true );
		$correct_currency           = (bool) get_option( 'correct_currency', false );
		$correct_key                = (bool) get_option( 'correct_megaventory_apikey', false );
		$is_megaventory_initialized = (bool) get_option( 'is_megaventory_initialized', false );
		$is_api_key_set             = (bool) get_option( 'api_key_is_set', false );
		$do_mv_requests             = (bool) get_option( 'do_megaventory_requests', true );
		$mv_account_expired         = (bool) get_option( 'mv_account_expired', false );
		$mv_account_admin           = (bool) get_option( 'mv_account_admin', false );
		$woo_integration_enabled    = (bool) get_option( 'mv_woo_integration_enabled', false );
		$has_new_mv_api_key         = (bool) get_option( 'new_mv_api_key', false );

		$are_megaventory_products_synchronized = get_option( 'are_megaventory_products_synchronized', null );
		if ( null !== get_option( 'are_megaventory_products_synchronized', null ) ) {

			$are_megaventory_products_synchronized = (bool) get_option( 'are_megaventory_products_synchronized', null );
		}

		$are_megaventory_clients_synchronized = get_option( 'are_megaventory_clients_synchronized', null );
		if ( null !== get_option( 'are_megaventory_clients_synchronized', null ) ) {

			$are_megaventory_clients_synchronized = (bool) get_option( 'are_megaventory_clients_synchronized', null );
		}

		$are_megaventory_coupons_synchronized = get_option( 'are_megaventory_coupons_synchronized', null );
		if ( null !== get_option( 'are_megaventory_coupons_synchronized', null ) ) {

			$are_megaventory_coupons_synchronized = (bool) get_option( 'are_megaventory_coupons_synchronized', null );
		}

		$is_megaventory_stock_adjusted = (bool) get_option( 'is_megaventory_stock_adjusted', null );
		if ( null !== get_option( 'is_megaventory_stock_adjusted', null ) ) {

			$is_megaventory_stock_adjusted = (bool) get_option( 'is_megaventory_stock_adjusted', null );
		}

		$is_megaventory_synchronized = false;

		$is_old_version = false;

		// for old versions.
		if ( $is_megaventory_initialized && ( null === $are_megaventory_products_synchronized || null === $are_megaventory_clients_synchronized || null === $are_megaventory_coupons_synchronized ) ) {

			$is_old_version = true;
		}

		if ( $are_megaventory_products_synchronized && $are_megaventory_clients_synchronized && $are_megaventory_coupons_synchronized ) {

			$is_megaventory_synchronized = true;
		}

		$update_credentials_nonce = wp_create_nonce( 'update-credentials-nonce' );
		?>
			<?php if ( ! $is_megaventory_initialized && ! $correct_currency && ! $correct_key ) : ?>
				<div class="notice notice-error"><p>Megaventory automatic synchronization failed</p></div>
				<div class="notice notice-error"><p>Check that the base Megaventory Currency and API Key are correct and your store is initialized</p></div>
			<?php elseif ( ! $correct_connection ) : ?>
			<div class="notice notice-error"><p>There seems to be no connection to Megaventory.</p></div>
			<div class="notice notice-error"><p>Check if the Megaventory service is online at: <a href="https://status.megaventory.com" target="_blank">https://status.megaventory.com</a></p></div>
			<?php elseif ( ! $is_api_key_set ) : ?>
			<div class="notice notice-error"><p>Welcome to Megaventory extension!</p></div>
			<div class="notice notice-error"><p>Please apply your API key to get started. You can find it in your Megaventory account under 'My Profile' where your user icon is.</p></div>
			<?php elseif ( ! $correct_key ) : ?>
			<div class="notice notice-error"><p>Megaventory Error! Invalid API key!</p></div>
				<?php if ( $mv_account_expired ) : ?>
				<div class="notice notice-error"><p>Your Account has expired. If you wish to continue using megaventory, please login at www.megaventory.com and extend your account.</p></div>
				<?php endif; ?>
			<?php elseif ( ! $do_mv_requests ) : ?>
			<div class="notice notice-warning"><p>Unable to verify Megaventory API key</p></div>
			<div class="notice notice-warning"><p>Please check your API key and try again. All Megaventory synchronization tasks have been disabled due to excessive failed requests. Please ensure your Megaventory account is active and enter a valid API key or disable the extension if you are not planning on using the integration.</p></div>
			<?php elseif ( ! $mv_account_admin ) : ?>
			<div class="notice notice-error"><p>Megaventory error! WooCommerce integration needs administrator's credentials!</p></div>
			<div class="notice notice-error"><p>Please contact your Megaventory account administrator and use an API key that corresponds to your Megaventory administrator.</p></div>
			<?php elseif ( ! $woo_integration_enabled ) : ?>
			<div class="notice notice-error"><p>Megaventory error! WooCommerce integration is not enabled in your Megaventory account.</p></div>
			<div class="notice notice-error"><p>Please contact your Megaventory account administrator.</p></div>
			<?php elseif ( ! $correct_currency ) : ?>
			<div class="notice notice-error"><p>Megaventory error! Currencies in WooCommerce and Megaventory do not match! Megaventory extension will halt until this issue is resolved!</p></div>
			<div class="notice notice-error"><p>If you are sure that the currency is correct or if you have just updated the base currency in Megaventory, please refresh until this warning disappears.</p></div>
			<?php endif; ?>
			<?php if ( ! $is_megaventory_initialized ) : ?>
				<?php if ( $has_new_mv_api_key ) : ?>
					<div class="notice notice-warning"><p>You added an API key for a new account. You need to run the Initial Sync again</p></div>
				<?php else : ?>
					<div class="notice notice-warning"><p>You need to run the Initial Sync before any data synchronization takes place!</p></div>
				<?php endif; ?>
			<?php endif; ?>
			<div class="mv-admin">
			<div class="mv-row row-main">
				<div class="connection">
					<h3>Status</h3>
					<div class="mv-status">
						<ul class="mv-status">
							<li class="mv-li-left">
							<?php if ( $correct_connection ) : ?>
								<span class="fa fa-check">
								</span>
								<span class="success-desc">Connection</span>
							<?php else : ?>
								<span class="fa fa-times">
								</span>
								<span class="error-desc">Connection</span>
							<?php endif; ?>
							</li>
							<li class="mv-li-left">
							<?php if ( $correct_key ) : ?>
								<span class="fa fa-check">
								</span>
								<span class="success-desc">API Key</span>
							<?php else : ?>
								<span class="fa fa-times">
								</span>
								<span class="error-desc">API Key</span>
							<?php endif; ?>
							</li>
						</ul>
					</div>
				</div>
			</div>
			<hr/>
			<div class="mv-row row-main">
				<div class="credentials">
					<h3>Setup</h3>
					<div class="mv-row">
						<?php if ( ! $is_megaventory_initialized ) : ?>
							<div class="warning mv-warn">Megaventory is not initialized</div>
						<?php endif; ?>	
						<div class="mv-form">
							<form id="options" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="megaventory">
								<div class="mv-form-body">
									<table class="credentialsTable">
										<tr>
											<td>
												<label class="MarLe30 width25per" for="api_key">Megaventory API key: </label>
											</td>
											<td>
												<input type="text" autocomplete="new-password" class="flLeft width80per fontFamilyPassword" name="api_key" value="<?php echo esc_html( \Megaventory\API::get_api_key() ); ?>" id="api_key"><img class="width30px flLeft MarLe15" src="https://cdn1.iconfinder.com/data/icons/eyes-set/100/eye1-01-128.png" onclick="show_hide_api_key();" />
											</td>
											<script>
												function show_hide_api_key(obj) {
													var obj = document.getElementById("api_key");
													if ( obj.classList.contains("fontFamilyPassword")) {
														obj.classList.remove("fontFamilyPassword");
														return;
													}
													obj.classList.add("fontFamilyPassword");
													return;
												}
											</script>
										</tr>
										<tr>
											<td>
												<label class="width25per" for="api_host">Megaventory API host: </label>
											</td>
											<td>
												<input class="flLeft width80per" type="text" id="api_host" name="api_host" value="<?php echo esc_url( \Megaventory\API::get_api_host() ); ?>"/>
											</td>
										</tr>
										<tr>
											<td>
											</td>
											<td>
												<input type="hidden" name="update-credentials-nonce" value="<?php echo esc_attr( $update_credentials_nonce ); ?>"/>
												<div class="mv-form-bottom">
													<input class="updateButton CurPointer" type="submit" value="Update"/>
												</div>
											</td>
										</tr>
									</table>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>	
			<?php if ( $correct_connection && $correct_currency && $correct_key ) : ?>
				<hr/>
				<div class="mv-row row-main">
					<div class="actions">
						<h3>Synchronization</h3>
						<div class="MarTop10">
							<?php if ( $is_megaventory_initialized ) : ?>

								<div class="initNoticeDiv">
									<span>Initial Synchronization ran on <?php echo esc_attr( get_option( 'megaventory_initialized_time' ) ); ?>, 
									<span class="CurPointer" onclick="megaventory_reinitialize(0,-1,1,3,'initialize')" ><a href="#">Run again</a></span>
									</span>
								</div>

								<?php if ( $is_old_version ) : ?>

									<div id="sync-wc-mv" class="updateButton CurPointer pushAction" onclick="megaventory_import(0,3,-1,1,0,0,'products')" >
										<span class="mv-action" title="Synchronize products from your WooCommerce to your Megaventory account">Push Products from WooCommerce to Megaventory </span>
									</div>

									<div id="sync-clients" class="updateButton CurPointer pushAction" onclick="megaventory_import(0,3,-1,1,0,0,'clients')" >
										<span class="mv-action" title="Synchronize clients from your WooCommerce to your Megaventory account">Push Clients from WooCommerce to Megaventory</span>
									</div>

									<div id="sync-coupons" class="updateButton CurPointer pushAction" onclick="megaventory_import(0,3,-1,1,0,0,'coupons')" >
										<span class="mv-action" title="Synchronize coupons from your WooCommerce to your Megaventory account">Push Coupons from WooCommerce to Megaventory</span>
									</div>

									<div id="pull-updates" class="updateButton CurPointer pushAction MarTop10" onclick="megaventory_pull_integration_updates()" >
										<span class="mv-action" title="Apply Pending Updates">Pull Updates from Megaventory</span>
									</div>

								<?php else : ?>

									<?php if ( $are_megaventory_products_synchronized ) : ?>

										<div class="initNoticeDiv">
											<span>Products Synchronization: <?php echo esc_attr( get_option( 'megaventory_products_synchronized_time' ) ); ?>, 
											<span class="CurPointer" onclick="megaventory_import(0,3,-1,1,0,0,'products')" ><a href="#">Run again</a></span>
											</span>
										</div>
									<?php endif; ?>

									<?php if ( $are_megaventory_clients_synchronized ) : ?>

										<div class="initNoticeDiv">
											<span>Clients Synchronization: <?php echo esc_attr( get_option( 'megaventory_clients_synchronized_time' ) ); ?>, 
											<span class="CurPointer" onclick="megaventory_import(0,3,-1,1,0,0,'clients')" ><a href="#">Run again</a></span>
											</span>
										</div>

									<?php endif; ?>

									<?php if ( $are_megaventory_coupons_synchronized ) : ?>

										<div class="initNoticeDiv">
											<span>Coupons Synchronization: <?php echo esc_attr( get_option( 'megaventory_coupons_synchronized_time' ) ); ?>, 
											<span class="CurPointer" onclick="megaventory_import(0,3,-1,1,0,0,'coupons')" ><a href="#">Run again</a></span>
											</span>
										</div>

									<?php endif; ?>

									<?php if ( $is_megaventory_stock_adjusted ) : ?>

										<div class="initNoticeDiv">
											<span>Product Quantity Synchronization: <?php echo esc_attr( get_option( 'megaventory_stock_synchronized_time' ) ); ?><br>
											Products Quantity synchronization to Megaventory, 
											<span class="CurPointer" onclick="megaventory_sync_stock_to_mv(0)" ><a href="#">Run again</a></span>
											<select id="mv_adjustment_document_status" name="mv_adjustment_document_status" class="select-control" onchange="adjustment_status_changed()" title="Issue adjustments with the following status">
												<option value="Pending">Pending</option>
												<option value="Verified" selected>Approved</option>
											</select>
											<script>
												function adjustment_status_changed() {
													let preferred_status = jQuery( '#mv_adjustment_document_status' ).val();

													if ( "Pending" == preferred_status ) {
														jQuery( '#lbl_mv_adjustment_document_location' ).hide();
														jQuery( '#mv_adjustment_document_location' ).hide();
													} else {
														jQuery( '#lbl_mv_adjustment_document_location' ).show();
														jQuery( '#mv_adjustment_document_location' ).show();
													}
												}
											</script>
											<select id="mv_adjustment_document_location" name="mv_adjustment_document_location" class="select-control" title="Issue adjustments in the following location">
												<?php $inventories = \Megaventory\Models\Location::get_megaventory_locations(); ?>
												<?php foreach ( $inventories as $inventory ) : ?>

													<option value="<?php echo esc_attr( $inventory['InventoryLocationID'] ); ?>"><?php echo esc_attr( $inventory['InventoryLocationName'] ); ?> </option>

												<?php endforeach; ?>
											</select>
											<br>
											or
											<br>
											Products Quantity synchronization from Megaventory, 
											<span class="CurPointer" onclick="megaventory_sync_stock_from_mv(0)" ><a href="#">Run again</a></span>
											</span>
										</div>

									<?php endif; ?>

									<?php if ( false === $are_megaventory_products_synchronized ) : ?>

										<div id="sync-wc-mv" class="updateButton CurPointer pushAction" onclick="megaventory_import(0,3,-1,1,0,0,'products')" >
											<span class="mv-action" title="Synchronize products from your WooCommerce to your Megaventory account">Push Products from WooCommerce to Megaventory </span>
										</div>

									<?php elseif ( false === $are_megaventory_clients_synchronized ) : ?>

										<div id="sync-clients" class="updateButton CurPointer pushAction" onclick="megaventory_import(0,3,-1,1,0,0,'clients')" >
											<span class="mv-action" title="Synchronize clients from your WooCommerce to your Megaventory account">Push Clients from WooCommerce to Megaventory</span>
										</div>

										<div id="skip-clients" class="pushAction MarTop10" onclick="megaventory_skip_clients_synchronization()" >
												<span class="mv-action" title="Synchronize clients later"><a href="#">Synchronize Clients later</a></span>
										</div>

									<?php elseif ( false === $are_megaventory_coupons_synchronized ) : ?>

										<div id="sync-coupons" class="updateButton CurPointer pushAction" onclick="megaventory_import(0,3,-1,1,0,0,'coupons')" >
											<span class="mv-action" title="Synchronize coupons from your WooCommerce to your Megaventory account">Push Coupons from WooCommerce to Megaventory</span>
										</div>

										<div id="skip-coupons" class="pushAction MarTop10" onclick="megaventory_skip_coupons_synchronization()" >
												<span class="mv-action" title="Synchronize Coupons later"><a href="#">Synchronize Coupons later</a></span>
										</div>

									<?php endif; ?>

									<?php if ( $is_megaventory_synchronized && false === $is_megaventory_stock_adjusted ) : ?>
										<div class="initNoticeDiv stockNotice">
											<span class="displayBlock">There are two ways to synchronize product quantity:</span>
											<span class="displayBlock Mar10">- To push your WooCommerce product quantity to your Megaventory account choose <strong>Push Product Quantity to Megaventory</strong>.
											This action will create <strong>Adjustment Documents</strong> in your Megaventory account. Approving these documents will update the Megaventory Product Quantity.<br/><strong>We strongly suggest to go to your products and fill in the purchase price, before proceeding with this action, so that you get an accurate overview of the inventory value in Megaventory.</strong><br/>
												<label for="mv_adjustment_document_status">Issue adjustments with the following status</label>
												<select id="mv_adjustment_document_status" name="mv_adjustment_document_status" class="select-control" onchange="adjustment_status_changed()">
													<option value="Pending">Pending</option>
													<option value="Verified" selected>Approved</option>
												</select><br/>
												<script>
													function adjustment_status_changed() {
														let preferred_status = jQuery( '#mv_adjustment_document_status' ).val();

														if ( "Pending" == preferred_status ) {
															jQuery( '#lbl_mv_adjustment_document_location' ).hide();
															jQuery( '#mv_adjustment_document_location' ).hide();
														} else {
															jQuery( '#lbl_mv_adjustment_document_location' ).show();
															jQuery( '#mv_adjustment_document_location' ).show();
														}
													}
												</script>
												<label id="lbl_mv_adjustment_document_location" for="mv_adjustment_document_location">Issue adjustments in the following location</label>
												<select id="mv_adjustment_document_location" name="mv_adjustment_document_location" class="select-control" title="">
													<?php $inventories = \Megaventory\Models\Location::get_megaventory_locations(); ?>
													<?php foreach ( $inventories as $inventory ) : ?>

														<option value="<?php echo esc_attr( $inventory['InventoryLocationID'] ); ?>"><?php echo esc_attr( $inventory['InventoryLocationName'] ); ?> </option>

													<?php endforeach; ?>
												</select>
											</span>
											<div id="create-adj" class="updateButton CurPointer pushAction" onclick="megaventory_sync_stock_to_mv(0)" >
												<span class="mv-action" title="Create Pending Adjustments on your Megaventory account based on your WooCommerce quantity">Push Product Quantity to Megaventory</span>
											</div>
											<span class="displayBlock Mar10">- If you have set already your Product Quantity on your Megaventory account choose <strong>Pull Product Quantity from Megaventory</strong>.
											This action will overwrite your product quantity in your WooCommerce with the sum of all the <strong>on-hand quantity</strong> added from every Inventory Location in your Megaventory account.
											</span>
											<div id="pull-stock" class="updateButton CurPointer pushAction MarTop10" onclick="megaventory_sync_stock_from_mv(0)" >
												<span class="mv-action" title="Synchronize products quantity from your Megaventory account">Pull Product Quantity from Megaventory</span>
											</div>
											<div id="skip-stock" class="pushAction MarTop10" onclick="megaventory_skip_stock_synchronization()" >
												<span class="mv-action" title="Synchronize quantity later"><a href="#">Synchronize Quantity later</a></span>
											</div>
										</div>

									<?php endif; ?>

									<?php if ( $is_megaventory_stock_adjusted ) : ?>

										<div class="pullUpdates">
											<div id="pull-updates" class="updateButton CurPointer pushAction MarTop10" onclick="megaventory_pull_integration_updates()" >
												<span class="mv-action" title="Apply Pending Updates">Pull Updates from Megaventory</span>
											</div>
											<div class="displayInline Padd10" onmouseover="ShowHint()" onmouseout="HideHint()">
												<div>
													<i class="fa fa-lightbulb-o CurPointer fontSize18"></i>
												</div>
												<div id="pull-updates-hint" class="pullUpdatesHint displayNone">
													<span>Stock levels and order statuses are typically updated from Megaventory to 
														Woocommerce automatically every 1'. 
														You can trigger these updates manually too 
														if your server does not support this automation.</span>
												</div>
											</div>
											<script>
												function ShowHint() {
													document.getElementById('pull-updates-hint').classList.remove("displayNone");
												}
												function HideHint() {
													document.getElementById('pull-updates-hint').classList.add("displayNone");
												}
											</script>
										</div>

									<?php endif; ?>

								<?php endif; ?>

							<?php else : ?>
								<div id="initialize" class="updateButton CurPointer pushAction" onclick="megaventory_initialize(0,-1,1,3,'initialize')" >
									<span class="mv-action" title="Initialize Megaventory plugin">Initial Synchronization</span>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( $is_megaventory_initialized && $is_megaventory_synchronized && $correct_connection && $correct_currency && $correct_key ) : ?>
				<hr/>
				<div class="mv-row row-main">
					<div class="wp-cron">
						<div class="actions">
							<h3>Alternate WordPress Cron</h3>
							<div class="MarTop10">
								<table class="form-table">
									<tbody>
										<tr>
											<th scope="row"><label for="enable_alternate_wp_cron">Enable Alternate WordPress Cron</label></th>
											<td><input id="enable_alternate_wp_cron" type="checkbox" name="alternate_wp_cron" onclick="megaventory_change_alternate_cron_status()" <?php echo ( (bool) get_option( 'megaventory_alternate_wp_cron', false ) ) ? 'checked' : ''; ?>/><span class='description'>Enable this option only if the regular cron is unable to run properly</span></td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				<hr/>
			<?php endif; ?>
			<div class="mv-row row-main">
				<h3>Taxes</h3>
				<div class="tax-wrap">
					<table id="taxes" class="wp-list-table widefat fixed striped posts">
					<thead>
						<tr>
							<th>Tax ID</th>
							<th>Megaventory ID</th>
							<th>Name</th>
							<th>Rate</th>
						</tr>
					</thead>
					<?php foreach ( $taxes_objects as $tax_object ) : ?>
						<tr>
							<td><?php echo esc_attr( $tax_object->wc_id ); ?></td>
							<td><?php echo esc_attr( $tax_object->mv_id ); ?></td>
							<td><?php echo esc_attr( $tax_object->name ); ?></td>
							<td><?php echo esc_attr( $tax_object->rate ); ?></td>
						</tr>
					<?php endforeach; ?>
					</table>
				</div>
			</div>
			</div>
		<?php
	}
}
