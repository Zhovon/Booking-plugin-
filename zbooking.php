<?php
/**
 * Plugin Name: Zbooking
 * Plugin URI:  https://zhovon.com
 * Update URI:  https://zhovon.com/zbooking
 * Description: Custom booking system integrated with WooCommerce. Supports multi-service selection, coupon codes, admin confirm/reject via email, and WooCommerce customer accounts.
 * Version:     3.1
 * Author:      zhovon
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ZB_VERSION', '3.1' );
define( 'ZB_PATH',    plugin_dir_path( __FILE__ ) );
define( 'ZB_URL',     plugin_dir_url( __FILE__ ) );

require_once ZB_PATH . 'includes/db-table.php';
require_once ZB_PATH . 'includes/settings.php';
require_once ZB_PATH . 'includes/calendar-sync.php';
require_once ZB_PATH . 'includes/registration.php';
require_once ZB_PATH . 'includes/booking-form.php';
require_once ZB_PATH . 'includes/form-handler.php';
require_once ZB_PATH . 'includes/admin-page.php';
require_once ZB_PATH . 'includes/user-dashboard.php';

register_activation_hook( __FILE__, 'zb_activate' );
function zb_activate() {
    zb_create_booking_table();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'wp_enqueue_scripts', 'zb_enqueue_assets' );
function zb_enqueue_assets() {
    wp_enqueue_style(
        'zb-style',
        ZB_URL . 'assets/style.css',
        [],
        ZB_VERSION
    );

    global $post;
    if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'zbooking' ) ) {
        wp_enqueue_style(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            [],
            '4.6.13'
        );
        wp_enqueue_script(
            'flatpickr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
            [],
            '4.6.13',
            true
        );
        wp_enqueue_script(
            'flatpickr-da',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/da.js',
            [ 'flatpickr' ],
            '4.6.13',
            true
        );
    }
}

add_action( 'wp', 'zb_remove_wc_cart_buttons' );
function zb_remove_wc_cart_buttons() {
    if ( is_admin() ) {
        return;
    }
    remove_action( 'woocommerce_after_shop_loop_item',   'woocommerce_template_loop_add_to_cart', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

    add_action( 'woocommerce_single_product_summary', 'zb_booking_button_single', 30 );
}

add_filter( 'woocommerce_loop_add_to_cart_link', 'zb_replace_loop_add_to_cart_link', 20, 3 );

function zb_get_product_booking_target_url( $product_id ) {
    $booking_url = zb_get_booking_url( [ 'p_id' => absint( $product_id ) ] );

    if ( is_user_logged_in() ) {
        return $booking_url;
    }

    return zb_get_login_url( [ 'redirect_to' => $booking_url ] );
}

function zb_replace_loop_add_to_cart_link( $html, $product, $args ) {
    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return $html;
    }

    $target = esc_url( zb_get_product_booking_target_url( $product->get_id() ) );

    $raw_classes = '';
    if ( preg_match( '/class=("|\')(.*?)(\1)/i', (string) $html, $m ) ) {
        $raw_classes = (string) $m[2];
    } elseif ( ! empty( $args['class'] ) ) {
        $raw_classes = (string) $args['class'];
    }

    $classes = preg_split( '/\s+/', trim( $raw_classes ) );
    if ( ! is_array( $classes ) ) {
        $classes = [];
    }

    $remove = [
        'add_to_cart_button',
        'ajax_add_to_cart',
        'product_type_simple',
        'product_type_variable',
        'product_type_grouped',
        'product_type_external',
    ];

    $classes = array_values( array_filter( $classes, static function ( $c ) use ( $remove ) {
        return '' !== $c && ! in_array( $c, $remove, true );
    } ) );

    if ( ! in_array( 'button', $classes, true ) ) {
        $classes[] = 'button';
    }
    $classes[] = 'zb-booking-btn-loop';

    $safe_classes = implode(
        ' ',
        array_map(
            static function ( $c ) {
                return sanitize_html_class( $c );
            },
            $classes
        )
    );

    return sprintf(
        '<a href="%s" class="%s" data-zb-booking="1" rel="nofollow">%s</a>',
        $target,
        esc_attr( $safe_classes ),
        esc_html__( 'Book nu', 'zbooking' )
    );
}

function zb_booking_button_single() {
    global $product;
    if ( ! $product ) return;
    printf(
        '<a href="%s" class="button alt zb-booking-single-btn" data-zb-booking="1" rel="nofollow">Book nu</a>',
        esc_url( zb_get_product_booking_target_url( $product->get_id() ) )
    );
}

add_action( 'wp_head', 'zb_force_hide_single_add_to_cart', 99 );

function zb_force_hide_single_add_to_cart() {
    if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }
    echo '<style>.single-product form.cart{display:none !important;}.single-product .zb-booking-single-btn{display:inline-flex;align-items:center;justify-content:center;margin-top:0;}</style>';
}

add_action( 'wp_footer', 'zb_booking_cta_click_guard', 99 );

function zb_booking_cta_click_guard() {
    if ( is_admin() ) {
        return;
    }
    ?>
    <script>
    (function () {
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-zb-booking="1"]');
            if (!link) return;
            var href = link.getAttribute('href');
            if (!href) return;
            e.preventDefault();
            window.location.href = href;
        }, true);
    })();
    </script>
    <?php
}

add_action( 'template_redirect', 'zb_force_nocache_on_booking_pages', 1 );

add_action( 'template_redirect', 'zb_redirect_cart_like_booking_links', 2 );

add_filter( 'wp_nav_menu_objects', 'zb_swap_login_menu_item_for_logout', 20, 2 );

function zb_swap_login_menu_item_for_logout( $items, $args ) {
    if ( ! is_user_logged_in() ) {
        return $items;
    }

    $login_slug = trim( (string) zb_get_setting( 'login_slug' ), '/' );
    if ( '' === $login_slug ) {
        return $items;
    }

    foreach ( $items as $item ) {
        if ( empty( $item->url ) ) {
            continue;
        }

        $path = wp_parse_url( $item->url, PHP_URL_PATH );
        if ( ! is_string( $path ) ) {
            continue;
        }

        $item_slug = trim( basename( untrailingslashit( $path ) ), '/' );
        if ( $item_slug === $login_slug ) {
            $item->url   = zb_get_login_logout_url();
            $item->title = 'Log ud';
        }
    }

    return $items;
}

function zb_redirect_cart_like_booking_links() {
    if ( is_admin() || empty( $_GET['p_id'] ) ) {
        return;
    }

    if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) || ! function_exists( 'is_account_page' ) ) {
        return;
    }

    if ( is_cart() || is_checkout() || is_account_page() ) {
        $target = zb_get_product_booking_target_url( absint( $_GET['p_id'] ) );
        wp_safe_redirect( $target );
        exit;
    }
}

function zb_force_nocache_on_booking_pages() {
    global $post;
    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $protected_shortcodes = [ 'zbooking', 'zb_auth', 'zb_dashboard' ];

    foreach ( $protected_shortcodes as $sc ) {
        if ( has_shortcode( $post->post_content, $sc ) ) {
            nocache_headers();
            header( 'Surrogate-Control: no-store' );
            header( 'Vary: Cookie' );
            if ( ! defined( 'DONOTCACHEPAGE' ) )   { define( 'DONOTCACHEPAGE',   true ); }
            if ( ! defined( 'DONOTCACHEDB' ) )     { define( 'DONOTCACHEDB',     true ); }
            if ( ! defined( 'DONOTMINIFY' ) )      { define( 'DONOTMINIFY',      true ); }
            if ( ! defined( 'DONOTCACHEOBJECT' ) ) { define( 'DONOTCACHEOBJECT', true ); }
            return;
        }
    }

    if ( function_exists( 'is_account_page' ) && is_account_page() ) {
        nocache_headers();
        if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
    }
}

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

// Secure Invoice Routing
add_action( 'init', function() {
    if ( ! isset( $_GET['zb_invoice'] ) ) return;
    
    $booking_id = absint( $_GET['zb_invoice'] );
    $provided_token = sanitize_text_field( $_GET['token'] ?? '' );
    global $wpdb;
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}zb_bookings WHERE id = %d", $booking_id ) );

    if ( ! $booking ) wp_die('Booking ikke fundet.');
    
    $has_token_access = function_exists( 'zb_validate_booking_invoice_token' ) && zb_validate_booking_invoice_token( $booking_id, $booking->email, $provided_token );

    // Authorization: Admin, the owner, or a signed email token can view.
    if ( ! current_user_can( 'manage_options' ) && absint( $booking->user_id ) !== get_current_user_id() && ! $has_token_access ) {
        wp_die('Ingen adgang til denne faktura.');
    }

    require_once ZB_PATH . 'includes/invoice-template.php';
    zb_render_invoice( $booking );
    exit;
} );

// Hide default WooCommerce buttons to focus on Zbooking.
