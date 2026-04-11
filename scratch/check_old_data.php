<?php
require_once('wp-load.php');
global $wpdb;
$tables = $wpdb->get_col("SHOW TABLES LIKE '%osf_bookings%'");
if (!empty($tables)) {
    echo "OLD_TABLE_FOUND:" . $tables[0] . "\n";
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables[0]}");
    echo "OLD_BOOKINGS_COUNT:" . $count . "\n";
} else {
    echo "NO_OLD_TABLE_FOUND\n";
}
