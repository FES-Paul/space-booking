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
        $space_id = $booking_data['space_id'];
        $space_title = get_the_title($space_id);
        $package_id = $booking_data['package_id'] ?? null;
        $package_title = $package_id ? get_the_title($package_id) : null;
        $product->set_name(sprintf('Booking #%d - %s', $booking_id, $package_title ?: $space_title));
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
        // Format detailed description with full breakdown and extras
        $breakdown_html = '<strong>Space booking service - #' . $booking_id . '</strong>';
        $date = $booking_data['date'] ?? '';
        $start = $booking_data['start_time'] ?? '';
        $end = $booking_data['end_time'] ?? '';
        $location_title = $package_title ?: $space_title;
        if ($date && $start && $end) {
            $breakdown_html .= '<br><small>' . htmlspecialchars($location_title) . ' | ' . $date . ' ' . $start . '–' . $end . '</small>';
        }
        $breakdown_raw = $booking_data['breakdown'] ?? [];
        if (!empty($breakdown_raw) && is_array($breakdown_raw)) {
            $breakdown_html .= '<h4 style=\"margin: 15px 0 10px 0; font-size: 1.1em;\">Price Breakdown</h4>';
            $breakdown_html .= '<ul style=\"margin: 10px 0; padding-left: 20px;\">';
            foreach ($breakdown_raw as $item) {
                $label = htmlspecialchars($item['label'] ?? 'Item');
                $amount = number_format((float) ($item['amount'] ?? 0), 2);
                $symbol = get_woocommerce_currency_symbol();
                $breakdown_html .= '<li style=\"margin-bottom: 5px;\"><strong>' . $label . '</strong>: ' . $symbol . $amount . '</li>';
            }
            $breakdown_html .= '</ul>';
        }
        // Add detailed extras breakdown with prices
        $extras = $booking_data['extras'] ?? [];
        if (!empty($extras)) {
            $breakdown_html .= '<h4 style=\"margin: 15px 0 10px 0; font-size: 1.1em;\">Included Extras</h4>';
            $breakdown_html .= '<ul style=\"margin: 10px 0; padding-left: 20px;\">';
            foreach ($extras as $extra) {
                $extra_id = (int) $extra['extra_id'];
                $qty = (int) ($extra['quantity'] ?? 1);
                $extra_title = get_the_title($extra_id);
                $extra_price = (float) get_post_meta($extra_id, '_sb_extra_price', true);
                $line_total = $extra_price * $qty;
                $symbol = get_woocommerce_currency_symbol();
                $breakdown_html .= '<li style=\"margin-bottom: 5px;\"><strong>' . htmlspecialchars($extra_title) . '</strong> × ' . $qty . ': ' . $symbol . number_format($line_total, 2) . '</li>';
            }
            $breakdown_html .= '</ul>';
        }
        $breakdown_html .= '<div style=\"margin-top: 15px; padding-top: 10px; border-top: 2px solid #eee; font-weight: bold; font-size: 1.2em;\">Total: <strong>' . get_woocommerce_currency_symbol() . number_format($total_price, 2) . '</strong></div>';
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
                'sb_space_id' => $space_id,
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
     * WC Checkout: Save booking meta to order line items (backup to order meta)
     */
    public static function save_booking_meta_to_order_line_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values['sb_booking_id'])) {
            $item->add_meta_data('sb_booking_id', $values['sb_booking_id']);
            error_log('SpaceBooking WC: Saved sb_booking_id=' . $values['sb_booking_id'] . ' to line item');
        }
    }

    /**
     * Hook registration helper
     */
    public static function register_hooks()
    {
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'save_booking_meta_to_order_line_item'], 10, 4);
    }
}