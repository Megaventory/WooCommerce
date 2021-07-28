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

/**
 * Generates admin's page.
 *
 * @return void
 */
function generate_admin_page() {
	global $wpdb;

	if ( ! wp_next_scheduled( 'pull_changes_event' ) ) {
		wp_schedule_event( time(), '1min', 'pull_changes_event' );
	}

	$entities_errors = $wpdb->get_results(
		"
		SELECT * 
		FROM {$wpdb->prefix}megaventory_errors_log 
		ORDER BY created_at 
		DESC LIMIT 50;
		"
	); // db call ok. no-cache ok.

	$successes = $wpdb->get_results(
		"
		SELECT * 
		FROM {$wpdb->prefix}megaventory_success_log 
		ORDER BY created_at 
		DESC LIMIT 50;
		"
	); // db call ok. no-cache ok.

	$taxes_objects = Tax::wc_all();

	$correct_connection         = (bool) get_option( 'correct_connection', true );
	$correct_currency           = (bool) get_option( 'correct_currency', false );
	$correct_key                = (bool) get_option( 'correct_megaventory_apikey', false );
	$is_megaventory_initialized = (bool) get_option( 'is_megaventory_initialized', false );
	$is_api_key_set             = (bool) get_transient( 'api_key_is_set', false );
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
		<h1>Megaventory</h1>
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
											<input type="text" autocomplete="new-password" class="flLeft width80per fontFamilyPassword" name="api_key" value="<?php echo esc_html( get_api_key() ); ?>" id="api_key"><img class="width30px flLeft MarLe15" src="https://cdn1.iconfinder.com/data/icons/eyes-set/100/eye1-01-128.png" onclick="show_hide_api_key();" />
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
											<input class="flLeft width80per" type="text" id="api_host" name="api_host" value="<?php echo esc_url( get_api_host() ); ?>"/>
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
			<?php if ( $is_megaventory_synchronized && $is_megaventory_stock_adjusted ) : ?>
			<div class="mv-row row-main">
				<div class="actions">
					<h3>Issue Adjustment documents with the following status</h3>
					<div class="MarTop10">
						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row"><label for="enable_alternate_wp_cron">Adjustment document status</label></th>
									<?php $status_option = get_option( 'megaventory_adjustment_document_status_option', 'Pending' ); ?>
									<td>
										<select id="mv_adjustment_document_status" name="mv_adjustment_document_status" class="select-control">
											<option value="Pending" <?php echo ( 'Pending' === $status_option ) ? 'selected' : ''; ?>>Pending</option>
											<option value="Verified" <?php echo ( 'Verified' === $status_option ) ? 'selected' : ''; ?>>Approved</option>
										</select>
										<input onclick="changeDocumentStatusOption()" class="mvDocStatusUpdate updateButton CurPointer" type="submit" value="Save"/>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<hr />
			<?php endif; ?>
			<div class="mv-row row-main">
				<div class="actions">
					<h3>Synchronization</h3>
					<div class="MarTop10">
						<?php if ( $is_megaventory_initialized ) : ?>

							<div class="initNoticeDiv">
								<span>Initial Synchronization ran on <?php echo esc_attr( get_option( 'megaventory_initialized_time' ) ); ?>, 
								<span class="CurPointer" onclick="ajaxReInitialize(0,-1,1,3,'initialize')" ><a href="#">Run again</a></span>
								</span>
							</div>

							<?php if ( $is_old_version ) : ?>

								<div id="sync-wc-mv" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,3,-1,1,0,0,'products')" >
									<span class="mv-action" title="Synchronize products from your WooCommerce to your Megaventory account">Push Products from WooCommerce to Megaventory </span>
								</div>

								<div id="sync-clients" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,3,-1,1,0,0,'clients')" >
									<span class="mv-action" title="Synchronize clients from your WooCommerce to your Megaventory account">Push Clients from WooCommerce to Megaventory</span>
								</div>

								<div id="sync-coupons" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,3,-1,1,0,0,'coupons')" >
									<span class="mv-action" title="Synchronize coupons from your WooCommerce to your Megaventory account">Push Coupons from WooCommerce to Megaventory</span>
								</div>

								<div id="pull-updates" class="updateButton CurPointer pushAction MarTop10" onclick="ajaxPullUpdates()" >
									<span class="mv-action" title="Apply Pending Updates">Pull Updates from Megaventory</span>
								</div>

							<?php else : ?>

								<?php if ( $are_megaventory_products_synchronized ) : ?>

									<div class="initNoticeDiv">
										<span>Products Synchronization: <?php echo esc_attr( get_option( 'megaventory_products_synchronized_time' ) ); ?>, 
										<span class="CurPointer" onclick="ajaxImport(0,3,-1,1,0,0,'products')" ><a href="#">Run again</a></span>
										</span>
									</div>
								<?php endif; ?>

								<?php if ( $are_megaventory_clients_synchronized ) : ?>

									<div class="initNoticeDiv">
										<span>Clients Synchronization: <?php echo esc_attr( get_option( 'megaventory_clients_synchronized_time' ) ); ?>, 
										<span class="CurPointer" onclick="ajaxImport(0,3,-1,1,0,0,'clients')" ><a href="#">Run again</a></span>
										</span>
									</div>

								<?php endif; ?>

								<?php if ( $are_megaventory_coupons_synchronized ) : ?>

									<div class="initNoticeDiv">
										<span>Coupons Synchronization: <?php echo esc_attr( get_option( 'megaventory_coupons_synchronized_time' ) ); ?>, 
										<span class="CurPointer" onclick="ajaxImport(0,3,-1,1,0,0,'coupons')" ><a href="#">Run again</a></span>
										</span>
									</div>

								<?php endif; ?>

								<?php if ( $is_megaventory_stock_adjusted ) : ?>

									<div class="initNoticeDiv">
										<span>Product Quantity Synchronization: <?php echo esc_attr( get_option( 'megaventory_stock_synchronized_time' ) ); ?><br>
										Products Quantity synchronization to Megaventory, 
										<span class="CurPointer" onclick="SyncStockToMegaventory(0)" ><a href="#">Run again</a></span><br>
										or<br>
										Products Quantity synchronization from Megaventory, 
										<span class="CurPointer" onclick="SyncStockFromMegaventory(0)" ><a href="#">Run again</a></span>
										</span>
									</div>

								<?php endif; ?>

								<?php if ( false === $are_megaventory_products_synchronized ) : ?>

									<div id="sync-wc-mv" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,3,-1,1,0,0,'products')" >
										<span class="mv-action" title="Synchronize products from your WooCommerce to your Megaventory account">Push Products from WooCommerce to Megaventory </span>
									</div>

								<?php elseif ( false === $are_megaventory_clients_synchronized ) : ?>

									<div id="sync-clients" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,3,-1,1,0,0,'clients')" >
										<span class="mv-action" title="Synchronize clients from your WooCommerce to your Megaventory account">Push Clients from WooCommerce to Megaventory</span>
									</div>

								<?php elseif ( false === $are_megaventory_coupons_synchronized ) : ?>

									<div id="sync-coupons" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,3,-1,1,0,0,'coupons')" >
										<span class="mv-action" title="Synchronize coupons from your WooCommerce to your Megaventory account">Push Coupons from WooCommerce to Megaventory</span>
									</div>

								<?php endif; ?>

								<?php if ( $is_megaventory_synchronized && false === $is_megaventory_stock_adjusted ) : ?>
									<div class="initNoticeDiv stockNotice">
										<span class="displayBlock">There are two ways to synchronize product quantity:</span>
										<span class="displayBlock Mar10">- To push your WooCommerce product quantity to your Megaventory account choose <strong>Push Product Quantity to Megaventory</strong>.
										This action will create <strong>Adjustment Documents</strong> in your Megaventory account. Approving these documents will update the Megaventory Product Quantity.<br/><strong>We strongly suggest to go to your products and fill in the purchase price, before proceeding with this action, so that you get an accurate overview of the inventory value in Megaventory.</strong><br/>
											<label for="mv_adjustment_document_status">Issue adjustments with the following status</label>
											<select id="mv_adjustment_document_status" name="mv_adjustment_document_status" class="select-control">
												<option value="Pending">Pending</option>
												<option value="Verified">Approved</option>
											</select>
										</span>
										<div id="create-adj" class="updateButton CurPointer pushAction" onclick="SyncStockToMegaventory(0)" >
											<span class="mv-action" title="Create Pending Adjustments on your Megaventory account based on your WooCommerce quantity">Push Product Quantity to Megaventory</span>
										</div>
										<span class="displayBlock Mar10">- If you have set already your Product Quantity on your Megaventory account choose <strong>Pull Product Quantity from Megaventory</strong>.
										This action will overwrite your product quantity in your WooCommerce with the sum of all the <strong>on-hand quantity</strong> added from every Inventory Location in your Megaventory account.
										</span>
										<div id="pull-stock" class="updateButton CurPointer pushAction MarTop10" onclick="SyncStockFromMegaventory(0)" >
											<span class="mv-action" title="Synchronize products quantity from your Megaventory account">Pull Product Quantity from Megaventory</span>
										</div>
										<div id="skip-stock" class="pushAction MarTop10" onclick="SkipStockSynchronization()" >
											<span class="mv-action" title="Synchronize quantity later"><a href="#">Synchronize Quantity later</a></span>
										</div>
									</div>

								<?php endif; ?>

								<?php if ( $is_megaventory_stock_adjusted ) : ?>

									<div class="pullUpdates">
										<div id="pull-updates" class="updateButton CurPointer pushAction MarTop10" onclick="ajaxPullUpdates()" >
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
							<div id="initialize" class="updateButton CurPointer pushAction" onclick="ajaxInitialize(0,-1,1,3,'initialize')" >
								<span class="mv-action" title="Initialize Megaventory plugin">Initial Synchronization</span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $is_megaventory_initialized && $correct_connection && $correct_currency && $correct_key ) : ?>
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
										<td><input id="enable_alternate_wp_cron" type="checkbox" name="alternate_wp_cron" onclick="changeWpCronStatus()" <?php echo ( (bool) get_option( 'megaventory_alternate_wp_cron', false ) ) ? 'checked' : ''; ?>/><span class='description'>Enable this option only if the regular cron is unable to run properly</span></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>	
		<?php endif; ?>		
		<?php
		$inventories = array();

		if ( $correct_connection && $correct_currency && $correct_key ) {

			$inventories = Location::get_megaventory_locations();
		}

		$default_inventory_id = (int) get_option( 'default-megaventory-inventory-location' );
		?>
		<hr/>
		<div class="mv-row row-main">
			<div class='inventories'>
			<h3>Choose the Megaventory Inventory Location where the WooCommerce Sales Orders will be pushed to.</h3>
				<table class="wp-list-table widefat fixed striped posts" id="locations">
					<thead>
						<tr>
							<th></th>
							<th>Abbreviation</th>
							<th>Full Name</th>
							<th>Address</th>
						</tr>
					</thead>
					<tbody>	
					<?php foreach ( $inventories as $inventory ) : ?>
						<?php
						$mv_location_id_to_abbr = get_option( 'mv_location_id_to_abbr' );

						if ( ! isset( $mv_location_id_to_abbr ) ) {
							$mv_location_id_to_abbr = array();
						}

						$mv_location_id_to_abbr[ $inventory['InventoryLocationID'] ] = $inventory['InventoryLocationAbbreviation'];

						update_option( 'mv_location_id_to_abbr', $mv_location_id_to_abbr );
						?>
						<tr>
							<td>
								<input name="mvLocation" id=<?php echo esc_attr( $inventory['InventoryLocationID'] ) . ' '; ?>type="radio"
								<?php
								// if there is no default inventory location set, set the first one by default, if exist.
								if ( 0 === $default_inventory_id ) {

									update_option( 'default-megaventory-inventory-location', $inventory['InventoryLocationID'] );
									$default_inventory_id = $inventory['InventoryLocationID'];
								}

								if ( $inventory['InventoryLocationID'] === $default_inventory_id ) {

									echo esc_attr( 'checked' );
								}
								?>
								onclick="changeDefaultInventory(this.id)" >
							</td>
							<td>
								<span><?php echo esc_attr( $inventory['InventoryLocationAbbreviation'] ); ?></span>
							</td>
							<td>
								<span><?php echo esc_attr( $inventory['InventoryLocationName'] ); ?></span>
							</td>
							<td>
								<span><?php echo esc_attr( $inventory['InventoryLocationAddress'] ); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<hr/>
		<div class="mv-row row-main">
			<h3 class="green">Success log</h3>
			<div class="userNotificationTable-wrap">
			<table id="success-log" class="wp-list-table widefat fixed striped posts">
			<thead>
				<tr>
					<th class="mediumColumn">Import ID</th>
					<th>Created at</th>
					<th>Entity type</th>
					<th>Entity name</th>
					<th class="smallColumn">Status</th>
					<th>Full message</th>
					<th class="smallColumn">Code</th>
				</tr>
			</thead>
			<tbody>
		<?php	foreach ( $successes as $success ) : ?>
				<tr>
					<td><?php echo esc_attr( $success->id ); ?></td>
					<td><?php echo esc_attr( $success->created_at ); ?></td>
					<td><?php echo esc_attr( $success->type ); ?></td>
					<td><?php echo esc_attr( $success->name ); ?></td>
					<td><?php echo esc_attr( $success->transaction_status ); ?></td>
					<td><?php echo esc_attr( $success->message ); ?></td>
					<td><?php echo esc_attr( $success->code ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
			</table>
			</div>
		</div>
		<hr/>
		<div class="mv-row row-main">
			<h3 class="red">Error log</h3>
			<div class="userNotificationTable-wrap">
				<table id="error-log" class="wp-list-table widefat fixed striped posts">
				<thead>
					<tr>
						<th class="smallColumn">Error ID</th>
						<th class="mediumColumn">Megaventory ID</th>
						<th class="mediumColumn">WooCommerce ID</th>
						<th class="mediumColumn">Created at</th>
						<th>Error type</th>
						<th>Entity name</th>
						<th>Problem</th>
						<th>Full message</th>
						<th class="smallColumn">Code</th>
					</tr>
				</thead>
				<?php	foreach ( $entities_errors as $entity_error ) : ?>
					<tr>
						<td><?php echo esc_attr( $entity_error->id ); ?></td>
						<td><?php echo esc_attr( $entity_error->mv_id ); ?></td>
						<td><?php echo esc_attr( $entity_error->wc_id ); ?></td>
						<td><?php echo esc_attr( $entity_error->created_at ); ?></td>
						<td><?php echo esc_attr( $entity_error->type ); ?></td>
						<td><?php echo esc_attr( $entity_error->name ); ?></td>
						<td><?php echo esc_attr( $entity_error->problem ); ?></td>
						<td><?php echo esc_attr( $entity_error->message ); ?></td>
						<td><?php echo esc_attr( $entity_error->code ); ?></td>
					</tr>
				<?php endforeach; ?>
				</table>
			</div>
		</div>
		<hr/>
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
