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
     * Create booking + extras as SEPARATE virtual products in WooCommerce cart.
     *
     * @param array $booking_data  From BookingController (space_id, date, etc.)
     * @param float $total_price   Total (for validation only)
     * @param int   $booking_id    DB booking ID
     * @return string WC checkout URL
     */
    public function add_booking_to_cart(array $booking_data, float $total_price, int $booking_id): string
    {
        if (!function_exists('WC') || !WC()) {
            throw new \RuntimeException('WooCommerce not initialized.');
        }

        if (WC()->cart === null) {
            throw new \RuntimeException('WooCommerce cart not available.');
        }

        $space_id = $booking_data['space_id'];
        $space_title = get_the_title($space_id);
        $package_id = $booking_data['package_id'] ?? null;
        $package_title = $package_id ? get_the_title($package_id) : null;
        $date = $booking_data['date'] ?? '';
        $start = $booking_data['start_time'] ?? '';
        $end = $booking_data['end_time'] ?? '';
        $extras = $booking_data['extras'] ?? [];
        $customer_name = $booking_data['customer_name'] ?? '';
        $customer_email = $booking_data['customer_email'] ?? '';

        error_log('SpaceBooking WC: Creating MULTIPLE products for booking #' . $booking_id . ' (' . count($extras) . ' extras)');

        // Helper to create consistent virtual product
        $create_product = function ($name, $price) use ($booking_id) {
            $product = new WC_Product_Simple();
            $product->set_name($name);
            $product->set_regular_price($price);
            $product->set_status('publish');
            $product->set_catalog_visibility('hidden');
            $product->set_virtual(true);
            $product->set_manage_stock(true);
            $product->set_stock_quantity(1);
            $product->set_stock_status('instock');
            $product->set_sold_individually(true);
            $product->save();
            error_log('SpaceBooking WC: Created product ID ' . $product->get_id() . ' "' . $name . '" price $' . $price);
            return $product;
        };

        // 1. MAIN BOOKING PRODUCT (base price only, assumes breakdown[0] or calc from total-extras)
        // For simplicity, use passed total_price for main (extras separate) - adjust if base needed
        // Calculate base_price: total minus sum of extras
        $base_price = $total_price;
        foreach ($extras as $extra_data) {
            $extra_id = (int) $extra_data['extra_id'];
            $quantity = max(1, (int) ($extra_data['quantity'] ?? 1));
            $extra_price = (float) get_post_meta($extra_id, '_sb_extra_price', true);
            $base_price -= $extra_price * $quantity;
        }
        $base_price = max(0, $base_price);  // Ensure non-negative

        $main_product = $create_product(
            sprintf('Booking #%d - %s', $booking_id, $package_title ?: $space_title),
            $base_price
        );

        // Simple summary description for main product
        $summary_html = '<strong>Space Booking Service #' . $booking_id . '</strong>';
        if ($date && $start && $end) {
            $summary_html .= '<br><small>' . htmlspecialchars($package_title ?: $space_title) . ' | ' . $date . ' ' . $start . '–' . $end . '</small>';
        }
        $summary_html .= '<br><small>Customer: ' . htmlspecialchars($customer_name) . ' <' . htmlspecialchars($customer_email) . '></small>';
        $main_product->set_description($summary_html);
        $main_product->save();

        // Clear cart
        WC()->cart->empty_cart();

        // Common meta for ALL items
        $common_meta = [
            'sb_booking_id' => $booking_id,
            'sb_space_id' => $space_id,
            'sb_date' => $booking_data['date'],
            'sb_start_time' => $booking_data['start_time'],
            'sb_end_time' => $booking_data['end_time'],
            'sb_customer_name' => $customer_name,
            'sb_customer_email' => $customer_email,
            'sb_extras' => wp_json_encode($extras),
            'sb_breakdown' => wp_json_encode($booking_data['breakdown'] ?? []),
        ];

        // 2. Add MAIN product to cart
        wc_clear_notices();
        $main_key = WC()->cart->add_to_cart($main_product->get_id(), 1, 0, [], $common_meta);
        if (!$main_key) {
            throw new \RuntimeException('Failed to add main booking product to cart.');
        }
        error_log('SpaceBooking WC: Main product added, key: ' . $main_key);

        // 3. Add EXTRA products (separate line items)
        foreach ($extras as $extra_data) {
            $extra_id = (int) $extra_data['extra_id'];
            $quantity = max(1, (int) ($extra_data['quantity'] ?? 1));
            $extra_title = get_the_title($extra_id);
            $extra_price = (float) get_post_meta($extra_id, '_sb_extra_price', true);
            $extra_total = $extra_price * $quantity;

            $extra_product = $create_product(
                sprintf('%s (x%d)', $extra_title, $quantity),
                $extra_total
            );
            $extra_product->set_description('Booking extra #' . $booking_id);
            $extra_product->save();

            $extra_key = WC()->cart->add_to_cart($extra_product->get_id(), 1, 0, [], $common_meta);
            if (!$extra_key) {
                error_log('SpaceBooking WC: Warning - failed to add extra ' . $extra_id . ', continuing...');
            } else {
                error_log('SpaceBooking WC: Extra "' . $extra_title . '" added, key: ' . $extra_key);
            }
        }

        error_log('SpaceBooking WC: Booking #' . $booking_id . ' fully added (' . (1 + count($extras)) . ' items)');

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
