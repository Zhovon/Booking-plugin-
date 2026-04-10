<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}zb_bookings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}zb_addons" );

$users = get_users( [ 'fields' => 'ID' ] );
foreach ( $users as $user_id ) {
    delete_user_meta( $user_id, 'company_name' );
    delete_user_meta( $user_id, 'contact_person' );
    delete_user_meta( $user_id, 'phone' );
    delete_user_meta( $user_id, 'address' );
    delete_user_meta( $user_id, 'cvr' );
}

delete_option( 'zb_version' );
