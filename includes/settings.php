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
        'license_token'         => '',
        'license_secret_key'    => 'aspirine',
        'license_verify_url'    => 'https://zhovon.com/api/zbooking/license/verify',
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

function zb_get_currency_symbol() {
    $symbol = class_exists( 'WooCommerce' ) ? (string) get_woocommerce_currency_symbol() : 'kr';
    $symbol = html_entity_decode( $symbol, ENT_QUOTES, 'UTF-8' );
    $symbol = preg_replace( '/\x{00A0}/u', ' ', $symbol );
    $symbol = trim( preg_replace( '/\s+/u', ' ', $symbol ) );

    return '' !== $symbol ? $symbol : 'kr';
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

function zb_get_login_logout_url( $args = [] ) {
    $url = zb_get_login_url( $args );

    if ( is_user_logged_in() ) {
        $url = add_query_arg( 'zb_action', 'logout', $url );
    }

    return $url;
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

function zb_get_total_bookings_count() {
    global $wpdb;

    $table = $wpdb->prefix . 'zb_bookings';
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

    return absint( $count );
}

function zb_get_license_status( $force_refresh = false ) {
    $cache_key = 'zb_license_status_v1';

    if ( ! $force_refresh ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && isset( $cached['valid'] ) ) {
            return $cached;
        }
    }

    $token = trim( (string) zb_get_setting( 'license_token' ) );
    $secret = trim( (string) zb_get_setting( 'license_secret_key' ) );
    $url   = trim( (string) zb_get_setting( 'license_verify_url' ) );

    if ( '' === $token ) {
        $status = [
            'valid'   => false,
            'message' => 'No license token configured.',
            'mode'    => 'demo',
        ];
        set_transient( $cache_key, $status, HOUR_IN_SECONDS );
        return $status;
    }

    if ( '' === $url ) {
        $status = [
            'valid'   => false,
            'message' => 'No verification URL configured.',
            'mode'    => 'demo',
        ];
        set_transient( $cache_key, $status, HOUR_IN_SECONDS );
        return $status;
    }

    $response = wp_remote_post(
        esc_url_raw( $url ),
        [
            'timeout' => 12,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode(
                [
                    'token'      => $token,
                    'secret_key' => $secret ?: 'aspirine',
                    'domain'     => home_url(),
                    'plugin'     => 'zbooking',
                    'plugin_ver' => defined( 'ZB_VERSION' ) ? ZB_VERSION : '',
                ]
            ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        $status = [
            'valid'   => false,
            'message' => 'License check failed: ' . $response->get_error_message(),
            'mode'    => 'demo',
        ];
        set_transient( $cache_key, $status, HOUR_IN_SECONDS );
        return $status;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $json = json_decode( $body, true );

    if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
        $status = [
            'valid'   => false,
            'message' => 'License server rejected the request.',
            'mode'    => 'demo',
        ];
        set_transient( $cache_key, $status, HOUR_IN_SECONDS );
        return $status;
    }

    $is_valid = ! empty( $json['valid'] );
    $message  = isset( $json['message'] ) ? (string) $json['message'] : ( $is_valid ? 'License active.' : 'License not valid.' );

    $status = [
        'valid'      => $is_valid,
        'message'    => $message,
        'mode'       => $is_valid ? 'licensed' : 'demo',
        'expires_at' => isset( $json['expires_at'] ) ? sanitize_text_field( (string) $json['expires_at'] ) : '',
    ];

    set_transient( $cache_key, $status, $is_valid ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

    return $status;
}

function zb_is_license_valid() {
    $status = zb_get_license_status();
    return ! empty( $status['valid'] );
}

function zb_get_booking_invoice_token( $booking_id, $email = '' ) {
    $booking_id = absint( $booking_id );
    $email = strtolower( trim( (string) $email ) );

    return hash_hmac( 'sha256', $booking_id . '|' . $email, wp_salt( 'auth' ) );
}

function zb_validate_booking_invoice_token( $booking_id, $email, $token ) {
    $booking_id = absint( $booking_id );
    $email = strtolower( trim( (string) $email ) );
    $token = trim( (string) $token );

    if ( '' === $token || '' === $email ) {
        return false;
    }

    return hash_equals( zb_get_booking_invoice_token( $booking_id, $email ), $token );
}

function zb_get_booking_invoice_url( $booking_id, $email = '' ) {
    $url = add_query_arg( 'zb_invoice', absint( $booking_id ), home_url( '/' ) );

    if ( '' !== (string) $email ) {
        $url = add_query_arg( 'token', zb_get_booking_invoice_token( $booking_id, $email ), $url );
    }

    return $url;
}

function zb_get_booking_reschedule_url( $booking_id ) {
    return wp_nonce_url(
        add_query_arg( 'zb_reschedule', absint( $booking_id ), zb_get_dashboard_url() ),
        'zb_reschedule_' . absint( $booking_id )
    );
}

function zb_render_email_button( $url, $label, $background = '#4a7c59' ) {
    return '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:10px 18px;margin:6px 8px 6px 0;background:' . esc_attr( $background ) . ';color:#fff;text-decoration:none;border-radius:5px;font-weight:600;">' . esc_html( $label ) . '</a>';
}
