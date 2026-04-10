<?php

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'zb_admin_menu' );
add_action( 'admin_menu', 'zb_add_main_menu' );

function zb_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=product',
        'Zbooking – Services',
        'Booking Services',
        'manage_options',
        'zb-addons',
        'zb_render_addons_page'
    );
}

function zb_add_main_menu() {
    add_menu_page(
        'Zbooking',
        'Zbooking',
        'manage_options',
        'zb-show-bookings',
        'zb_bookings_page_admin',
        'dashicons-calendar-alt',
        56
    );
}

function zb_render_addons_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'zb_addons';

    if ( isset( $_POST['zb_delete_addon'] ) ) {
        check_admin_referer( 'zb_delete_addon_action' );
        $wpdb->delete( $table, [ 'id' => absint( $_POST['addon_id'] ) ], [ '%d' ] );
        echo '<div class="notice notice-success is-dismissible"><p>Service slettet.</p></div>';
    }

    if ( isset( $_POST['zb_add_addon'] ) ) {
        check_admin_referer( 'zb_add_addon_action' );
        $wpdb->insert(
            $table,
            [
                'title'       => sanitize_text_field( $_POST['title'] ),
                'description' => sanitize_textarea_field( $_POST['description'] ),
                'time'        => absint( $_POST['time'] ),
                'price'       => floatval( $_POST['price'] ),
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%d', '%f', '%s' ]
        );
        echo '<div class="notice notice-success is-dismissible"><p>Service tilføjet.</p></div>';
    }

    $addons = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
    ?>
    <div class="wrap">
        <h1>Zbooking – Services / Addons</h1>

        <h2>Tilføj ny service</h2>
        <form method="post" style="max-width:520px;background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:8px;margin-bottom:30px;">
            <?php wp_nonce_field( 'zb_add_addon_action' ); ?>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th><label for="zb_addon_title">Servicenavn</label></th>
                    <td><input id="zb_addon_title" type="text" name="title" class="regular-text" placeholder="f.eks. Ordance" required></td>
                </tr>
                <tr>
                    <th><label for="zb_addon_desc">Beskrivelse</label></th>
                    <td><textarea id="zb_addon_desc" name="description" class="regular-text" rows="2" placeholder="Kort beskrivelse (valgfrit)"></textarea></td>
                </tr>
                <tr>
                    <th><label for="zb_addon_time">Varighed (min)</label></th>
                    <td><input id="zb_addon_time" type="number" name="time" class="small-text" min="1" placeholder="40" required></td>
                </tr>
                <tr>
                    <th><label for="zb_addon_price">Pris (ekskl. moms, kr)</label></th>
                    <td><input id="zb_addon_price" type="number" name="price" class="small-text" min="0" step="0.01" placeholder="500" required></td>
                </tr>
            </table>
            <button type="submit" name="zb_add_addon" class="button button-primary" style="margin-top:12px;">
                + Tilføj service
            </button>
        </form>

        <h2>Eksisterende services</h2>
        <?php if ( $addons ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th><th>Servicenavn</th><th>Beskrivelse</th>
                    <th>Varighed (min)</th><th>Pris (ekskl. moms)</th>
                    <th>Oprettet</th><th>Handling</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $addons as $a ) : ?>
                <tr>
                    <td><?php echo absint( $a->id ); ?></td>
                    <td><?php echo esc_html( $a->title ); ?></td>
                    <td><?php echo esc_html( $a->description ); ?></td>
                    <td><?php echo absint( $a->time ); ?> min</td>
                    <td><?php echo number_format( floatval( $a->price ), 2, ',', '.' ); ?> kr</td>
                    <td><?php echo esc_html( $a->created_at ); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field( 'zb_delete_addon_action' ); ?>
                            <input type="hidden" name="addon_id" value="<?php echo absint( $a->id ); ?>">
                            <button name="zb_delete_addon" class="button button-small"
                                    onclick="return confirm('Slet denne service?');">Slet</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
            <p>Ingen services oprettet endnu.</p>
        <?php endif; ?>
    </div>
    <?php
}

add_action( 'admin_post_zb_booking_action', 'zb_handle_email_booking_action' );

function zb_handle_email_booking_action() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Ingen adgang.', 'Adgang nægtet', [ 'response' => 403 ] );
    }

    $booking_id = absint( $_GET['booking_id'] ?? 0 );
    $allowed    = [ 'Accepted', 'Rejected' ];
    $status     = in_array( $_GET['status'] ?? '', $allowed, true )
                  ? sanitize_text_field( $_GET['status'] )
                  : '';

    if ( ! $booking_id || ! $status ) {
        wp_die( 'Ugyldig anmodning.', 'Fejl', [ 'back_link' => true ] );
    }
    if ( ! isset( $_GET['_wpnonce'] ) ||
         ! wp_verify_nonce( $_GET['_wpnonce'], 'zb_booking_action_' . $booking_id ) ) {
        wp_die( 'Sikkerhedstjek mislykkedes eller linket er udløbet.', 'Nonce fejl', [ 'back_link' => true ] );
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'zb_bookings';
    $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $booking_id ) );

    if ( ! $booking ) {
        wp_die( 'Booking ikke fundet.', 'Fejl', [ 'back_link' => true ] );
    }
    if ( $booking->status === $status ) {
        wp_safe_redirect( add_query_arg(
            [ 'page' => 'zb-show-bookings', 'zb_notice' => 'already_set' ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $booking_id ], [ '%s' ], [ '%d' ] );
    zb_send_status_email( $booking, $status );

    $notice = $status === 'Accepted' ? 'confirmed' : 'rejected';
    wp_safe_redirect( add_query_arg(
        [ 'page' => 'zb-show-bookings', 'zb_notice' => $notice, 'booking_id' => $booking_id ],
        admin_url( 'admin.php' )
    ) );
    exit;
}

function zb_send_status_email( $booking, $status ) {
    $currency  = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : 'kr';
    $site_name = get_bloginfo( 'name' ) ?: 'homefoto';
    $sep       = str_repeat( '═', 42 );

    if ( $status === 'Accepted' ) {
        $subject = '✅ Din booking er bekræftet – faktura afventer fotografering | ' . $site_name;
        $body    = "Hej {$booking->contact_person},\n\n";
        $body   .= "Din booking er nu bekræftet! Vi ser frem til at fotografere ejendommen.\n\n";
        $body   .= "{$sep}\nBOOKING-BEKRÆFTELSE\n{$sep}\n";
        $body   .= "Booking-ID:          #{$booking->id}\n";
        $body   .= "Firmanavn:           {$booking->company_name}\n";
        $body   .= "Booket af:           {$booking->booked_by}\n";
        $body   .= "Ejendomsadresse:     {$booking->address}\n";
        $body   .= "Valgte services:     {$booking->services}\n";
        $body   .= "Dato:                {$booking->booking_date}\n";
        $body   .= "Tidspunkt:           {$booking->booking_time}\n";
        $body   .= "Pris ekskl. moms:    {$booking->price} {$currency}\n";
        if ( ! empty( $booking->coupon_price ) ) {
            $body .= "Pris m. rabat:       {$booking->coupon_price} {$currency}\n";
        }
        $body .= "Betalingsstatus:     Afventer betaling\n";
        $body .= "{$sep}\n\n";
        $body .= "Betaling sker efter fotografering. Du modtager en faktura på din e-mail.\n\n";
        $body .= "Med venlig hilsen\n{$site_name}\nbooking@homefoto.dk\nhomefoto.dk";
    } else {
        $subject = 'Din booking-anmodning – opdatering | ' . $site_name;
        $body    = "Hej {$booking->contact_person},\n\n";
        $body   .= "Desværre kan vi ikke bekræfte din booking-anmodning for:\n";
        $body   .= "Ejendomsadresse: {$booking->address} den {$booking->booking_date}.\n\n";
        $body   .= "Vi beklager ulejligheden. Kontakt os på booking@homefoto.dk for at finde et nyt tidspunkt.\n\n";
        $body   .= "Med venlig hilsen\n{$site_name}\nbooking@homefoto.dk\nhomefoto.dk";
    }

    wp_mail( $booking->email, $subject, $body );
}
