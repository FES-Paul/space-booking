<?php declare(strict_types=1);

namespace SpaceBooking\Services;

use SpaceBooking\Services\BookingRepository;
use SpaceBooking\Services\EmailService;

/**
 * Custom confirmation emails with shortcodes and admin template.
 */
final class ConfirmationEmailService extends EmailService
{
    private int $booking_id;
    private int $order_id;
    private array $booking;
    private array $extras;
    private array $price_breakdown;

    public function __construct(int $booking_id, int $order_id)
    {
        $this->booking_id = $booking_id;
        $this->order_id = $order_id;

        $repo = new BookingRepository();
        $this->booking = $repo->find($booking_id);
        $this->extras = $repo->get_extras($booking_id);

        // Get enriched breakdown from WC order
        $order = wc_get_order($order_id);
        $this->price_breakdown = $order ? $order->get_meta('_sb_price_breakdown_enriched', true) ?: [] : [];
    }

    public function send_custom_confirmation(): bool
    {
        if (!$this->booking || empty($this->booking['customer_email'])) {
            return false;
        }

        $template = get_option('sb_confirmation_email_template', '');
        if (empty($template)) {
            $template = $this->render_template('emails/booking-confirmation-admin-custom.php', $this->get_template_vars());
        } else {
            $template = $this->parse_shortcodes($template, $this->get_template_vars());
        }

        $subject = sprintf(
            __('Booking Confirmed #%d - %s on %s', 'space-booking'),
            $this->booking['id'],
            get_the_title((int) $this->booking['space_id']),
            $this->booking['booking_date']
        );

        $this->send($this->booking['customer_email'], $subject, $template);

        return true;  // wp_mail always returns true if no fatal error
    }

    private function get_template_vars(): array
    {
        $space_id = (int) $this->booking['space_id'];
        $access_instructions = get_post_meta($space_id, '_sb_access_instructions', true) ?: __('No access instructions available.', 'space-booking');

        return [
            'booking' => $this->booking,
            'extras' => $this->extras,
            'price_breakdown' => $this->get_price_breakdown_html(),
            'customer_name' => $this->booking['customer_name'],
            'space_name' => get_the_title($space_id),
            'access_instructions' => $access_instructions,
            'total_price' => CurrencyService::format((float) $this->booking['total_price']),
            'order_id' => $this->order_id,
            'site_name' => get_bloginfo('name'),
        ];
    }

    private function parse_shortcodes(string $content, array $vars): string
    {
        $shortcodes = [
            '[customer_name]' => esc_html($vars['customer_name']),
            '[space_name]' => esc_html($vars['space_name']),
            '[booking_details]' => $this->get_booking_details_html($vars['booking']),
            '[price_breakdown]' => $vars['price_breakdown'],
            '[access_instructions]' => nl2br(esc_html($vars['access_instructions'])),
            '[total_price]' => $vars['total_price'],
            '[order_id]' => esc_html($vars['order_id']),
            '[site_name]' => esc_html($vars['site_name']),
        ];

        return str_replace(array_keys($shortcodes), array_values($shortcodes), $content);
    }

    private function get_price_breakdown_html(): string
    {
        if (empty($this->price_breakdown)) {
            return '<p>' . __('Price details not available.', 'space-booking') . '</p>';
        }

        $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0;">';
        $html .= '<thead><tr><th style="text-align:left;padding:8px;border-bottom:2px solid #ddd;">Description</th><th style="text-align:right;padding:8px;border-bottom:2px solid #ddd;">Amount</th></tr></thead>';
        $html .= '<tbody>';
        $total = 0.0;
        foreach ($this->price_breakdown as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            $total += $amount;
            $html .= sprintf(
                '<tr><td style="padding:8px;">%s</td><td style="text-align:right;padding:8px;">%s</td></tr>',
                esc_html($item['label'] ?? 'Unknown'),
                CurrencyService::format($amount)
            );
        }
        $html .= sprintf(
            '<tr style="font-weight:bold;border-top:2px solid #2d6a4f;"><td>Total</td><td style="text-align:right;">%s</td></tr>',
            CurrencyService::format($total)
        );
        $html .= '</tbody></table>';

        return $html;
    }

    private function get_booking_details_html(array $booking): string
    {
        $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0;">';
        $html .= sprintf('<tr><td><strong>Space:</strong></td><td>%s</td></tr>', esc_html(get_the_title((int) $booking['space_id'])));
        $html .= sprintf('<tr><td><strong>Date:</strong></td><td>%s</td></tr>', esc_html($booking['booking_date']));
        $html .= sprintf('<tr><td><strong>Time:</strong></td><td>%s – %s</td></tr>', substr($booking['start_time'], 0, 5), substr($booking['end_time'], 0, 5));
        $html .= sprintf('<tr><td><strong>Duration:</strong></td><td>%sh</td></tr>', $booking['duration_hours']);
        if (!empty($booking['notes'])) {
            $html .= sprintf('<tr><td><strong>Notes:</strong></td><td>%s</td></tr>', esc_html($booking['notes']));
        }
        $html .= '</table>';
        return $html;
    }
}