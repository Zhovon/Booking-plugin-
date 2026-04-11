<?php
define('ABSPATH', './');
define('ZB_PATH', './');
define('ARRAY_A', 'ARRAY_A');
define('EP_ROOT', 1);
define('EP_PAGES', 2);

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function get_bloginfo() { return 'Homefoto'; }
function site_url($p) { return $p; }
function admin_url($p) { return $p; }
function wp_safe_redirect() {}
function wp_unslash($v) { return $v; }
function sanitize_text_field($v) { return $v; }
function sanitize_email($v) { return $v; }
function sanitize_textarea_field($v) { return $v; }
function wp_verify_nonce() { return true; }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 1; }
function wp_get_current_user() { return (object)['roles'=>['customer']]; }

class WP_User { function __construct($id){} function set_role($r){} }

$wpdb = new stdClass();
$wpdb->prefix = 'wp_';

// Try loading files in order
try {
    require_once 'includes/db-table.php';
    require_once 'includes/registration.php';
    require_once 'includes/booking-form.php';
    require_once 'includes/form-handler.php';
    require_once 'includes/admin-page.php';
    require_once 'includes/user-dashboard.php';
    echo "SUCCESS: No syntax or redeclaration errors detected.";
} catch (Throwable $e) {
    echo "FATAL ERROR DETECTED: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
}
