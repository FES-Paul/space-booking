<?php

/**
 * Admin Bookings Page Template
 * Tabs: Calendar Grid | List View
 */
defined('ABSPATH') || exit;

// ── Filters Form ─────────────────────────────────────────────────────────
?>
<style>
.sb-admin-bookings {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto;
}

.sb-tabs {
    display: flex;
    border-bottom: 1px solid #c3c4c7;
    margin: 0 0 20px;
}

.sb-tab-btn {
    background: none;
    border: none;
    padding: 12px 24px;
    cursor: pointer;
    font-size: 14px;
    color: #50575e;
    border-bottom: 2px solid transparent;
}

.sb-tab-btn.active {
    color: #1d2327;
    border-bottom-color: #2271b1;
}

.sb-tab-content {
    display: none;
}

.sb-tab-content.active {
    display: block;
}

.sb-calendar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}

.sb-day-cell {
    border: 1px solid #e0e3e6;
    border-radius: 6px;
    padding: 12px;
    background: #fff;
}

.sb-day-header {
    font-weight: 600;
    margin: 0 0 8px;
    color: #1d2327;
}

.sb-booking {
    background: #f6f7f7;
    border-radius: 4px;
    padding: 6px 8px;
    margin: 4px 0;
    font-size: 13px;
}

.sb-status {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.sb-status--confirmed {
    background: #d4edda;
    color: #155724;
}

.sb-status--pending {
    background: #fff3cd;
    color: #856404;
}

.sb-month-section {
    margin-bottom: 40px;
}

.sb-month-section h3 {
    margin: 0 0 20px;
    color: #1d2327;
    border-bottom: 2px solid #2271b1;
    padding-bottom: 8px;
}

.sb-filters {
    background: #fff;
    border: 1px solid #c3c4c7;
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 24px;
}

.sb-filters select,
.sb-filters input {
    margin-right: 12px;
    padding: 6px 10px;
    border: 1px solid #8c8f94;
    border-radius: 3px;
}

@media (max-width: 782px) {
    .sb-calendar-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<div class="sb-admin-bookings">
    <h1><?php esc_html_e('All Bookings', 'space-booking'); ?></h1>

    <form method="get" class="sb-filters">
        <input type="hidden" name="page" value="space-booking-bookings">
        <select name="status">
            <option value=""><?php esc_html_e('All Status', 'space-booking'); ?></option>
            <option value="confirmed" <?php selected($_GET['status'] ?? '', 'confirmed'); ?>>Confirmed</option>
            <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>>Pending</option>
        </select>
        <select name="space_id">
            <option value=""><?php esc_html_e('All Spaces', 'space-booking'); ?></option>
            <?php
            $spaces = get_posts(['post_type' => 'sb_space', 'posts_per_page' => -1, 'post_status' => 'publish']);
            foreach ($spaces as $space) {
                echo '<option value="' . $space->ID . '"' . selected($_GET['space_id'] ?? '', $space->ID, false) . '>' . esc_html($space->post_title) . '</option>';
            }
            ?>
        </select>
        <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>"
            placeholder="From">
        <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" placeholder="To">
        <button type="submit" class="button"><?php esc_html_e('Filter', 'space-booking'); ?></button>
        <a href="<?php echo esc_url(remove_query_arg(['status', 'space_id', 'date_from', 'date_to'])); ?>"
            class="button button-secondary"><?php esc_html_e('Clear Filters', 'space-booking'); ?></a>
    </form>

    <div class="sb-calendar-grouped">

        <?php
        global $wpdb;
        $where = ['1=1'];
        $params = [];
        $status_input = sanitize_text_field($_GET['status'] ?? '');
        if ($status_input) {
            $where[] = 'b.status = %s';
            $params[] = $status_input;
        }
        $space_id_input = $_GET['space_id'] ?? '';
        $space_id = absint($space_id_input);
        if ($space_id) {
            $where[] = 'b.space_id = %d';
            $params[] = $space_id;
        }
        $date_from_input = sanitize_text_field($_GET['date_from'] ?? '');
        if ($date_from_input) {
            $date_from = date('Y-m-d', strtotime($date_from_input));
            if ($date_from) {
                $where[] = 'b.booking_date >= %s';
                $params[] = $date_from;
            }
        }
        $date_to_input = sanitize_text_field($_GET['date_to'] ?? '');
        if ($date_to_input) {
            $date_to = date('Y-m-d', strtotime($date_to_input));
            if ($date_to) {
                $where[] = 'b.booking_date <= %s';
                $params[] = $date_to;
            }
        }
        if (empty($date_from_input)) {
            $where[] = 'b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 36 MONTH)';
        }
        if (empty($date_to_input)) {
            $where[] = 'b.booking_date <= DATE_ADD(CURDATE(), INTERVAL 36 MONTH)';
        }
        if ($status_input && $status_input !== 'pending') {
            $where[] = '(b.status != "pending" OR b.expired_at > NOW())';
        }
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, p.post_title AS space_name FROM {$wpdb->prefix}sb_bookings b 
             LEFT JOIN {$wpdb->posts} p ON p.ID = b.space_id 
             WHERE " . implode(' AND ', $where) . ' 
             ORDER BY b.booking_date, b.start_time',
            ...$params
        ), ARRAY_A) ?: [];

        $grouped = [];
        foreach ($bookings as $b) {
            $date = new DateTime($b['booking_date']);
            $month_key = $date->format('Y-m');
            $month_name = $date->format('F Y');
            $day = $date->format('j');
            $grouped[$month_key]['name'] = $month_name;
            $grouped[$month_key]['days'][$b['booking_date']]['day'] = $date->format('M j');
            $grouped[$month_key]['days'][$b['booking_date']]['bookings'][] = $b;
        }

        if (empty($grouped)) {
            echo '<p>' . esc_html__('No bookings match your filters.', 'space-booking') . '</p>';
        } else {
            foreach ($grouped as $month_data) {
                echo '<section class="sb-month-section">';
                echo '<h3>' . esc_html($month_data['name']) . '</h3>';
                echo '<div class="sb-calendar-grid">';
                $days_in_month = array_keys($month_data['days']);
                sort($days_in_month);
                foreach ($days_in_month as $date) {
                    $day_data = $month_data['days'][$date];
                    echo '<div class="sb-day-cell">';
                    echo '<div class="sb-day-header">' . esc_html($day_data['day']) . '</div>';
                    if (!empty($day_data['bookings'])) {
                        foreach ($day_data['bookings'] as $b) {
                            $time = substr($b['start_time'], 0, 5) . '-' . substr($b['end_time'], 0, 5);
                            echo '<div class="sb-booking">';
                            echo esc_html($time . ' - ' . $b['customer_name']);
                            echo ' <span class="sb-status sb-status--' . esc_attr($b['status']) . '">' . esc_html(ucfirst($b['status'])) . '</span>';
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                }
                echo '</div>';
                echo '</section>';
            }
        }
        ?>
    </div>
</div>



<?php
?>