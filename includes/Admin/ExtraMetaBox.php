<?php
declare(strict_types=1);

namespace SpaceBooking\Admin;

/**
 * Meta box for the sb_extra CPT: price and inventory quantity.
 */
final class ExtraMetaBox {

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add' ] );
		add_action( 'save_post_sb_extra', [ $this, 'save' ], 10, 2 );
	}

	public function add(): void {
		add_meta_box(
			'sb-extra-settings',
			__( 'Extra Settings', 'space-booking' ),
			[ $this, 'render' ],
			'sb_extra',
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'sb_extra_save', 'sb_extra_nonce' );

		$price     = get_post_meta( $post->ID, '_sb_extra_price',     true );
		$inventory = get_post_meta( $post->ID, '_sb_inventory',        true ) ?: 1;

		// Allowed spaces (optional restriction)
		$allowed   = get_post_meta( $post->ID, '_sb_allowed_spaces', true );
		$spaces    = get_posts( [ 'post_type' => 'sb_space', 'posts_per_page' => -1 ] );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sb_extra_price"><?php esc_html_e( 'Price ($)', 'space-booking' ); ?></label></th>
				<td><input type="number" id="sb_extra_price" name="sb_extra_price" step="0.01" min="0"
					value="<?php echo esc_attr( $price ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="sb_inventory"><?php esc_html_e( 'Inventory Qty', 'space-booking' ); ?></label></th>
				<td>
					<input type="number" id="sb_inventory" name="sb_inventory" min="1"
						value="<?php echo esc_attr( $inventory ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Max bookable units per time slot.', 'space-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Allowed Spaces', 'space-booking' ); ?></label></th>
				<td>
					<?php foreach ( $spaces as $space ) : ?>
						<label style="display:block;margin-bottom:4px">
							<input type="checkbox" name="sb_allowed_spaces[]"
								value="<?php echo esc_attr( $space->ID ); ?>"
								<?php checked( is_array( $allowed ) && in_array( $space->ID, $allowed, false ) ); ?>>
							<?php echo esc_html( $space->post_title ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Leave all unchecked to allow in all spaces.', 'space-booking' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['sb_extra_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['sb_extra_nonce'] ), 'sb_extra_save' )
			|| defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		update_post_meta( $post_id, '_sb_extra_price', (float) ( $_POST['sb_extra_price'] ?? 0 ) );
		update_post_meta( $post_id, '_sb_inventory',   (int)   ( $_POST['sb_inventory']   ?? 1 ) );

		$allowed = array_map( 'absint', (array) ( $_POST['sb_allowed_spaces'] ?? [] ) );
		update_post_meta( $post_id, '_sb_allowed_spaces', $allowed );
	}
}
