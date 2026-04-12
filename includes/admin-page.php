<?php

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'zb_add_main_menu', 5 );
add_action( 'admin_menu', 'zb_admin_menu', 20 );
add_action( 'admin_post_zb_run_manual_migration', 'zb_handle_manual_migration' );
add_action( 'admin_post_zb_save_settings', 'zb_handle_save_settings' );

function zb_handle_manual_migration() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No access.' );
    zb_create_booking_table(); // This calls create and then migrate/inject
    wp_safe_redirect( admin_url( 'admin.php?page=zb-show-bookings' ) );
    exit;
}

function zb_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=product',
        'Zbooking – Services',
        'Booking Services',
        'manage_options',
        'zb-addons',
        'zb_render_addons_page'
    );

    add_submenu_page(
        'zb-show-bookings',
        'Zbooking Settings',
        'Settings',
        'manage_options',
        'zb-settings',
        'zb_render_settings_page'
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

function zb_handle_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No access.' );
    }

    check_admin_referer( 'zb_save_settings' );

    $defaults = zb_get_settings_defaults();
    $stored   = zb_get_settings();

    $settings = [
        'booking_slug'          => sanitize_title( wp_unslash( $_POST['booking_slug'] ?? $defaults['booking_slug'] ) ),
        'login_slug'            => sanitize_title( wp_unslash( $_POST['login_slug'] ?? $defaults['login_slug'] ) ),
        'dashboard_slug'        => sanitize_title( wp_unslash( $_POST['dashboard_slug'] ?? $defaults['dashboard_slug'] ) ),
        'slot_interval_minutes' => max( 5, absint( $_POST['slot_interval_minutes'] ?? $defaults['slot_interval_minutes'] ) ),
        'default_duration'      => max( 15, absint( $_POST['default_duration'] ?? $defaults['default_duration'] ) ),
        'business_start'        => zb_normalize_hhmm( wp_unslash( $_POST['business_start'] ?? $defaults['business_start'] ), $defaults['business_start'] ),
        'business_end'          => zb_normalize_hhmm( wp_unslash( $_POST['business_end'] ?? $defaults['business_end'] ), $defaults['business_end'] ),
        'outlook_enabled'       => empty( $_POST['outlook_enabled'] ) ? 0 : 1,
        'outlook_tenant'        => sanitize_text_field( wp_unslash( $_POST['outlook_tenant'] ?? $defaults['outlook_tenant'] ) ),
        'outlook_client_id'     => sanitize_text_field( wp_unslash( $_POST['outlook_client_id'] ?? '' ) ),
        'outlook_client_secret' => sanitize_text_field( wp_unslash( $_POST['outlook_client_secret'] ?? '' ) ),
        'google_enabled'        => empty( $_POST['google_enabled'] ) ? 0 : 1,
        'google_client_id'      => sanitize_text_field( wp_unslash( $_POST['google_client_id'] ?? '' ) ),
        'google_client_secret'  => sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ?? '' ) ),
    ];

    if ( '' === $settings['booking_slug'] ) {
        $settings['booking_slug'] = $defaults['booking_slug'];
    }
    if ( function_exists( 'zb_is_reserved_woocommerce_slug' ) && zb_is_reserved_woocommerce_slug( $settings['booking_slug'] ) ) {
        $settings['booking_slug'] = $defaults['booking_slug'];
    }
    if ( '' === $settings['login_slug'] ) {
        $settings['login_slug'] = $defaults['login_slug'];
    }
    if ( '' === $settings['dashboard_slug'] ) {
        $settings['dashboard_slug'] = $defaults['dashboard_slug'];
    }

    if ( 0 !== $settings['slot_interval_minutes'] % 5 ) {
        $settings['slot_interval_minutes'] = 15;
    }

    if ( $settings['default_duration'] < $settings['slot_interval_minutes'] ) {
        $settings['default_duration'] = $stored['default_duration'];
    }

    update_option( 'zb_settings', $settings, false );
    flush_rewrite_rules();

    wp_safe_redirect( add_query_arg( [ 'page' => 'zb-settings', 'zb_saved' => '1' ], admin_url( 'admin.php' ) ) );
    exit;
}

function zb_render_settings_page() {
    $settings = zb_get_settings();
    ?>
    <div class="wrap">
        <h1>Zbooking Settings</h1>
        <p class="description" style="margin:8px 0 18px;">Calendar connections are managed only by the site admin from this page.</p>
        <?php if ( isset( $_GET['zb_saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
        <?php elseif ( isset( $_GET['zb_oauth'] ) ) : ?>
            <div class="notice <?php echo 'success' === sanitize_key( $_GET['zb_oauth'] ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( wp_unslash( $_GET['zb_msg'] ?? 'Calendar connection updated.' ) ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:900px;">
            <input type="hidden" name="action" value="zb_save_settings">
            <?php wp_nonce_field( 'zb_save_settings' ); ?>

            <h2>Calendar Connect</h2>
            <p class="description">Calendar sync options for the admin account.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="outlook_enabled">Enable Outlook sync</label></th>
                    <td><label><input id="outlook_enabled" name="outlook_enabled" type="checkbox" value="1" <?php checked( (int) $settings['outlook_enabled'], 1 ); ?>> Use Outlook calendar for conflict checks and event creation</label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="google_enabled">Enable Google sync</label></th>
                    <td><label><input id="google_enabled" name="google_enabled" type="checkbox" value="1" <?php checked( (int) $settings['google_enabled'], 1 ); ?>> Use Google Calendar for conflict checks and event creation</label></td>
                </tr>
            </table>

            <h2>Booking Rules</h2>
            <p class="description">Define slot interval, default duration, and working hours.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="slot_interval_minutes">Slot interval (minutes)</label></th>
                    <td><input id="slot_interval_minutes" name="slot_interval_minutes" type="number" min="5" step="5" value="<?php echo esc_attr( $settings['slot_interval_minutes'] ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="default_duration">Default duration (minutes)</label></th>
                    <td><input id="default_duration" name="default_duration" type="number" min="15" step="15" value="<?php echo esc_attr( $settings['default_duration'] ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="business_start">Business start</label></th>
                    <td><input id="business_start" name="business_start" type="time" value="<?php echo esc_attr( $settings['business_start'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="business_end">Business end</label></th>
                    <td><input id="business_end" name="business_end" type="time" value="<?php echo esc_attr( $settings['business_end'] ); ?>" class="regular-text"></td>
                </tr>
            </table>

            <h2>General Slugs</h2>
            <p class="description">Optional fallback URLs used when shortcode pages are not detected.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="booking_slug">Booking slug</label></th>
                    <td><input id="booking_slug" name="booking_slug" type="text" value="<?php echo esc_attr( $settings['booking_slug'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="login_slug">Login slug</label></th>
                    <td><input id="login_slug" name="login_slug" type="text" value="<?php echo esc_attr( $settings['login_slug'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dashboard_slug">Dashboard slug</label></th>
                    <td><input id="dashboard_slug" name="dashboard_slug" type="text" value="<?php echo esc_attr( $settings['dashboard_slug'] ); ?>" class="regular-text"></td>
                </tr>
            </table>

            <details style="margin:18px 0 0; padding:14px; background:#fff; border:1px solid #dcdcde; border-radius:8px;">
                <summary style="cursor:pointer; font-weight:600;">Advanced App Setup</summary>
                <p class="description" style="margin-top:12px;">Set these once in Azure and Google Cloud. After that, use the connect buttons above. This section is for the admin only.</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="outlook_tenant">Outlook tenant</label></th>
                        <td><input id="outlook_tenant" name="outlook_tenant" type="text" value="<?php echo esc_attr( $settings['outlook_tenant'] ?? 'common' ); ?>" class="regular-text"><p class="description">Use <code>common</code> unless your app needs a fixed tenant.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="outlook_client_id">Outlook client ID</label></th>
                        <td><input id="outlook_client_id" name="outlook_client_id" type="text" value="<?php echo esc_attr( $settings['outlook_client_id'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="outlook_client_secret">Outlook client secret</label></th>
                        <td><input id="outlook_client_secret" name="outlook_client_secret" type="password" value="<?php echo esc_attr( $settings['outlook_client_secret'] ); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row">Outlook callback URL</th>
                        <td><code><?php echo esc_html( admin_url( 'admin-post.php?action=zb_calendar_callback&provider=outlook' ) ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="google_client_id">Google client ID</label></th>
                        <td><input id="google_client_id" name="google_client_id" type="text" value="<?php echo esc_attr( $settings['google_client_id'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="google_client_secret">Google client secret</label></th>
                        <td><input id="google_client_secret" name="google_client_secret" type="password" value="<?php echo esc_attr( $settings['google_client_secret'] ); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row">Google callback URL</th>
                        <td><code><?php echo esc_html( admin_url( 'admin-post.php?action=zb_calendar_callback&provider=google' ) ); ?></code></td>
                    </tr>
                </table>
            </details>

            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
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
    $status     = function_exists( 'zb_normalize_booking_status' )
        ? zb_normalize_booking_status( sanitize_text_field( $_GET['status'] ?? '' ) )
        : sanitize_text_field( $_GET['status'] ?? '' );

    if ( ! $booking_id || ! in_array( $status, [ 'accepted', 'rejected' ], true ) ) {
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

        if ( function_exists( 'zb_is_status_accepted' ) && zb_is_status_accepted( $status ) && function_exists( 'zb_calendar_create_events_for_booking' ) ) {
            zb_calendar_create_events_for_booking( $booking_id, (array) $booking );
    }

    $notice = ( function_exists( 'zb_is_status_accepted' ) && zb_is_status_accepted( $status ) ) ? 'confirmed' : 'rejected';
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

    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    $normalized_status = function_exists( 'zb_normalize_booking_status' ) ? zb_normalize_booking_status( $status ) : strtolower( (string) $status );

    if ( 'accepted' === $normalized_status ) {
        $subject = '✅ Din booking er bekræftet – ' . $site_name;
        $content = "Hej {$booking->contact_person},<br><br>";
        $content .= "Din booking er nu bekræftet! Vi ser frem til at fotografere ejendommen.<br><br>";
        $content .= "<strong>BOOKINGDETALJER:</strong><br>";
        $content .= "Adresse: " . esc_html( $booking->address ) . "<br>";
        $content .= "Dato: " . esc_html( $booking->booking_date ) . " " . esc_html( $booking->booking_time ) . "<br><br>";
        $content .= "Vi har vedhæftet en kalenderfil, så du nemt kan gemme aftalen.<br><br>";
        $content .= "Betaling sker efter fotografering.";

        $attachments = [];
        if ( function_exists( 'zb_generate_ics' ) ) {
            $ics_file = zb_generate_ics( $booking->id, (array) $booking );
            if ( file_exists( $ics_file ) ) $attachments[] = $ics_file;
        }

        wp_mail( $booking->email, $subject, zb_get_styled_html( 'Booking Bekræftet', $content ), $headers, $attachments );

        if ( ! empty( $attachments ) ) @unlink( $attachments[0] );
    } else {
        $subject = 'Din booking-anmodning – opdatering | ' . $site_name;
        $content = "Hej {$booking->contact_person},<br><br>";
        $content .= "Desværre kan vi ikke bekræfte din booking-anmodning for:<br>";
        $content .= "<strong>" . esc_html( $booking->address ) . "</strong><br><br>";
        $content .= "Kontakt os venligst direkte hvis du ønsker at finde en anden tid.";

        wp_mail( $booking->email, $subject, zb_get_styled_html( 'Opdatering på din booking', $content ), $headers );
    }
}
