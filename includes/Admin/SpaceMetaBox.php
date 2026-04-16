<?php

declare(strict_types=1);

namespace SpaceBooking\Admin;

/**
 * Meta box for the sb_space CPT: hourly rate, hours, day overrides, capacity.
 */
final class SpaceMetaBox
{

	public function register(): void
	{
		add_action('add_meta_boxes', [$this, 'add']);
		add_action('save_post_sb_space', [$this, 'save'], 10, 2);
	}

	public function add(): void
	{
		add_meta_box(
			'sb-space-settings',
			__('Space Settings', 'space-booking'),
			[$this, 'render'],
			'sb_space',
			'normal',
			'high'
		);
	}

	public function render(\WP_Post $post): void
	{
		wp_nonce_field('sb_space_save', 'sb_space_nonce');

		$rate        = get_post_meta($post->ID, '_sb_hourly_rate',  true);
		$min_dur     = get_post_meta($post->ID, '_sb_min_duration', true) ?: 1;
		$max_dur     = get_post_meta($post->ID, '_sb_max_duration', true) ?: 8;
		$capacity    = get_post_meta($post->ID, '_sb_capacity',     true);
		$overrides   = get_post_meta($post->ID, '_sb_day_overrides', true);

		if (! is_array($overrides)) {
			$overrides = [];
		}

		$days = [
			0 => __('Sunday',    'space-booking'),
			1 => __('Monday',    'space-booking'),
			2 => __('Tuesday',   'space-booking'),
			3 => __('Wednesday', 'space-booking'),
			4 => __('Thursday',  'space-booking'),
			5 => __('Friday',    'space-booking'),
			6 => __('Saturday',  'space-booking'),
		];
?>
<style>
.sb-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.sb-meta-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
}

.sb-meta-field input {
    width: 100%;
}

.sb-day-row {
    display: grid;
    grid-template-columns: 100px 1fr 1fr 80px;
    gap: 8px;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid #eee;
}
</style>
<div class="sb-meta-grid">
    <div class="sb-meta-field">
        <?php $symbol = \SpaceBooking\Services\CurrencyService::get_symbol(); ?>
        <label for="sb_hourly_rate"><?php printf( esc_html__('Hourly Rate (%s)', 'space-booking'), $symbol ); ?></label>
        <input type="number" id="sb_hourly_rate" name="sb_hourly_rate" step="0.01" min="0"
            value="<?php echo esc_attr($rate); ?>">

    </div>
    <div class="sb-meta-field">
        <label for="sb_capacity"><?php esc_html_e('Capacity (guests)', 'space-booking'); ?></label>
        <input type="number" id="sb_capacity" name="sb_capacity" min="0" value="<?php echo esc_attr($capacity); ?>">
    </div>
    <div class="sb-meta-field">
        <label for="sb_min_duration"><?php esc_html_e('Min Duration (hours)', 'space-booking'); ?></label>
        <input type="number" id="sb_min_duration" name="sb_min_duration" min="1" max="24"
            value="<?php echo esc_attr($min_dur); ?>">
    </div>
    <div class="sb-meta-field">
        <label for="sb_max_duration"><?php esc_html_e('Max Duration (hours)', 'space-booking'); ?></label>
        <input type="number" id="sb_max_duration" name="sb_max_duration" min="1" max="24"
            value="<?php echo esc_attr($max_dur); ?>">
    </div>
    <div class="sb-meta-field">
        <label for="sb_buffer_pre"><?php esc_html_e('Pre-Event Buffer (minutes)', 'space-booking'); ?></label>
        <input type="number" id="sb_buffer_pre" name="sb_buffer_pre" min="0"
            value="<?php echo esc_attr( get_post_meta($post->ID, '_sb_buffer_pre_minutes', true ) ?: '' ); ?>">
        <p class="description"><?php esc_html_e('Overrides global. 0 = use global.', 'space-booking'); ?></p>
    </div>
    <div class="sb-meta-field">
        <label for="sb_buffer_post"><?php esc_html_e('Post-Event Buffer (minutes)', 'space-booking'); ?></label>
        <input type="number" id="sb_buffer_post" name="sb_buffer_post" min="0"
            value="<?php echo esc_attr( get_post_meta($post->ID, '_sb_buffer_post_minutes', true ) ?: '' ); ?>">
        <p class="description"><?php esc_html_e('Overrides global. 0 = use global.', 'space-booking'); ?></p>
    </div>
</div>

<h4><?php esc_html_e('Day-specific Hour Overrides', 'space-booking'); ?></h4>
<p class="description">
    <?php esc_html_e('Leave blank to use global hours. Check "Closed" to mark as unavailable.', 'space-booking'); ?></p>

<div class="sb-day-row" style="font-weight:600">
    <span><?php esc_html_e('Day', 'space-booking'); ?></span>
    <span><?php esc_html_e('Open', 'space-booking'); ?></span>
    <span><?php esc_html_e('Close', 'space-booking'); ?></span>
    <span><?php esc_html_e('Closed', 'space-booking'); ?></span>
</div>

<?php foreach ($days as $num => $name) :
			$ov     = $overrides[$num] ?? [];
			$open   = $ov['open']   ?? '';
			$close  = $ov['close']  ?? '';
			$closed = ! empty($ov['closed']);
		?>
<div class="sb-day-row">
    <span><?php echo esc_html($name); ?></span>
    <input type="time" name="sb_day_overrides[<?php echo esc_attr($num); ?>][open]"
        value="<?php echo esc_attr($open); ?>">
    <input type="time" name="sb_day_overrides[<?php echo esc_attr($num); ?>][close]"
        value="<?php echo esc_attr($close); ?>">
    <input type="checkbox" name="sb_day_overrides[<?php echo esc_attr($num); ?>][closed]" value="1"
        <?php checked($closed); ?>>
</div>
<?php endforeach; ?>

<div>
    <h4><?php esc_html_e('Price Overrides (specific dates/times)', 'space-booking'); ?></h4>
    <p class="description">
        <?php esc_html_e('Set custom hourly rates for specific date/time ranges. Overlaps split pro-rata.', 'space-booking'); ?>
    </p>

    <div id="sb-price-overrides">
        <?php
				$price_overrides = get_post_meta($post->ID, '_sb_price_overrides', true);
				if (! is_array($price_overrides)) $price_overrides = [];	
				foreach ($price_overrides as $i => $ov) :
				?>
        <div class="sb-override-row"
            style="display:grid;grid-template-columns:100px 120px 120px 100px 30px;gap:8px;align-items:center;padding:8px 0;border-bottom:1px solid #ddd;">
            <?php foreach ([0=>__('Sun'),1=>__('Mon'),2=>__('Tue'),3=>__('Wed'),4=>__('Thu'),5=>__('Fri'),6=>__('Sat')] as $day_num => $day_name) : ?>
            <label style="font-size:12px;"><input type="checkbox" name="sb_price_overrides[<?php echo $i; ?>][days][]"
                    value="<?php echo $day_num; ?>"
                    <?php checked( in_array($day_num, $ov['days'] ?? []) ); ?>><?php echo $day_name; ?></label>
            <?php endforeach; ?>
            <input type="time" name="sb_price_overrides[<?php echo $i; ?>][start_time]"
                value="<?php echo esc_attr($ov['start_time'] ?? ''); ?>" required>
            <input type="time" name="sb_price_overrides[<?php echo $i; ?>][end_time]"
                value="<?php echo esc_attr($ov['end_time'] ?? ''); ?>" required>
            <input type="number" name="sb_price_overrides[<?php echo $i; ?>][hourly_rate]" step="0.01" min="0"
                value="<?php echo esc_attr($ov['hourly_rate'] ?? ''); ?>" required style="width:100%;">
            <button type="button" class="button-link sb-remove-override" style="color:#d63638;">×</button>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" id="sb-add-override"
        class="button"><?php esc_html_e('Add Price Override', 'space-booking'); ?></button>
</div>


</div>

<script>
jQuery(document).ready(function($) {
    let overrideIndex = <?php echo count($price_overrides); ?>;
    const dayLabels = <?php 
					$day_short = [0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat'];
					echo json_encode($day_short);
				?>;
    $('#sb-add-override').click(function() {
        let checkboxes = '';
        for (let d = 0; d < 7; d++) {
            checkboxes +=
                '<label style="font-size:11px;white-space:nowrap;display:inline-block;margin-right:2px;"><input type="checkbox" name="sb_price_overrides[' +
                overrideIndex + '][days][]" value="' + d + '">' + dayLabels[d] + '</label>';
        }
        const row =
            '<div class="sb-override-row" style="display:grid;grid-template-columns:repeat(7,1fr) 120px 120px 100px 30px;gap:2px;align-items:center;padding:8px 0;border-bottom:1px solid #ddd;">' +
            checkboxes +
            '<input type="time" name="sb_price_overrides[' + overrideIndex +
            '][start_time]" required>' +
            '<input type="time" name="sb_price_overrides[' + overrideIndex + '][end_time]" required>' +
            '<input type="number" name="sb_price_overrides[' + overrideIndex +
            '][hourly_rate]" step="0.01" min="0" required style="width:100%;">' +
            '<button type="button" class="button-link sb-remove-override" style="color:#d63638;">×</button>' +
            '</div>';
        $('#sb-price-overrides').append(row);
        overrideIndex++;
    });
    $(document).on('click', '.sb-remove-override', function() {
        $(this).closest('.sb-override-row').remove();
    });
});
</script>
<?php
	}

	public function save(int $post_id, \WP_Post $post): void
	{
		if (
			! isset($_POST['sb_space_nonce'])
			|| ! wp_verify_nonce(sanitize_key($_POST['sb_space_nonce']), 'sb_space_save')
			|| defined('DOING_AUTOSAVE') && DOING_AUTOSAVE
			|| ! current_user_can('edit_post', $post_id)
		) {
			return;
		}

		update_post_meta($post_id, '_sb_hourly_rate',  (float) ($_POST['sb_hourly_rate']  ?? 0));
		update_post_meta($post_id, '_sb_min_duration', (int)   ($_POST['sb_min_duration'] ?? 1));
		update_post_meta($post_id, '_sb_max_duration', (int)   ($_POST['sb_max_duration'] ?? 8));
		update_post_meta($post_id, '_sb_capacity',     (int)   ($_POST['sb_capacity']     ?? 0));
		update_post_meta($post_id, '_sb_buffer_pre_minutes',  (int) ($_POST['sb_buffer_pre']  ?? 0));
		update_post_meta($post_id, '_sb_buffer_post_minutes', (int) ($_POST['sb_buffer_post'] ?? 0));

		// Day overrides
		$raw_overrides = $_POST['sb_day_overrides'] ?? [];
		$clean         = [];

		for ($i = 0; $i <= 6; $i++) {
			$ov = $raw_overrides[$i] ?? [];
			if (! empty($ov['closed'])) {
				$clean[$i] = ['closed' => true];
			} elseif (! empty($ov['open']) || ! empty($ov['close'])) {
				$clean[$i] = [
					'open'  => sanitize_text_field($ov['open']  ?? ''),
					'close' => sanitize_text_field($ov['close'] ?? ''),
				];
			}
		}

		update_post_meta($post_id, '_sb_day_overrides', $clean);

		// Price overrides - validate no overlaps per day
		$raw_price_ov = $_POST['sb_price_overrides'] ?? [];
		$clean_price_ov = [];
		$day_slots = []; // [day_num => [[start_min, end_min]]]

		foreach ( $raw_price_ov as $ov ) {
			$days = array_map( 'intval', $ov['days'] ?? [] );
			$start_time = sanitize_text_field( $ov['start_time'] ?? '' );
			$end_time = sanitize_text_field( $ov['end_time'] ?? '' );
			$hourly_rate = (float) ( $ov['hourly_rate'] ?? 0 );

			if ( empty( $days ) || empty( $start_time ) || empty( $end_time ) || $hourly_rate <= 0 ) continue;

			$start_min = (int) substr( $start_time, 0, 2 ) * 60 + (int) substr( $start_time, 3, 2 );
			$end_min = (int) substr( $end_time, 0, 2 ) * 60 + (int) substr( $end_time, 3, 2 );

			if ( $start_min >= $end_min ) {
				add_settings_error( 'sb_space', 'invalid_time', 'Start time must be before end time.', 'error' );
				return;
			}

			// Check overlaps per day
			foreach ( $days as $day ) {
				if ( ! isset( $day_slots[ $day ] ) ) $day_slots[ $day ] = [];
				foreach ( $day_slots[ $day ] as $existing ) {
					if ( ! ( $end_min <= $existing[0] || $start_min >= $existing[1] ) ) {
						$days_labels = [0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];
						add_settings_error( 'sb_space', 'overlapping_schedule', 'Overlapping price schedules on ' . $days_labels[ $day ] . '. Fix before saving.', 'error' );
						return;
					}
				}
				$day_slots[ $day ][] = [ $start_min, $end_min ];
			}

			$clean_price_ov[] = [
				'days'        => $days,
				'start_time'  => $start_time,
				'end_time'    => $end_time,
				'hourly_rate' => $hourly_rate
			];
		}
		update_post_meta( $post_id, '_sb_price_overrides', $clean_price_ov );
	}
}