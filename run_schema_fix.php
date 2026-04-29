<?php
/** Working DB Schema Fix - Space Booking Plugin */
$docroot = dirname(dirname(__DIR__));
chdir($docroot);
define('WP_USE_THEMES', true);
require_once ABSPATH . 'wp-load.php';
require_once 'wp-content/plugins/space-booking/includes/Installer.php';

echo "🔧 Space Booking DB Schema Fix\n";
echo 'Working directory: ' . getcwd() . "\n";
echo "================================\n\n";

global $wpdb;
$table = $wpdb->prefix . 'sb_bookings';

$columns_to_add = [
    'extras' => 'LONGTEXT NULL AFTER `notes`',
    'duration_hours' => 'DECIMAL(4,2) DEFAULT 0.00 AFTER `end_time`',
    'base_price' => 'DECIMAL(10,2) DEFAULT 0.00 AFTER `duration_hours`',
    'extras_price' => 'DECIMAL(10,2) DEFAULT 0.00 AFTER `base_price`',
    'modifier_price' => 'DECIMAL(10,2) DEFAULT 0.00 AFTER `extras_price`',
    'total_price' => 'DECIMAL(10,2) DEFAULT 0.00 AFTER `modifier_price`',
];

$fixed = 0;
$existing_columns = $wpdb->get_col("DESCRIBE {$table}", 0);

foreach ($columns_to_add as $col => $def) {
    if (!in_array($col, $existing_columns)) {
        $sql = "ALTER TABLE {$table} ADD COLUMN `{$col}` {$def}";
        $result = $wpdb->query($sql);
        if ($result !== false) {
            echo "✅ Added column: {$col}\n";
            $fixed++;
        } else {
            echo "❌ FAILED to add {$col}: " . $wpdb->last_error . "\n";
        }
    } else {
        echo "⏭️ Column {$col} already exists\n";
    }
}

if ($fixed > 0) {
    echo "\n🎉 FIXED {$fixed} columns! Bookings should now work.\n";
} else {
    echo "\nℹ️ No changes needed - schema appears correct.\n";
}

echo "\n📋 Current wp_sb_bookings schema:\n";
$schema = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
foreach ($schema as $row) {
    echo "  {$row['Field']}\n";
}

echo "\n✅ COMPLETE. Test booking creation now.\n";
?>