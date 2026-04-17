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

        error_log('SpaceBooking WC: Creating product for booking #' . $booking_id . ', cart available');

        // Create virtual product on-the-fly
        $product = new WC_Product_Simple();
        $product->set_name(sprintf('Booking #%d - %s', $booking_id, get_the_title($booking_data['space_id'])));
        $product->set_price($total_price);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_manage_stock(true);
        $product->set_stock_quantity(1);
        $product->set_stock_status('instock');
        $product->set_sold_individually(true);
        $product->set_catalog_visibility('catalog');
        $product->set_regular_price($total_price);
        // Format detailed description from breakdown
        $breakdown_html = 'Space booking service - #' . $booking_id;
        $space_title = get_the_title($booking_data['space_id']);
        $date = $booking_data['date'] ?? '';
        $start = $booking_data['start_time'] ?? '';
        $end = $booking_data['end_time'] ?? '';
        if ($date && $start && $end) {
            $breakdown_html .= '<br><small>' . $space_title . ' | ' . $date . ' ' . $start . '–' . $end . '</small>';
        }
        $breakdown_raw = $booking_data['breakdown'] ?? [];
        if (!empty($breakdown_raw) && is_array($breakdown_raw)) {
            $breakdown_html .= '<ul style=\"margin: 10px 0; padding-left: 20px;\">';
            foreach ($breakdown_raw as $item) {
                $label = htmlspecialchars($item['label'] ?? 'Item');
                $amount = number_format((float) ($item['amount'] ?? 0), 2);
                $symbol = get_woocommerce_currency_symbol();
                $breakdown_html .= '<li>' . $label . ': <strong>' . $symbol . $amount . '</strong></li>';
            }
            $breakdown_html .= '</ul>';
        }
        $breakdown_html .= '<em>Total: ' . get_woocommerce_currency_symbol() . number_format($total_price, 2) . '</em>';
        $product->set_description($breakdown_html);
        $product->save();

        error_log('SpaceBooking WC: Product ID ' . $product->get_id() . ' published, price $' . $total_price);
        error_log('SpaceBooking WC: Product virtual=' . ($product->is_virtual() ? 'yes' : 'no') . ', price=' . $product->get_price() . ', status=' . $product->get_status());

        // Clear cart if needed
        WC()->cart->empty_cart();
        error_log('SpaceBooking WC: Cart emptied, items count after: ' . sizeof(WC()->cart->get_cart()));

        // Add to cart with meta
        wc_clear_notices();
        $errors_before = wc_get_notices('error');
        error_log('SpaceBooking WC: About to add_to_cart product_id=' . $product->get_id() . ', cart_errors_before: ' . json_encode($errors_before ?: 'none'));
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
                'sb_extras' => wp_json_encode($booking_data['extras'] ?? []),
                'sb_breakdown' => wp_json_encode($booking_data['breakdown'] ?? []),
            ]
        );
        $errors_after = wc_get_notices('error');
        error_log('SpaceBooking WC: add_to_cart returned key: ' . ($cart_item_key ?: 'NULL/FALSE') . ', cart_errors_after: ' . json_encode($errors_after ?: 'none') . ', cart count: ' . WC()->cart->get_cart_contents_count());

        if (!$cart_item_key) {
            $fail_errors = wc_get_notices('error') ?: 'none';
            error_log('SpaceBooking WC: add_to_cart FAILED. Key null. Full cart errors: ' . json_encode($fail_errors));
            WC()->cart->empty_cart('yes');  // Clear invalid state
            $fail_notices = wc_get_notices('error');
            $fail_msgs = array_column($fail_notices ?: [], 'notice');
            throw new \RuntimeException('Failed to add booking to cart. Cart errors: ' . implode(', ', $fail_msgs));
        }

        error_log('SpaceBooking WC: Booking #' . $booking_id . ' added to cart key: ' . $cart_item_key);

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
