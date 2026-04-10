<?php
/**
 * Plugin Name: Zbooking
 * Plugin URI:  https://zhovon.com
 * Description: Custom booking system integrated with WooCommerce. Supports multi-service selection, coupon codes, admin confirm/reject via email, and WooCommerce customer accounts.
 * Version:     2.4
 * Author:      zhovon
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ZB_VERSION', '2.4' );
define( 'ZB_PATH',    plugin_dir_path( __FILE__ ) );
define( 'ZB_URL',     plugin_dir_url( __FILE__ ) );

require_once ZB_PATH . 'includes/db-table.php';
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
}

add_action( 'init', 'zb_remove_wc_cart_buttons' );
function zb_remove_wc_cart_buttons() {
    if ( is_admin() ) {
        return;
    }
    remove_action( 'woocommerce_after_shop_loop_item',   'woocommerce_template_loop_add_to_cart', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

    add_action( 'woocommerce_after_shop_loop_item',   'zb_booking_button_loop', 10 );
    add_action( 'woocommerce_single_product_summary', 'zb_booking_button_single', 30 );
}

function zb_booking_button_loop() {
    global $product;
    if ( ! $product ) return;
    printf(
        '<a href="%s" class="button alt">Book nu</a>',
        esc_url( add_query_arg( 'p_id', $product->get_id(), site_url( '/bookings/' ) ) )
    );
}

function zb_booking_button_single() {
    global $product;
    if ( ! $product ) return;
    printf(
        '<a href="%s" class="button alt" style="display:inline-block;margin-top:10px;">Book nu</a>',
        esc_url( add_query_arg( 'p_id', $product->get_id(), site_url( '/bookings/' ) ) )
    );
}

add_action( 'template_redirect', 'zb_force_nocache_on_booking_pages', 1 );

function zb_force_nocache_on_booking_pages() {
    global $post;
    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $protected_shortcodes = [ 'zbooking', 'zb_signup' ];

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
