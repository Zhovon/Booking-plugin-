<?php

defined( 'ABSPATH' ) || exit;

function zb_create_booking_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_bookings = $wpdb->prefix . 'zb_bookings';
    $sql_bookings   = "CREATE TABLE {$table_bookings} (
        id             BIGINT        NOT NULL AUTO_INCREMENT,
        user_id        BIGINT        NOT NULL DEFAULT 0,
        company_name   VARCHAR(255)  NOT NULL DEFAULT '',
        contact_person VARCHAR(255)  NOT NULL DEFAULT '',
        booked_by      VARCHAR(255)  NOT NULL DEFAULT '',
        email          VARCHAR(255)  NOT NULL DEFAULT '',
        phone          VARCHAR(50)   NOT NULL DEFAULT '',
        price          VARCHAR(50)            DEFAULT NULL,
        coupon_price   VARCHAR(50)            DEFAULT NULL,
        coupon         VARCHAR(100)           DEFAULT NULL,
        address        TEXT,
        seller_contact TEXT,
        services       TEXT,
        comments       TEXT,
        booking_date   DATE,
        booking_time   VARCHAR(10)            DEFAULT NULL,
        duration_minutes SMALLINT     NOT NULL DEFAULT 60,
        timeslot_start DATETIME                DEFAULT NULL,
        timeslot_end   DATETIME                DEFAULT NULL,
        outlook_event_id VARCHAR(191)          DEFAULT NULL,
        google_event_id  VARCHAR(191)          DEFAULT NULL,
        status         VARCHAR(20)   NOT NULL DEFAULT 'pending',
        created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id    (user_id),
        KEY status     (status),
        KEY booking_date (booking_date),
        KEY timeslot_start (timeslot_start),
        KEY timeslot_end (timeslot_end)
    ) {$charset_collate};";
    dbDelta( $sql_bookings );

    $table_addons = $wpdb->prefix . 'zb_addons';
    $sql_addons   = "CREATE TABLE {$table_addons} (
        id          BIGINT        NOT NULL AUTO_INCREMENT,
        title       VARCHAR(255)  NOT NULL DEFAULT '',
        description TEXT,
        time        SMALLINT      NOT NULL DEFAULT 0,
        price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";
    dbDelta( $sql_addons );

    zb_migrate_osf_data();
    zb_maybe_inject_demo_data();
}

function zb_migrate_osf_data() {
    global $wpdb;

    $old_addons   = $wpdb->prefix . 'osf_addons';
    $new_addons   = $wpdb->prefix . 'zb_addons';
    $old_bookings = $wpdb->prefix . 'osf_bookings';
    $new_bookings = $wpdb->prefix . 'zb_bookings';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_addons'" ) === $old_addons ) {
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $new_addons" );
        if ( (int) $count === 0 ) {
            $data = $wpdb->get_results( "SELECT * FROM $old_addons", ARRAY_A );
            foreach ( $data as $row ) {
                unset( $row['id'] );
                $wpdb->insert( $new_addons, $row );
            }
        }
    }

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_bookings'" ) === $old_bookings ) {
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $new_bookings" );
        if ( (int) $count === 0 ) {
            $data = $wpdb->get_results( "SELECT * FROM $old_bookings", ARRAY_A );
            foreach ( $data as $row ) {
                $mapped = [
                    'user_id'        => absint( $row['user_id'] ?? 0 ),
                    'company_name'   => sanitize_text_field( $row['osf_company'] ?? $row['company_name'] ?? '' ),
                    'contact_person' => sanitize_text_field( $row['osf_contact'] ?? $row['contact_person'] ?? '' ),
                    'booked_by'      => sanitize_text_field( $row['booked_by'] ?? '' ),
                    'email'          => sanitize_email( $row['email'] ?? '' ),
                    'phone'          => sanitize_text_field( $row['phone'] ?? '' ),
                    'price'          => sanitize_text_field( $row['price'] ?? '' ),
                    'coupon_price'   => sanitize_text_field( $row['coupon_price'] ?? '' ),
                    'coupon'         => sanitize_text_field( $row['coupon'] ?? '' ),
                    'address'        => sanitize_textarea_field( $row['address'] ?? '' ),
                    'seller_contact' => sanitize_textarea_field( $row['seller_contact'] ?? '' ),
                    'services'       => sanitize_textarea_field( $row['services'] ?? '' ),
                    'comments'       => sanitize_textarea_field( $row['comments'] ?? '' ),
                    'booking_date'   => sanitize_text_field( $row['booking_date'] ?? '' ),
                    'booking_time'   => sanitize_text_field( $row['booking_time'] ?? '' ),
                    'duration_minutes' => absint( $row['duration_minutes'] ?? 60 ),
                    'timeslot_start' => sanitize_text_field( $row['timeslot_start'] ?? '' ),
                    'timeslot_end'   => sanitize_text_field( $row['timeslot_end'] ?? '' ),
                    'outlook_event_id' => sanitize_text_field( $row['outlook_event_id'] ?? '' ),
                    'google_event_id'  => sanitize_text_field( $row['google_event_id'] ?? '' ),
                    'status'         => sanitize_text_field( $row['status'] ?? 'pending' ),
                    'created_at'     => sanitize_text_field( $row['created_at'] ?? current_time('mysql') ),
                ];
                $wpdb->insert( $new_bookings, $mapped );
            }
        }
    }
}

function zb_maybe_inject_demo_data() {
    global $wpdb;
    $table = $wpdb->prefix . 'zb_addons';
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

    if ( (int) $count === 0 ) {
        $demos = [
            [ 'title' => 'Ordance',  'time' => 40, 'price' => 500.00 ],
            [ 'title' => 'Panotagning', 'time' => 30, 'price' => 700.00 ],
            [ 'title' => 'Pakke 1',  'time' => 60, 'price' => 1500.00 ],
        ];
        foreach ( $demos as $d ) {
            $wpdb->insert( $table, [
                'title'      => $d['title'],
                'time'       => $d['time'],
                'price'      => $d['price'],
                'created_at' => current_time( 'mysql' )
            ] );
        }
    }
}
