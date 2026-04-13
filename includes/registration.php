<?php

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'zb_handle_signup' );
add_action( 'init', 'zb_handle_login' );
add_action( 'init', 'zb_handle_auth_actions' );
add_action( 'init', 'zb_handle_dashboard_updates' );

function zb_handle_auth_actions() {
    if ( ! is_user_logged_in() || empty( $_GET['zb_action'] ) || 'logout' !== sanitize_key( $_GET['zb_action'] ) ) {
        return;
    }

    wp_logout();
    wp_safe_redirect( zb_get_login_url() );
    exit;
}

function zb_handle_dashboard_updates() {
    if ( ! is_user_logged_in() || ! isset( $_POST['zb_action'] ) ) return;
    $user_id = get_current_user_id();

    if ( $_POST['zb_action'] === 'update_profile' ) {
        if ( ! wp_verify_nonce( $_POST['zb_profile_nonce'], 'zb_update_profile' ) ) wp_die( 'Ugyldig anmodning.' );

        update_user_meta( $user_id, 'company_name',   sanitize_text_field( $_POST['company_name'] ) );
        update_user_meta( $user_id, 'contact_person', sanitize_text_field( $_POST['contact_person'] ) );
        update_user_meta( $user_id, 'phone',          $phone = sanitize_text_field( $_POST['phone'] ) );
        update_user_meta( $user_id, 'address',        $address = sanitize_text_field( $_POST['address'] ) );
        
        // Sync with WooCommerce billing
        update_user_meta( $user_id, 'billing_phone',     $phone );
        update_user_meta( $user_id, 'billing_address_1', $address );

        if ( ! empty( $_FILES['zb_avatar']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attach_id = media_handle_upload( 'zb_avatar', 0 );
            if ( ! is_wp_error( $attach_id ) ) {
                update_user_meta( $user_id, 'zb_avatar_id', $attach_id );
            }
        }
        $dashboard_url = zb_get_dashboard_url();
        wp_safe_redirect( add_query_arg( [ 'zb_tab' => 'profile', 'zb_msg' => 'profile_updated' ], $dashboard_url ) );
        exit;
    }

    if ( $_POST['zb_action'] === 'update_password' ) {
        if ( ! wp_verify_nonce( $_POST['zb_pwd_nonce'], 'zb_update_password' ) ) wp_die( 'Ugyldig anmodning.' );
        if ( $_POST['new_password'] !== $_POST['confirm_password'] ) {
            wp_die( 'Adgangskoderne er ikke ens.', 'Fejl', [ 'back_link' => true ] );
        }
        wp_set_password( $_POST['new_password'], $user_id );
        wp_set_auth_cookie( $user_id );
        $dashboard_url = zb_get_dashboard_url();
        wp_safe_redirect( add_query_arg( [ 'zb_tab' => 'security', 'zb_msg' => 'pwd_updated' ], $dashboard_url ) );
        exit;
    }
}

function zb_handle_login() {
    if ( ! isset( $_POST['zb_login_submit'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['zb_login_nonce'], 'zb_login_action' ) ) wp_die( 'Sikkerhedstjek fejlede.' );

    $creds = [
        'user_login'    => sanitize_text_field( $_POST['log'] ),
        'user_password' => $_POST['pwd'],
        'remember'      => isset( $_POST['rememberme'] ),
    ];

    $user = wp_signon( $creds, is_ssl() );

    if ( is_wp_error( $user ) ) {
        wp_safe_redirect( add_query_arg( 'zb_login_error', '1', wp_get_referer() ) );
        exit;
    }

    wp_safe_redirect( zb_get_booking_url() );
    exit;
}

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
        wp_safe_redirect( add_query_arg( 'zb_error', 'invalid_email', wp_get_referer() ?: zb_get_login_url( [ 'action' => 'signup' ] ) ) );
        exit;
    }
    if ( strlen( $password ) < 8 ) {
        wp_safe_redirect( add_query_arg( 'zb_error', 'weak_password', wp_get_referer() ?: zb_get_login_url( [ 'action' => 'signup' ] ) ) );
        exit;
    }
    if ( email_exists( $email ) ) {
        wp_safe_redirect( add_query_arg( 'zb_error', 'email_exists', wp_get_referer() ?: zb_get_login_url( [ 'action' => 'signup' ] ) ) );
        exit;
    }

    $user_id = wp_create_user( $email, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        wp_safe_redirect( add_query_arg( 'zb_error', 'generic', wp_get_referer() ?: zb_get_login_url( [ 'action' => 'signup' ] ) ) );
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

    // Admin Notification for New Signup
    $admin_subject = '[NY BRUKER] Ny konto oprettet – ' . $company;
    $admin_body    = "En ny kunde har oprettet en konto:\n\n";
    $admin_body   .= "Firma: {$company}\nKontakt: {$contact}\nE-mail: {$email}\nTlf: {$phone}\n";
    wp_mail( get_option('admin_email'), $admin_subject, $admin_body );

    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id, true );

    wp_safe_redirect( zb_get_booking_url() );
    exit;
}

add_shortcode( 'zb_auth', 'zb_unified_auth_form' );

function zb_unified_auth_form() {
    if ( is_user_logged_in() ) {
        wp_safe_redirect( zb_get_dashboard_url() );
        exit;
    }

    $mode = ( isset($_GET['action']) && $_GET['action'] === 'signup' ) ? 'signup' : 'login';
    
    ob_start();
    ?>
    <div class="zb-auth-container">
        <?php if ( $mode === 'login' ) : ?>
            <div class="zb-login-wrap">
                <h2>Log ind</h2>
                <?php if ( isset( $_GET['zb_login_error'] ) ) : ?>
                    <div class="zb-alert zb-alert--error">Forkert e-mail eller adgangskode.</div>
                <?php endif; ?>
                <form method="post" class="zb-login-form">
                    <?php wp_nonce_field( 'zb_login_action', 'zb_login_nonce' ); ?>
                    <div class="zb-field"><label>E-mail</label><input type="text" name="log" required></div>
                    <div class="zb-field"><label>Adgangskode</label><input type="password" name="pwd" required></div>
                    <label style="font-size: 13px; font-weight: 400;"><input name="rememberme" type="checkbox" value="forever"> Husk mig</label>
                    <button type="submit" name="zb_login_submit" class="zb-login-btn">Log ind</button>
                    <p style="text-align:center; font-size:13px; margin-top:20px;">
                        Mangler du en konto? <a href="<?php echo esc_url(add_query_arg('action', 'signup')); ?>" style="color:#4a7c59; font-weight:600;">Opret her</a>
                    </p>
                </form>
            </div>
        <?php else : ?>
            <div class="zb-signup-wrap" style="margin-top:0;">
                <h2>Opret konto</h2>
                <p class="zb-sub">Opret konto for at administrere dine bookinger.</p>
                <form method="post" class="zb-signup-form" novalidate>
                    <?php wp_nonce_field( 'zb_signup_action', 'zb_signup_nonce' ); ?>
                    <div class="zb-field"><label>Firmanavn</label><input type="text" name="company_name" required></div>
                    <div class="zb-field"><label>Kontaktperson</label><input type="text" name="contact_person" required></div>
                    <div class="zb-field"><label>E-mail</label><input type="email" name="email" required></div>
                    <div class="zb-field"><label>Telefon</label><input type="tel" name="phone" required></div>
                    <div class="zb-field"><label>Adresse</label><input type="text" name="address" required></div>
                    <div class="zb-field"><label>Adgangskode</label><input type="password" name="password" required minlength="8"></div>
                    <button type="submit" name="zb_signup_submit" class="zb-signup-btn">Opret konto</button>
                    <p style="text-align:center; font-size:13px; margin-top:20px;">
                        Har du allerede en konto? <a href="<?php echo esc_url(remove_query_arg('action')); ?>" style="color:#4a7c59; font-weight:600;">Log ind her</a>
                    </p>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <style>
        .zb-auth-container { max-width: 480px; margin: 40px auto; font-family: inherit; }
        .zb-auth-container h2 { font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 25px; color: #111; }
        .zb-login-form, .zb-signup-form { background: #fff; padding: 35px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); display: flex; flex-direction: column; gap: 18px; border: 1px solid #f0f0f0; }
        .zb-login-btn, .zb-signup-btn { padding: 14px; background: #4a7c59; color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .zb-login-btn:hover, .zb-signup-btn:hover { background: #3d6b4c; transform: translateY(-1px); }
        .zb-field label { display: block; font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; }
        .zb-field input { width: 100%; padding: 12px 15px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; box-sizing: border-box; }
        .zb-field input:focus { border-color: #4a7c59; outline: none; box-shadow: 0 0 0 3px rgba(74,124,89,0.1); }
        .zb-alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 10px; text-align: center; }
        .zb-alert--error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
    <?php
    return ob_get_clean();
}
