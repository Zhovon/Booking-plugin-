<?php

defined( 'ABSPATH' ) || exit;

function zb_get_settings_defaults() {
    return [
        'booking_slug'          => 'bookings',
        'login_slug'            => 'login',
        'dashboard_slug'        => 'min-konto',
        'slot_interval_minutes' => 15,
        'default_duration'      => 60,
        'business_start'        => '08:00',
        'business_end'          => '18:00',
        'outlook_enabled'       => 0,
        'outlook_tenant'        => 'common',
        'outlook_tenant_id'     => '',
        'outlook_client_id'     => '',
        'outlook_client_secret' => '',
        'outlook_user_id'       => '',
        'google_enabled'        => 0,
        'google_client_id'      => '',
        'google_client_secret'  => '',
    ];
}

function zb_get_settings() {
    $stored = get_option( 'zb_settings', [] );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    return wp_parse_args( $stored, zb_get_settings_defaults() );
}

function zb_get_setting( $key ) {
    $settings = zb_get_settings();
    return $settings[ $key ] ?? null;
}

function zb_get_slug_url( $setting_key, $args = [] ) {
    $slug = trim( (string) zb_get_setting( $setting_key ), '/' );
    if ( '' === $slug ) {
        $defaults = zb_get_settings_defaults();
        $slug     = trim( $defaults[ $setting_key ], '/' );
    }

    $url = home_url( '/' . $slug . '/' );
    if ( ! empty( $args ) ) {
        $url = add_query_arg( $args, $url );
    }

    return $url;
}

function zb_is_reserved_woocommerce_slug( $slug ) {
    $slug = trim( strtolower( (string) $slug ), '/' );
    return in_array( $slug, [ 'cart', 'checkout', 'my-account', 'shop' ], true );
}

function zb_get_shortcode_page_url( $shortcode, $args = [] ) {
    $pages = get_posts(
        [
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            's'              => '[' . $shortcode,
        ]
    );

    if ( empty( $pages[0] ) ) {
        return '';
    }

    $url = get_permalink( (int) $pages[0] );
    if ( ! $url ) {
        return '';
    }

    if ( ! empty( $args ) ) {
        $url = add_query_arg( $args, $url );
    }

    return $url;
}

function zb_get_best_page_url( $shortcode, $slug_setting_key, $args = [] ) {
    $shortcode_url = zb_get_shortcode_page_url( $shortcode, $args );
    if ( '' !== $shortcode_url ) {
        return $shortcode_url;
    }

    return zb_get_slug_url( $slug_setting_key, $args );
}

function zb_get_booking_url( $args = [] ) {
    $url = zb_get_best_page_url( 'zbooking', 'booking_slug', $args );

    $path = wp_parse_url( $url, PHP_URL_PATH );
    if ( is_string( $path ) ) {
        $slug = trim( basename( untrailingslashit( $path ) ) );
        if ( zb_is_reserved_woocommerce_slug( $slug ) ) {
            $url = home_url( '/bookings/' );
            if ( ! empty( $args ) ) {
                $url = add_query_arg( $args, $url );
            }
        }
    }

    return $url;
}

function zb_get_login_url( $args = [] ) {
    return zb_get_best_page_url( 'zb_auth', 'login_slug', $args );
}

function zb_get_dashboard_url( $args = [] ) {
    return zb_get_best_page_url( 'zb_dashboard', 'dashboard_slug', $args );
}

function zb_normalize_hhmm( $value, $fallback = '00:00' ) {
    $value = trim( (string) $value );
    if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $value ) ) {
        return $fallback;
    }

    return $value;
}

function zb_get_slot_interval_minutes() {
    $step = absint( zb_get_setting( 'slot_interval_minutes' ) );
    if ( $step < 5 ) {
        $step = 15;
    }

    // Keep intervals aligned and predictable.
    if ( 0 !== $step % 5 ) {
        $step = 15;
    }

    return $step;
}

function zb_get_default_duration_minutes() {
    $duration = absint( zb_get_setting( 'default_duration' ) );
    if ( $duration < zb_get_slot_interval_minutes() ) {
        $duration = 60;
    }

    return $duration;
}
