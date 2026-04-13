<?php

defined( 'ABSPATH' ) || exit;

add_shortcode( 'zbooking', 'zb_booking_form' );

function zb_booking_form() {
    if ( ! is_user_logged_in() ) {
        $current_url = home_url( add_query_arg( null, null ) );
        wp_safe_redirect( zb_get_login_url( [ 'redirect_to' => $current_url ] ) );
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
    $billing_company = get_user_meta( $user_id, 'billing_company', true );
    $billing_contact = get_user_meta( $user_id, 'billing_first_name', true );
    $billing_phone   = get_user_meta( $user_id, 'billing_phone', true );
    $billing_address = get_user_meta( $user_id, 'billing_address_1', true );

    $profile_company = $meta_company ?: $billing_company;
    $profile_contact = $meta_contact ?: $billing_contact ?: $current_user->display_name;
    $profile_phone   = $meta_phone ?: $billing_phone;
    $profile_address = $meta_address ?: $billing_address;
    $currency     = function_exists( 'zb_get_currency_symbol' ) ? zb_get_currency_symbol() : 'kr';

    $is_reschedule       = isset( $_GET['reschedule'] ) && '1' === (string) $_GET['reschedule'];
    $editing_booking     = null;
    $editing_booking_id  = 0;
    $prefill_email       = $current_user->user_email;
    $prefill_comments    = '';
    $prefill_seller      = '';
    $prefill_services    = '';
    $prefill_price       = 0;
    $prefill_minutes     = 0;
    $prefill_booking_date = '';
    $prefill_booking_time = '';

    if ( $is_reschedule && isset( $_GET['booking_id'] ) ) {
        $editing_booking_id = absint( $_GET['booking_id'] );
        global $wpdb;
        $table = $wpdb->prefix . 'zb_bookings';
        $editing_booking = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d", $editing_booking_id, $user_id )
        );

        if ( ! $editing_booking ) {
            echo '<p style="padding:20px;color:#c0392b;">Du har ikke adgang til denne booking.</p>';
            return ob_get_clean();
        }

        $profile_company      = $editing_booking->company_name ?: $profile_company;
        $profile_contact      = $editing_booking->contact_person ?: $profile_contact;
        $profile_phone        = $editing_booking->phone ?: $profile_phone;
        $profile_address      = $editing_booking->address ?: $profile_address;
        $prefill_email        = $editing_booking->email ?: $prefill_email;
        $prefill_comments     = (string) $editing_booking->comments;
        $prefill_seller       = (string) $editing_booking->seller_contact;
        $prefill_services     = (string) $editing_booking->services;
        $prefill_price        = floatval( $editing_booking->price );
        $prefill_minutes      = absint( $editing_booking->duration_minutes );
        $prefill_booking_date = (string) $editing_booking->booking_date;
        $prefill_booking_time = (string) $editing_booking->booking_time;
    }

    $initial_product_id      = absint( $_GET['p_id'] ?? 0 );
    $initial_product_title   = '';
    $initial_product_price   = 0;
    $initial_total_minutes   = 0;
    $initial_services_hidden = '';

    if ( $is_reschedule && null !== $editing_booking ) {
        $initial_product_title   = $prefill_services;
        $initial_product_price   = $prefill_price;
        $initial_total_minutes   = $prefill_minutes;
        $initial_services_hidden = $prefill_services;
    } elseif ( $initial_product_id && class_exists( 'WooCommerce' ) ) {
        $product = wc_get_product( $initial_product_id );
        if ( $product ) {
            $initial_product_title = $product->get_name();
            $initial_product_price = (float) wc_get_price_to_display( $product );
            $initial_services_hidden = $initial_product_title;
        }
    }

    ob_start();

    if ( isset( $_GET['booking_id'] ) && ! $is_reschedule ) {
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
            'accepted' => [ 'label' => 'Bekræftet',            'color' => '#15803d' ],
            'rejected' => [ 'label' => 'Afvist',               'color' => '#b91c1c' ],
        ];
        $normalized_status = function_exists( 'zb_normalize_booking_status' ) ? zb_normalize_booking_status( $booking->status ) : strtolower( (string) $booking->status );
        $st = $status_map[ $normalized_status ] ?? [ 'label' => esc_html( $booking->status ), 'color' => '#555' ];
        ?>
        <div class="zb-confirm-wrap">
            <div class="zb-confirm-icon">✅</div>
            <h2><?php echo isset( $_GET['rescheduled'] ) && '1' === (string) $_GET['rescheduled'] ? 'Booking opdateret!' : 'Booking-anmodning sendt!'; ?></h2>
            <p class="zb-confirm-sub"><?php echo isset( $_GET['rescheduled'] ) && '1' === (string) $_GET['rescheduled'] ? 'Vi har modtaget ændringerne og opdateret bookingen.' : 'Vi bekræfter, at din anmodning er modtaget og behandles snarest.'; ?></p>
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
        <h1><?php echo $is_reschedule ? 'Reschedule Booking' : 'Make a Booking'; ?></h1>
        <p class="logged-in">Logged in as <strong><?php echo esc_html( $current_user->display_name ); ?></strong></p>
    </div>

    <form class="layout" method="post" id="zb-booking-form" novalidate autocomplete="on">
        <?php wp_nonce_field( 'zb_booking_submit', 'zb_booking_nonce' ); ?>
        <?php if ( $is_reschedule && $editing_booking_id ) : ?>
            <input type="hidden" name="zb_update_booking_id" value="<?php echo absint( $editing_booking_id ); ?>">
        <?php endif; ?>

        <div class="form-card">
            <h2><?php echo $is_reschedule ? 'Update Booking Details' : 'Make a Booking'; ?></h2>
            <?php if ( $is_reschedule ) : ?>
                <p class="zb-time-help" style="margin-bottom:16px;">Update the booking details below, then submit to save the new date, time, and changes.</p>
            <?php endif; ?>
            <div class="form-grid">

                <div class="form-group">
                    <label for="zb_company">Company Name</label>
                    <input id="zb_company" class="change_update" type="text"
                           name="company_name" autocomplete="organization"
                           value="<?php echo esc_attr( $profile_company ); ?>"
                           placeholder="Enter company name" required>
                </div>

                <div class="form-group">
                    <label for="zb_contact">Contact Person</label>
                    <input id="zb_contact" class="change_update" type="text"
                           name="contact_person" autocomplete="name"
                           value="<?php echo esc_attr( $profile_contact ); ?>"
                           placeholder="Full Name" required>
                </div>

                <div class="form-group">
                    <label for="zb_email">Email</label>
                    <input id="zb_email" type="email"
                           name="email" autocomplete="email"
                           value="<?php echo esc_attr( $prefill_email ); ?>"
                           placeholder="Your Email" required>
                </div>

                <div class="form-group">
                    <label for="zb_phone">Phone</label>
                    <input id="zb_phone" type="tel"
                           name="phone" autocomplete="tel"
                           value="<?php echo esc_attr( $profile_phone ); ?>"
                           placeholder="+45 12345678" required>
                </div>

                <div class="form-group">
                    <label for="zb_address">Address <span class="zb-required">*</span></label>
                    <input id="zb_address" class="change_update" type="text"
                           name="address" autocomplete="off"
                           value="<?php echo esc_attr( $profile_address ); ?>"
                           placeholder="Danmark | 1610 ......" required>
                </div>

                <div class="form-group">
                    <label for="zb_seller">Seller's Contact Information <span class="optional">(optional)</span></label>
                    <input id="zb_seller" type="text"
                           name="seller_contact" autocomplete="off"
                           value="<?php echo esc_attr( $prefill_seller ); ?>"
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
                                data-title="<?php echo esc_attr( $title ); ?>"
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
                              placeholder="E.g. ingen plantegning, hund i huset"><?php echo esc_textarea( $prefill_comments ); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="zb_date">Select Date <span class="zb-required">*</span></label>
                    <div class="input-with-icon zb-calendar-wrap" id="zbCalendarWrap">
                        <input id="zb_date" type="text" name="booking_date"
                               data-min-date="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) ) ); ?>"
                               value="<?php echo esc_attr( $prefill_booking_date ); ?>"
                               placeholder="Select an available date"
                               required readonly>
                    </div>
                    <div class="zb-calendar-status" id="zbCalendarStatus" aria-live="polite">Loading available dates...</div>
                    <small class="zb-time-help">Only dates with availability are selectable.</small>
                </div>

                <div class="form-group">
                    <label for="zb_time">Select Time <span class="zb-required">*</span></label>
                    <div class="input-with-icon">
                        <select id="zb_time" name="booking_time" required>
                            <option value=""><?php echo $prefill_booking_time ? 'Loading time slots...' : 'Select date first'; ?></option>
                        </select>
                    </div>
                    <small class="zb-time-help">Time slots are in <?php echo esc_html( zb_get_slot_interval_minutes() ); ?>-minute intervals.</small>
                </div>

                <input type="hidden" name="services"           class="zb_selected_services" value="<?php echo esc_attr( $initial_services_hidden ); ?>">
                <input type="hidden" name="price"              class="zb_selected_price"    value="<?php echo esc_attr( $initial_product_price ); ?>">
                <input type="hidden" name="total_minutes"      class="zb_total_minutes"     value="<?php echo esc_attr( $initial_total_minutes ); ?>">
                <input type="hidden" name="active_coupon_code" class="zb_active_coupon"     value="">
                <input type="hidden" name="coupon_price"       class="zb_coupon_price"      value="<?php echo esc_attr( $editing_booking ? $editing_booking->coupon_price : '' ); ?>">
                <input type="hidden" name="booked_by"          value="<?php echo esc_attr( $current_user->display_name ); ?>">

            </div>
        </div>

        <div class="sidebar-card">
            <h2>Booking Overview</h2>

            <div class="overview-header">
                <div class="overview-name company_name"><?php echo esc_html( $profile_company ?: 'Company Name' ); ?></div>
                <div class="overview-name contact_person"><?php echo esc_html( $profile_contact ?: $current_user->display_name ); ?></div>
            </div>

            <div class="overview-section">
                <div class="overview-label">Property Address</div>
                <div class="overview-value address"><?php echo esc_html( $profile_address ?: 'Aarhusvej, 4300 ...' ); ?></div>
            </div>

            <div class="overview-section">
                <div class="overview-label">Selected Services</div>
                <div class="overview-services">
                    <?php if ( $initial_product_title ) : ?>
                    <div class="overview-service-item zb-svc-item" data-title="<?php echo esc_attr( $initial_product_title ); ?>">
                        <div class="check-icon"><svg viewBox="0 0 10 10" fill="none"><path d="M2 5l2.5 2.5L8 3" stroke="#4a7c59" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                        <span class="service-name"><?php echo esc_html( $initial_product_title ); ?></span>
                        <span class="service-meta-mini"><?php echo esc_html( number_format( $initial_product_price, 0, ',', '.' ) ); ?><?php echo esc_html( $currency ); ?> · <?php echo esc_html( $initial_total_minutes ? $initial_total_minutes . ' min' : '0 min' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="overview-footer">
                <div class="total-row">
                    <span class="total-label">Total</span>
                    <span class="total-amount" data-raw="<?php echo esc_attr( $initial_product_price ); ?>"><?php echo esc_html( number_format( $initial_product_price, 0, ',', '.' ) ); ?><?php echo esc_html( $currency ); ?></span>
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

                <input type="hidden" name="zb_submit_booking" value="1">
                <button class="btn-confirm" type="submit" id="zbSubmitBtn">
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
        var zbSlotNonce = '<?php echo esc_js( wp_create_nonce( 'zb_slots_nonce' ) ); ?>';
        var slotStep    = <?php echo (int) zb_get_slot_interval_minutes(); ?>;
        var defaultDur  = <?php echo (int) zb_get_default_duration_minutes(); ?>;
        var totalPrice  = 0;
        var totalMins   = 0;
        var calendar    = null;

        var initP      = '<?php echo esc_js( $initial_product_title ); ?>';
        var initPrice  = <?php echo (float) $initial_product_price; ?>;
        var editMode   = <?php echo $is_reschedule ? 'true' : 'false'; ?>;
        var editBookingId = <?php echo (int) $editing_booking_id; ?>;
        var editBookingDate = '<?php echo esc_js( $prefill_booking_date ); ?>';
        var editBookingTime = '<?php echo esc_js( $prefill_booking_time ); ?>';

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
        var dateInput      = document.getElementById('zb_date');
        var timeSelect     = document.getElementById('zb_time');
        var calendarWrap   = document.getElementById('zbCalendarWrap');
        var calendarStatus = document.getElementById('zbCalendarStatus');
        var minDate        = dateInput ? (dateInput.dataset.minDate || '') : '';
        var toastHost      = null;

        function fmt(m) {
            if (m < 60) return m + ' min';
            var h = Math.floor(m / 60), r = m % 60;
            return r ? h + 'h ' + r + 'min' : h + 'h';
        }
        function fmtPrice(p) {
            return p.toLocaleString('da-DK', {minimumFractionDigits:0}) + currency;
        }

        function ensureToastHost() {
            if (toastHost) return toastHost;
            toastHost = document.createElement('div');
            toastHost.className = 'zb-toast-host';
            document.body.appendChild(toastHost);
            return toastHost;
        }

        function zbNotify(type, title, body) {
            var host = ensureToastHost();
            var toast = document.createElement('div');
            toast.className = 'zb-toast zb-toast--' + (type || 'info');
            toast.innerHTML = '<div class="zb-toast-title"></div><div class="zb-toast-body"></div>';
            toast.querySelector('.zb-toast-title').textContent = title || 'Notice';
            toast.querySelector('.zb-toast-body').textContent = body || '';
            host.appendChild(toast);

            setTimeout(function () {
                toast.classList.add('is-leaving');
                setTimeout(function () {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 220);
            }, 3200);
        }

        function fetchJsonWithTimeout(url, options, timeoutMs) {
            var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            var timer = null;
            var requestOptions = options || {};

            if (controller) {
                requestOptions = Object.assign({}, requestOptions, { signal: controller.signal });
                timer = setTimeout(function () {
                    controller.abort();
                }, timeoutMs || 12000);
            }

            return fetch(url, requestOptions)
                .then(function (response) {
                    if (timer) {
                        clearTimeout(timer);
                    }
                    return response.json();
                })
                .catch(function (error) {
                    if (timer) {
                        clearTimeout(timer);
                    }
                    throw error;
                });
        }

        function updateTotals() {
            totalAmountEl.dataset.raw  = totalPrice;
            totalAmountEl.textContent  = fmtPrice(totalPrice);
            hiddenPrice.value          = totalPrice;
            hiddenMins.value           = totalMins || defaultDur;
            var names = Array.from(overviewSvcs.querySelectorAll('.service-name'))
                             .map(function (s) { return s.textContent; });
            hiddenSvcs.value = names.join(', ');
            zbResetCoupon();
            loadAvailableDates();
        }

        function hydrateInitialTotalsFromDom() {
            var rawPrice = parseFloat((hiddenPrice && hiddenPrice.value) ? hiddenPrice.value : (totalAmountEl ? totalAmountEl.dataset.raw : '0')) || 0;
            var rawMins  = parseInt((hiddenMins && hiddenMins.value) ? hiddenMins.value : '0', 10) || 0;
            totalPrice = rawPrice;
            totalMins  = rawMins;
            if (totalAmountEl) {
                totalAmountEl.dataset.raw = String(totalPrice);
                totalAmountEl.textContent = fmtPrice(totalPrice);
            }
            if (hiddenPrice) hiddenPrice.value = String(totalPrice);
            if (hiddenMins) hiddenMins.value = String(totalMins);
        }

        function getDuration() {
            var mins = parseInt(hiddenMins.value || '0', 10) || 0;
            if (mins < slotStep) mins = defaultDur;
            if (mins % slotStep !== 0) mins = Math.ceil(mins / slotStep) * slotStep;
            return mins;
        }

        function resetSlotSelect(msg) {
            if (!timeSelect) return;
            timeSelect.innerHTML = '';
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = msg || 'No available slots';
            timeSelect.appendChild(opt);
            timeSelect.disabled = true;
        }

        function setCalendarLoading(isLoading) {
            if (!calendarWrap) return;
            calendarWrap.classList.toggle('zb-loading', !!isLoading);
        }

        function setCalendarStatus(text, kind) {
            if (!calendarStatus) return;
            calendarStatus.textContent = text || '';
            calendarStatus.classList.remove('is-info', 'is-error', 'is-ok');
            calendarStatus.classList.add(kind || 'is-info');
        }

        function loadSlots() {
            if (!dateInput || !timeSelect) return;
            var bookingDate = (dateInput.value || '').trim();
            if (!bookingDate) {
                resetSlotSelect('Select date first');
                return;
            }

            resetSlotSelect('Loading slots...');

            var fd = new FormData();
            fd.append('action', 'zb_get_available_slots');
            fd.append('nonce', zbSlotNonce);
            fd.append('booking_date', bookingDate);
            fd.append('duration_minutes', String(getDuration()));
            if (editBookingId) {
                fd.append('booking_id', String(editBookingId));
            }

            fetchJsonWithTimeout(ajaxUrl, { method: 'POST', body: fd }, 12000)
                .then(function (res) {
                    if (!res.success || !res.data || !Array.isArray(res.data.slots)) {
                        resetSlotSelect('No available slots');
                        zbNotify('error', 'Time slots unavailable', 'Could not fetch available times. Please try another date.');
                        return;
                    }

                    timeSelect.innerHTML = '';
                    var first = document.createElement('option');
                    first.value = '';
                    first.textContent = 'Select time';
                    timeSelect.appendChild(first);

                    res.data.slots.forEach(function (slot) {
                        var opt = document.createElement('option');
                        opt.value = slot;
                        opt.textContent = slot;
                        timeSelect.appendChild(opt);
                    });

                    timeSelect.disabled = res.data.slots.length === 0;
                    if (res.data.slots.length === 0) {
                        first.textContent = 'No available slots';
                    } else if (editMode && editBookingTime) {
                        timeSelect.value = editBookingTime;
                    }
                })
                .catch(function () {
                    resetSlotSelect('Unable to load slots');
                    zbNotify('error', 'Network issue', 'Unable to load time slots right now.');
                });
        }

        function ensureDateStillAvailable(availableDates) {
            if (!dateInput) return;
            var selected = (dateInput.value || '').trim();
            if (!selected) return;
            if (availableDates.indexOf(selected) !== -1) return;
            if (editMode && editBookingDate && selected === editBookingDate) return;

            dateInput.value = '';
            if (calendar) {
                calendar.clear();
            }
            resetSlotSelect('Select date first');
        }

        function initCalendar(availableDates, restrictToAvailableDates) {
            if (!dateInput) return;

            var lockToAvailability = restrictToAvailableDates !== false;

            if (typeof window.flatpickr !== 'function') {
                dateInput.readOnly = false;
                dateInput.type = 'date';
                if (minDate) {
                    dateInput.min = minDate;
                }
                setCalendarStatus('Native date picker active.', 'is-info');
                return;
            }

            if (calendar) {
                calendar.destroy();
                calendar = null;
            }

            var calendarOptions = {
                dateFormat: 'Y-m-d',
                minDate: minDate || 'tomorrow',
                disableMobile: true,
                monthSelectorType: 'static',
                inline: true,
                locale: (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.da) ? 'da' : 'default',
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    if (dayElem.classList.contains('flatpickr-disabled')) {
                        dayElem.setAttribute('title', 'Ikke tilgaengelig');
                    }
                },
                onChange: function(selectedDates, dateStr) {
                    dateInput.value = dateStr || '';
                    loadSlots();
                }
            };

            if (lockToAvailability) {
                calendarOptions.enable = availableDates;
            }

            calendar = window.flatpickr(dateInput, calendarOptions);

            if (dateInput.value) {
                calendar.setDate(dateInput.value, false, 'Y-m-d');
            }
        }

        function loadAvailableDates() {
            if (!dateInput) return;

            setCalendarLoading(true);
            setCalendarStatus('Loading available dates...', 'is-info');

            var fd = new FormData();
            fd.append('action', 'zb_get_available_dates');
            fd.append('nonce', zbSlotNonce);
            fd.append('duration_minutes', String(getDuration()));
            if (editBookingId) {
                fd.append('booking_id', String(editBookingId));
            }

            fetchJsonWithTimeout(ajaxUrl, { method: 'POST', body: fd }, 12000)
                .then(function (res) {
                    setCalendarLoading(false);
                    if (!res.success || !res.data || !Array.isArray(res.data.dates)) {
                        initCalendar([], false);
                        ensureDateStillAvailable([]);
                        setCalendarStatus('Availability check failed. Dates are temporarily open; final validation still happens on submit.', 'is-error');
                        zbNotify('error', 'Calendar not available', 'Availability could not be loaded, so the date picker is open temporarily.');
                        return;
                    }

                    var dates = res.data.dates.slice();
                    if (editMode && editBookingDate && dates.indexOf(editBookingDate) === -1) {
                        dates.push(editBookingDate);
                        dates.sort();
                    }

                    if (dates.length) {
                        initCalendar(dates);
                        ensureDateStillAvailable(dates);
                        setCalendarStatus(dates.length + ' available dates in the next 45 days.', 'is-ok');
                    } else {
                        initCalendar([], false);
                        setCalendarStatus('No prefiltered dates were found. You can still choose a future date and it will be checked on submit.', 'is-error');
                        zbNotify('info', 'No dates prefiltered', 'The calendar is open so you can still choose a future date; availability is checked again on submit.');
                    }

                    if (dateInput.value) {
                        loadSlots();
                    } else {
                        if (editMode && editBookingDate) {
                            dateInput.value = editBookingDate;
                            loadSlots();
                            return;
                        }
                        resetSlotSelect('Select date first');
                    }
                })
                .catch(function () {
                    setCalendarLoading(false);
                    initCalendar([], false);
                    setCalendarStatus('Availability check failed. Dates are temporarily open; final validation still happens on submit.', 'is-error');
                    resetSlotSelect('Select date first');
                    zbNotify('error', 'Calendar load failed', 'Availability could not be loaded, so the date picker is open temporarily.');
                });
        }

        function addServiceToOverview(title, price, time) {
            var existing = Array.from(overviewSvcs.querySelectorAll('.zb-svc-item')).find(function (node) {
                return (node.dataset.title || '') === title;
            });
            if (existing) {
                existing.remove();
                totalPrice -= price; totalMins -= time;
                if (totalPrice < 0) totalPrice = 0;
                if (totalMins < 0) totalMins = 0;
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
                var title = (svc.dataset.title || svc.getAttribute('title') || '').trim();
                var price = parseFloat(svc.dataset.price) || 0;
                var time  = parseInt(svc.dataset.time, 10) || 0;
                if (!title) return;
                if (addServiceToOverview(title, price, time)) {
                    svc.classList.add('zb-selected');
                } else {
                    svc.classList.remove('zb-selected');
                }
                updateTotals();
            });
        }

        var addServiceBtn = document.querySelector('.btn-add-service-ui');
        if (addServiceBtn) {
            addServiceBtn.addEventListener('click', function() {
                var wrap = document.querySelector('.custom-service-wrap');
                if (!wrap) return;
                wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
            });
        }

        window.zbAddService = function () {
            var inp = document.querySelector('.zb_custom_service_input');
            var val = (inp.value || '').trim();
            if (!val) return;
            addServiceToOverview(val, 0, 0);
            updateTotals();
            inp.value = '';
            document.querySelector('.custom-service-wrap').style.display = 'none';
        };

        hydrateInitialTotalsFromDom();

        if (initP && !hiddenSvcs.value.trim()) {
            var match = Array.from(document.querySelectorAll('.zb-service-item')).find(function(i){ return (i.dataset.title || i.getAttribute('title') || '') === initP; });
            if (match) {
                match.click();
            } else {
                addServiceToOverview(initP, initPrice, 0);
                updateTotals();
            }
        }

        // Safety: if URL has p_id and product prefill did not apply by name matching, force base product total.
        if (!hiddenSvcs.value.trim() && initP && initPrice > 0) {
            addServiceToOverview(initP, initPrice, 0);
            updateTotals();
        }

        if (dateInput) {
            dateInput.addEventListener('change', loadSlots);
            if (editMode && editBookingDate) {
                dateInput.value = editBookingDate;
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
                        zbNotify('success', 'Coupon applied', 'Discount has been applied to this booking.');
                    } else {
                        zbNotify('error', 'Coupon invalid', (res.data && res.data.message) ? res.data.message : 'Invalid or expired coupon.');
                    }
                });
            });
        }

        var removeCouponBtn = document.querySelector('.zb-remove-coupon');
        if (removeCouponBtn) {
            removeCouponBtn.addEventListener('click', zbResetCoupon);
        }

        document.getElementById('zb-booking-form').addEventListener('submit', function (e) {
            if (!hiddenSvcs.value.trim()) {
                e.preventDefault();
                zbNotify('error', 'Missing service', 'Please select at least one service before booking.');
                return;
            }
            if (!timeSelect || !timeSelect.value) {
                e.preventDefault();
                zbNotify('error', 'Missing time', 'Please select an available time slot.');
                return;
            }
            var btn = document.getElementById('zbSubmitBtn');
            btn.disabled = true; btn.textContent = editMode ? 'Updating...' : 'Processing...';
        });

        loadAvailableDates();
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

add_action( 'wp_ajax_zb_get_available_slots', 'zb_get_available_slots' );
add_action( 'wp_ajax_zb_get_available_dates', 'zb_get_available_dates' );

function zb_collect_available_slots_for_date( $booking_date, $duration, $busy_intervals = null, $exclude_booking_id = 0 ) {
    $step     = zb_get_slot_interval_minutes();
    $duration = absint( $duration );
    if ( $duration < $step ) {
        $duration = zb_get_default_duration_minutes();
    }
    if ( 0 !== $duration % $step ) {
        $duration = (int) ceil( $duration / $step ) * $step;
    }

    $tz        = wp_timezone();
    $day_start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $booking_date . ' ' . zb_normalize_hhmm( (string) zb_get_setting( 'business_start' ), '08:00' ), $tz );
    $day_end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $booking_date . ' ' . zb_normalize_hhmm( (string) zb_get_setting( 'business_end' ), '18:00' ), $tz );

    if ( ! $day_start || ! $day_end || $day_end <= $day_start ) {
        return [];
    }

    if ( null === $busy_intervals && function_exists( 'zb_calendar_get_busy_intervals' ) && ! empty( zb_calendar_connected_providers() ) ) {
        $day_start_utc = $day_start->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp();
        $day_end_utc   = $day_end->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp();
        $busy_intervals = zb_calendar_get_busy_intervals( $day_start_utc, $day_end_utc );
    }

    if ( ! is_array( $busy_intervals ) ) {
        $busy_intervals = [];
    }

    $slots = [];
    for ( $cursor = $day_start; $cursor < $day_end; $cursor = $cursor->modify( '+' . $step . ' minutes' ) ) {
        $candidate_end = $cursor->modify( '+' . $duration . ' minutes' );
        if ( $candidate_end > $day_end ) {
            break;
        }

        $booking_time = $cursor->format( 'H:i' );
        $bounds       = function_exists( 'zb_build_slot_bounds' ) ? zb_build_slot_bounds( $booking_date, $booking_time, $duration ) : false;
        if ( false === $bounds ) {
            continue;
        }

        if ( function_exists( 'zb_has_booking_conflict' ) && zb_has_booking_conflict( $bounds['start_mysql'], $bounds['end_mysql'], $booking_date, $booking_time, $exclude_booking_id ) ) {
            continue;
        }

        $outlook_conflict = false;
        foreach ( $busy_intervals as $busy ) {
            $busy_start = (int) ( $busy['start'] ?? 0 );
            $busy_end   = (int) ( $busy['end'] ?? 0 );
            if ( $busy_start < $bounds['end_utc'] && $busy_end > $bounds['start_utc'] ) {
                $outlook_conflict = true;
                break;
            }
        }

        if ( $outlook_conflict ) {
            continue;
        }

        $slots[] = $booking_time;
    }

    return $slots;
}

function zb_get_available_dates() {
    if ( ! check_ajax_referer( 'zb_slots_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Login required.' ] );
    }

    $duration = absint( $_POST['duration_minutes'] ?? 0 );
    if ( $duration < zb_get_slot_interval_minutes() ) {
        $duration = zb_get_default_duration_minutes();
    }
    $exclude_booking_id = absint( $_POST['booking_id'] ?? 0 );

    $today = strtotime( wp_date( 'Y-m-d' ) . ' 00:00:00' );
    $from  = strtotime( '+1 day', $today );
    $to    = strtotime( '+45 days', $today );

    $preloaded_busy = null;
    if ( function_exists( 'zb_calendar_get_busy_intervals' ) && ! empty( zb_calendar_connected_providers() ) ) {
        $preloaded_busy = zb_calendar_get_busy_intervals( $from, strtotime( '+1 day', $to ) );
    }

    $available_dates = [];
    for ( $cursor = $from; $cursor <= $to; $cursor = strtotime( '+1 day', $cursor ) ) {
        $date  = wp_date( 'Y-m-d', $cursor );
        $slots = zb_collect_available_slots_for_date( $date, $duration, $preloaded_busy, $exclude_booking_id );
        if ( ! empty( $slots ) ) {
            $available_dates[] = $date;
        }
    }

    wp_send_json_success( [ 'dates' => $available_dates ] );
}

function zb_get_available_slots() {
    if ( ! check_ajax_referer( 'zb_slots_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Login required.' ] );
    }

    $booking_date = sanitize_text_field( $_POST['booking_date'] ?? '' );
    if ( ! function_exists( 'zb_is_valid_booking_date' ) || ! zb_is_valid_booking_date( $booking_date ) ) {
        wp_send_json_error( [ 'message' => 'Invalid booking date.' ] );
    }

    $duration = absint( $_POST['duration_minutes'] ?? 0 );
    $exclude_booking_id = absint( $_POST['booking_id'] ?? 0 );
    $slots = zb_collect_available_slots_for_date( $booking_date, $duration, null, $exclude_booking_id );

    wp_send_json_success( [ 'slots' => $slots ] );
}
