jQuery(document).ready(function ($) {
    'use strict';

    var toastHost = null;

    function ensureToastHost() {
        if (toastHost) return toastHost;
        toastHost = $('<div class="zb-admin-toast-host" />');
        $('body').append(toastHost);
        return toastHost;
    }

    function zbAdminNotify(type, title, body) {
        var $host = ensureToastHost();
        var $toast = $('<div class="zb-admin-toast zb-admin-toast--' + (type || 'info') + '"><div class="zb-admin-toast-title"></div><div class="zb-admin-toast-body"></div></div>');
        $toast.find('.zb-admin-toast-title').text(title || 'Notice');
        $toast.find('.zb-admin-toast-body').text(body || '');
        $host.append($toast);

        setTimeout(function () {
            $toast.addClass('is-leaving');
            setTimeout(function () {
                $toast.remove();
            }, 220);
        }, 3000);
    }

    $(document).on('click', '.zb-edit-btn', function () {
        var row = $(this).closest('tr');
        $('#zb_bid').val(row.data('id'));
        $('#zb_modal_company').text(row.data('company'));
        $('#zb_modal_email').text(row.data('email'));
        $('#zb_modal_address').text(row.data('address'));
        $('#zb_modal_services').text(row.data('services'));
        $('#zb_modal_booking_date').val(row.data('booking-date') || '');
        $('#zb_modal_booking_time').val(row.data('booking-time') || '');
        $('#zb_modal_status').val(row.data('status') || 'pending');
        $('#zbModal').addClass('zb-open');
    });
    $('#zbCloseModal').on('click', function () {
        $('#zbModal').removeClass('zb-open');
    });
    $('#zbModal').on('click', function (e) {
        if (e.target === this) {
            $('#zbModal').removeClass('zb-open');
        }
    });
    $('#zbEditForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]');
        var $row = $('tr[data-id="' + $('#zb_bid').val() + '"]');
        $btn.prop('disabled', true).text('Gemmer...');

        $.post(zb_ajax.ajax_url, {
            action:     'zb_update_status',
            nonce:      zb_ajax.nonce,
            booking_id: $('#zb_bid').val(),
            status:     $('#zb_modal_status').val(),
            booking_date: $('#zb_modal_booking_date').val(),
            booking_time: $('#zb_modal_booking_time').val(),
            company_name: $row.data('company'),
            contact_person: $row.data('contact'),
            email: $row.data('email'),
            address: $row.data('address'),
            services: $row.data('services')
        }, function (response) {
            $btn.prop('disabled', false).text('Gem ændringer');
            if (response.success) {
                var bookingId = $('#zb_bid').val();
                $('tr[data-id="' + bookingId + '"]')
                    .attr('data-status', $('#zb_modal_status').val())
                    .attr('data-booking-date', $('#zb_modal_booking_date').val())
                    .attr('data-booking-time', $('#zb_modal_booking_time').val())
                    .find('.zb-datetime-cell').text($('#zb_modal_booking_date').val() + ' ' + $('#zb_modal_booking_time').val())
                    .end()
                    .find('.zb-status-cell').text($('#zb_modal_status').val());
                $('#zbModal').removeClass('zb-open');
                zbAdminNotify('success', 'Booking opdateret', 'Dato, tid og status blev gemt korrekt.');
            } else {
                zbAdminNotify('error', 'Opdatering mislykkedes', String(response.data || 'Ukendt fejl'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Gem ændringer');
            zbAdminNotify('error', 'Netvaerksfejl', 'Kunne ikke opdatere bookingstatus. Proev igen.');
        });
    });
});
