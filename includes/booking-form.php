<?php

defined( 'ABSPATH' ) || exit;

add_shortcode( 'zbooking', 'zb_booking_form' );

function zb_booking_form() {
    if ( ! is_user_logged_in() ) {
        $current_url = home_url( add_query_arg( null, null ) );
        wp_safe_redirect( site_url( '/login?redirect_to=' . rawurlencode( $current_url ) ) );
        exit;
    }

    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID;

    wp_cache_delete( $user_id, 'user_meta' );
    wp_cache_delete( $user_id, 'users' );

    $meta_company = get_user_meta( $user_id, 'company_name',   true );
    $meta_contact = get_user_meta( $user_id, 'contact_person', true );
    $meta_phone   = get_user_meta( $user_id, 'phone',          true );
    $currency     = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : 'kr';

    ob_start();

    if ( isset( $_GET['booking_id'] ) ) {
        $booking_id = absint( $_GET['booking_id'] );
        global $wpdb;
        $table   = $wpdb->prefix . 'zb_bookings';
        $booking = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d", $booking_id, $user_id )
        );

        if ( ! $booking ) {
            echo '<p style="padding:20px;color:#c0392b;">Du har ikke adgang til denne booking.</p>';
            return ob_get_clean();
        }

        $status_map = [
            'pending'  => [ 'label' => 'Afventer bekræftelse', 'color' => '#b45309' ],
            'Accepted' => [ 'label' => 'Bekræftet',            'color' => '#15803d' ],
            'Rejected' => [ 'label' => 'Afvist',               'color' => '#b91c1c' ],
        ];
        $st = $status_map[ $booking->status ] ?? [ 'label' => esc_html( $booking->status ), 'color' => '#555' ];
        ?>
        <div class="zb-confirm-wrap">
            <div class="zb-confirm-icon">✅</div>
            <h2>Booking-anmodning sendt!</h2>
            <p class="zb-confirm-sub">Vi bekræfter, at din anmodning er modtaget og behandles snarest.</p>
            <div class="zb-details">
                <div class="zb-row"><span>Booking ID</span><strong>#<?php echo absint( $booking->id ); ?></strong></div>
                <div class="zb-row"><span>Firma</span><strong><?php echo esc_html( $booking->company_name ); ?></strong></div>
                <div class="zb-row"><span>Booket af</span><strong><?php echo esc_html( $booking->booked_by ); ?></strong></div>
                <div class="zb-row"><span>Ejendomsadresse</span><strong><?php echo esc_html( $booking->address ); ?></strong></div>
                <div class="zb-row"><span>Ydelser</span><strong><?php echo esc_html( $booking->services ); ?></strong></div>
                <div class="zb-row"><span>Dato</span><strong><?php echo esc_html( $booking->booking_date ); ?></strong></div>
                <div class="zb-row"><span>Tidspunkt</span><strong><?php echo esc_html( $booking->booking_time ); ?></strong></div>
                <div class="zb-row"><span>Pris ekskl. moms</span><strong><?php echo esc_html( $booking->price ); ?> <?php echo esc_html( $currency ); ?></strong></div>
                <?php if ( $booking->coupon_price ) : ?>
                <div class="zb-row"><span>Rabatpris</span><strong><?php echo esc_html( $booking->coupon_price ); ?> <?php echo esc_html( $currency ); ?></strong></div>
                <?php endif; ?>
                <div class="zb-row">
                    <span>Status</span>
                    <strong style="color:<?php echo esc_attr( $st['color'] ); ?>">
                        <?php echo esc_html( $st['label'] ); ?>
                    </strong>
                </div>
            </div>
            <a class="zb-new-booking-btn" href="<?php echo esc_url( remove_query_arg( 'booking_id' ) ); ?>">
                + Book en ny adresse
            </a>
        </div>
        <style>
            .zb-confirm-wrap { max-width:560px; margin:40px auto; text-align:center; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
            .zb-confirm-icon { font-size:48px; margin-bottom:12px; }
            .zb-confirm-wrap h2 { font-size:24px; font-weight:700; color:#111; margin:0 0 8px; }
            .zb-confirm-sub { color:#555; margin:0 0 24px; font-size:15px; }
            .zb-details { background:#fff; border-radius:12px; padding:24px; box-shadow:0 2px 12px rgba(0,0,0,.08); text-align:left; }
            .zb-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f3f3f0; font-size:14px; }
            .zb-row:last-child { border-bottom:none; }
            .zb-row span { color:#777; }
            .zb-row strong { color:#111; max-width:55%; text-align:right; }
            .zb-new-booking-btn { display:inline-block; margin-top:20px; padding:12px 24px; background:#4a7c59; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; font-size:14px; transition:background .2s; }
            .zb-new-booking-btn:hover { background:#3d6b4c; color:#fff; }
        </style>
        <?php
        return ob_get_clean();
    }

    ?>
    <div class="page-header">
        <h1>Lav en booking</h1>
        <p class="logged-in">Logget ind som <strong><?php echo esc_html( $current_user->display_name ); ?></strong></p>
    </div>

    <form class="layout" method="post" id="zb-booking-form" novalidate autocomplete="on">
        <?php wp_nonce_field( 'zb_booking_submit', 'zb_booking_nonce' ); ?>

        <div class="form-card">
            <h2>Lav en booking</h2>
            <div class="form-grid">

                <div class="form-group">
                    <label for="zb_company">Firmanavn</label>
                    <input id="zb_company" class="change_update" type="text"
                           name="company_name" autocomplete="organization"
                           value="<?php echo esc_attr( $meta_company ); ?>"
                           placeholder="Skriv dit firmanavn" required>
                </div>

                <div class="form-group">
                    <label for="zb_contact">Kontaktperson</label>
                    <input id="zb_contact" class="change_update" type="text"
                           name="contact_person" autocomplete="name"
                           value="<?php echo esc_attr( $meta_contact ); ?>"
                           placeholder="Fulde navn" required>
                </div>

                <div class="form-group">
                    <label for="zb_email">E-mail</label>
                    <input id="zb_email" type="email"
                           name="email" autocomplete="email"
                           value="<?php echo esc_attr( $current_user->user_email ); ?>"
                           placeholder="din@email.dk" required>
                </div>

                <div class="form-group">
                    <label for="zb_phone">Telefon</label>
                    <input id="zb_phone" type="tel"
                           name="phone" autocomplete="tel"
                           value="<?php echo esc_attr( $meta_phone ); ?>"
                           placeholder="+45 12 34 56 78" required>
                </div>

                <div class="form-group full">
                    <label for="zb_seller">Sælgers kontaktoplysninger
                        <span class="optional">(valgfrit)</span>
                    </label>
                    <input id="zb_seller" type="text"
                           name="seller_contact" autocomplete="off"
                           placeholder="Sælgers navn og telefon">
                </div>

                <hr class="form-divider">

                <div class="form-group full">
                    <label for="zb_address">Ejendomsadresse <span class="zb-required">*</span></label>
                    <input id="zb_address" class="change_update" type="text"
                           name="address" autocomplete="off"
                           placeholder="Vejnavn og husnummer, postnummer, by" required>
                </div>

                <div class="form-group full">
                    <label for="zb_booked_by">Booket af <span class="zb-required">*</span></label>
                    <input id="zb_booked_by" type="text"
                           name="booked_by" autocomplete="name"
                           value="<?php echo esc_attr( $meta_contact ); ?>"
                           placeholder="Dit fulde navn" required>
                </div>

                <hr class="form-divider">

                <div class="form-group full">
                    <div class="section-title">Vælg services</div>
                    <p style="font-size:13px;color:#888;margin:0 0 10px;">
                        Vælg én eller flere ydelser – tidsinterval beregnes automatisk.
                    </p>
                    <div class="services-list" id="zbServiceList">
                        <?php
                        global $wpdb;
                        $addons = $wpdb->get_results(
                            "SELECT * FROM {$wpdb->prefix}zb_addons ORDER BY id ASC"
                        );
                        if ( $addons ) :
                            foreach ( $addons as $a ) :
                                $price = floatval( $a->price );
                                $time  = absint( $a->time );
                                $title = esc_html( $a->title );
                        ?>
                        <div class="zb-service-item"
                             title="<?php echo $title; ?>"
                             data-price="<?php echo $price; ?>"
                             data-time="<?php echo $time; ?>">
                            <?php echo $title; ?> &ndash;
                            <?php echo number_format( $price, 0, ',', '.' ); ?> <?php echo esc_html( $currency ); ?>
                            &ndash; <?php echo $time; ?> min
                        </div>
                        <?php
                            endforeach;
                        else :
                        ?>
                        <p style="color:#999;font-size:13px;">
                            Ingen services oprettet endnu. Kontakt administrator.
                        </p>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="services"           class="zb_selected_services" value="">
                    <input type="hidden" name="price"              class="zb_selected_price"    value="">
                    <input type="hidden" name="total_minutes"      class="zb_total_minutes"     value="">
                    <input type="hidden" name="active_coupon_code" class="zb_active_coupon"     value="">
                    <input type="hidden" name="coupon_price"       class="zb_coupon_price"      value="">

                    <div id="zbTimeSummary" style="display:none; margin-top:10px;">
                        <span style="font-size:13px;color:#4a7c59;font-weight:600;">
                            ⏱ Samlet tid: <span id="zbTotalTime"></span>
                        </span>
                    </div>

                    <div class="custom_services" style="margin-top:12px;">
                        <span style="font-size:13px;color:#555;margin-bottom:6px;display:block;">Tilføj egen service</span>
                        <div style="display:flex;gap:8px;">
                            <input class="zb_custom_service_input" type="text"
                                   autocomplete="off"
                                   placeholder="Navn på service (f.eks. special rengøring)"
                                   style="flex:1;">
                            <button class="btn-add-service" onclick="zbAddService()" type="button">
                                <svg viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="zb_date">Vælg dato <span class="zb-required">*</span></label>
                    <input id="zb_date" type="date" name="booking_date"
                           min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="zb_time">Vælg tid <span class="zb-required">*</span></label>
                    <input id="zb_time" type="time" name="booking_time" required>
                </div>

                <div class="form-group full">
                    <label for="zb_comments">Kommentarer
                        <span class="optional">(valgfrit)</span>
                    </label>
                    <textarea id="zb_comments" name="comments"
                              placeholder="Ingen plantegning, hund i huset..."></textarea>
                </div>

            </div>
        </div>

        <div class="sidebar-card">
            <h2>Booking Oversigt</h2>

            <div class="overview-name">
                <span class="company_name"><?php echo esc_html( $meta_company ); ?></span>
                <span class="contact_person"><?php echo esc_html( $meta_contact ); ?></span>
            </div>

            <div class="overview-section-label" style="margin-top:14px;">Ejendomsadresse</div>
            <div class="address" style="font-size:13px;color:#555;min-height:20px;margin-bottom:14px;"></div>

            <div class="overview-section-label">Valgte services</div>
            <div class="overview-services"></div>

            <div class="overview-total">
                <span class="total-label">I alt ekskl. moms</span>
                <span class="total-amount" data-raw="0">0 <?php echo esc_html( $currency ); ?></span>
            </div>

            <div class="coupon-applied" style="display:none;">
                <div class="overview-total" style="border-top:none;padding-top:0;">
                    <span class="zb-applied-code" style="font-size:13px;color:#4a7c59;font-weight:600;"></span>
                    <span class="zb-applied-price" style="font-size:18px;font-weight:700;color:#111;"></span>
                </div>
                <button type="button" class="zb-remove-coupon"
                        style="font-size:12px;color:#999;background:none;border:none;cursor:pointer;padding:0;text-decoration:underline;margin-top:-4px;">
                    × Fjern rabat
                </button>
            </div>

            <div class="discount-row">
                <input class="copuon_input" id="zbCouponInput" type="text"
                       autocomplete="off" placeholder="Rabatkode">
                <button class="btn-apply" id="zbApplyBtn" type="button">Anvend</button>
            </div>

            <button class="btn-confirm" type="submit" name="zb_submit_booking" id="zbSubmitBtn">
                Send booking-anmodning
            </button>

            <p class="notification-note">
                Bekræftelse sendes til
                <a href="mailto:<?php echo esc_attr( $current_user->user_email ); ?>">
                    <?php echo esc_html( $current_user->user_email ); ?>
                </a>
                og <a href="mailto:booking@homefoto.dk">booking@homefoto.dk</a>.
            </p>
        </div>

    </form>

    <style>
        .zb-required { color:#e53e3e; font-weight:700; }
        .zb-service-item { cursor:pointer; transition:border-color .15s, background .15s; }
        .zb-service-item:hover { background:#e8f5ed; border-color:#4a7c59; }
        .zb-service-item.zb-selected { background:#d1fae5; border-color:#4a7c59; font-weight:600; }
        #zbSubmitBtn:disabled { opacity:.6; cursor:wait; }
    </style>

    <script>
    (function () {
        'use strict';

        var currency    = '<?php echo esc_js( $currency ); ?>';
        var ajaxUrl     = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
        var zbNonce     = '<?php echo esc_js( wp_create_nonce( 'zb_coupon_nonce' ) ); ?>';
        var totalPrice  = 0;
        var totalMins   = 0;
        document.querySelectorAll('.change_update').forEach(function (inp) {
            function sync() {
                var el = document.querySelector('.' + inp.name);
                if (el) el.textContent = inp.value;
            }
            inp.addEventListener('input', sync);
            sync();
        });

        var addrInput = document.getElementById('zb_address');
        var addrDiv   = document.querySelector('.address');
        if (addrInput && addrDiv) {
            addrInput.addEventListener('input', function () { addrDiv.textContent = this.value; });
        }
        var svcList        = document.getElementById('zbServiceList');
        var overviewSvcs   = document.querySelector('.overview-services');
        var totalAmountEl  = document.querySelector('.total-amount');
        var hiddenSvcs     = document.querySelector('.zb_selected_services');
        var hiddenPrice    = document.querySelector('.zb_selected_price');
        var hiddenMins     = document.querySelector('.zb_total_minutes');
        var timeSummary    = document.getElementById('zbTimeSummary');
        var totalTimeEl    = document.getElementById('zbTotalTime');

        function fmt(m) {
            if (m < 60) return m + ' min';
            var h = Math.floor(m / 60), r = m % 60;
            return r ? h + ' t ' + r + ' min' : h + ' t';
        }
        function fmtPrice(p) {
            return p.toLocaleString('da-DK', {minimumFractionDigits:0}) + ' ' + currency;
        }
        function updateTotals() {
            totalAmountEl.dataset.raw  = totalPrice;
            totalAmountEl.textContent  = fmtPrice(totalPrice);
            hiddenPrice.value          = totalPrice;
            hiddenMins.value           = totalMins;
            var names = Array.from(overviewSvcs.querySelectorAll('.service-name'))
                             .map(function (s) { return s.textContent; });
            hiddenSvcs.value = names.join(', ');
            timeSummary.style.display = totalMins > 0 ? 'block' : 'none';
            if (totalMins > 0) totalTimeEl.textContent = fmt(totalMins);
            zbResetCoupon();
        }

        if (svcList) {
            svcList.addEventListener('click', function (e) {
                var svc = e.target.closest('.zb-service-item');
                if (!svc) return;
                var title = svc.getAttribute('title');
                var price = parseFloat(svc.dataset.price) || 0;
                var time  = parseInt(svc.dataset.time, 10) || 0;
                var key   = title.replace(/"/g, '\\"');
                var existing = overviewSvcs.querySelector('.zb-svc-item[data-title="' + key + '"]');
                if (existing) {
                    existing.remove();
                    svc.classList.remove('zb-selected');
                    totalPrice -= price; totalMins -= time;
                } else {
                    var item = document.createElement('div');
                    item.className    = 'overview-service-item zb-svc-item';
                    item.dataset.title = title;
                    item.innerHTML = '<div class="check-icon"><svg viewBox="0 0 10 10" fill="none"><path d="M2 5l2.5 2.5L8 3" stroke="#4a7c59" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>'
                        + '<span class="service-name">' + title + '</span>'
                        + '<span class="service-meta">' + fmtPrice(price) + ' · ' + time + ' min</span>';
                    overviewSvcs.appendChild(item);
                    svc.classList.add('zb-selected');
                    totalPrice += price; totalMins += time;
                }
                updateTotals();
            });
        }

        window.zbAddService = function () {
            var inp = document.querySelector('.zb_custom_service_input');
            var val = (inp.value || '').trim();
            if (!val || !svcList) return;
            var row = document.createElement('div');
            row.setAttribute('title', val);
            row.dataset.price = '0'; row.dataset.time = '0';
            row.className = 'zb-service-item';
            row.textContent = val;
            svcList.appendChild(row);
            inp.value = '';
        };
        var couponInput   = document.getElementById('zbCouponInput');
        var applyBtn      = document.getElementById('zbApplyBtn');
        var couponDiv     = document.querySelector('.coupon-applied');
        var appliedCode   = document.querySelector('.zb-applied-code');
        var appliedPrice  = document.querySelector('.zb-applied-price');
        var removeBtn     = document.querySelector('.zb-remove-coupon');
        var activeCoupon  = document.querySelector('.zb_active_coupon');
        var couponPrice   = document.querySelector('.zb_coupon_price');

        function zbResetCoupon() {
            if (!couponDiv || couponDiv.style.display === 'none') return;
            couponDiv.style.display = 'none';
            totalAmountEl.textContent = fmtPrice(totalPrice);
            if (couponInput) couponInput.value = '';
            if (activeCoupon) activeCoupon.value = '';
            if (couponPrice)  couponPrice.value  = '';
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var code  = couponInput ? couponInput.value.trim() : '';
                var price = parseFloat(hiddenPrice.value) || 0;
                if (!code)  { alert('Indtast venligst en rabatkode.'); return; }
                if (!price) { alert('Vælg venligst mindst én service.'); return; }

                applyBtn.disabled = true; applyBtn.textContent = '...';

                var fd = new FormData();
                fd.append('action', 'zb_apply_coupon');
                fd.append('nonce',  zbNonce);
                fd.append('coupon', code);
                fd.append('price',  price);

                fetch(ajaxUrl, { method:'POST', body:fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    applyBtn.disabled = false; applyBtn.textContent = 'Anvend';
                    if (res.success) {
                        var np = parseFloat(res.data.new_price);
                        totalAmountEl.innerHTML = '<s style="color:#999;">' + fmtPrice(price) + '</s>';
                        appliedCode.textContent  = code;
                        appliedPrice.textContent = fmtPrice(np);
                        couponDiv.style.display  = 'block';
                        activeCoupon.value = code;
                        couponPrice.value  = np;
                    } else {
                        alert(res.data.message || 'Ugyldig rabatkode.');
                        activeCoupon.value = ''; couponPrice.value = '';
                    }
                })
                .catch(function () {
                    applyBtn.disabled = false; applyBtn.textContent = 'Anvend';
                    alert('Noget gik galt. Prøv igen.');
                });
            });
        }

        if (removeBtn) removeBtn.addEventListener('click', zbResetCoupon);
        var form      = document.getElementById('zb-booking-form');
        var submitBtn = document.getElementById('zbSubmitBtn');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!hiddenSvcs.value.trim()) {
                    e.preventDefault();
                    alert('Vælg venligst mindst én service.');
                    return;
                }
                if (submitBtn) {
                    submitBtn.disabled    = true;
                    submitBtn.textContent = 'Sender anmodning...';
                }
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

add_action( 'wp_ajax_zb_apply_coupon',        'zb_apply_coupon' );
add_action( 'wp_ajax_nopriv_zb_apply_coupon', 'zb_apply_coupon' );

function zb_apply_coupon() {
    if ( ! check_ajax_referer( 'zb_coupon_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Sikkerhedstjek mislykkedes.' ] );
    }
    if ( ! class_exists( 'WooCommerce' ) ) {
        wp_send_json_error( [ 'message' => 'WooCommerce er ikke aktivt.' ] );
    }

    $code  = sanitize_text_field( $_POST['coupon'] ?? '' );
    $price = floatval( $_POST['price'] ?? 0 );

    if ( empty( $code ) ) {
        wp_send_json_error( [ 'message' => 'Ingen rabatkode angivet.' ] );
    }

    $coupon      = new WC_Coupon( $code );
    $usage_limit = (int) $coupon->get_usage_limit();
    $usage_count = (int) $coupon->get_usage_count();

    if ( ! $coupon->get_id() ) {
        wp_send_json_error( [ 'message' => 'Ugyldig rabatkode.' ] );
    }
    if ( $usage_limit > 0 && $usage_count >= $usage_limit ) {
        wp_send_json_error( [ 'message' => 'Rabatkoden har nået sit maksimale antal anvendelser.' ] );
    }
    $expiry = $coupon->get_date_expires();
    if ( $expiry && $expiry->getTimestamp() < current_time( 'timestamp' ) ) {
        wp_send_json_error( [ 'message' => 'Denne rabatkode er udløbet.' ] );
    }

    $type      = $coupon->get_discount_type();
    $amount    = floatval( $coupon->get_amount() );
    $new_price = $price;

    if ( in_array( $type, [ 'fixed_cart', 'fixed_product' ], true ) ) {
        $new_price = max( 0.0, $price - $amount );
    } elseif ( $type === 'percent' ) {
        $new_price = max( 0.0, $price - ( $price * $amount / 100 ) );
    }

    wp_send_json_success( [
        'new_price' => round( $new_price, 2 ),
        'discount'  => round( $price - $new_price, 2 ),
    ] );
}
