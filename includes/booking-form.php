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
    $meta_address = get_user_meta( $user_id, 'address',        true );
    $currency     = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : 'kr';

    $initial_product_id    = absint( $_GET['p_id'] ?? 0 );
    $initial_product_title = '';
    $initial_product_price = 0;

    if ( $initial_product_id && class_exists( 'WooCommerce' ) ) {
        $product = wc_get_product( $initial_product_id );
        if ( $product ) {
            $initial_product_title = $product->get_name();
            $initial_product_price = (float) $product->get_price();
        }
    }

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
            <a class="zb-new-booking-btn" href="<?php echo esc_url( remove_query_arg( [ 'booking_id', 'p_id' ] ) ); ?>">
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
        <h1>Make a Booking</h1>
        <p class="logged-in">Logged in as <strong><?php echo esc_html( $current_user->display_name ); ?></strong></p>
    </div>

    <form class="layout" method="post" id="zb-booking-form" novalidate autocomplete="on">
        <?php wp_nonce_field( 'zb_booking_submit', 'zb_booking_nonce' ); ?>

        <div class="form-card">
            <h2>Make a Booking</h2>
            <div class="form-grid">

                <div class="form-group">
                    <label for="zb_company">Company Name</label>
                    <input id="zb_company" class="change_update" type="text"
                           name="company_name" autocomplete="organization"
                           value="<?php echo esc_attr( $meta_company ); ?>"
                           placeholder="Enter company name" required>
                </div>

                <div class="form-group">
                    <label for="zb_contact">Contact Person</label>
                    <input id="zb_contact" class="change_update" type="text"
                           name="contact_person" autocomplete="name"
                           value="<?php echo esc_attr( $meta_contact ); ?>"
                           placeholder="Full Name" required>
                </div>

                <div class="form-group">
                    <label for="zb_email">Email</label>
                    <input id="zb_email" type="email"
                           name="email" autocomplete="email"
                           value="<?php echo esc_attr( $current_user->user_email ); ?>"
                           placeholder="Your Email" required>
                </div>

                <div class="form-group">
                    <label for="zb_phone">Phone</label>
                    <input id="zb_phone" type="tel"
                           name="phone" autocomplete="tel"
                           value="<?php echo esc_attr( $meta_phone ); ?>"
                           placeholder="+45 12345678" required>
                </div>

                <div class="form-group">
                    <label for="zb_address">Address <span class="zb-required">*</span></label>
                    <input id="zb_address" class="change_update" type="text"
                           name="address" autocomplete="off"
                           value="<?php echo esc_attr( $meta_address ); ?>"
                           placeholder="Danmark | 1610 ......" required>
                </div>

                <div class="form-group">
                    <label for="zb_seller">Seller's Contact Information <span class="optional">(optional)</span></label>
                    <input id="zb_seller" type="text"
                           name="seller_contact" autocomplete="off"
                           placeholder="Anders Holm - 451000 ...">
                </div>

                <div class="form-group">
                    <label>Select Services</label>
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
                            <span class="svc-title"><?php echo $title; ?></span>
                            <span class="svc-meta">@ <?php echo number_format( $price, 0, ',', '.' ); ?><?php echo esc_html( $currency ); ?> · <?php echo $time; ?>min</span>
                            <span class="svc-arrow"></span>
                        </div>
                        <?php
                            endforeach;
                        endif;
                        ?>
                        <button class="btn-add-service-ui" type="button">+ Add Service</button>
                    </div>

                    <div class="custom-service-wrap" style="display:none; margin-top:10px;">
                        <div style="display:flex;gap:8px;">
                            <input class="zb_custom_service_input" type="text"
                                   placeholder="Add Service (optional)"
                                   style="flex:1;">
                            <button class="btn-add-service-action" onclick="zbAddService()" type="button">↑</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="zb_comments">Comments <span class="optional">(Optional)</span></label>
                    <textarea id="zb_comments" name="comments"
                              placeholder="E.g. ingen plantegning, hund i huset"></textarea>
                </div>

                <div class="form-group">
                    <label for="zb_date">Select Date <span class="zb-required">*</span></label>
                    <div class="input-with-icon">
                        <input id="zb_date" type="date" name="booking_date"
                               min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="zb_time">Select Time <span class="zb-required">*</span></label>
                    <div class="input-with-icon">
                        <input id="zb_time" type="time" name="booking_time" required>
                    </div>
                </div>

                <input type="hidden" name="services"           class="zb_selected_services" value="">
                <input type="hidden" name="price"              class="zb_selected_price"    value="">
                <input type="hidden" name="total_minutes"      class="zb_total_minutes"     value="">
                <input type="hidden" name="active_coupon_code" class="zb_active_coupon"     value="">
                <input type="hidden" name="coupon_price"       class="zb_coupon_price"      value="">
                <input type="hidden" name="booked_by"          value="<?php echo esc_attr( $current_user->display_name ); ?>">

            </div>
        </div>

        <div class="sidebar-card">
            <h2>Booking Overview</h2>

            <div class="overview-header">
                <div class="overview-name company_name"><?php echo esc_html( $meta_company ?: 'Company Name' ); ?></div>
                <div class="overview-name contact_person"><?php echo esc_html( $meta_contact ?: $current_user->display_name ); ?></div>
            </div>

            <div class="overview-section">
                <div class="overview-label">Property Address</div>
                <div class="overview-value address"><?php echo esc_html( $meta_address ?: 'Aarhusvej, 4300 ...' ); ?></div>
            </div>

            <div class="overview-section">
                <div class="overview-label">Selected Services</div>
                <div class="overview-services">
                    <!-- JS will fill this -->
                </div>
            </div>

            <div class="overview-footer">
                <div class="total-row">
                    <span class="total-label">Total</span>
                    <span class="total-amount" data-raw="0">0<?php echo esc_html( $currency ); ?></span>
                </div>

                <div class="coupon-applied" style="display:none;">
                    <div class="total-row" style="border:none; padding:0; margin-bottom:8px;">
                        <span class="zb-applied-code" style="color:#4a7c59;"></span>
                        <span class="zb-applied-price"></span>
                    </div>
                    <button type="button" class="zb-remove-coupon">× Remove coupon</button>
                </div>

                <div class="discount-row">
                    <input id="zbCouponInput" type="text" placeholder="Discount Code">
                    <button class="btn-apply" id="zbApplyBtn" type="button">Apply</button>
                </div>

                <button class="btn-confirm" type="submit" name="zb_submit_booking" id="zbSubmitBtn">
                    Confirm Booking
                </button>

                <p class="notification-note">
                    Notifications will be sent to <?php echo esc_html( $current_user->user_email ); ?><br>
                    and <strong><?php echo esc_html( get_option( 'admin_email' ) ); ?></strong>
                </p>
            </div>
        </div>

    </form>

    <script>
    (function () {
        'use strict';
        var currency    = '<?php echo esc_js( $currency ); ?>';
        var ajaxUrl     = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
        var zbNonce     = '<?php echo esc_js( wp_create_nonce( 'zb_coupon_nonce' ) ); ?>';
        var totalPrice  = 0;
        var totalMins   = 0;

        var initP      = '<?php echo esc_js( $initial_product_title ); ?>';
        var initPrice  = <?php echo (float) $initial_product_price; ?>;

        document.querySelectorAll('.change_update').forEach(function (inp) {
            function sync() {
                var el = document.querySelector('.overview-header .' + inp.name);
                if (!el) el = document.querySelector('.overview-section .' + inp.name);
                if (el) el.textContent = inp.value || (inp.name === 'address' ? 'Aarhusvej, 4300 ...' : '');
            }
            inp.addEventListener('input', sync);
        });

        var svcList        = document.getElementById('zbServiceList');
        var overviewSvcs   = document.querySelector('.overview-services');
        var totalAmountEl  = document.querySelector('.total-amount');
        var hiddenSvcs     = document.querySelector('.zb_selected_services');
        var hiddenPrice    = document.querySelector('.zb_selected_price');
        var hiddenMins     = document.querySelector('.zb_total_minutes');

        function fmt(m) {
            if (m < 60) return m + ' min';
            var h = Math.floor(m / 60), r = m % 60;
            return r ? h + 'h ' + r + 'min' : h + 'h';
        }
        function fmtPrice(p) {
            return p.toLocaleString('da-DK', {minimumFractionDigits:0}) + currency;
        }

        function updateTotals() {
            totalAmountEl.dataset.raw  = totalPrice;
            totalAmountEl.textContent  = fmtPrice(totalPrice);
            hiddenPrice.value          = totalPrice;
            hiddenMins.value           = totalMins;
            var names = Array.from(overviewSvcs.querySelectorAll('.service-name'))
                             .map(function (s) { return s.textContent; });
            hiddenSvcs.value = names.join(', ');
            zbResetCoupon();
        }

        function addServiceToOverview(title, price, time) {
            var key = title.replace(/"/g, '\\"');
            var existing = overviewSvcs.querySelector('.zb-svc-item[data-title="' + key + '"]');
            if (existing) {
                existing.remove();
                totalPrice -= price; totalMins -= time;
                return false;
            } else {
                var item = document.createElement('div');
                item.className    = 'overview-service-item zb-svc-item';
                item.dataset.title = title;
                item.innerHTML = '<div class="check-icon"><svg viewBox="0 0 10 10" fill="none"><path d="M2 5l2.5 2.5L8 3" stroke="#4a7c59" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>'
                    + '<span class="service-name">' + title + '</span>'
                    + '<span class="service-meta-mini">' + fmtPrice(price) + ' · ' + fmt(time) + '</span>';
                overviewSvcs.appendChild(item);
                totalPrice += price; totalMins += time;
                return true;
            }
        }

        if (svcList) {
            svcList.addEventListener('click', function (e) {
                var svc = e.target.closest('.zb-service-item');
                if (!svc) return;
                var title = svc.getAttribute('title');
                var price = parseFloat(svc.dataset.price) || 0;
                var time  = parseInt(svc.dataset.time, 10) || 0;
                if (addServiceToOverview(title, price, time)) {
                    svc.classList.add('zb-selected');
                } else {
                    svc.classList.remove('zb-selected');
                }
                updateTotals();
            });
        }

        document.querySelector('.btn-add-service-ui').addEventListener('click', function() {
            var wrap = document.querySelector('.custom-service-wrap');
            wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
        });

        window.zbAddService = function () {
            var inp = document.querySelector('.zb_custom_service_input');
            var val = (inp.value || '').trim();
            if (!val) return;
            addServiceToOverview(val, 0, 0);
            updateTotals();
            inp.value = '';
            document.querySelector('.custom-service-wrap').style.display = 'none';
        };

        if (initP) {
            var match = Array.from(document.querySelectorAll('.zb-service-item')).find(function(i){ return i.getAttribute('title') === initP; });
            if (match) {
                match.click();
            } else {
                addServiceToOverview(initP, initPrice, 0);
                updateTotals();
            }
        }

        var couponInput   = document.getElementById('zbCouponInput');
        var applyBtn      = document.getElementById('zbApplyBtn');
        var couponDiv     = document.querySelector('.coupon-applied');
        var appliedCode   = document.querySelector('.zb-applied-code');
        var appliedPrice  = document.querySelector('.zb-applied-price');
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
                if (!code || !price) return;
                applyBtn.disabled = true;
                var fd = new FormData();
                fd.append('action', 'zb_apply_coupon');
                fd.append('nonce',  zbNonce);
                fd.append('coupon', code);
                fd.append('price',  price);
                fetch(ajaxUrl, { method:'POST', body:fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    applyBtn.disabled = false;
                    if (res.success) {
                        var np = parseFloat(res.data.new_price);
                        totalAmountEl.innerHTML = '<s style="color:#999;font-size:14px;margin-right:8px;">' + fmtPrice(price) + '</s>' + fmtPrice(np);
                        appliedCode.textContent  = code;
                        appliedPrice.textContent = fmtPrice(np);
                        couponDiv.style.display  = 'block';
                        activeCoupon.value = code;
                        couponPrice.value  = np;
                    } else { alert(res.data.message || 'Error'); }
                });
            });
        }

        document.querySelector('.zb-remove-coupon').addEventListener('click', zbResetCoupon);

        document.getElementById('zb-booking-form').addEventListener('submit', function (e) {
            if (!hiddenSvcs.value.trim()) {
                e.preventDefault();
                alert('Please select at least one service.');
                return;
            }
            var btn = document.getElementById('zbSubmitBtn');
            btn.disabled = true; btn.textContent = 'Processing...';
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

add_action( 'wp_ajax_zb_apply_coupon', 'zb_apply_coupon' );

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
