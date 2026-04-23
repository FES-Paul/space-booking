<?php
/** Admin AJAX Handlers for Booking Updates */
add_action('wp_ajax_sb_update_booking_status', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_ajax_referer('sb_update_booking', '_wpnonce');

    $booking_id = absint($_POST['booking_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    $feedback = sanitize_textarea_field($_POST['feedback'] ?? '');

    if (!$booking_id || !in_array($status, ['pending', 'in_review', 'confirmed'])) {
        wp_send_json_error('Invalid input');
    }

    $repo = new \SpaceBooking\Services\BookingRepository();
    $booking = $repo->find($booking_id);

    if (!$booking) {
        wp_send_json_error('Booking not found');
    }

    $extra_data = $feedback ? ['admin_feedback' => $feedback] : [];
    $updated = $repo->update_status($booking_id, $status, $extra_data);

    if ($updated) {
        wp_send_json_success([
            'status' => $status,
            'feedback' => $feedback
        ]);
    } else {
        wp_send_json_error('Failed to update booking');
    }
});

// Enqueue edit page scripts (only on booking edit page)
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if ($screen->id === 'space-booking_page_space-booking-bookings' && isset($_GET['edit'])) {
        wp_enqueue_script('jquery');
    }
});
?>