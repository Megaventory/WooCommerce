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

	global $connection_value,$currency_value,$key_value,$initialize_value;

	$update_credentials_nonce = wp_create_nonce( 'update-credentials-nonce' );
	?>
		<div class="mv-admin">
		<h1>Megaventory</h1>
		<div class="mv-row row-main">
			<div class="connection">
				<h3>Status</h3>
				<div class="mv-status">
					<ul class="mv-status">
						<li class="mv-li-left"><span>Connection: </span>
						<?php if ( '&check;' === $connection_value ) : ?>
							<span class="checkmark">
								<div class="checkmark_stem"></div>
								<div class="checkmark_kick"></div>
							</span>
						<?php else : ?>
							<span class="checkmark">
								<div class="checkmark_stem_error"></div>
								<div class="checkmark_kick_error"></div>
							</span>
						<?php endif; ?>
						</li>
						<li class="mv-li-left"><span>Key: </span>
						<?php if ( '&check;' === $key_value ) : ?>
							<span class="checkmark">
								<div class="checkmark_stem"></div>
								<div class="checkmark_kick"></div>
							</span>
						<?php else : ?>
							<span class="checkmark">
								<div class="checkmark_stem_error"></div>
								<div class="checkmark_kick_error"></div>
							</span>
						<?php endif; ?>
						</li>
						<li class="mv-li-left"><span>Currency: </span>
						<?php if ( '&check;' === $currency_value ) : ?>
							<span class="checkmark">
								<div class="checkmark_stem"></div>
								<div class="checkmark_kick"></div>
							</span>
						<?php else : ?>
							<span class="checkmark">
								<div class="checkmark_stem_error"></div>
								<div class="checkmark_kick_error"></div>
							</span>
						<?php endif; ?>
						</li>
						<?php if ( '&check;' !== $initialize_value ) : ?>
							<li class="mv-li-left"><span>Initial Sync: </span>
								<span class="checkmark">
									<div class="checkmark_stem_error"></div>
									<div class="checkmark_kick_error"></div>
								</span>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</div>
			<div class="credentials">
				<h3>Setup</h3>
				<div class="mv-row">
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
											<input type="password" class="flLeft width80per" name="api_key" value="<?php echo esc_html( get_api_key() ); ?>" id="api_key"><img class="width30px flLeft MarLe15" src="https://cdn1.iconfinder.com/data/icons/eyes-set/100/eye1-01-128.png" onclick="show_hide_api_key();" />
										</td>
										<script>
											function show_hide_api_key(obj) {
												var obj = document.getElementById("api_key");
												if ( "text" == obj.type) {
													obj.type = "password";
													return;
												}
												obj.type = "text";
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
		<?php if ( '&check;' === $connection_value && '&check;' === $currency_value && '&check;' === $key_value ) : ?>
			<div class="mv-row row-main">
				<div class="actions">
					<h3>Initialization</h3>
					<div class="MarTop10">
						<?php if ( '&check;' === $initialize_value ) : ?>
							<div id="initialize-notice" class="initNoticeDiv">
								<span class="initializeNotice">Initial Sync ran on <?php echo esc_attr( get_option( 'megaventory_initialized_time' ) ); ?><br>
								If you would like to run the Initial Sync again, 
								<span class="CurPointer" onclick="ajaxInitialize(0,0,5,'initialize')" ><a href="#">click here</a></span>
								</span>
							</div>

							<div id="sync-wc-mv" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,5,0,0,'products')" >
								Push Products from WooCommerce to Megaventory
							</div>
							<div id="sync-clients" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,5,0,0,'clients')" >
								Push Clients from WooCommerce to Megaventory
							</div>
							<div id="sync-coupons" class="updateButton CurPointer pushAction" onclick="ajaxImport(0,5,0,0,'coupons')" >
								Push Coupons from WooCommerce to Megaventory
							</div>
						<?php else : ?>
							<div id="initialize" class="updateButton CurPointer" onclick="ajaxInitialize(0,0,5,'initialize')" >
								Initial Sync
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<br>
		<?php
		$inventories = array();

		if ( '&check;' === $connection_value && '&check;' === $currency_value && '&check;' === $key_value ) {

			$inventories = Location::get_megaventory_locations();
		}

		$default_inventory_id = (int) get_option( 'default-megaventory-inventory-location' );
		?>
		<div class="mv-row row-main">
			<div class='inventories'>
			<h2> Choose the Megaventory Inventory Location where the WooCommerce Sales Orders will be pushed to. </h2>
				<table class="wp-list-table widefat fixed striped posts">
					<th>
						<td>
							Abbreviation
						</td>
						<td>
							Full Name
						</td>
						<td>
							Address
						</td>
					</th>
					<?php foreach ( $inventories as $inventory ) : ?>
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
				</table>
			</div>
		</div>
		<div class="mv-row row-main">
			<h2 class="green">Success log</h2>
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

		<div class="mv-row row-main">
			<h2 class="red">Error log</h2>
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

		<div class="mv-row row-main">
			<h2>Taxes</h2>
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

			<h1>Current Sync Count: 0%</h1>

			<div class="InnerloadingBox">
				<span>.</span><span>.</span><span>.</span><br>
			</div>
		</div>
	<?php
}
