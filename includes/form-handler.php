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

    $admin_subject = '[NY ANMODNING #' . $booking_id . '] ' . $data['company_name'] . ' – ' . wp_strip_all_tags( $data['address'] );
    $admin_body    = "Ny booking-anmodning modtaget (ID: #{$booking_id})\n\n";
    $admin_body   .= "{$sep}\nKUNDEOPLYSNINGER\n{$sep}\n";
    $admin_body   .= "Firmanavn:           {$data['company_name']}\n";
    $admin_body   .= "Kontaktperson:       {$data['contact_person']}\n";
    $admin_body   .= "Booket af:           {$data['booked_by']}\n";
    $admin_body   .= "E-mail:              {$data['email']}\n";
    $admin_body   .= "Telefon:             {$data['phone']}\n";
    $admin_body   .= "\n{$sep}\nBOOKINGDETALJER\n{$sep}\n";
    $admin_body   .= "Ejendomsadresse:     {$data['address']}\n";
    if ( ! empty( $data['seller_contact'] ) ) {
        $admin_body .= "Sælgers kontakt:     {$data['seller_contact']}\n";
    }
    $admin_body .= "Valgte services:     {$data['services']}\n";
    $admin_body .= "Ønsket dato:         {$data['booking_date']}\n";
    $admin_body .= "Ønsket tidspunkt:    {$data['booking_time']}\n";
    $admin_body .= "Pris ekskl. moms:    {$data['price']} {$currency}\n";
    if ( ! empty( $data['coupon'] ) ) {
        $admin_body .= "Rabatkode:           {$data['coupon']}\n";
        $admin_body .= "Pris m. rabat:       {$data['coupon_price']} {$currency}\n";
    }
    if ( ! empty( $data['comments'] ) ) {
        $admin_body .= "Kommentarer:         {$data['comments']}\n";
    }
    $admin_body .= "\n{$sep}\nHANDLING PÅKRÆVET\n{$sep}\n\n";
    $admin_body .= "✅  BEKRÆFT booking:\n{$confirm_url}\n\n";
    $admin_body .= "❌  AFVIS booking:\n{$reject_url}\n\n";
    $admin_body .= "── Alle bookinger:\n" . admin_url( 'admin.php?page=zb-show-bookings' ) . "\n";

    wp_mail( get_option( 'admin_email' ), $admin_subject, $admin_body );

    $customer_subject = 'Din booking-anmodning er modtaget – ' . $site_name;
    $customer_body    = "Hej {$data['contact_person']},\n\n";
    $customer_body   .= "Tak for din booking-anmodning hos {$site_name}.\n";
    $customer_body   .= "Vi bekræfter hermed, at vi har modtaget din anmodning og behandler den snarest.\n";
    $customer_body   .= "Du vil modtage en endelig bekræftelse pr. e-mail.\n\n";
    $customer_body   .= "{$sep}\nBOOKING-ANMODNING\n{$sep}\n";
    $customer_body   .= "Booking-ID:          #{$booking_id}\n";
    $customer_body   .= "Firmanavn:           {$data['company_name']}\n";
    $customer_body   .= "Booket af:           {$data['booked_by']}\n";
    $customer_body   .= "Ejendomsadresse:     {$data['address']}\n";
    $customer_body   .= "Valgte services:     {$data['services']}\n";
    $customer_body   .= "Ønsket dato:         {$data['booking_date']}\n";
    $customer_body   .= "Ønsket tidspunkt:    {$data['booking_time']}\n";
    $customer_body   .= "Pris ekskl. moms:    {$data['price']} {$currency}\n";
    if ( ! empty( $data['coupon'] ) ) {
        $customer_body .= "Rabat ({$data['coupon']}):    {$data['coupon_price']} {$currency}\n";
    }
    $customer_body .= "Status:              Afventer bekræftelse\n";
    $customer_body .= "{$sep}\n\n";
    $customer_body .= "Betaling sker efter fotografering.\n\n";
    $admin_email = get_option( 'admin_email' );
    $customer_body .= "Med venlig hilsen\n{$site_name}\n{$admin_email}\n" . home_url();

    wp_mail( $data['email'], $customer_subject, $customer_body );
}
