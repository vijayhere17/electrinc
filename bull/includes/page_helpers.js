/**
 * Shared page helpers — local replacements for Laravel production helpers.
 */
(function ($) {
    'use strict';

    window.redirectWithFlashMessage = function (redirect_url, status, message) {
        if (message) {
            alert(message);
        }
        window.location.href = redirect_url;
    };

    window.redirectWithFlashMessageAndDataId = function (redirect_url, status, message, data_id) {
        redirectWithFlashMessage(redirect_url, status, message);
    };

    window.data_table_export_icon_style = function () {
        var pdfIcon = 'https://img.icons8.com/color/48/pdf.png';
        var excelIcon = 'https://img.icons8.com/color/48/ms-excel.png';
        $('.dt-button.buttons-pdf').css('border', 'none')
            .html('<img src="' + pdfIcon + '" style="width:25px;" alt="PDF" title="PDF">');
        $('.dt-button.buttons-excel').css('border', 'none')
            .html('<img src="' + excelIcon + '" style="width:25px;" alt="Excel" title="Excel">');
    };

    // Wire delete confirmation forms to local API
    $(document).on('submit', 'form#dform', function (e) {
        e.preventDefault();
        var $form = $(this);
        var postData = {};
        var url = '';

        if ($('#delete_transaction_id').val()) {
            url = 'api/delete_transaction.php';
            postData.transaction_id = $('#delete_transaction_id').val();
        } else if ($('#delete_rani_id').val()) {
            url = 'api/delete_rani.php';
            postData.id = $('#delete_rani_id').val();
        } else if ($('#delete_rupu_id').val()) {
            url = 'api/delete_rupu.php';
            postData.id = $('#delete_rupu_id').val();
        } else if ($('#delete_havaalo_id').val()) {
            url = 'api/delete_havaalo.php';
            postData.id = $('#delete_havaalo_id').val();
        } else if ($('#delete_bank_contra_id').val()) {
            url = 'api/delete_bank_contra.php';
            postData.id = $('#delete_bank_contra_id').val();
        } else if ($('#delete_rani_transfer_id').val()) {
            url = 'api/delete_rani_transfer.php';
            postData.id = $('#delete_rani_transfer_id').val();
        } else if ($('#delete_rupu_transfer_id').val()) {
            url = 'api/delete_rupu_transfer.php';
            postData.id = $('#delete_rupu_transfer_id').val();
        }

        if (!url) return false;

        $.post(url, postData, function (response) {
            var json = typeof response === 'object' ? response : $.parseJSON(response);
            if (json.success) {
                $('.modal').modal('hide');
                if (typeof transactionTable !== 'undefined') transactionTable.ajax.reload();
                if (typeof raniTable !== 'undefined') raniTable.ajax.reload();
                if (typeof rupuTable !== 'undefined') rupuTable.ajax.reload();
                if (typeof havaaloTable !== 'undefined') havaaloTable.ajax.reload();
                if (typeof bankContraTable !== 'undefined') bankContraTable.ajax.reload();
                location.reload();
            } else {
                alert(json.msg || 'Delete failed');
            }
        });
        return false;
    });

    // Data Blank — disabled for safety
    $(document).on('click', '.data_blank_class', function () {
        if (confirm('Data Blank is disabled in this environment.')) {
            alert('This action is not available locally.');
        }
        return false;
    });

})(jQuery);
