<?php

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'zb_handle_booking' );

function zb_handle_booking() {
    if ( ! isset( $_POST['zb_submit_booking'] ) ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( site_url( '/login?redirect_to=' . rawurlencode( wp_get_referer() ?: '/' ) ) );
        exit;
    }

    if ( ! isset( $_POST['zb_booking_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zb_booking_nonce'] ) ), 'zb_booking_submit' ) ) {
        wp_die( 'Sikkerhedstjek mislykkedes. Gå tilbage og prøv igen.', 'Fejl', [ 'back_link' => true ] );
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

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $booking_date ) ||
         ! strtotime( $booking_date ) ||
         strtotime( $booking_date ) <= current_time( 'timestamp' ) ) {
        wp_die( 'Ugyldig dato. Vælg en fremtidig dato.', 'Fejl', [ 'back_link' => true ] );
    }
    if ( ! preg_match( '/^\d{2}:\d{2}$/', $booking_time ) ) {
        wp_die( 'Ugyldigt tidspunkt.', 'Fejl', [ 'back_link' => true ] );
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
        'price'          => (string) $raw_price,
        'coupon'         => $final_coupon_code,
        'coupon_price'   => $final_coupon_price,
        'status'         => 'pending',
    ];
    $formats = [ '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ];

    $inserted = $wpdb->insert( $table, $data, $formats );

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

    wp_safe_redirect( add_query_arg( 'booking_id', $booking_id, get_permalink() ) );
    exit;
}

function zb_generate_ics( $booking_id, $data ) {
    $start = date( 'Ymd\THis', strtotime( $data['booking_date'] . ' ' . $data['booking_time'] ) );
    $end   = date( 'Ymd\THis', strtotime( $data['booking_date'] . ' ' . $data['booking_time'] . ' +1 hour' ) );
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
        admin_url( 'admin-post.php?action=zb_booking_action&booking_id=' . $booking_id . '&status=Accepted' ),
        'zb_booking_action_' . $booking_id
    );
    $reject_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=zb_booking_action&booking_id=' . $booking_id . '&status=Rejected' ),
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
