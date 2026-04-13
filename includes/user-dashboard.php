<?php
/**
 * Zbooking – User Dashboard & Admin Table
 * Clean rewrite – zero duplicate functions.
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================
 * HOOKS
 * ========================================================= */
add_action( 'init',                                       'zb_add_bookings_endpoint' );
add_filter( 'woocommerce_account_menu_items',             'zb_add_bookings_menu_item' );
add_action( 'woocommerce_account_user-bookings_endpoint', 'zb_render_bookings_tab' );
add_shortcode( 'zb_dashboard',                            'zb_render_bookings_tab' );
add_action( 'init',                                       'zb_handle_reschedule_request' );
add_action( 'wp_ajax_zb_update_status',                   'zb_ajax_update_status' );
add_action( 'admin_enqueue_scripts',                      'zb_conditionally_enqueue_admin_js' );

/* =========================================================
 * RESCHEDULE REQUEST
 * ========================================================= */
function zb_handle_reschedule_request() {
    if ( ! isset( $_GET['zb_reschedule'] ) ) {
        return;
    }
    $id = absint( $_GET['zb_reschedule'] );
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'zb_reschedule_' . $id ) ) {
        return;
    }

    global $wpdb;
    $booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zb_bookings WHERE id = %d AND user_id = %d",
            $id,
            get_current_user_id()
        )
    );
    if ( ! $booking ) {
        return;
    }

    wp_safe_redirect(
        add_query_arg(
            [
                'booking_id' => $id,
                'reschedule' => 1,
            ],
            zb_get_booking_url()
        )
    );
    exit;
}

/* =========================================================
 * WOO ENDPOINT
 * ========================================================= */
function zb_add_bookings_endpoint() {
    add_rewrite_endpoint( 'user-bookings', EP_ROOT | EP_PAGES );
}

function zb_add_bookings_menu_item( $items ) {
    $new_items = [];
    foreach ( $items as $key => $label ) {
        $new_items[ $key ] = $label;
        if ( 'dashboard' === $key ) {
            $new_items['user-bookings'] = 'Mine bookinger';
        }
    }
    return $new_items;
}

/* =========================================================
 * MAIN DASHBOARD SHORTCODE / ENDPOINT
 * ========================================================= */
function zb_render_bookings_tab() {
    if ( ! is_user_logged_in() ) {
        echo '<p>Log venligst ind for at se dine bookinger.</p>';
        return;
    }

    $active_tab = isset( $_GET['zb_tab'] ) ? sanitize_key( $_GET['zb_tab'] ) : 'bookings';
    ?>
    <style>
        .zb-tabs{display:flex;gap:20px;border-bottom:1px solid #e5e7eb;margin-bottom:30px}
        .zb-tab-link{padding:12px 4px;font-size:14px;font-weight:600;color:#6b7280;border-bottom:2px solid transparent;text-decoration:none;transition:.2s}
        .zb-tab-link:hover{color:#4a7c59}
        .zb-tab-link.active{color:#4a7c59;border-bottom-color:#4a7c59}
        .zb-dashboard-card{background:#fff;border-radius:12px;padding:30px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
    </style>
    <div class="zb-tabs">
        <a href="<?php echo esc_url( add_query_arg( 'zb_tab', 'bookings' ) ); ?>" class="zb-tab-link <?php echo 'bookings' === $active_tab ? 'active' : ''; ?>">Bookinger</a>
        <a href="<?php echo esc_url( add_query_arg( 'zb_tab', 'profile' ) ); ?>"  class="zb-tab-link <?php echo 'profile'  === $active_tab ? 'active' : ''; ?>">Profil</a>
        <a href="<?php echo esc_url( add_query_arg( 'zb_tab', 'security' ) ); ?>" class="zb-tab-link <?php echo 'security' === $active_tab ? 'active' : ''; ?>">Sikkerhed</a>
    </div>
    <div class="zb-dashboard-card">
        <?php
        if ( 'profile' === $active_tab ) {
            zb_render_profile_tab();
        } elseif ( 'security' === $active_tab ) {
            zb_render_security_tab();
        } else {
            zb_render_customer_bookings_table();
        }
        ?>
    </div>
    <?php
}

/* =========================================================
 * CUSTOMER BOOKINGS TABLE
 * ========================================================= */
function zb_render_customer_bookings_table() {
    global $wpdb;
    $user_id  = get_current_user_id();
    $table    = $wpdb->prefix . 'zb_bookings';
    $bookings = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY booking_date DESC", $user_id )
    );
    $currency  = function_exists( 'zb_get_currency_symbol' ) ? zb_get_currency_symbol() : 'kr';
    $st_labels = [
        'pending'  => '<span style="color:#b45309;">Afventer bekræftelse</span>',
        'accepted' => '<span style="color:#15803d;font-weight:600;">&#10003; Bekræftet</span>',
        'rejected' => '<span style="color:#b91c1c;">&#10007; Afvist</span>',
    ];

    echo '<h2 style="font-size:20px;margin-bottom:16px;">Mine bookinger</h2>';

    if ( $bookings ) {
        echo '<div style="overflow-x:auto;">';
        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table">';
        echo '<thead><tr><th>ID</th><th>Adresse</th><th>Ydelser</th><th>Dato / Tid</th><th>Pris ekskl. moms</th><th>Status</th><th>Handling</th></tr></thead>';
        echo '<tbody>';
        foreach ( $bookings as $b ) {
            $normalized_status = function_exists( 'zb_normalize_booking_status' ) ? zb_normalize_booking_status( $b->status ) : strtolower( (string) $b->status );
            $st  = $st_labels[ $normalized_status ] ?? esc_html( $b->status );
            $nonce_url = wp_nonce_url(
                add_query_arg( 'zb_reschedule', $b->id, wp_get_referer() ?: get_permalink() ),
                'zb_reschedule_' . $b->id
            );
            echo '<tr>';
            echo '<td>#' . absint( $b->id ) . '</td>';
            echo '<td>' . esc_html( $b->address ) . '</td>';
            echo '<td>' . esc_html( $b->services ) . '</td>';
            echo '<td>' . esc_html( $b->booking_date . ' ' . $b->booking_time ) . '</td>';
            echo '<td>';
            if ( $b->coupon_price ) {
                echo '<s style="color:#999;">' . esc_html( $b->price ) . '</s> ' . esc_html( $b->coupon_price ) . ' ' . esc_html( $currency );
            } else {
                echo esc_html( $b->price ) . ' ' . esc_html( $currency );
            }
            echo '</td>';
            echo '<td>' . wp_kses( $st, [ 'span' => [ 'style' => [] ] ] ) . '</td>';
            echo '<td>';
            if ( 'pending' === $normalized_status || 'accepted' === $normalized_status ) {
                echo '<a href="' . esc_url( $nonce_url ) . '" style="font-size:12px;color:#4a7c59;text-decoration:none;font-weight:600;margin-right:12px;">Anmod om ny tid</a>';
            }
            if ( 'accepted' === $normalized_status ) {
                echo '<a href="' . esc_url( zb_get_booking_invoice_url( $b->id, $b->email ) ) . '" target="_blank" style="font-size:12px;color:#4a7c59;text-decoration:none;font-weight:600;">Vis faktura</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p style="color:#888;">Ingen bookinger fundet. <a href="' . esc_url( zb_get_booking_url() ) . '">Lav din første booking &rarr;</a></p>';
    }
}

/* =========================================================
 * PROFILE TAB
 * ========================================================= */
function zb_render_profile_tab() {
    $user_id    = get_current_user_id();
    $company    = get_user_meta( $user_id, 'company_name',    true );
    $contact    = get_user_meta( $user_id, 'contact_person',  true );
    $phone      = get_user_meta( $user_id, 'phone',           true );
    $address    = get_user_meta( $user_id, 'address',         true );
    $avatar_id  = get_user_meta( $user_id, 'zb_avatar_id',    true );
    $avatar_url = $avatar_id ? wp_get_attachment_url( $avatar_id ) : '';
    ?>
    <form method="post" enctype="multipart/form-data" class="zb-signup-form" style="box-shadow:none;padding:0;">
        <?php wp_nonce_field( 'zb_update_profile', 'zb_profile_nonce' ); ?>
        <input type="hidden" name="zb_action" value="update_profile">
        <div class="zb-field">
            <label>Profilbillede (valgfrit)</label>
            <?php if ( $avatar_url ) : ?>
                <img src="<?php echo esc_url( $avatar_url ); ?>" style="width:80px;height:80px;object-fit:cover;border-radius:50%;margin-bottom:10px;display:block;">
            <?php endif; ?>
            <input type="file" name="zb_avatar" accept="image/*">
        </div>
        <div class="zb-field"><label>Firmanavn</label><input type="text" name="company_name"   value="<?php echo esc_attr( $company ); ?>"></div>
        <div class="zb-field"><label>Kontaktperson</label><input type="text" name="contact_person" value="<?php echo esc_attr( $contact ); ?>"></div>
        <div class="zb-field"><label>Telefon</label><input type="text" name="phone"   value="<?php echo esc_attr( $phone ); ?>"></div>
        <div class="zb-field"><label>Adresse</label><input type="text" name="address" value="<?php echo esc_attr( $address ); ?>"></div>
        <button type="submit" class="zb-signup-btn">Gem ændringer</button>
    </form>
    <?php
}

/* =========================================================
 * SECURITY TAB
 * ========================================================= */
function zb_render_security_tab() {
    ?>
    <form method="post" class="zb-signup-form" style="box-shadow:none;padding:0;">
        <?php wp_nonce_field( 'zb_update_password', 'zb_pwd_nonce' ); ?>
        <input type="hidden" name="zb_action" value="update_password">
        <div class="zb-field"><label>Ny adgangskode</label><input type="password" name="new_password"     required minlength="8" placeholder="Mindst 8 tegn"></div>
        <div class="zb-field"><label>Bekræft adgangskode</label><input type="password" name="confirm_password" required minlength="8"></div>
        <button type="submit" class="zb-signup-btn">Opdater adgangskode</button>
    </form>
    <?php
}

/* =========================================================
 * ADMIN JS
 * ========================================================= */
function zb_conditionally_enqueue_admin_js( $hook ) {
    if ( 'toplevel_page_zb-show-bookings' !== $hook ) {
        return;
    }
    wp_enqueue_style( 'zb-admin-style', ZB_URL . 'assets/zb-admin.css', [], ZB_VERSION );
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'zb-admin-js', ZB_URL . 'assets/zb-admin.js', [ 'jquery' ], ZB_VERSION, true );
    wp_localize_script( 'zb-admin-js', 'zb_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'zb_update_status_nonce' ),
    ] );
}

/* =========================================================
 * AJAX STATUS UPDATE
 * ========================================================= */
function zb_ajax_update_status() {
    check_ajax_referer( 'zb_update_status_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Ingen adgang.' );
    }

    $id      = absint( $_POST['booking_id'] );
    $status  = function_exists( 'zb_normalize_booking_status' )
        ? zb_normalize_booking_status( sanitize_text_field( $_POST['status'] ?? '' ) )
        : sanitize_text_field( $_POST['status'] );
    $allowed = [ 'pending', 'accepted', 'rejected' ];

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

    if ( 'pending' !== $status && function_exists( 'zb_send_status_email' ) ) {
        zb_send_status_email( $booking, $status );
    }

    if ( function_exists( 'zb_is_status_accepted' ) && zb_is_status_accepted( $status ) && function_exists( 'zb_calendar_create_events_for_booking' ) ) {
        zb_calendar_create_events_for_booking( $id, (array) $booking );
    }

    wp_send_json_success( [ 'status' => $status ] );
}
