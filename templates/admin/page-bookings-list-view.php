<?php
/** Modern List View for All Bookings - Responsive Table Layout */
defined('ABSPATH') || exit;

global $wpdb;

// Stats Query
$total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_bookings b WHERE b.booking_date >= CURDATE() - INTERVAL 30 DAY");
$pending = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_bookings b WHERE b.status = 'pending' AND b.booking_date >= CURDATE() - INTERVAL 30 DAY");
$confirmed = $total_bookings - $pending;  // Simplified

// Bookings Query (paginated)
$page = max(1, absint($_GET['paged'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];
if ($status = sanitize_text_field($_GET['status'] ?? '')) {
    $where[] = 'b.status = %s';
    $params[] = $status;
}
if ($space_id = absint($_GET['space_id'] ?? 0)) {
    $where[] = 'b.space_id = %d';
    $params[] = $space_id;
}
// Date range simplified
$params[] = $offset;
$params[] = $per_page;

$bookings = $wpdb->get_results($wpdb->prepare(
    "SELECT b.*, p.post_title AS space_name FROM {$wpdb->prefix}sb_bookings b 
\t LEFT JOIN {$wpdb->posts} p ON p.ID = b.space_id 
\t WHERE " . implode(' AND ', $where) . " 
\t ORDER BY b.booking_date DESC, b.start_time ASC
\t LIMIT %d OFFSET %d",
    ...$params
), ARRAY_A) ?: [];
?>
<div class="sb-admin-bookings">
    <h1>All Bookings</h1>

    <!-- Stats Bar -->
    <div class="sb-stats-bar">
        <div class="sb-stat">
            <span class="sb-stat-number"><?php echo $total_bookings; ?></span>
            <span>Total Bookings</span>
        </div>
        <div class="sb-stat sb-stat--pending"><?php echo $pending; ?> <span>Pending</span></div>
        <div class="sb-stat sb-stat--confirmed"><?php echo $confirmed; ?> <span>Confirmed</span></div>
    </div>

    <!-- Filters -->
    <form method="get" class="sb-filters">
        <input type="hidden" name="page" value="space-booking-bookings">
        <select name="status">
            <option value="">All Status</option>
            <option value="confirmed" <?php selected($_GET['status'] ?? '', 'confirmed'); ?>>Confirmed</option>
            <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>>Pending</option>
        </select>
        <select name="space_id">
            <option value="">All Spaces</option>
            <?php
            $spaces = get_posts(['post_type' => 'sb_space', 'posts_per_page' => -1]);
            foreach ($spaces as $space) {
                echo '<option value="' . $space->ID . '" ' . selected($_GET['space_id'] ?? '', $space->ID, false) . '>' . esc_html($space->post_title) . '</option>';
            }
            ?>
        </select>
        <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
        <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
        <button type="submit" class="button">Filter</button>
    </form>

    <!-- Table -->
    <?php if (empty($bookings)): ?>
    <p>No bookings found.</p>
    <?php else: ?>
    <table class="sb-bookings-table wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Customer</th>
                <th>Space</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr class="sb-booking-row">
                <td><input type="checkbox" class="row-select"></td>
                <td><?php echo esc_html($b['customer_name']); ?>
                    <br><small><?php echo esc_html($b['customer_email']); ?></small></td>
                <td><?php echo esc_html($b['space_name']); ?></td>
                <td><?php echo esc_html($b['booking_date']); ?></td>
                <td><?php echo esc_html(substr($b['start_time'], 0, 5) . '–' . substr($b['end_time'], 0, 5)); ?>
                </td>
                <td><span
                        class="sb-badge sb-badge--<?php echo esc_attr($b['status']); ?>"><?php echo ucfirst(esc_html($b['status'])); ?></span>
                </td>
                <td>$<?php echo number_format($b['total_amount'] / 100, 2); ?></td>
                <td class="sb-actions">
                    <button class="sb-btn sb-btn--ghost sb-btn-edit" data-id="<?php echo $b['id']; ?>">Edit</button>
                    <button class="sb-btn sb-btn--ghost sb-btn-approve"
                        data-id="<?php echo $b['id']; ?>">Approve</button>
                    <button class="sb-btn sb-btn--ghost sb-btn-cancel" data-id="<?php echo $b['id']; ?>">Cancel</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="tablenav">
        <!-- Pagination links here -->
    </div>
    <?php endif; ?>
</div>