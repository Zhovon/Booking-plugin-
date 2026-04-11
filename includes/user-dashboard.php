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

    $subject  = '[RE-SCHEDULING REQUEST] #' . $id . ' – ' . $booking->company_name;
    $body     = "A customer has requested to reschedule booking #{$id}.\n\n";
    $body    .= 'Address: ' . $booking->address . "\n";
    $body    .= 'Current Date: ' . $booking->booking_date . ' ' . $booking->booking_time . "\n";
    $body    .= "\nPlease contact the customer at " . $booking->email . " to arrange a new time.\n";
    $body    .= "\nManage bookings: " . admin_url( 'admin.php?page=zb-show-bookings' );

    wp_mail( get_option( 'admin_email' ), $subject, $body );
    wp_safe_redirect( add_query_arg( 'zb_msg', 'reschedule_sent', site_url( '/my-account-2/' ) ) );
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

    $user = wp_get_current_user();
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        zb_render_admin_bookings_table();
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
    $currency  = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : 'kr';
    $st_labels = [
        'pending'  => '<span style="color:#b45309;">Afventer bekræftelse</span>',
        'Accepted' => '<span style="color:#15803d;font-weight:600;">&#10003; Bekræftet</span>',
        'Rejected' => '<span style="color:#b91c1c;">&#10007; Afvist</span>',
    ];

    echo '<h2 style="font-size:20px;margin-bottom:16px;">Mine bookinger</h2>';

    if ( $bookings ) {
        echo '<div style="overflow-x:auto;">';
        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table">';
        echo '<thead><tr><th>ID</th><th>Adresse</th><th>Ydelser</th><th>Dato / Tid</th><th>Pris ekskl. moms</th><th>Status</th><th>Handling</th></tr></thead>';
        echo '<tbody>';
        foreach ( $bookings as $b ) {
            $st  = $st_labels[ $b->status ] ?? esc_html( $b->status );
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
            if ( 'pending' === $b->status || 'Accepted' === $b->status ) {
                echo '<a href="' . esc_url( $nonce_url ) . '" style="font-size:12px;color:#4a7c59;text-decoration:none;font-weight:600;margin-right:12px;">Anmod om ny tid</a>';
            }
            if ( 'Accepted' === $b->status ) {
                echo '<a href="' . esc_url( home_url( '?zb_invoice=' . $b->id ) ) . '" target="_blank" style="font-size:12px;color:#4a7c59;text-decoration:none;font-weight:600;">Vis faktura</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p style="color:#888;">Ingen bookinger fundet. <a href="' . esc_url( site_url( '/bookings/' ) ) . '">Lav din første booking &rarr;</a></p>';
    }
}

/* =========================================================
 * ADMIN BOOKINGS TABLE
 * ========================================================= */
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

        <div id="zbModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:99999;justify-content:center;align-items:center;">
            <div style="background:#fff;padding:28px;border-radius:10px;width:440px;max-width:96vw;box-shadow:0 8px 40px rgba(0,0,0,.25);">
                <h2 style="margin:0 0 16px;font-size:18px;">Rediger booking</h2>
                <form id="zbEditForm">
                    <input type="hidden" name="booking_id" id="zb_bid">
                    <p><strong>Firma:</strong> <span id="zb_modal_company"></span></p>
                    <p><strong>E-mail:</strong> <span id="zb_modal_email"></span></p>
                    <p><strong>Adresse:</strong> <span id="zb_modal_address"></span></p>
                    <p><strong>Ydelser:</strong> <span id="zb_modal_services"></span></p>
                    <p>
                        <label for="zb_modal_status" style="font-weight:600;float:none;">Status:</label><br>
                        <select name="status" id="zb_modal_status" style="width:100%;margin-top:6px;padding:8px;">
                            <option value="pending">Afventer bekræftelse</option>
                            <option value="Accepted">Bekræftet</option>
                            <option value="Rejected">Afvist</option>
                        </select>
                    </p>
                    <div style="display:flex;gap:8px;margin-top:16px;">
                        <button type="submit" class="button button-primary">Gem status</button>
                        <button type="button" id="zbCloseModal" class="button">Luk</button>
                    </div>
                </form>
            </div>
        </div>
        <style>#zbModal{display:none}#zbModal.zb-open{display:flex!important}</style>
    </div>
    <?php
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

    if ( 'pending' !== $status && function_exists( 'zb_send_status_email' ) ) {
        zb_send_status_email( $booking, $status );
    }

    wp_send_json_success( [ 'status' => $status ] );
}
