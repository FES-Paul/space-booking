<?php declare(strict_types=1);

namespace SpaceBooking\Integrations;

use SpaceBooking\Services\BookingRepository;
use SpaceBooking\Services\WooCommerceService;

/**
 * WooCommerce hooks for booking fulfillment.
 */
final class WooCommerceIntegration
{
    public static function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Use template_redirect for cart/checkout page load after session ready
        add_action('template_redirect', [self::class, 'populate_pending_cart'], 20);
        add_action('woocommerce_checkout_order_processed', [self::class, 'save_order_meta'], 10, 3);
        add_action('woocommerce_payment_complete', [self::class, 'confirm_booking'], 10, 1);
        add_action('woocommerce_order_status_pending_to_processing', [self::class, 'confirm_booking'], 10, 1);
    }

    public static function populate_pending_cart(): void
    {
        error_log('SpaceBooking: Template Redirect Heartbeat - URL: ' . $_SERVER['REQUEST_URI']);

        if (!is_cart() && !is_checkout() && !is_account_page()) {
            return;
        }

        if (null === WC()->session) {
            return;
        }

        error_log('SpaceBooking WC populate_pending_cart triggered on ' . (is_cart() ? 'cart' : 'checkout'));
        // Check for pending via booking ID link (fixes guest session mismatch)
        // Try session first
        $pending_id = WC()->session->get('sb_pending_booking_id');

        if (!$pending_id) {
            // Fallback: scan recent transients
            error_log('SpaceBooking WC no session ID, scanning transients...');
            $transients = get_transient('sb_pending_checkout_list');
            if (!$transients) {
                $all_transients = [];
                global $wpdb;
                $results = $wpdb->get_results("
                    SELECT option_name, option_value 
                    FROM {$wpdb->options} 
                    WHERE option_name LIKE '_transient_sb_pending_checkout_%' 
                    AND option_value != ''
                    ORDER BY option_id DESC 
                    LIMIT 5
                ");
                foreach ($results as $row) {
                    $id = str_replace('_transient_sb_pending_checkout_', '', $row->option_name);
                    if (is_numeric($id)) {
                        $all_transients[] = (int) $id;
                    }
                }
                $transients = $all_transients;
            }

            foreach ($transients as $possible_id) {
                $pending = get_transient('sb_pending_checkout_' . $possible_id);
                if ($pending) {
                    $pending_id = $possible_id;
                    error_log('SpaceBooking WC found pending transient fallback ID: ' . $pending_id);
                    break;
                }
            }
        }

        if (!$pending_id) {
            error_log('SpaceBooking WC no pending booking found (session or transient)');
            return;
        }

        $pending = get_transient('sb_pending_checkout_' . $pending_id);
        if (!$pending) {
            error_log('SpaceBooking WC no pending data in transient for #' . $pending_id);
            WC()->session?->set('sb_pending_booking_id', null);
            return;
        }

        error_log('SpaceBooking WC populating pending booking #' . $pending_id . ' from transient');

        $wc = new \SpaceBooking\Services\WooCommerceService();
        try {
            $wc->add_booking_to_cart(
                $pending['booking_data'],
                $pending['total_price'],
                $pending_id
            );
            error_log('SpaceBooking WC populate success for #' . $pending_id);
        } catch (Exception $e) {
            error_log('SpaceBooking WC populate failed for #' . $pending_id . ': ' . $e->getMessage());
        }

        // Clear transient and session
        delete_transient('sb_pending_checkout_' . $pending_id);
        WC()->session->set('sb_pending_booking_id', null);
        error_log('SpaceBooking WC pending cleared (transient + session)');
    }

    /**
     * Save booking ID to order meta during checkout.
     */
    public static function save_order_meta($order_id, $posted_data, $order)
    {
        // Cart item data has sb_booking_id from WooCommerceService::add_booking_to_cart
        foreach ($order->get_items() as $item) {
            $cart_item_data = $item->get_data();
            if (isset($cart_item_data['sb_booking_id'])) {
                $order->update_meta_data('_sb_booking_id', $cart_item_data['sb_booking_id']);
                $order->save_meta_data();
                break;
            }
        }
    }

    /**
     * Confirm booking when order is paid/processing.
     */
    public static function confirm_booking($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $booking_id = $order->get_meta('_sb_booking_id');
        if (!$booking_id) {
            return;
        }

        $repo = new BookingRepository();
        $booking = $repo->find((int) $booking_id);
        if ($booking && $booking['status'] === 'pending') {
            $repo->update_status($booking_id, 'confirmed');
            (new \SpaceBooking\Services\EmailService())->send_confirmation($booking);
        }
    }
}
