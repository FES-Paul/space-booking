<?php declare(strict_types=1);

namespace SpaceBooking\Admin;

/**
 * Communication settings tab with WYSIWYG email template editor.
 */
final class CommunicationTab
{
    public static function init()
    {
        add_action('admin_init', [self::class, 'register']);
    }

    public static function register()
    {
        register_setting(
            'space_booking_settings',
            'sb_confirmation_email_template',
            [
                'sanitize_callback' => 'wp_kses_post',
                'default' => '<p>Hi [customer_name],</p><p>Your booking #[order_id] for <strong>[space_name]</strong> on [booking_details] is confirmed.</p><p>Total: <strong>[total_price]</strong></p>[price_breakdown]<p><strong>Access Instructions:</strong><br>[access_instructions]</p><p>Thank you!</p>'
            ]
        );

        add_settings_section(
            'sb_communication_section',
            'Custom Confirmation Email',
            null,
            'space-booking-communication'
        );

        add_settings_field(
            'sb_confirmation_email_template',
            'Email Body Template',
            [self::class, 'template_editor'],
            'space-booking-communication',
            'sb_communication_section'
        );
    }

    public static function template_editor()
    {
        $content = get_option('sb_confirmation_email_template');
        wp_editor($content, 'sb_confirmation_email_template', [
            'textarea_name' => 'sb_confirmation_email_template',
            'media_buttons' => false,
            'textarea_rows' => 15,
            'teeny' => true,
        ]);
        echo '<p class="description">Customize email body. Shortcodes are automatically replaced.</p>';
        self::shortcode_cheatsheet();
    }

    private static function shortcode_cheatsheet()
    {
        ?>
<div style="margin-top:20px;padding:20px;background:#f9f9f9;border-radius:5px;">
    <h4>Shortcode Cheat Sheet</h4>
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <th>Shortcode</th>
            <th>Replaces With</th>
        </tr>
        <tr>
            <td>[customer_name]</td>
            <td>Customer name</td>
        </tr>
        <tr>
            <td>[booking_date]</td>
            <td>Date</td>
        </tr>
        <tr>
            <td>[space_name]</td>
            <td>Space title</td>
        </tr>
        <tr>
            <td>[access_instructions]</td>
            <td>Venue instructions</td>
        </tr>
        <tr>
            <td>[total_price]</td>
            <td>Total amount</td>
        </tr>
        <tr>
            <td>[order_id]</td>
            <td>Order number</td>
        </tr>
    </table>
</div>
<?php
    }
}