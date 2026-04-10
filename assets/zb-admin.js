jQuery(document).ready(function ($) {
    'use strict';
    $(document).on('click', '.zb-edit-btn', function () {
        var row = $(this).closest('tr');
        $('#zb_bid').val(row.data('id'));
        $('#zb_modal_company').text(row.data('company'));
        $('#zb_modal_email').text(row.data('email'));
        $('#zb_modal_address').text(row.data('address'));
        $('#zb_modal_services').text(row.data('services'));
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
        $btn.prop('disabled', true).text('Gemmer...');

        $.post(zb_ajax.ajax_url, {
            action:     'zb_update_status',
            nonce:      zb_ajax.nonce,
            booking_id: $('#zb_bid').val(),
            status:     $('#zb_modal_status').val()
        }, function (response) {
            $btn.prop('disabled', false).text('Gem status');
            if (response.success) {
                var newStatus = response.data.status;
                $('tr[data-id="' + $('#zb_bid').val() + '"]')
                    .attr('data-status', newStatus)
                    .find('.zb-status-cell')
                    .text(newStatus);
                $('#zbModal').removeClass('zb-open');
            } else {
                alert('Opdatering mislykkedes: ' + (response.data || 'Ukendt fejl'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Gem status');
            alert('Netværksfejl. Prøv igen.');
        });
    });
});
