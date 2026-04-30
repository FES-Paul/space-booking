<?php declare(strict_types=1);

namespace SpaceBooking\Admin;

use SpaceBooking\Services\BookingRepository;
use SpaceBooking\Services\ConfirmationEmailService;

/**
 * Adds "Send Booking Confirmation Email" to WooCommerce order actions.
 */
final class WooCommerceOrderActions
{
    public static function register(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_filter('woocommerce_order_actions', [self::class, 'add_confirmation_action'], 20, 2);
        add_filter('woocommerce_order_actions', [self::class, 'add_test_email_action'], 10, 2);
        error_log('SB_DEBUG: WooCommerceOrderActions hooks registered');
        add_action('woocommerce_order_action_sb_send_confirmation_email', [self::class, 'handle_send_confirmation']);
        add_action('woocommerce_order_action_send_test_email_action', [self::class, 'handle_test_email']);
    }

    /**
     * Add action only if order has _sb_booking_id meta.
     */
    public static function add_confirmation_action(array $actions, \WC_Order $order): array
    {
        error_log('SB_DEBUG: Checking Order Actions for Order #' . $order->get_id());

        // Check order meta first
        $booking_id = $order->get_meta('_sb_booking_id');
        if (!$booking_id) {
            // Fallback: check line items for sb_booking_id
            foreach ($order->get_items() as $item) {
                $booking_id = $item->get_meta('sb_booking_id');
                if ($booking_id) {
                    error_log('SB_DEBUG: Found sb_booking_id=' . $booking_id . ' in line item');
                    break;
                }
            }
        }

        if (!$booking_id) {
            error_log('SB_DEBUG: No Booking ID found for this order.');
            return $actions;
        }
        error_log('SB_DEBUG: Booking ID found. Adding Action.');

        $repo = new BookingRepository();
        $booking = $repo->find((int) $booking_id);
        if (!$booking || !in_array($booking['status'], ['in_review', 'confirmed'])) {
            return $actions;
        }

        $actions['sb_send_confirmation_email'] = __('Send Booking Confirmation Email', 'space-booking');

        return $actions;
    }

    /**
     * Add "Send Confirmation Email" action to ALL orders (matching functions.php test)
     */
    public static function add_test_email_action(array $actions, \WC_Order $order): array
    {
        $actions['send_test_email_action'] = __('Send Confirmation Email', 'space-booking');
        return $actions;
    }

    /**
     * Handle the action: send custom email + add order note.
     */
    public static function handle_send_confirmation(\WC_Order $order): void
    {
        $booking_id = $order->get_meta('_sb_booking_id');
        if (!$booking_id) {
            $order->add_order_note(__('ERROR: No booking ID found.', 'space-booking'), 1);
            return;
        }

        $repo = new BookingRepository();
        $booking = $repo->find((int) $booking_id);
        if (!$booking || empty($booking['customer_email'])) {
            $order->add_order_note(__('ERROR: Invalid booking or missing email.', 'space-booking'), 1);
            return;
        }

        $email_service = new ConfirmationEmailService($booking_id, $order->get_id());
        $result = $email_service->send_custom_confirmation();

        if ($result) {
            /* translators: %s: customer email */
            $order->add_order_note(sprintf(__('Successfully sent Custom Booking Confirmation to %s.', 'space-booking'), $booking['customer_email']));
        } else {
            $order->add_order_note(__('FAILED: Confirmation email could not be sent. Check server logs.', 'space-booking'), 1);
        }

        delete_transient('woocommerce_order_actions_' . $order->get_id());  // Refresh actions
    }

    /**
     * Handle Test Email action (exact copy from working functions.php test)
     */
    public static function handle_test_email(\WC_Order $order): void
    {
        // Get the billing email from the order (as seen in your screenshot)
        $to = $order->get_billing_email();
        $order_id = $order->get_id();

        // Define the email subject and content
        $subject = 'Test Email for Order #' . $order_id;

        // Simple HTML Template
        $message = '
            <h2>Hello ' . $order->get_billing_first_name() . ',</h2>
            <p>This is a test email sent manually from your order dashboard.</p>
            <p><strong>Order Details:</strong></p>
            <ul>
                <li>Order ID: ' . $order_id . '</li>
                <li>Total: ' . $order->get_formatted_order_total() . '</li>
            </ul>
            <p>Thank you for testing!</p>
        ';

        // Set headers for HTML email
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email using the standard WordPress mailer
        wp_mail($to, $subject, $message, $headers);

        // Add an order note so you know the email was sent
        $order->add_order_note(__('Test email sent to customer manually.', 'space-booking'));
    }
}
