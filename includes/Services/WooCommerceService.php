<?php declare(strict_types=1);

namespace SpaceBooking\Services;

use WC_Cart;
use WC_Order;
use WC_Product_Simple;

/**
 * WooCommerce integration service.
 */
final class WooCommerceService
{
    /**
     * Create a booking item in WooCommerce cart as a virtual product.
     *
     * @param array $booking_data  From BookingController (space_id, date, etc.)
     * @param float $total_price
     * @param int   $booking_id    DB booking ID
     * @return string WC checkout URL or add-to-cart URL
     */
    public function add_booking_to_cart(array $booking_data, float $total_price, int $booking_id): string
    {
        if (!function_exists('WC') || !WC()) {
            throw new \RuntimeException('WooCommerce not initialized.');
        }

        if (WC()->cart === null) {
            throw new \RuntimeException('WooCommerce cart not available.');
        }

        // Create virtual product on-the-fly
        $product = new WC_Product_Simple();
        $product->set_name(sprintf('Booking #%d - %s', $booking_id, get_the_title($booking_data['space_id'])));
        $product->set_price($total_price);
        $product->set_virtual(true);
        $product->save();

        // Clear cart if needed
        WC()->cart->empty_cart();

        // Add to cart with meta
        $cart_item_key = WC()->cart->add_to_cart(
            $product->get_id(),
            1,
            0,
            [],  // no variation
            [
                'sb_booking_id' => $booking_id,
                'sb_space_id' => $booking_data['space_id'],
                'sb_date' => $booking_data['date'],
                'sb_start_time' => $booking_data['start_time'],
                'sb_end_time' => $booking_data['end_time'],
                'sb_customer_name' => $booking_data['customer_name'],
                'sb_customer_email' => $booking_data['customer_email'],
                'sb_extras' => $booking_data['extras'] ?? [],
                'sb_breakdown' => $booking_data['breakdown'] ?? [],
            ]
        );

        if (!$cart_item_key) {
            throw new \RuntimeException('Failed to add booking to cart.');
        }

        // Redirect to checkout
        return wc_get_checkout_url();
    }

    /**
     * Confirm booking from WC order payment complete.
     * Called from WC hook.
     */
    public static function confirm_from_order(int $order_id): void
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
        if ($repo->find($booking_id)) {
            $repo->update_status($booking_id, 'confirmed');
            $booking = $repo->find($booking_id);
            (new EmailService())->send_confirmation($booking);
        }
    }

    /**
     * Get booking ID from cart item for order creation.
     */
    public static function save_booking_meta_to_order($item, $cart_item_key, $values, $order)
    {
        if (isset($values['sb_booking_id'])) {
            $order->add_meta_data('_sb_booking_id', $values['sb_booking_id']);
        }
    }
}
