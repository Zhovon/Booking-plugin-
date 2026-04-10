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
        status         VARCHAR(20)   NOT NULL DEFAULT 'pending',
        created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id    (user_id),
        KEY status     (status),
        KEY booking_date (booking_date)
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
}
