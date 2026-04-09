<?php
declare(strict_types=1);

namespace SpaceBooking\Admin;

/**
 * Meta box for the sb_space CPT: hourly rate, hours, day overrides, capacity.
 */
final class SpaceMetaBox {

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add' ] );
		add_action( 'save_post_sb_space', [ $this, 'save' ], 10, 2 );
	}

	public function add(): void {
		add_meta_box(
			'sb-space-settings',
			__( 'Space Settings', 'space-booking' ),
			[ $this, 'render' ],
			'sb_space',
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'sb_space_save', 'sb_space_nonce' );

		$rate        = get_post_meta( $post->ID, '_sb_hourly_rate',  true );
		$min_dur     = get_post_meta( $post->ID, '_sb_min_duration', true ) ?: 1;
		$max_dur     = get_post_meta( $post->ID, '_sb_max_duration', true ) ?: 8;
		$capacity    = get_post_meta( $post->ID, '_sb_capacity',     true );
		$overrides   = get_post_meta( $post->ID, '_sb_day_overrides', true );

		if ( ! is_array( $overrides ) ) {
			$overrides = [];
		}

		$days = [
			0 => __( 'Sunday',    'space-booking' ),
			1 => __( 'Monday',    'space-booking' ),
			2 => __( 'Tuesday',   'space-booking' ),
			3 => __( 'Wednesday', 'space-booking' ),
			4 => __( 'Thursday',  'space-booking' ),
			5 => __( 'Friday',    'space-booking' ),
			6 => __( 'Saturday',  'space-booking' ),
		];
		?>
		<style>
			.sb-meta-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
			.sb-meta-field label { display:block; font-weight:600; margin-bottom:4px; }
			.sb-meta-field input { width:100%; }
			.sb-day-row { display:grid; grid-template-columns:100px 1fr 1fr 80px; gap:8px; align-items:center; padding:6px 0; border-bottom:1px solid #eee; }
		</style>
		<div class="sb-meta-grid">
			<div class="sb-meta-field">
				<label for="sb_hourly_rate"><?php esc_html_e( 'Hourly Rate ($)', 'space-booking' ); ?></label>
				<input type="number" id="sb_hourly_rate" name="sb_hourly_rate" step="0.01" min="0"
					value="<?php echo esc_attr( $rate ); ?>">
			</div>
			<div class="sb-meta-field">
				<label for="sb_capacity"><?php esc_html_e( 'Capacity (guests)', 'space-booking' ); ?></label>
				<input type="number" id="sb_capacity" name="sb_capacity" min="0"
					value="<?php echo esc_attr( $capacity ); ?>">
			</div>
			<div class="sb-meta-field">
				<label for="sb_min_duration"><?php esc_html_e( 'Min Duration (hours)', 'space-booking' ); ?></label>
				<input type="number" id="sb_min_duration" name="sb_min_duration" min="1" max="24"
					value="<?php echo esc_attr( $min_dur ); ?>">
			</div>
			<div class="sb-meta-field">
				<label for="sb_max_duration"><?php esc_html_e( 'Max Duration (hours)', 'space-booking' ); ?></label>
				<input type="number" id="sb_max_duration" name="sb_max_duration" min="1" max="24"
					value="<?php echo esc_attr( $max_dur ); ?>">
			</div>
		</div>

		<h4><?php esc_html_e( 'Day-specific Hour Overrides', 'space-booking' ); ?></h4>
		<p class="description"><?php esc_html_e( 'Leave blank to use global hours. Check "Closed" to mark as unavailable.', 'space-booking' ); ?></p>

		<div class="sb-day-row" style="font-weight:600">
			<span><?php esc_html_e( 'Day', 'space-booking' ); ?></span>
			<span><?php esc_html_e( 'Open', 'space-booking' ); ?></span>
			<span><?php esc_html_e( 'Close', 'space-booking' ); ?></span>
			<span><?php esc_html_e( 'Closed', 'space-booking' ); ?></span>
		</div>

		<?php foreach ( $days as $num => $name ) :
			$ov     = $overrides[ $num ] ?? [];
			$open   = $ov['open']   ?? '';
			$close  = $ov['close']  ?? '';
			$closed = ! empty( $ov['closed'] );
		?>
		<div class="sb-day-row">
			<span><?php echo esc_html( $name ); ?></span>
			<input type="time" name="sb_day_overrides[<?php echo esc_attr( $num ); ?>][open]"
				value="<?php echo esc_attr( $open ); ?>">
			<input type="time" name="sb_day_overrides[<?php echo esc_attr( $num ); ?>][close]"
				value="<?php echo esc_attr( $close ); ?>">
			<input type="checkbox" name="sb_day_overrides[<?php echo esc_attr( $num ); ?>][closed]"
				value="1" <?php checked( $closed ); ?>>
		</div>
		<?php endforeach; ?>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['sb_space_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['sb_space_nonce'] ), 'sb_space_save' )
			|| defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		update_post_meta( $post_id, '_sb_hourly_rate',  (float) ( $_POST['sb_hourly_rate']  ?? 0 ) );
		update_post_meta( $post_id, '_sb_min_duration', (int)   ( $_POST['sb_min_duration'] ?? 1 ) );
		update_post_meta( $post_id, '_sb_max_duration', (int)   ( $_POST['sb_max_duration'] ?? 8 ) );
		update_post_meta( $post_id, '_sb_capacity',     (int)   ( $_POST['sb_capacity']     ?? 0 ) );

		// Day overrides
		$raw_overrides = $_POST['sb_day_overrides'] ?? [];
		$clean         = [];

		for ( $i = 0; $i <= 6; $i++ ) {
			$ov = $raw_overrides[ $i ] ?? [];
			if ( ! empty( $ov['closed'] ) ) {
				$clean[ $i ] = [ 'closed' => true ];
			} elseif ( ! empty( $ov['open'] ) || ! empty( $ov['close'] ) ) {
				$clean[ $i ] = [
					'open'  => sanitize_text_field( $ov['open']  ?? '' ),
					'close' => sanitize_text_field( $ov['close'] ?? '' ),
				];
			}
		}

		update_post_meta( $post_id, '_sb_day_overrides', $clean );
	}
}
