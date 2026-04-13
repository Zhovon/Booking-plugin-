<?php

defined( 'ABSPATH' ) || exit;

function zb_calendar_connections_option_name() {
    return 'zb_calendar_connections';
}

function zb_calendar_state_prefix() {
    return 'zb_calendar_oauth_state_';
}

function zb_calendar_get_connections() {
    $connections = get_option( zb_calendar_connections_option_name(), [] );
    return is_array( $connections ) ? $connections : [];
}

function zb_calendar_save_connections( $connections ) {
    update_option( zb_calendar_connections_option_name(), is_array( $connections ) ? $connections : [], false );
}

function zb_calendar_get_connection( $provider ) {
    $connections = zb_calendar_get_connections();
    return isset( $connections[ $provider ] ) && is_array( $connections[ $provider ] ) ? $connections[ $provider ] : [];
}

function zb_calendar_set_connection( $provider, $connection ) {
    $connections = zb_calendar_get_connections();
    $connections[ $provider ] = is_array( $connection ) ? $connection : [];
    zb_calendar_save_connections( $connections );
}

function zb_calendar_clear_connection( $provider ) {
    $connections = zb_calendar_get_connections();
    unset( $connections[ $provider ] );
    zb_calendar_save_connections( $connections );
}

function zb_calendar_provider_config( $provider ) {
    $provider = strtolower( (string) $provider );

    if ( 'google' === $provider ) {
        return [
            'provider'          => 'google',
            'label'             => 'Google Calendar',
            'enabled_key'       => 'google_enabled',
            'client_id_key'     => 'google_client_id',
            'client_secret_key' => 'google_client_secret',
            'tenant_key'        => '',
            'auth_url'          => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'         => 'https://oauth2.googleapis.com/token',
            'userinfo_url'      => 'https://openidconnect.googleapis.com/v1/userinfo',
            'api_base'          => 'https://www.googleapis.com/calendar/v3',
            'scope'             => [
                'openid',
                'email',
                'profile',
                'offline_access',
                'https://www.googleapis.com/auth/calendar',
            ],
            'calendar_id'       => 'primary',
            'connect_param'     => 'google',
        ];
    }

    return [
        'provider'          => 'outlook',
        'label'             => 'Outlook',
        'enabled_key'       => 'outlook_enabled',
        'client_id_key'     => 'outlook_client_id',
        'client_secret_key' => 'outlook_client_secret',
        'tenant_key'        => 'outlook_tenant',
        'auth_url'          => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
        'token_url'         => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
        'userinfo_url'      => 'https://graph.microsoft.com/v1.0/me?$select=mail,userPrincipalName,displayName',
        'api_base'          => 'https://graph.microsoft.com/v1.0',
        'scope'             => [
            'openid',
            'profile',
            'email',
            'offline_access',
            'User.Read',
            'Calendars.ReadWrite',
        ],
        'calendar_id'       => 'me',
        'connect_param'     => 'outlook',
    ];
}

function zb_calendar_is_enabled( $provider ) {
    $config = zb_calendar_provider_config( $provider );
    return (int) zb_get_setting( $config['enabled_key'] ) === 1;
}

function zb_calendar_is_connected( $provider ) {
    $connection = zb_calendar_get_connection( $provider );
    return ! empty( $connection['refresh_token'] ) && ! empty( $connection['access_token'] );
}

function zb_calendar_is_configured( $provider ) {
    if ( ! zb_calendar_is_enabled( $provider ) ) {
        return false;
    }

    $config = zb_calendar_provider_config( $provider );
    $client_id = trim( (string) zb_get_setting( $config['client_id_key'] ) );
    $client_secret = trim( (string) zb_get_setting( $config['client_secret_key'] ) );

    return '' !== $client_id && '' !== $client_secret && zb_calendar_is_connected( $provider );
}

function zb_calendar_connected_providers() {
    $providers = [];
    foreach ( [ 'outlook', 'google' ] as $provider ) {
        if ( zb_calendar_is_configured( $provider ) ) {
            $providers[] = $provider;
        }
    }
    return $providers;
}

function zb_calendar_callback_url( $provider ) {
    return add_query_arg(
        [ 'provider' => strtolower( (string) $provider ) ],
        admin_url( 'admin-post.php?action=zb_calendar_callback' )
    );
}

function zb_calendar_auth_state_key( $state ) {
    return zb_calendar_state_prefix() . sanitize_key( $state );
}

function zb_calendar_store_state( $provider ) {
    $state = wp_generate_password( 32, false, false );
    set_transient(
        zb_calendar_auth_state_key( $state ),
        [
            'provider' => strtolower( (string) $provider ),
            'user_id'   => get_current_user_id(),
            'created'   => current_time( 'timestamp' ),
        ],
        10 * MINUTE_IN_SECONDS
    );

    return $state;
}

function zb_calendar_auth_url( $provider ) {
    $config = zb_calendar_provider_config( $provider );
    $client_id = trim( (string) zb_get_setting( $config['client_id_key'] ) );
    $tenant = $config['tenant_key'] ? trim( (string) zb_get_setting( $config['tenant_key'] ) ) : '';

    if ( '' === $client_id ) {
        return '';
    }

    $state = zb_calendar_store_state( $provider );
    $redirect_uri = zb_calendar_callback_url( $provider );

    $params = [
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => implode( ' ', $config['scope'] ),
        'state'         => $state,
        'prompt'        => 'consent select_account',
    ];

    if ( 'google' === $provider ) {
        $params['access_type'] = 'offline';
        $params['include_granted_scopes'] = 'true';
    }

    $auth_url = $config['auth_url'];
    if ( false !== strpos( $auth_url, '{tenant}' ) ) {
        $auth_url = str_replace( '{tenant}', rawurlencode( $tenant ?: 'common' ), $auth_url );
    }

    return add_query_arg( $params, $auth_url );
}

function zb_calendar_admin_notice_redirect( $provider, $status, $message = '' ) {
    $query = [
        'page'   => 'zb-settings',
        'zb_cal' => sanitize_key( $provider ),
        'zb_oauth' => sanitize_key( $status ),
    ];
    if ( '' !== $message ) {
        $query['zb_msg'] = rawurlencode( $message );
    }

    wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
    exit;
}

function zb_calendar_exchange_code_for_token( $provider, $code ) {
    $config = zb_calendar_provider_config( $provider );
    $client_id = trim( (string) zb_get_setting( $config['client_id_key'] ) );
    $client_secret = trim( (string) zb_get_setting( $config['client_secret_key'] ) );
    $redirect_uri = zb_calendar_callback_url( $provider );
    $tenant = $config['tenant_key'] ? trim( (string) zb_get_setting( $config['tenant_key'] ) ) : '';

    $token_url = $config['token_url'];
    if ( false !== strpos( $token_url, '{tenant}' ) ) {
        $token_url = str_replace( '{tenant}', rawurlencode( $tenant ?: 'common' ), $token_url );
    }

    $body = [
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $redirect_uri,
    ];

    if ( 'google' === $provider ) {
        $body['scope'] = implode( ' ', $config['scope'] );
    }

    $response = wp_remote_post(
        $token_url,
        [
            'timeout' => 20,
            'body'    => $body,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $payload = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status < 200 || $status >= 300 || empty( $payload['access_token'] ) ) {
        return new WP_Error( 'zb_calendar_token_error', 'Unable to get calendar access token.', [ 'status' => $status, 'body' => $payload ] );
    }

    return is_array( $payload ) ? $payload : [];
}

function zb_calendar_refresh_access_token( $provider, $refresh_token ) {
    $config = zb_calendar_provider_config( $provider );
    $client_id = trim( (string) zb_get_setting( $config['client_id_key'] ) );
    $client_secret = trim( (string) zb_get_setting( $config['client_secret_key'] ) );
    $tenant = $config['tenant_key'] ? trim( (string) zb_get_setting( $config['tenant_key'] ) ) : '';

    $token_url = $config['token_url'];
    if ( false !== strpos( $token_url, '{tenant}' ) ) {
        $token_url = str_replace( '{tenant}', rawurlencode( $tenant ?: 'common' ), $token_url );
    }

    $body = [
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh_token,
    ];

    if ( 'google' === $provider ) {
        $body['scope'] = implode( ' ', $config['scope'] );
    }

    $response = wp_remote_post(
        $token_url,
        [
            'timeout' => 20,
            'body'    => $body,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $payload = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status < 200 || $status >= 300 || empty( $payload['access_token'] ) ) {
        return new WP_Error( 'zb_calendar_refresh_error', 'Unable to refresh calendar access token.', [ 'status' => $status, 'body' => $payload ] );
    }

    return is_array( $payload ) ? $payload : [];
}

function zb_calendar_get_access_token( $provider ) {
    $connection = zb_calendar_get_connection( $provider );
    if ( empty( $connection['access_token'] ) ) {
        return new WP_Error( 'zb_calendar_no_token', 'Calendar account is not connected.' );
    }

    $expires_at = absint( $connection['expires_at'] ?? 0 );
    if ( $expires_at > current_time( 'timestamp' ) + 120 ) {
        return (string) $connection['access_token'];
    }

    if ( empty( $connection['refresh_token'] ) ) {
        return (string) $connection['access_token'];
    }

    $refreshed = zb_calendar_refresh_access_token( $provider, (string) $connection['refresh_token'] );
    if ( is_wp_error( $refreshed ) ) {
        return $refreshed;
    }

    $connection['access_token'] = (string) $refreshed['access_token'];
    $connection['expires_at']   = current_time( 'timestamp' ) + max( 300, absint( $refreshed['expires_in'] ?? 3600 ) - 120 );
    if ( ! empty( $refreshed['refresh_token'] ) ) {
        $connection['refresh_token'] = (string) $refreshed['refresh_token'];
    }

    zb_calendar_set_connection( $provider, $connection );

    return (string) $connection['access_token'];
}

function zb_calendar_api_request( $provider, $method, $path, $args = [] ) {
    $config = zb_calendar_provider_config( $provider );
    $token  = zb_calendar_get_access_token( $provider );
    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $base_url = $config['api_base'];
    $url = rtrim( $base_url, '/' ) . '/' . ltrim( $path, '/' );

    if ( ! empty( $args['query'] ) ) {
        $url = add_query_arg( $args['query'], $url );
    }

    $request_args = [
        'method'  => strtoupper( $method ),
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'   => 'application/json',
        ],
    ];

    if ( isset( $args['body'] ) ) {
        $request_args['body'] = wp_json_encode( $args['body'] );
    }

    $response = wp_remote_request( $url, $request_args );
    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    $payload = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status < 200 || $status >= 300 ) {
        return new WP_Error( 'zb_calendar_api_error', 'Calendar API request failed.', [ 'status' => $status, 'body' => $payload, 'url' => $url ] );
    }

    return is_array( $payload ) ? $payload : [];
}

function zb_calendar_get_profile( $provider ) {
    if ( 'google' === $provider ) {
        $token = zb_calendar_get_access_token( $provider );
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_remote_get(
            'https://openidconnect.googleapis.com/v1/userinfo',
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . (string) $token,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
            return new WP_Error( 'zb_google_profile_error', 'Unable to read Google profile.', [ 'status' => $status, 'body' => $body ] );
        }

        return [
            'email' => (string) ( $body['email'] ?? '' ),
            'name'  => (string) ( $body['name'] ?? '' ),
        ];
    }

    $response = zb_calendar_api_request( $provider, 'GET', '/me', [] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return [
        'email' => (string) ( $response['mail'] ?? $response['userPrincipalName'] ?? '' ),
        'name'  => (string) ( $response['displayName'] ?? '' ),
    ];
}

function zb_calendar_store_connection( $provider, $token_data ) {
    $profile = zb_calendar_get_profile( $provider );
    $existing = zb_calendar_get_connection( $provider );

    $profile_email = '';
    $profile_name  = '';

    if ( is_wp_error( $profile ) ) {
        error_log( 'Zbooking calendar profile lookup failed: ' . $profile->get_error_message() );
    } elseif ( is_array( $profile ) ) {
        $profile_email = (string) ( $profile['email'] ?? '' );
        $profile_name  = (string) ( $profile['name'] ?? '' );
    }

    $connection = [
        'access_token'  => (string) ( $token_data['access_token'] ?? '' ),
        'refresh_token'  => ! empty( $token_data['refresh_token'] ) ? (string) $token_data['refresh_token'] : (string) ( $existing['refresh_token'] ?? '' ),
        'expires_at'    => current_time( 'timestamp' ) + max( 300, absint( $token_data['expires_in'] ?? 3600 ) - 120 ),
        'email'         => $profile_email,
        'name'          => $profile_name,
        'connected_at'  => current_time( 'mysql' ),
    ];

    zb_calendar_set_connection( $provider, $connection );
    return $connection;
}

function zb_calendar_bootstrap_routes() {
    add_action( 'admin_post_zb_calendar_connect', 'zb_calendar_handle_connect' );
    add_action( 'admin_post_zb_calendar_callback', 'zb_calendar_handle_callback' );
    add_action( 'admin_post_zb_calendar_disconnect', 'zb_calendar_handle_disconnect' );
}
add_action( 'init', 'zb_calendar_bootstrap_routes', 1 );

function zb_calendar_handle_connect() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No access.' );
    }

    $provider = sanitize_key( $_GET['provider'] ?? '' );
    if ( ! in_array( $provider, [ 'outlook', 'google' ], true ) ) {
        wp_die( 'Invalid provider.' );
    }

    $nonce_action = 'zb_calendar_connect_' . $provider;
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
        wp_die( 'Security check failed.' );
    }

    $auth_url = zb_calendar_auth_url( $provider );
    if ( '' === $auth_url ) {
        zb_calendar_admin_notice_redirect( $provider, 'error', 'Missing client ID or client secret.' );
    }

    wp_safe_redirect( $auth_url );
    exit;
}

function zb_calendar_handle_callback() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No access.' );
    }

    $provider = sanitize_key( $_GET['provider'] ?? '' );
    if ( ! in_array( $provider, [ 'outlook', 'google' ], true ) ) {
        wp_die( 'Invalid provider.' );
    }

    $state = sanitize_text_field( $_GET['state'] ?? '' );
    $code  = sanitize_text_field( $_GET['code'] ?? '' );

    if ( '' === $state || '' === $code ) {
        zb_calendar_admin_notice_redirect( $provider, 'error', 'Missing authorization code.' );
    }

    $state_data = get_transient( zb_calendar_auth_state_key( $state ) );
    delete_transient( zb_calendar_auth_state_key( $state ) );

    if ( ! is_array( $state_data ) || ( $state_data['provider'] ?? '' ) !== $provider ) {
        zb_calendar_admin_notice_redirect( $provider, 'error', 'Invalid authorization state.' );
    }

    $token_data = zb_calendar_exchange_code_for_token( $provider, $code );
    if ( is_wp_error( $token_data ) ) {
        error_log( 'Zbooking calendar connect error: ' . $token_data->get_error_message() );
        zb_calendar_admin_notice_redirect( $provider, 'error', 'Could not complete calendar connection.' );
    }

    zb_calendar_store_connection( $provider, $token_data );
    zb_calendar_admin_notice_redirect( $provider, 'success', ucfirst( $provider ) . ' connected successfully.' );
}

function zb_calendar_handle_disconnect() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No access.' );
    }

    $provider = sanitize_key( $_GET['provider'] ?? '' );
    if ( ! in_array( $provider, [ 'outlook', 'google' ], true ) ) {
        wp_die( 'Invalid provider.' );
    }

    $nonce_action = 'zb_calendar_disconnect_' . $provider;
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
        wp_die( 'Security check failed.' );
    }

    zb_calendar_clear_connection( $provider );
    zb_calendar_admin_notice_redirect( $provider, 'success', ucfirst( $provider ) . ' disconnected.' );
}

function zb_calendar_busy_intervals_for_provider( $provider, $start_ts, $end_ts ) {
    if ( ! zb_calendar_is_configured( $provider ) ) {
        return [];
    }

    $start_iso = gmdate( 'c', $start_ts );
    $end_iso   = gmdate( 'c', $end_ts );

    if ( 'google' === $provider ) {
        $response = zb_calendar_api_request(
            $provider,
            'POST',
            'freeBusy',
            [
                'body' => [
                    'timeMin' => $start_iso,
                    'timeMax' => $end_iso,
                    'items'   => [ [ 'id' => 'primary' ] ],
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'Zbooking Google busy fetch failed: ' . $response->get_error_message() );
            return [];
        }

        $busy = [];
        $calendars = $response['calendars']['primary']['busy'] ?? [];
        foreach ( (array) $calendars as $slot ) {
            $slot_start = strtotime( (string) ( $slot['start'] ?? '' ) );
            $slot_end   = strtotime( (string) ( $slot['end'] ?? '' ) );
            if ( $slot_start && $slot_end && $slot_end > $slot_start ) {
                $busy[] = [ 'start' => $slot_start, 'end' => $slot_end ];
            }
        }

        return $busy;
    }

    $response = zb_calendar_api_request(
        $provider,
        'GET',
        '/me/calendarView',
        [
            'query' => [
                'startDateTime' => $start_iso,
                'endDateTime'   => $end_iso,
                '$top'          => 200,
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        error_log( 'Zbooking Outlook busy fetch failed: ' . $response->get_error_message() );
        return [];
    }

    $busy = [];
    foreach ( (array) ( $response['value'] ?? [] ) as $event ) {
        $show_as = strtolower( (string) ( $event['showAs'] ?? 'busy' ) );
        if ( 'free' === $show_as ) {
            continue;
        }

        $slot_start = strtotime( (string) ( $event['start']['dateTime'] ?? '' ) );
        $slot_end   = strtotime( (string) ( $event['end']['dateTime'] ?? '' ) );
        if ( $slot_start && $slot_end && $slot_end > $slot_start ) {
            $busy[] = [ 'start' => $slot_start, 'end' => $slot_end ];
        }
    }

    return $busy;
}

function zb_calendar_get_busy_intervals( $start_ts, $end_ts ) {
    $busy = [];

    foreach ( zb_calendar_connected_providers() as $provider ) {
        $provider_busy = zb_calendar_busy_intervals_for_provider( $provider, $start_ts, $end_ts );
        if ( is_array( $provider_busy ) ) {
            $busy = array_merge( $busy, $provider_busy );
        }
    }

    return $busy;
}

function zb_calendar_has_conflict( $start_ts, $end_ts ) {
    if ( empty( zb_calendar_connected_providers() ) ) {
        return false;
    }

    $busy = zb_calendar_get_busy_intervals( $start_ts, $end_ts );
    foreach ( $busy as $slot ) {
        $busy_start = (int) ( $slot['start'] ?? 0 );
        $busy_end   = (int) ( $slot['end'] ?? 0 );
        if ( $busy_start < $end_ts && $busy_end > $start_ts ) {
            return true;
        }
    }

    return false;
}

function zb_calendar_create_event_for_provider( $provider, $booking_id, $data ) {
    if ( ! zb_calendar_is_configured( $provider ) ) {
        return null;
    }

    $duration = max( zb_get_slot_interval_minutes(), absint( $data['duration_minutes'] ?? zb_get_default_duration_minutes() ) );
    $start_ts = strtotime( (string) $data['booking_date'] . ' ' . (string) $data['booking_time'] . ':00' );
    $end_ts   = strtotime( '+' . $duration . ' minutes', $start_ts );

    if ( ! $start_ts || ! $end_ts ) {
        return null;
    }

    if ( 'google' === $provider ) {
        $payload = [
            'summary'     => sprintf( 'Booking #%d - %s', $booking_id, (string) $data['company_name'] ),
            'description' => sprintf(
                "Booking ID: #%d\nCompany: %s\nContact: %s\nPhone: %s\nServices: %s",
                $booking_id,
                (string) $data['company_name'],
                (string) $data['contact_person'],
                (string) $data['phone'],
                (string) $data['services']
            ),
            'location'    => (string) $data['address'],
            'start'       => [ 'dateTime' => gmdate( 'Y-m-d\TH:i:s\Z', $start_ts ), 'timeZone' => 'UTC' ],
            'end'         => [ 'dateTime' => gmdate( 'Y-m-d\TH:i:s\Z', $end_ts ), 'timeZone' => 'UTC' ],
        ];

        $response = zb_calendar_api_request( $provider, 'POST', '/calendars/primary/events', [ 'body' => $payload ] );
        if ( is_wp_error( $response ) ) {
            error_log( 'Zbooking Google event create failed: ' . $response->get_error_message() );
            return null;
        }

        return (string) ( $response['id'] ?? '' );
    }

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
        'start'   => [ 'dateTime' => gmdate( 'Y-m-d\TH:i:s', $start_ts ), 'timeZone' => 'UTC' ],
        'end'     => [ 'dateTime' => gmdate( 'Y-m-d\TH:i:s', $end_ts ), 'timeZone' => 'UTC' ],
        'location' => [ 'displayName' => (string) $data['address'] ],
    ];

    $response = zb_calendar_api_request( $provider, 'POST', '/me/events', [ 'body' => $payload ] );
    if ( is_wp_error( $response ) ) {
        error_log( 'Zbooking Outlook event create failed: ' . $response->get_error_message() );
        return null;
    }

    return (string) ( $response['id'] ?? '' );
}

function zb_calendar_create_events_for_booking( $booking_id, $data ) {
    $created = [];
    $table   = $GLOBALS['wpdb']->prefix . 'zb_bookings';

    foreach ( zb_calendar_connected_providers() as $provider ) {
        $event_id = zb_calendar_create_event_for_provider( $provider, $booking_id, $data );
        if ( ! $event_id ) {
            continue;
        }

        $created[ $provider ] = $event_id;

        if ( 'google' === $provider ) {
            $GLOBALS['wpdb']->update( $table, [ 'google_event_id' => sanitize_text_field( $event_id ) ], [ 'id' => absint( $booking_id ) ], [ '%s' ], [ '%d' ] );
        } else {
            $GLOBALS['wpdb']->update( $table, [ 'outlook_event_id' => sanitize_text_field( $event_id ) ], [ 'id' => absint( $booking_id ) ], [ '%s' ], [ '%d' ] );
        }
    }

    return $created;
}

function zb_calendar_delete_event_for_provider( $provider, $event_id ) {
    $provider = strtolower( (string) $provider );
    $event_id = trim( (string) $event_id );

    if ( '' === $event_id || ! zb_calendar_is_configured( $provider ) ) {
        return false;
    }

    if ( 'google' === $provider ) {
        $response = zb_calendar_api_request( $provider, 'DELETE', '/calendars/primary/events/' . rawurlencode( $event_id ), [] );
    } else {
        $response = zb_calendar_api_request( $provider, 'DELETE', '/me/events/' . rawurlencode( $event_id ), [] );
    }

    if ( is_wp_error( $response ) ) {
        error_log( 'Zbooking calendar delete failed for ' . $provider . ': ' . $response->get_error_message() );
        return false;
    }

    return true;
}

function zb_calendar_delete_events_for_booking( $booking ) {
    $booking = is_array( $booking ) ? $booking : (array) $booking;
    $deleted = [];

    if ( ! empty( $booking['google_event_id'] ) ) {
        $deleted['google'] = zb_calendar_delete_event_for_provider( 'google', (string) $booking['google_event_id'] );
    }

    if ( ! empty( $booking['outlook_event_id'] ) ) {
        $deleted['outlook'] = zb_calendar_delete_event_for_provider( 'outlook', (string) $booking['outlook_event_id'] );
    }

    return $deleted;
}

function zb_outlook_is_enabled() {
    return zb_calendar_is_enabled( 'outlook' );
}

function zb_google_is_enabled() {
    return zb_calendar_is_enabled( 'google' );
}

function zb_outlook_is_configured() {
    return zb_calendar_is_configured( 'outlook' );
}

function zb_google_is_configured() {
    return zb_calendar_is_configured( 'google' );
}

function zb_outlook_has_conflict( $start_ts, $end_ts ) {
    return zb_calendar_has_conflict( $start_ts, $end_ts );
}

function zb_outlook_get_busy_intervals( $start_ts, $end_ts ) {
    return zb_calendar_get_busy_intervals( $start_ts, $end_ts );
}

function zb_outlook_create_event_for_booking( $booking_id, $data ) {
    return zb_calendar_create_events_for_booking( $booking_id, $data );
}

function zb_calendar_render_connect_button( $provider ) {
    $provider = sanitize_key( $provider );
    $config = zb_calendar_provider_config( $provider );
    $connected = zb_calendar_is_configured( $provider );
    $connect_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=zb_calendar_connect&provider=' . $provider ),
        'zb_calendar_connect_' . $provider
    );
    $disconnect_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=zb_calendar_disconnect&provider=' . $provider ),
        'zb_calendar_disconnect_' . $provider
    );

    $connection = zb_calendar_get_connection( $provider );
    $label = ! empty( $connection['email'] ) ? $connection['email'] : ( $connected ? 'Connected' : 'Not connected' );

    echo '<p><strong>' . esc_html( $config['label'] ) . ':</strong> ' . esc_html( $label ) . '</p>';
    if ( $connected ) {
        echo '<a class="button button-secondary" href="' . esc_url( $disconnect_url ) . '">Disconnect ' . esc_html( $config['label'] ) . '</a> ';
    }
    echo '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( $connect_url ) . '">' . ( $connected ? 'Reconnect' : 'Connect' ) . ' ' . esc_html( $config['label'] ) . '</a>';
}
