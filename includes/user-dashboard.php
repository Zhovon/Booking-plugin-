<?php

defined( 'ABSPATH' ) || exit;

add_action( 'init',                                       'zb_add_bookings_endpoint' );
add_filter( 'woocommerce_account_menu_items',             'zb_add_bookings_menu_item' );
add_action( 'woocommerce_account_user-bookings_endpoint', 'zb_render_bookings_tab' );
add_shortcode( 'zb_dashboard', 'zb_render_bookings_tab' );
add_action( 'init', 'zb_handle_reschedule_request' );

function zb_handle_reschedule_request() {
    if ( ! isset( $_GET['zb_reschedule'] ) ) return;
    $id = absint( $_GET['zb_reschedule'] );
    if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'zb_reschedule_' . $id ) ) return;
    
    global $wpdb;
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}zb_bookings WHERE id = %d AND user_id = %d", $id, get_current_user_id() ) );
    if ( ! $booking ) return;

    $subject = '[RE-SCHEDULING REQUEST] #' . $id . ' – ' . $booking->company_name;
    $body    = "A customer has requested to reschedule booking #{$id}.\n\n";
    $body   .= "Address: " . $booking->address . "\n";
    $body   .= "Current Date: " . $booking->booking_date . " " . $booking->booking_time . "\n";
    $body   .= "\nPlease contact the customer at " . $booking->email . " to arrange a new time.\n";
    $body   .= "\nManage bookings: " . admin_url('admin.php?page=zb-show-bookings');

    wp_mail( get_option('admin_email'), $subject, $body );
    
    wp_safe_redirect( add_query_arg( 'zb_msg', 'reschedule_sent', wp_get_referer() ) );
    exit;
}

function zb_add_bookings_endpoint() {
    add_rewrite_endpoint( 'user-bookings', EP_ROOT | EP_PAGES );
}

function zb_add_bookings_menu_item( $items ) {
    $new_items = [];
    foreach ( $items as $key => $label ) {
        $new_items[ $key ] = $label;
        if ( $key === 'dashboard' ) {
            $new_items['user-bookings'] = 'Mine bookinger';
        }
    }
    return $new_items;
}

function zb_render_bookings_tab() {
    if ( ! is_user_logged_in() ) {
        echo '<p>Log venligst ind for at se dine bookinger.</p>';
        return;
    }
    $user = wp_get_current_user();
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        zb_render_admin_bookings_table();
    } else {
        zb_render_customer_bookings_table();
    }
}

function zb_render_customer_bookings_table() {
    global $wpdb;
    $user_id  = get_current_user_id();
    $table    = $wpdb->prefix . 'zb_bookings';
    $bookings = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY booking_date DESC", $user_id )
    );
    $currency  = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : 'kr';
    $st_labels = [
        'pending'  => '<span style="color:#b45309;">Afventer bekræftelse</span>',
        'Accepted' => '<span style="color:#15803d;font-weight:600;">✅ Bekræftet</span>',
        'Rejected' => '<span style="color:#b91c1c;">❌ Afvist</span>',
    ];
    ?>
    <h2 style="font-size:20px;margin-bottom:16px;">Mine bookinger</h2>
    <?php if ( $bookings ) : ?>
    <div style="overflow-x:auto;">
        <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table">
            <thead>
                <tr>
                    <th>ID</th><th>Adresse</th><th>Ydelser</th>
                    <th>Dato / Tid</th><th>Pris ekskl. moms</th><th>Status</th><th>Handling</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $bookings as $b ) :
                    $st = $st_labels[ $b->status ] ?? esc_html( $b->status );
                ?>
                <tr>
                    <td>#<?php echo absint( $b->id ); ?></td>
                    <td><?php echo esc_html( $b->address ); ?></td>
                    <td><?php echo esc_html( $b->services ); ?></td>
                    <td><?php echo esc_html( $b->booking_date . ' ' . $b->booking_time ); ?></td>
                    <td>
                        <?php if ( $b->coupon_price ) : ?>
                            <s style="color:#999;"><?php echo esc_html( $b->price ); ?></s>
                            <?php echo esc_html( $b->coupon_price ); ?> <?php echo esc_html( $currency ); ?>
                        <?php else : ?>
                            <?php echo esc_html( $b->price ); ?> <?php echo esc_html( $currency ); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo wp_kses( $st, [ 'span' => [ 'style' => [] ] ] ); ?></td>
                    <td>
                        <?php if ( $b->status === 'pending' || $b->status === 'Accepted' ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'zb_reschedule', $b->id, wp_get_referer() ), 'zb_reschedule_' . $b->id ) ); ?>" 
                           style="font-size:12px; color:#4a7c59; text-decoration:none; font-weight:600;">Request Reschedule</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else : ?>
        <p style="color:#888;">
            Ingen bookinger fundet.
            <a href="<?php echo esc_url( site_url( '/bookings/' ) ); ?>">Lav din første booking →</a>
        </p>
    <?php endif;
}

function zb_bookings_page_admin() {
    zb_render_admin_bookings_table();
}

function zb_render_admin_bookings_table() {
    global $wpdb;
    $table    = $wpdb->prefix . 'zb_bookings';
    $currency = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : 'kr';

    if ( isset( $_GET['zb_notice'] ) ) {
        $notices = [
            'confirmed'   => 'Booking bekræftet og kundemail sendt.',
            'rejected'    => 'Booking afvist og kundemail sendt.',
            'already_set' => 'Status var allerede opdateret.',
        ];
        $msg = $notices[ sanitize_key( $_GET['zb_notice'] ) ] ?? '';
        if ( $msg ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
        }
    }

    $bookings = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 500" );
    ?>
    <div class="wrap">
    <h1 class="wp-heading-inline">Zbooking – Alle bookinger</h1>
    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=zb_run_manual_migration' ) ); ?>" class="button secondary" style="margin-left:10px;">Restore / Migrate Old Data</a>
    <?php if ( $bookings ) : ?>
    <div style="overflow-x:auto;margin-top:16px;">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th><th>Firma</th><th>Kontaktperson</th><th>E-mail</th>
                    <th>Adresse</th><th>Ydelser</th><th>Dato / Tid</th>
                    <th>Pris</th><th>Status</th><th>Handling</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $bookings as $b ) : ?>
                <tr data-id="<?php echo absint( $b->id ); ?>"
                    data-company="<?php echo esc_attr( $b->company_name ); ?>"
                    data-contact="<?php echo esc_attr( $b->contact_person ); ?>"
                    data-email="<?php echo esc_attr( $b->email ); ?>"
                    data-address="<?php echo esc_attr( $b->address ); ?>"
                    data-services="<?php echo esc_attr( $b->services ); ?>"
                    data-status="<?php echo esc_attr( $b->status ); ?>">
                    <td>#<?php echo absint( $b->id ); ?></td>
                    <td><?php echo esc_html( $b->company_name ); ?></td>
                    <td><?php echo esc_html( $b->contact_person ); ?></td>
                    <td><a href="mailto:<?php echo esc_attr( $b->email ); ?>"><?php echo esc_html( $b->email ); ?></a></td>
                    <td><?php echo esc_html( $b->address ); ?></td>
                    <td><?php echo esc_html( $b->services ); ?></td>
                    <td><?php echo esc_html( $b->booking_date . ' ' . $b->booking_time ); ?></td>
                    <td>
                        <?php echo esc_html( $b->price ); ?> <?php echo esc_html( $currency ); ?>
                        <?php if ( $b->coupon_price ) : ?>
                            <br><small style="color:#15803d;">Rabat: <?php echo esc_html( $b->coupon_price ); ?> <?php echo esc_html( $currency ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="zb-status-cell"><?php echo esc_html( $b->status ); ?></td>
                    <td><button class="zb-edit-btn button button-small">Rediger</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else : ?>
        <p style="color:#888;margin-top:16px;">Ingen bookinger modtaget endnu.</p>
    <?php endif; ?>
    </div>

    <div id="zbModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
         background:rgba(0,0,0,0.55); z-index:99999; justify-content:center; align-items:center;">
        <div style="background:#fff; padding:28px; border-radius:10px; width:440px; max-width:96vw;
                    box-shadow:0 8px 40px rgba(0,0,0,.25);">
            <h2 style="margin:0 0 16px; font-size:18px;">Rediger booking</h2>
            <form id="zbEditForm">
                <input type="hidden" name="booking_id" id="zb_bid">
                <p><strong>Firma:</strong> <span id="zb_modal_company"></span></p>
                <p><strong>E-mail:</strong> <span id="zb_modal_email"></span></p>
                <p><strong>Adresse:</strong> <span id="zb_modal_address"></span></p>
                <p><strong>Ydelser:</strong> <span id="zb_modal_services"></span></p>
                <p>
                    <label for="zb_modal_status" style="font-weight:600; float:none;">Status:</label><br>
                    <select name="status" id="zb_modal_status" style="width:100%; margin-top:6px; padding:8px;">
                        <option value="pending">Afventer bekræftelse</option>
                        <option value="Accepted">Bekræftet</option>
                        <option value="Rejected">Afvist</option>
                    </select>
                </p>
                <div style="display:flex; gap:8px; margin-top:16px;">
                    <button type="submit" class="button button-primary">Gem status</button>
                    <button type="button" id="zbCloseModal" class="button">Luk</button>
                </div>
            </form>
        </div>
    </div>
    <style>
        #zbModal { display:none; }
        #zbModal.zb-open { display:flex !important; }
    </style>
    <?php
}

add_action( 'wp_ajax_zb_update_status', 'zb_ajax_update_status' );
add_action( 'admin_enqueue_scripts',    'zb_conditionally_enqueue_admin_js' );

function zb_conditionally_enqueue_admin_js( $hook ) {
    if ( $hook !== 'toplevel_page_zb-show-bookings' ) {
        return;
    }
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script(
        'zb-admin-js',
        ZB_URL . 'assets/zb-admin.js',
        [ 'jquery' ],
        ZB_VERSION,
        true
    );
    wp_localize_script( 'zb-admin-js', 'zb_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'zb_update_status_nonce' ),
    ] );
}

function zb_ajax_update_status() {
    check_ajax_referer( 'zb_update_status_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Ingen adgang.' );
    }

    $id      = absint( $_POST['booking_id'] );
    $status  = sanitize_text_field( $_POST['status'] );
    $allowed = [ 'pending', 'Accepted', 'Rejected' ];

    if ( ! in_array( $status, $allowed, true ) ) {
        wp_send_json_error( 'Ugyldig status.' );
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'zb_bookings';
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

    if ( ! $booking ) {
        wp_send_json_error( 'Booking ikke fundet.' );
    }

    $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );

    if ( $status !== 'pending' ) {
        zb_send_status_email( $booking, $status );
    }

    wp_send_json_success( [ 'status' => $status ] );
}
