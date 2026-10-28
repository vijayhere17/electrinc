<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <h1>Account</h1>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-body">

                    <div id="error_msg"></div>

                    <form id="save_account">

                        <input type="hidden" id="account_id" name="id">

                        <div class="row">

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Account Name</label>
                                    <span class="text-danger">*</span>
                                    <input type="text"
                                           class="form-control"
                                           id="account_name"
                                           name="account_name"
                                           data-index="1"
                                           required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Account Group</label>
                                    <span class="text-danger">*</span>
                                    <!-- IMPORTANT: data-index MUST be on the <select> tag -->
                                    <select class="form-control select2"
                                            id="account_group_id"
                                            name="account_group_id"
                                            data-index="2"
                                            required>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea class="form-control"
                                              id="address"
                                              name="address"
                                              rows="2"
                                              data-index="3"></textarea>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Mobile No</label>
                                    <input type="text"
                                           class="form-control num_only"
                                           id="mobile_no"
                                           name="mobile_no"
                                           data-index="4">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Opening Gold</label>
                                    <input type="text"
                                           class="form-control num_only"
                                           id="opening_gold"
                                           name="opening_gold"
                                           data-index="5">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Opening Silver</label>
                                    <input type="text"
                                           class="form-control num_only"
                                           id="opening_silver"
                                           name="opening_silver"
                                           data-index="6">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Opening Amount</label>
                                    <input type="text"
                                           class="form-control num_only"
                                           id="opening_amount"
                                           name="opening_amount"
                                           data-index="7">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>TDS (%)</label>
                                    <input type="text"
                                           class="form-control num_only"
                                           id="tds"
                                           name="tds"
                                           data-index="8">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <br>
                                    <label>Is Cash Bank</label>
                                    &nbsp;
                                    <input type="checkbox"
                                           id="is_cash_bank"
                                           name="is_cash_bank"
                                           data-index="9"
                                           style="width:20px;height:20px;">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <label>&nbsp;</label>
                                <br>
                                <!-- data-index="10" on the Save button -->
                                <input type="button"
                                       class="btn btn-primary module_save_btn"
                                       value="Save"
                                       data-index="10">
                            </div>

                        </div>

                    </form>

                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="dataTable-account" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="100">Action</th>
                                    <th>Account Name</th>
                                    <th>Account Group</th>
                                    <th>Mobile No</th>
                                    <th>Address</th>
                                    <th>TDS</th>
                                    <th>Is Cash Bank</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- Scripts -->
<?php include 'includes/scripts_start.php'; ?>

<script>

var accountTable;

$(document).ready(function () {

    initAjaxSelect2(
        $("#account_group_id"),
        "api/get_account_groups.php"
    );

    accountTable = $('#dataTable-account').DataTable({
        ajax: {
            url: 'api/get_accounts_table.php',
            dataSrc: 'data'
        },
        columns: [
            {
                data: 'id',
                render: function (data) {
                    return `
<button class="btn btn-sm btn-info edit_account" data-account_id="${data}">
    <i class="fa fa-edit"></i>
</button>
<button class="btn btn-sm btn-danger delete_account" data-account_id="${data}">
    <i class="fa fa-times"></i>
</button>`;
                }
            },
            { data: 'account_name' },
            { data: 'group_name' },
            { data: 'mobile_no' },
            { data: 'address' },
            { data: 'tds' },
            { data: 'is_cash_bank' }
        ],
        ordering:  false,
        responsive: true,
        autoWidth: false
    });

    // ── STEP 5: Form submit (AJAX unchanged, add ERPNav.onSaveSuccess) ─
    $(document).on('submit', '#save_account', function (e) {
        e.preventDefault();

        var postData = new FormData(this);

        $('.module_save_btn').attr('disabled', true);

        $.ajax({
            url:         'api/save_account.php',
            type:        'POST',
            processData: false,
            contentType: false,
            cache:       false,
            data:        postData,
            success: function (response) {

                $('.module_save_btn').removeAttr('disabled');

                $('#save_account')[0].reset();

                $('#account_group_id').val(null).trigger('change');

                accountTable.ajax.reload(null, false);

                $('.module_save_btn').val('Save');

                alert('Account Saved Successfully');

                if (window.BULNav) BULNav.focusFirst();
            }
        });
    });

    // ── STEP 6: num_only (unchanged) ─────────────────────────────────
    $(document).on('input', '.num_only', function () {
        this.value = this.value.replace(/[^\d\.\-]/g, '');
    });

    // ── STEP 7: Save button click (unchanged) ────────────────────────
    $(document).on('click', '.module_save_btn', function () {
        $('#save_account').trigger('submit');
    });

    // ── STEP 8: Delete account (unchanged) ───────────────────────────
    $(document).on('click', '.delete_account', function () {
        if (!confirm('Delete this account?')) return;

        var id = $(this).data('account_id');

        $.ajax({
            url:  'api/delete_account.php',
            type: 'POST',
            data: { id: id },
            success: function () {
                accountTable.ajax.reload(null, false);
            }
        });
    });

    // ── STEP 9: Edit account (unchanged) ─────────────────────────────
    $(document).on('click', '.edit_account', function () {
        var id = $(this).data('account_id');

        $.ajax({
            url:      'api/get_account_details.php',
            type:     'POST',
            data:     { id: id },
            dataType: 'json',
            success: function (data) {

                $('#account_id').val(data.id);
                $('#account_name').val(data.account_name);
                $('#mobile_no').val(data.mobile_no);
                $('#address').val(data.address);
                $('#opening_gold').val(data.opening_gold);
                $('#opening_silver').val(data.opening_silver);
                $('#opening_amount').val(data.opening_amount);
                $('#tds').val(data.tds);

                $('#is_cash_bank').prop('checked', data.is_cash_bank == 1);

                var option = new Option(
                    data.group_name,
                    data.account_group_id,
                    true, true
                );
                $('#account_group_id').append(option).trigger('change');

                $('.module_save_btn').val('Update');

                window.scrollTo({ top: 0, behavior: 'smooth' });

                // Focus first field after loading for edit
                setTimeout(function () {
                    if (window.BULNav) BULNav.focusFirst();
                }, 300);
            }
        });
    });

});

</script>
<?php include 'includes/scripts_end.php'; ?>
