<?php

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'zb_handle_booking' );

function zb_normalize_booking_status( $status ) {
    $status = strtolower( trim( (string) $status ) );

    if ( 'accepted' === $status ) {
        return 'accepted';
    }

    if ( 'rejected' === $status ) {
        return 'rejected';
    }

    return 'pending';
}

function zb_is_status_accepted( $status ) {
    return 'accepted' === zb_normalize_booking_status( $status );
}

function zb_is_status_rejected( $status ) {
    return 'rejected' === zb_normalize_booking_status( $status );
}

function zb_get_active_booking_statuses_sql() {
    return [ 'pending', 'accepted', 'Accepted' ];
}

function zb_is_valid_booking_date( $date ) {
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return false;
    }

    $ts = strtotime( $date . ' 00:00:00' );
    if ( ! $ts ) {
        return false;
    }

    $today = strtotime( wp_date( 'Y-m-d' ) . ' 00:00:00' );
    return $ts > $today;
}

function zb_is_valid_slot_time( $time ) {
    if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m ) ) {
        return false;
    }

    $minutes = (int) $m[2];
    $step    = zb_get_slot_interval_minutes();

    return 0 === ( $minutes % $step );
}

function zb_build_slot_bounds( $date, $time, $duration_minutes ) {
    $tz = wp_timezone();

    $start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $tz );
    if ( ! $start ) {
        return false;
    }

    $duration = max( zb_get_slot_interval_minutes(), absint( $duration_minutes ) );
    $end      = $start->modify( '+' . $duration . ' minutes' );

    return [
        'start_local' => $start,
        'end_local'   => $end,
        'start_mysql' => $start->format( 'Y-m-d H:i:s' ),
        'end_mysql'   => $end->format( 'Y-m-d H:i:s' ),
        'start_utc'   => $start->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp(),
        'end_utc'     => $end->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp(),
    ];
}

function zb_within_business_hours( DateTimeImmutable $start, DateTimeImmutable $end ) {
    $start_hhmm = zb_normalize_hhmm( (string) zb_get_setting( 'business_start' ), '08:00' );
    $end_hhmm   = zb_normalize_hhmm( (string) zb_get_setting( 'business_end' ), '18:00' );

    $window_start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $start->format( 'Y-m-d' ) . ' ' . $start_hhmm, $start->getTimezone() );
    $window_end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $start->format( 'Y-m-d' ) . ' ' . $end_hhmm, $start->getTimezone() );

    if ( ! $window_start || ! $window_end ) {
        return true;
    }

    return $start >= $window_start && $end <= $window_end;
}

function zb_has_booking_conflict( $start_mysql, $end_mysql, $booking_date, $booking_time, $exclude_id = 0 ) {
    global $wpdb;

    $table = $wpdb->prefix . 'zb_bookings';

    $active_statuses = zb_get_active_booking_statuses_sql();
    $in_placeholders = implode( ',', array_fill( 0, count( $active_statuses ), '%s' ) );

    $sql = "SELECT id FROM {$table}
            WHERE status IN ({$in_placeholders})
              AND id <> %d
              AND (
                (timeslot_start IS NOT NULL AND timeslot_end IS NOT NULL AND timeslot_start < %s AND timeslot_end > %s)
                OR
                (timeslot_start IS NULL AND booking_date = %s AND booking_time = %s)
              )
            LIMIT 1";

    $prepare_args = array_merge(
        $active_statuses,
        [
            absint( $exclude_id ),
            $end_mysql,
            $start_mysql,
            $booking_date,
            $booking_time,
        ]
    );

    $conflict_id = $wpdb->get_var(
        $wpdb->prepare( $sql, ...$prepare_args )
    );

    return ! empty( $conflict_id );
}

function zb_handle_booking() {
    if ( ! isset( $_POST['zb_submit_booking'] ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( zb_get_login_url( [ 'redirect_to' => ( wp_get_referer() ?: home_url( '/' ) ) ] ) );
        exit;
    }

    if ( ! isset( $_POST['zb_booking_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zb_booking_nonce'] ) ), 'zb_booking_submit' ) ) {
        wp_die( 'Sikkerhedstjek mislykkedes. Gå tilbage og prøv igen.', 'Fejl', [ 'back_link' => true ] );
    }

    // Licensing gate: keep plugin usable in demo mode for first 3 bookings.
    if ( function_exists( 'zb_is_license_valid' ) && ! zb_is_license_valid() ) {
        $demo_limit = 3;
        $count      = function_exists( 'zb_get_total_bookings_count' ) ? zb_get_total_bookings_count() : 0;

        if ( $count >= $demo_limit ) {
            wp_die(
                'Demo-grænsen er nået (3 bookinger). Kontakt site-administratoren for fuld adgang.',
                'Demo-grænse nået',
                [ 'back_link' => true ]
            );
        }
    }

    $required = [
        'company_name'   => 'Firmanavn',
        'contact_person' => 'Kontaktperson',
        'email'          => 'E-mail',
        'phone'          => 'Telefon',
        'address'        => 'Ejendomsadresse',
        'booked_by'      => 'Booket af',
        'booking_date'   => 'Dato',
        'booking_time'   => 'Tidspunkt',
        'services'       => 'Services',
    ];
    foreach ( $required as $field => $label ) {
        if ( empty( trim( $_POST[ $field ] ?? '' ) ) ) {
            wp_die( "Feltet \"{$label}\" er påkrævet.", 'Mangler felt', [ 'back_link' => true ] );
        }
    }

    $company        = sanitize_text_field( $_POST['company_name'] );
    $contact        = sanitize_text_field( $_POST['contact_person'] );
    $booked_by      = sanitize_text_field( $_POST['booked_by'] );
    $email          = sanitize_email( $_POST['email'] );
    $phone          = sanitize_text_field( $_POST['phone'] );
    $address        = sanitize_textarea_field( $_POST['address'] );
    $seller_contact = sanitize_text_field( $_POST['seller_contact'] ?? '' );
    $comments       = sanitize_textarea_field( $_POST['comments'] ?? '' );
    $services       = sanitize_text_field( $_POST['services'] );
    $booking_date   = sanitize_text_field( $_POST['booking_date'] );
    $booking_time   = sanitize_text_field( $_POST['booking_time'] );

    if ( ! zb_is_valid_booking_date( $booking_date ) ) {
        wp_die( 'Ugyldig dato. Vælg en fremtidig dato.', 'Fejl', [ 'back_link' => true ] );
    }
    if ( ! zb_is_valid_slot_time( $booking_time ) ) {
        wp_die( 'Ugyldigt tidspunkt. Tid skal være i 15-minutters intervaller.', 'Fejl', [ 'back_link' => true ] );
    }

    $duration_minutes = absint( $_POST['total_minutes'] ?? 0 );
    if ( $duration_minutes < zb_get_slot_interval_minutes() ) {
        $duration_minutes = zb_get_default_duration_minutes();
    }
    if ( 0 !== $duration_minutes % zb_get_slot_interval_minutes() ) {
        $duration_minutes = (int) ceil( $duration_minutes / zb_get_slot_interval_minutes() ) * zb_get_slot_interval_minutes();
    }

    $bounds = zb_build_slot_bounds( $booking_date, $booking_time, $duration_minutes );
    if ( false === $bounds ) {
        wp_die( 'Kunne ikke fortolke dato/tid.', 'Fejl', [ 'back_link' => true ] );
    }
    if ( ! zb_within_business_hours( $bounds['start_local'], $bounds['end_local'] ) ) {
        wp_die( 'Valgt tidspunkt ligger uden for åbningstider.', 'Fejl', [ 'back_link' => true ] );
    }

    if ( function_exists( 'zb_calendar_has_conflict' ) && ! empty( zb_calendar_connected_providers() ) && zb_calendar_has_conflict( $bounds['start_utc'], $bounds['end_utc'] ) ) {
        wp_die( 'Tidsrummet er ikke længere ledigt i kalenderen.', 'Tidskonflikt', [ 'back_link' => true ] );
    }

    $raw_price      = floatval( $_POST['price'] ?? 0 );
    $coupon_code    = sanitize_text_field( $_POST['active_coupon_code'] ?? '' );
    $coupon_price   = floatval( $_POST['coupon_price'] ?? 0 );

    $final_coupon_code  = '';
    $final_coupon_price = '';

    if ( ! empty( $coupon_code ) && class_exists( 'WooCommerce' ) ) {
        $coupon_obj  = new WC_Coupon( $coupon_code );
        $usage_limit = (int) $coupon_obj->get_usage_limit();
        $usage_count = (int) $coupon_obj->get_usage_count();

        if ( ! $coupon_obj->get_id() ) {
            wp_die( 'Ugyldig rabatkode.', 'Fejl', [ 'back_link' => true ] );
        }
        if ( $usage_limit > 0 && $usage_count >= $usage_limit ) {
            wp_die( 'Rabatkoden har nået sit maksimale antal anvendelser.', 'Fejl', [ 'back_link' => true ] );
        }

        $coupon_obj->set_usage_count( $usage_count + 1 );
        if ( $usage_limit > 0 && ( $usage_count + 1 ) >= $usage_limit ) {
            $coupon_obj->set_date_expires( current_time( 'timestamp' ) );
        }
        $coupon_obj->save();

        $final_coupon_code  = $coupon_code;
        $final_coupon_price = $coupon_price > 0 ? (string) $coupon_price : '';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'zb_bookings';

    $data = [
        'user_id'        => get_current_user_id(),
        'company_name'   => $company,
        'contact_person' => $contact,
        'booked_by'      => $booked_by,
        'email'          => $email,
        'phone'          => $phone,
        'address'        => $address,
        'seller_contact' => $seller_contact,
        'comments'       => $comments,
        'services'       => $services,
        'booking_date'   => $booking_date,
        'booking_time'   => $booking_time,
        'duration_minutes' => $duration_minutes,
        'timeslot_start' => $bounds['start_mysql'],
        'timeslot_end'   => $bounds['end_mysql'],
        'price'          => (string) $raw_price,
        'coupon'         => $final_coupon_code,
        'coupon_price'   => $final_coupon_price,
        'status'         => 'pending',
    ];
    $formats = [ '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s' ];

    $inserted = false;
    $locked   = false;

    // Lock table briefly to avoid race-condition double bookings.
    if ( false !== $wpdb->query( "LOCK TABLES {$table} WRITE" ) ) {
        $locked = true;
    }

    if ( zb_has_booking_conflict( $bounds['start_mysql'], $bounds['end_mysql'], $booking_date, $booking_time ) ) {
        if ( $locked ) {
            $wpdb->query( 'UNLOCK TABLES' );
        }
        wp_die( 'Tidsrummet er netop blevet booket af en anden kunde. Vælg venligst et andet tidspunkt.', 'Tidskonflikt', [ 'back_link' => true ] );
    }

    $inserted = $wpdb->insert( $table, $data, $formats );

    if ( $locked ) {
        $wpdb->query( 'UNLOCK TABLES' );
    }

    if ( ! $inserted ) {
        error_log( 'Zbooking Database Error: ' . $wpdb->last_error );
        wp_die(
            'Der opstod en fejl under behandling af din booking. Prøv igen eller kontakt os på <a href="mailto:booking@homefoto.dk">booking@homefoto.dk</a>.',
            'Databasefejl',
            [ 'back_link' => true ]
        );
    }

    $booking_id = $wpdb->insert_id;
    zb_send_booking_emails( $booking_id, $data );

    wp_safe_redirect( zb_get_booking_url( [ 'booking_id' => $booking_id ] ) );
    exit;
}

function zb_generate_ics( $booking_id, $data ) {
    $duration = max( zb_get_slot_interval_minutes(), absint( $data['duration_minutes'] ?? zb_get_default_duration_minutes() ) );
    $start    = date( 'Ymd\THis', strtotime( $data['booking_date'] . ' ' . $data['booking_time'] ) );
    $end      = date( 'Ymd\THis', strtotime( $data['booking_date'] . ' ' . $data['booking_time'] . ' +' . $duration . ' minutes' ) );
    $addr  = str_replace( ',', '', $data['address'] );
    
    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Zbooking//NONSGML v1.0//EN\r\nBEGIN:VEVENT\r\n";
    $ics .= "UID:" . $booking_id . "@" . parse_url(home_url(), PHP_URL_HOST) . "\r\n";
    $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    $ics .= "DTSTART:{$start}\r\nDTEND:{$end}\r\n";
    $ics .= "SUMMARY:Foto/Video: " . addslashes($data['address']) . "\r\n";
    $ics .= "DESCRIPTION:Booking #" . $booking_id . "\\nServices: " . addslashes($data['services']) . "\r\n";
    $ics .= "LOCATION:" . addslashes($addr) . "\r\n";
    $ics .= "END:VEVENT\r\nEND:VCALENDAR";
    
    $upload_dir = wp_upload_dir();
    $file_path  = $upload_dir['basedir'] . '/booking-' . $booking_id . '.ics';
    file_put_contents( $file_path, $ics );
    return $file_path;
}

function zb_get_styled_html( $heading, $content ) {
    if ( ! class_exists( 'WooCommerce' ) ) return $content;
    ob_start();
    wc_get_template( 'emails/email-header.php', [ 'email_heading' => $heading ] );
    echo wpautop( wptexturize( $content ) );
    wc_get_template( 'emails/email-footer.php' );
    return ob_get_clean();
}

function zb_send_booking_emails( $booking_id, $data ) {
    $currency  = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : 'kr';
    $site_name = get_bloginfo( 'name' ) ?: 'homefoto';
    $sep       = str_repeat( '═', 42 );

    $confirm_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=zb_booking_action&booking_id=' . $booking_id . '&status=accepted' ),
        'zb_booking_action_' . $booking_id
    );
    $reject_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=zb_booking_action&booking_id=' . $booking_id . '&status=rejected' ),
        'zb_booking_action_' . $booking_id
    );

    $admin_content = "Ny booking-anmodning modtaget (ID: #{$booking_id})<br><br>";
    $admin_content .= "<strong>KUNDEOPLYSNINGER</strong><br>";
    $admin_content .= "Firmanavn: {$data['company_name']}<br>";
    $admin_content .= "Kontakt: {$data['contact_person']}<br>";
    $admin_content .= "Adresse: {$data['address']}<br>";
    $admin_content .= "Tlf: {$data['phone']}<br><br>";
    $admin_content .= "<strong>HANDLING PÅKRÆVET:</strong><br>";
    $admin_content .= '<a href="'.$confirm_url.'" style="padding:10px 20px; background:#4a7c59; color:#fff; text-decoration:none; border-radius:5px; display:inline-block; margin-right:10px;">BEKRÆFT</a>';
    $admin_content .= '<a href="'.$reject_url.'" style="padding:10px 20px; background:#b91c1c; color:#fff; text-decoration:none; border-radius:5px; display:inline-block;">AFVIS</a>';

    $admin_subject    = 'Ny booking-anmodning modtaget – ' . $data['address'];
    $customer_subject = 'Vi har modtaget din booking-anmodning – ' . $site_name;

    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    wp_mail( get_option( 'admin_email' ), $admin_subject, zb_get_styled_html( 'Ny Booking Anmodning', $admin_content ), $headers );

    $cust_content = "Hej {$data['contact_person']},<br><br>";
    $cust_content .= "Tak for din booking-anmodning. Vi bekræfter hermed at vi har modtaget den og behandler den snarest.<br><br>";
    $cust_content .= "<strong>Detaljer:</strong><br>";
    $cust_content .= "Adresse: {$data['address']}<br>";
    $cust_content .= "Ydelser: {$data['services']}<br>";
    $cust_content .= "Dato: {$data['booking_date']} {$data['booking_time']}<br><br>";
    $cust_content .= "Betaling sker efter fotografering.";

    wp_mail( $data['email'], $customer_subject, zb_get_styled_html( 'Booking Modtaget', $cust_content ), $headers );
}
