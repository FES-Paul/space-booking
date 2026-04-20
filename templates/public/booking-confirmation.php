<?php

/**
 * Public booking confirmation page template.
 * Used after WooCommerce payment success.
 */
if (!defined('ABSPATH'))
    exit;

global $wp_query;
$booking_id = intval(get_query_var('booking_id') ?? $_GET['id'] ?? 0);
$status = sanitize_text_field(get_query_var('status') ?? $_GET['status'] ?? '');

// Fetch booking if provided
$booking = null;
if ($booking_id) {
    $repo = new \SpaceBooking\Services\BookingRepository();
    $booking = $repo->find($booking_id);
    if ($booking) {
        wp_localize_script('space-booking-app', 'sbConfirmationData', [
            'bookingId' => $booking_id,
            'status' => $booking['status'],
            'spaceId' => $booking['space_id'],
        ]);
    }
}

get_header();
?>
<div id="sb-confirmation-app" data-booking-id="<?= esc_attr($booking_id) ?>" data-status="<?= esc_attr($status) ?>">
    <div class="sb-confirmation-loading">
        <p>Checking booking status...</p>
    </div>
</div>

<script type="module">
// Load BookingApp with confirmation step
import('/wp-content/plugins/space-booking/assets/js/booking-app.js')
    .then(() => {
        const app = document.getElementById('sb-confirmation-app');
        if (app && window.sbConfig) {
            app.innerHTML = '<div id="sb-booking-app"></div>';
            // Trigger step 7 via store or query
        }
    });
</script>

<?php if ($booking): ?>
<style>
.sb-confirmation-success {
    background: #d4edda;
    padding: 20px;
    border-radius: 8px;
}
</style>
<div class="sb-confirmation-success">
    <h2>Booking #<?= esc_html($booking_id) ?> Confirmed!</h2>
    <p>Space: <?= esc_html(get_the_title($booking['space_id'])) ?></p>
    <p>Date: <?= esc_html($booking['booking_date']) ?>
        <?= esc_html($booking['start_time']) ?>–<?= esc_html($booking['end_time']) ?></p>
</div>
<?php endif; ?>

<?php get_footer(); ?>