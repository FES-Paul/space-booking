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

        if (!is_cart() && !is_checkout()) {
            return;
        }

        if (null === WC()->session) {
            return;
        }

        error_log('SpaceBooking WC populate_pending_cart triggered on ' . (is_cart() ? 'cart' : 'checkout'));
        $pending = WC()->session->get('sb_pending_booking');
        if (!$pending || !isset($pending['booking_id'])) {
            error_log('SpaceBooking WC no pending booking in session');
            return;
        }

        error_log('SpaceBooking WC populating pending booking #' . $pending['booking_id']);
        error_log('REST Session ID: ' . WC()->session->get_customer_id());  // For comparison

        $wc = new \SpaceBooking\Services\WooCommerceService();
        try {
            $wc->add_booking_to_cart(
                $pending['booking_data'],
                $pending['total_price'],
                $pending['booking_id']
            );
            error_log('SpaceBooking WC populate success for #' . $pending['booking_id']);
        } catch (Exception $e) {
            error_log('SpaceBooking WC populate failed: ' . $e->getMessage());
        }

        // Clear session
        WC()->session->set('sb_pending_booking', null);
        error_log('SpaceBooking WC pending cleared');
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
