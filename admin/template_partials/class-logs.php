<?php
/**
 * Megaventory Logs Tab Content.
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
 * Logs template class
 */
class Logs {

	/**
	 * Generates Logs Tab Content.
	 *
	 * @return void
	 */
	public static function generate_page() {

		$entities_errors = \Megaventory\Models\MVWC_Error::get_messages();

		$successes = \Megaventory\Models\MVWC_Success::get_messages();

		?>
		<div class="mv-row row-main">
			<h3 class="green">Success log</h3>
			<div id="delete-success-logs" class="updateButton CurPointer pushAction"
				onclick="megaventory_delete_success_logs()">
				<span class="mv-action" title="Delete Success logs">Delete Success logs</span>
			</div>
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
						<?php foreach ( $successes as $success ) : ?>
							<tr>
								<td class="success_id"><?php echo esc_attr( $success->id ); ?></td>
								<td>
									<?php echo esc_attr( $success->created_at ); ?>
								</td>
								<td><?php echo esc_attr( $success->type ); ?></td>
								<td>
									<?php echo esc_attr( $success->name ); ?>
								</td>
								<td><?php echo esc_attr( $success->transaction_status ); ?></td>
								<td>
									<?php echo esc_attr( $success->message ); ?>
								</td>
								<td><?php echo esc_attr( $success->code ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<hr />
		<div class="mv-row row-main">
			<h3 class="red">Error log</h3>
			<div id="delete-error-logs" class="updateButton CurPointer pushAction" onclick="megaventory_delete_error_logs()">
				<span class="mv-action" title="Delete Error logs">Delete Error logs</span>
			</div>
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
					<?php foreach ( $entities_errors as $entity_error ) : ?>
						<tr>
							<td class="error_id"><?php echo esc_attr( $entity_error->id ); ?></td>
							<td>
								<?php echo esc_attr( $entity_error->mv_id ); ?>
							</td>
							<td><?php echo esc_attr( $entity_error->wc_id ); ?></td>
							<td>
								<?php echo esc_attr( $entity_error->created_at ); ?>
							</td>
							<td><?php echo esc_attr( $entity_error->type ); ?></td>
							<td>
								<?php echo esc_attr( $entity_error->name ); ?>
							</td>
							<td><?php echo esc_attr( $entity_error->problem ); ?></td>
							<td>
								<?php echo esc_attr( $entity_error->message ); ?>
							</td>
							<td><?php echo esc_attr( $entity_error->code ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
		<?php
	}
}
