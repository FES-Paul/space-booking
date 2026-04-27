<?php declare(strict_types=1);

namespace SpaceBooking\Controllers;

use SpaceBooking\Services\Interfaces\BookingRepositoryInterface;
use WP_REST_Request;

/**
 * OOP Controller for Admin AJAX handlers.
 * Handles booking status updates.
 */
final class AdminBookingController
{
    private BookingRepositoryInterface $repo;

    public function __construct(BookingRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Handle AJAX sb_update_booking_status
     */
    public function handleStatusUpdate(): void
    {
        if (!current_user_can('manage_space_bookings')) {
            wp_die('Unauthorized');
        }

        check_ajax_referer('sb_update_booking', '_wpnonce');

        $booking_id = absint($_POST['booking_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $feedback = sanitize_textarea_field($_POST['feedback'] ?? '');

        if (!$booking_id || !in_array($status, ['pending', 'in_review', 'confirmed'])) {
            wp_send_json_error('Invalid input');
        }

        $booking = $this->repo->find($booking_id);

        if (!$booking) {
            wp_send_json_error('Booking not found');
        }

        $extra_data = $feedback ? ['admin_feedback' => $feedback] : [];
        $updated = $this->repo->update_status($booking_id, $status, $extra_data);

        if ($updated) {
            wp_send_json_success([
                'status' => $status,
                'feedback' => $feedback
            ]);
        } else {
            wp_send_json_error('Failed to update booking');
        }
    }
}
