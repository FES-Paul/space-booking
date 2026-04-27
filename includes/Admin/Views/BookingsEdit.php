<?php declare(strict_types=1);

namespace SpaceBooking\Admin\Views;

use SpaceBooking\Services\BookingRepository;

final class BookingsEdit
{
    public function __construct(
        private BookingRepository $repo
    ) {}

    public function render(int $booking_id): void
    {
        $booking = $this->repo->findEnriched($booking_id);
        if (!$booking) {
            wp_die('Booking not found.');
        }

        $extras = $this->repo->get_extras($booking_id);
        $statuses = ['pending' => 'Pending', 'in_review' => 'In Review', 'confirmed' => 'Confirmed'];
        $status_color = [
            'pending' => '#fff3cd',
            'in_review' => '#cce5ff',
            'confirmed' => '#d4edda'
        ];
?>
<div class="sb-booking-edit">
    <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
        <a href="<?php echo esc_url(remove_query_arg('edit')); ?>" class="btn btn-secondary">← Back to Bookings</a>
        <h1>Edit Booking #<?php echo esc_html($booking['id']); ?></h1>
    </div>

    <div class="sb-section">
        <h3>📅 Booking Details</h3>
        <div class="sb-info-grid">
            <div><strong>Space:</strong> <?php echo esc_html($booking['space_title']); ?></div>
            <div><strong>Date:</strong> <?php echo esc_html($booking['booking_date']); ?></div>
            <div><strong>Time:</strong>
                <?php echo esc_html(substr($booking['start_time'], 0, 5) . ' - ' . substr($booking['end_time'], 0, 5)); ?>
            </div>
            <div><strong>Duration:</strong> <?php echo esc_html($booking['duration_hours']); ?>h</div>
            <div><strong>Customer:</strong> <?php echo esc_html($booking['customer_name']); ?></div>
            <div><strong>Email:</strong> <?php echo esc_html($booking['customer_email']); ?></div>
            <?php if ($booking['customer_phone']): ?><div><strong>Phone:</strong>
                <?php echo esc_html($booking['customer_phone']); ?></div><?php endif; ?>
            <?php if ($booking['notes']): ?><div><strong>Notes:</strong> <?php echo esc_html($booking['notes']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sb-section">
        <h3>💰 Pricing</h3>
        <div class="sb-price-breakdown">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span>Base: $<?php echo number_format($booking['base_price'], 2); ?></span>
            </div>
            <?php if ($booking['extras_price'] > 0): ?>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span>Extras: $<?php echo number_format($booking['extras_price'], 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($booking['modifier_price'] != 0): ?>
            <div style="display:flex; justify-content:space-between;">
                <span>Modifiers: $<?php echo number_format($booking['modifier_price'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div style="border-top:2px solid #2271b1; padding-top:12px; font-weight:bold; font-size:18px;">
                <span>Total: $<?php echo number_format($booking['total_price'], 2); ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($extras)): ?>
    <div class="sb-section">
        <h3>➕ Extras (<?php echo count($extras); ?>)</h3>
        <ul class="sb-extras-list">
            <?php foreach ($extras as $extra): ?>
            <li class="sb-extra-item">
                <span><?php echo esc_html($extra['extra_name']); ?> ×<?php echo $extra['quantity']; ?></span>
                <span>$<?php echo number_format($extra['quantity'] * $extra['unit_price'], 2); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="sb-section">
        <h3>⚙️ Update Status</h3>
        <form id="sb-edit-form" class="sb-edit-form">
            <div class="sb-form-row">
                <div class="sb-form-group">
                    <label for="status">Status <span style="color:#d63638;">*</span></label>
                    <select id="status" name="status" required>
                        <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($booking['status'], $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="sb-status-preview"
                        style="background:<?php echo $status_color[$booking['status']] ?? '#eee'; ?>;">
                        Current: <span class="sb-status-badge"
                            style="background:<?php echo $status_color[$booking['status']] ?? '#ccc'; ?>; color:#155724;"><?php echo esc_html(ucfirst($booking['status'])); ?></span>
                    </div>
                </div>
                <div class="sb-form-group">
                    <label for="feedback">Admin Feedback (optional)</label>
                    <textarea id="feedback" name="feedback" rows="3"
                        placeholder="Add notes about this status change..."><?php echo esc_textarea($booking['admin_feedback'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="sb-actions">
                <button type="submit" class="btn btn-primary">💾 Update Booking</button>
                <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const form = $('#sb-edit-form');
    const preview = $('#sb-status-preview');
    const statuses = {
        'pending': '#fff3cd',
        'in_review': '#cce5ff',
        'confirmed': '#d4edda'
    };

    // Live status preview
    $('#status').on('change', function() {
        const status = $(this).val();
        const badge = $('.sb-status-badge', preview);
        preview.css('background', statuses[status]);
        badge.text(status.charAt(0).toUpperCase() + status.slice(1))
            .css('background', statuses[status])
            .css('color', '#155724');
    });

    // Form submit
    form.on('submit', function(e) {
        e.preventDefault();
        const btn = form.find('.btn-primary').prop('disabled', true).text('Saving...');
        form.addClass('loading');

        $.post(ajaxurl, {
            action: 'sb_update_booking_status',
            booking_id: <?php echo $booking_id; ?>,
            status: $('#status').val(),
            feedback: $('#feedback').val(),
            _wpnonce: '<?php echo wp_create_nonce('sb_update_booking'); ?>'
        }, function(res) {
            if (res.success) {
                showToast('✅ Booking updated successfully!', 'success');
                // Update preview to match saved status
                $('#status').val(res.data.status).trigger('change');
                $('#feedback').val(res.data.feedback || '');
            } else {
                showToast('❌ ' + (res.data || 'Update failed'), 'error');
            }
            btn.prop('disabled', false).text('💾 Update Booking');
            form.removeClass('loading');
        }).fail(function() {
            showToast('❌ Network error. Please try again.', 'error');
            btn.prop('disabled', false).text('💾 Update Booking');
            form.removeClass('loading');
        });
    });

    function showToast(msg, type = '') {
        const toast = $(`<div class="toast ${type}">${msg}</div>`).appendTo('body');
        toast.css('transform', 'translateX(0)');
        setTimeout(() => toast.remove(), 4000);
    }
});
</script>
<?php
    }
}
?>