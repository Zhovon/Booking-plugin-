<?php

defined( 'ABSPATH' ) || exit;

function zb_outlook_is_enabled() {
    return (int) zb_get_setting( 'outlook_enabled' ) === 1;
}

function zb_outlook_is_configured() {
    if ( ! zb_outlook_is_enabled() ) {
        return false;
    }

    $required = [
        'outlook_tenant_id',
        'outlook_client_id',
        'outlook_client_secret',
        'outlook_user_id',
    ];

    foreach ( $required as $key ) {
        if ( '' === trim( (string) zb_get_setting( $key ) ) ) {
            return false;
        }
    }

    return true;
}

function zb_outlook_token_cache_key() {
    return 'zb_outlook_token';
}

function zb_outlook_get_access_token() {
    $cached = get_transient( zb_outlook_token_cache_key() );
    if ( is_string( $cached ) && '' !== $cached ) {
        return $cached;
    }

    if ( ! zb_outlook_is_configured() ) {
        return new WP_Error( 'zb_outlook_not_configured', 'Outlook integration is not configured.' );
    }

    $tenant_id     = trim( (string) zb_get_setting( 'outlook_tenant_id' ) );
    $client_id     = trim( (string) zb_get_setting( 'outlook_client_id' ) );
    $client_secret = trim( (string) zb_get_setting( 'outlook_client_secret' ) );

    $token_url = sprintf(
        'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
        rawurlencode( $tenant_id )
    );

    $response = wp_remote_post(
        $token_url,
        [
            'timeout' => 20,
            'body'    => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $body   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status < 200 || $status >= 300 || empty( $body['access_token'] ) ) {
        return new WP_Error( 'zb_outlook_token_failed', 'Unable to get Outlook access token.', $body );
    }

    $token      = (string) $body['access_token'];
    $expires_in = max( 300, absint( $body['expires_in'] ?? 3600 ) - 120 );

    set_transient( zb_outlook_token_cache_key(), $token, $expires_in );

    return $token;
}

function zb_outlook_api_get( $path, $query = [] ) {
    $token = zb_outlook_get_access_token();
    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $base = 'https://graph.microsoft.com/v1.0';
    $url  = $base . $path;

    if ( ! empty( $query ) ) {
        $url .= '?' . http_build_query( $query );
    }

    $response = wp_remote_get(
        $url,
        [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $body   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status < 200 || $status >= 300 ) {
        return new WP_Error( 'zb_outlook_api_error', 'Outlook API request failed.', [
            'status' => $status,
            'body'   => $body,
        ] );
    }

    return is_array( $body ) ? $body : [];
}

function zb_outlook_api_post( $path, $payload ) {
    $token = zb_outlook_get_access_token();
    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $response = wp_remote_post(
        'https://graph.microsoft.com/v1.0' . $path,
        [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $body   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status < 200 || $status >= 300 ) {
        return new WP_Error( 'zb_outlook_api_error', 'Outlook API request failed.', [
            'status' => $status,
            'body'   => $body,
        ] );
    }

    return is_array( $body ) ? $body : [];
}

function zb_outlook_has_conflict( $start_ts, $end_ts ) {
    if ( ! zb_outlook_is_configured() ) {
        return false;
    }

    $user_id = rawurlencode( trim( (string) zb_get_setting( 'outlook_user_id' ) ) );

    $result = zb_outlook_api_get(
        '/users/' . $user_id . '/calendarView',
        [
            'startDateTime' => gmdate( 'c', $start_ts ),
            'endDateTime'   => gmdate( 'c', $end_ts ),
            '$top'          => 50,
        ]
    );

    if ( is_wp_error( $result ) ) {
        error_log( 'Zbooking Outlook conflict check failed: ' . $result->get_error_message() );
        // Fail-open to keep booking operational if Graph is temporarily unavailable.
        return false;
    }

    $events = $result['value'] ?? [];
    foreach ( $events as $event ) {
        if ( empty( $event['showAs'] ) || 'free' !== strtolower( (string) $event['showAs'] ) ) {
            return true;
        }
    }

    return false;
}

function zb_outlook_get_busy_intervals( $start_ts, $end_ts ) {
    if ( ! zb_outlook_is_configured() ) {
        return [];
    }

    $user_id = rawurlencode( trim( (string) zb_get_setting( 'outlook_user_id' ) ) );

    $result = zb_outlook_api_get(
        '/users/' . $user_id . '/calendarView',
        [
            'startDateTime' => gmdate( 'c', $start_ts ),
            'endDateTime'   => gmdate( 'c', $end_ts ),
            '$top'          => 200,
        ]
    );

    if ( is_wp_error( $result ) ) {
        error_log( 'Zbooking Outlook busy-interval fetch failed: ' . $result->get_error_message() );
        return [];
    }

    $busy = [];
    foreach ( (array) ( $result['value'] ?? [] ) as $event ) {
        $show_as = strtolower( (string) ( $event['showAs'] ?? 'busy' ) );
        if ( 'free' === $show_as ) {
            continue;
        }

        $s = strtotime( (string) ( $event['start']['dateTime'] ?? '' ) );
        $e = strtotime( (string) ( $event['end']['dateTime'] ?? '' ) );

        if ( $s && $e && $e > $s ) {
            $busy[] = [ 'start' => $s, 'end' => $e ];
        }
    }

    return $busy;
}

function zb_outlook_create_event_for_booking( $booking_id, $data ) {
    if ( ! zb_outlook_is_configured() ) {
        return;
    }

    $duration  = max( zb_get_slot_interval_minutes(), absint( $data['duration_minutes'] ?? zb_get_default_duration_minutes() ) );
    $start_ts  = strtotime( $data['booking_date'] . ' ' . $data['booking_time'] . ':00' );
    $end_ts    = strtotime( '+' . $duration . ' minutes', $start_ts );

    $payload = [
        'subject' => sprintf( 'Booking #%d - %s', $booking_id, (string) $data['company_name'] ),
        'body'    => [
            'contentType' => 'Text',
            'content'     => sprintf(
                "Booking ID: #%d\nCompany: %s\nContact: %s\nPhone: %s\nServices: %s",
                $booking_id,
                (string) $data['company_name'],
                (string) $data['contact_person'],
                (string) $data['phone'],
                (string) $data['services']
            ),
        ],
        'start'   => [
            'dateTime' => gmdate( 'Y-m-d\TH:i:s', $start_ts ),
            'timeZone' => 'UTC',
        ],
        'end'     => [
            'dateTime' => gmdate( 'Y-m-d\TH:i:s', $end_ts ),
            'timeZone' => 'UTC',
        ],
        'location' => [
            'displayName' => (string) $data['address'],
        ],
    ];

    $user_id = rawurlencode( trim( (string) zb_get_setting( 'outlook_user_id' ) ) );
    $result  = zb_outlook_api_post( '/users/' . $user_id . '/events', $payload );

    if ( is_wp_error( $result ) ) {
        error_log( 'Zbooking Outlook event create failed: ' . $result->get_error_message() );
        return;
    }

    if ( ! empty( $result['id'] ) ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'zb_bookings',
            [ 'outlook_event_id' => sanitize_text_field( $result['id'] ) ],
            [ 'id' => absint( $booking_id ) ],
            [ '%s' ],
            [ '%d' ]
        );
    }
}
