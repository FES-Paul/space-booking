<?php declare(strict_types=1);

namespace SpaceBooking\Admin;

/**
 * Communication settings tab with WYSIWYG email template editor.
 */
final class SettingsCommunicationTab
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'settings_init']);
    }

    public function settings_init(): void
    {
        $this->register_setting();
        $this->settings_page();
    }

    public function add_submenu(): void
    {
        add_submenu_page(
            'space-booking-settings',
            __('Communication', 'space-booking'),
            __('Communication', 'space-booking'),
            'manage_options',
            'space-booking-communication',
            [$this, 'render_page']
        );
    }

    public function register_setting(): void
    {
        register_setting(
            'space_booking_settings',
            'sb_confirmation_email_template',
            [
                'sanitize_callback' => 'wp_kses_post',
                'default' => ''
            ]
        );
    }

    public function settings_page(): void
    {
        add_settings_section(
            'sb_communication_section',
            __('Custom Confirmation Email', 'space-booking'),
            null,
            'space-booking-communication'
        );

        add_settings_field(
            'sb_confirmation_email_template',
            __('Email Body Template', 'space-booking'),
            [$this, 'template_editor'],
            'space-booking-communication',
            'sb_communication_section'
        );
    }

    public function render_page(): void
    {
        ?>
<div class="wrap">
    <h1><?php esc_html_e('Communication Settings', 'space-booking'); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('space_booking_settings');
        do_settings_sections('space-booking-communication');
        submit_button();
        ?>
    </form>
    <?php $this->shortcode_cheatsheet(); ?>
</div>
<?php
    }

    public function template_editor(): void
    {
        $content = get_option('sb_confirmation_email_template', '');
        wp_editor($content, 'sb_confirmation_email_template', [
            'textarea_name' => 'sb_confirmation_email_template',
            'media_buttons' => false,
            'textarea_rows' => 15,
            'teeny' => true,
        ]);
        echo '<p class="description">' . __('Use the editor above to customize the email body. Available shortcodes will be replaced automatically.', 'space-booking') . '</p>';
    }

    private function shortcode_cheatsheet(): void
    {
?>
<div class="card" style="margin-top:40px;">
    <h2><?php esc_html_e('📝 Shortcode Cheat Sheet', 'space-booking'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Shortcode</th>
                <th>Description</th>
                <th>Example Output</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[customer_name]</code></td>
                <td>Customer's name</td>
                <td>John Doe</td>
            </tr>
            <tr>
                <td><code>[space_name]</code></td>
                <td>Space name</td>
                <td>Conference Room A</td>
            </tr>
            <tr>
                <td><code>[booking_details]</code></td>
                <td>Booking summary table</td>
                <td>Date, Time, Duration table</td>
            </tr>
            <tr>
                <td><code>[price_breakdown]</code></td>
                <td>Price table from WC</td>
                <td>Item | Amount table w/ Total</td>
            </tr>
            <tr>
                <td><code>[access_instructions]</code></td>
                <td>Venue access info</td>
                <td>Gate code: 1234</td>
            </tr>
            <tr>
                <td><code>[total_price]</code></td>
                <td>Total amount</td>
                <td>$250.00</td>
            </tr>
            <tr>
                <td><code>[order_id]</code></td>
                <td>WC Order ID</td>
                <td>#153</td>
            </tr>
            <tr>
                <td><code>[site_name]</code></td>
                <td>Site name</td>
                <td>Kukoolala</td>
            </tr>
        </tbody>
    </table>
    <p><em><?php _e("Leave shortcodes in the editor - they'll be replaced when sending.", 'space-booking'); ?></em></p>
</div>
<?php
    }
}