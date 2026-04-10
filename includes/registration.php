<?php

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'zb_handle_signup' );

function zb_handle_signup() {
    if ( ! isset( $_POST['zb_signup_submit'] ) ) {
        return;
    }

    if ( ! isset( $_POST['zb_signup_nonce'] ) ||
         ! wp_verify_nonce( $_POST['zb_signup_nonce'], 'zb_signup_action' ) ) {
        wp_die( 'Sikkerhedstjek mislykkedes. Gå tilbage og prøv igen.' );
    }

    $email    = sanitize_email( $_POST['email'] ?? '' );
    $password = $_POST['password'] ?? '';

    if ( ! is_email( $email ) ) {
        wp_safe_redirect( add_query_arg( 'zb_error', 'invalid_email', wp_get_referer() ?: site_url( '/opret-konto/' ) ) );
        exit;
    }
    if ( strlen( $password ) < 8 ) {
        wp_safe_redirect( add_query_arg( 'zb_error', 'weak_password', wp_get_referer() ?: site_url( '/opret-konto/' ) ) );
        exit;
    }
    if ( email_exists( $email ) ) {
        wp_safe_redirect( add_query_arg( 'zb_error', 'email_exists', wp_get_referer() ?: site_url( '/opret-konto/' ) ) );
        exit;
    }

    $user_id = wp_create_user( $email, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        wp_safe_redirect( add_query_arg( 'zb_error', 'generic', wp_get_referer() ?: site_url( '/opret-konto/' ) ) );
        exit;
    }

    $user = new WP_User( $user_id );
    $user->set_role( 'customer' );

    $company = sanitize_text_field( $_POST['company_name'] ?? '' );
    $contact = sanitize_text_field( $_POST['contact_person'] ?? '' );
    $phone   = sanitize_text_field( $_POST['phone'] ?? '' );
    $address = sanitize_text_field( $_POST['address'] ?? '' );
    $cvr     = sanitize_text_field( $_POST['cvr'] ?? '' );

    wp_update_user( [
        'ID'           => $user_id,
        'display_name' => $company ? $company . ' – ' . $contact : $contact,
        'first_name'   => $contact,
    ] );

    update_user_meta( $user_id, 'company_name',   $company );
    update_user_meta( $user_id, 'contact_person', $contact );
    update_user_meta( $user_id, 'phone',          $phone );
    update_user_meta( $user_id, 'address',        $address );
    update_user_meta( $user_id, 'cvr',            $cvr );

    update_user_meta( $user_id, 'billing_company',    $company );
    update_user_meta( $user_id, 'billing_first_name', $contact );
    update_user_meta( $user_id, 'billing_email',      $email );
    update_user_meta( $user_id, 'billing_phone',      $phone );
    update_user_meta( $user_id, 'billing_address_1',  $address );

    clean_user_cache( $user_id );

    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id, true );

    wp_safe_redirect( site_url( '/bookings/' ) );
    exit;
}

add_shortcode( 'zb_signup', 'zb_signup_form' );

function zb_signup_form() {
    if ( is_user_logged_in() ) {
        wp_safe_redirect( site_url( '/bookings/' ) );
        exit;
    }

    $errors = [
        'email_exists'  => 'E-mailadressen er allerede i brug. <a href="' . esc_url( wp_login_url() ) . '">Log venligst ind.</a>',
        'invalid_email' => 'Indtast venligst en gyldig e-mailadresse.',
        'weak_password' => 'Din adgangskode skal være mindst 8 tegn.',
        'generic'       => 'Der opstod en fejl. Prøv igen eller kontakt os på booking@homefoto.dk.',
    ];

    $error_html = '';
    if ( isset( $_GET['zb_error'] ) && array_key_exists( $_GET['zb_error'], $errors ) ) {
        $msg        = $errors[ sanitize_key( $_GET['zb_error'] ) ];
        $error_html = '<div class="zb-alert zb-alert--error">' . wp_kses( $msg, [ 'a' => [ 'href' => [] ] ] ) . '</div>';
    }

    ob_start();
    ?>
    <style>
        .zb-signup-wrap {
            max-width: 480px;
            margin: 40px auto;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .zb-signup-wrap h2 { font-size: 22px; font-weight: 700; color: #111; margin: 0 0 6px; }
        .zb-signup-wrap .zb-sub { color: #666; font-size: 14px; margin: 0 0 24px; }
        .zb-signup-form {
            background: #fff;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .zb-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #444;
            margin-bottom: 5px;
            float: none;
        }
        .zb-field input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
            color: #111;
        }
        .zb-field input:focus { border-color: #4a7c59; outline: none; box-shadow: 0 0 0 3px rgba(74,124,89,0.1); }
        .zb-field .zb-opt { font-weight: 400; color: #999; font-size: 11px; }
        .zb-signup-btn {
            padding: 13px;
            background: #4a7c59;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            font-family: inherit;
        }
        .zb-signup-btn:hover { background: #3d6b4c; }
        .zb-login-hint { text-align: center; font-size: 13px; color: #777; }
        .zb-login-hint a { color: #4a7c59; font-weight: 600; text-decoration: none; }
        .zb-alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 4px; }
        .zb-alert--error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    </style>

    <div class="zb-signup-wrap">
        <h2>Opret konto</h2>
        <p class="zb-sub">Opret en konto for at booke og administrere dine opgaver hos homefoto.</p>

        <?php echo $error_html; ?>

        <form method="post" class="zb-signup-form" novalidate>
            <?php wp_nonce_field( 'zb_signup_action', 'zb_signup_nonce' ); ?>

            <div class="zb-field">
                <label for="zb_reg_company">Firmanavn</label>
                <input id="zb_reg_company" type="text" name="company_name"
                       autocomplete="organization"
                       placeholder="Skriv dit firmanavn" required>
            </div>

            <div class="zb-field">
                <label for="zb_reg_contact">Kontaktperson</label>
                <input id="zb_reg_contact" type="text" name="contact_person"
                       autocomplete="name"
                       placeholder="Fulde navn" required>
            </div>

            <div class="zb-field">
                <label for="zb_reg_email">E-mail</label>
                <input id="zb_reg_email" type="email" name="email"
                       autocomplete="email"
                       placeholder="din@email.dk" required>
            </div>

            <div class="zb-field">
                <label for="zb_reg_phone">Telefon</label>
                <input id="zb_reg_phone" type="tel" name="phone"
                       autocomplete="tel"
                       placeholder="+45 12 34 56 78" required>
            </div>

            <div class="zb-field">
                <label for="zb_reg_address">Adresse</label>
                <input id="zb_reg_address" type="text" name="address"
                       autocomplete="street-address"
                       placeholder="Vejnavn, postnummer, by" required>
            </div>

            <div class="zb-field">
                <label for="zb_reg_cvr">CVR-nummer
                    <span class="zb-opt">(valgfrit)</span>
                </label>
                <input id="zb_reg_cvr" type="text" name="cvr"
                       autocomplete="off"
                       placeholder="f.eks. 12345678">
            </div>

            <div class="zb-field">
                <label for="zb_reg_pass">Adgangskode</label>
                <input id="zb_reg_pass" type="password" name="password"
                       autocomplete="new-password"
                       placeholder="Mindst 8 tegn" required minlength="8">
            </div>

            <button type="submit" name="zb_signup_submit" class="zb-signup-btn">
                Opret konto
            </button>

            <p class="zb-login-hint">
                Har du allerede en konto?
                <a href="<?php echo esc_url( site_url( '/login' ) ); ?>">Log ind her</a>
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
