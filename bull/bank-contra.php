<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    Add New Bank Contra
    <a href="index.php/admin/bankcontra" class="btn btn-primary float-right">Bank Contra List</a>
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="custom_alert_by_js d-none"></div>
<div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <form method="POST" action="index.php/admin/bankcontra/create" accept-charset="UTF-8" id="save_bankcontra" enctype="multipart/form-data" data-parsley-validate=""><input name="_token" type="hidden" value="pievynsYOomb2eSvst98DvM3oq4Po4LdBdrC1sYa">
        <input type="hidden" name="_token" value="pievynsYOomb2eSvst98DvM3oq4Po4LdBdrC1sYa">
        <input id="bankcontra_id" name="id" type="hidden" value="">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="bankcontra_date" class="control-label">Date</label><span class="text-danger">*</span>
                    <input type="text"
name="bankcontra_date"
id="bankcontra_date"
class="form-control"
placeholder=""
data-index="1"
value="<?php echo date('d-m-Y'); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="from_party_id" class="control-label">From</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="from_party_id" id="from_party_id" data-index="2" >
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="to_party_id" class="control-label">To</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_to_party_filter">Reset</span>
                    <select class="form-control select2" name="to_party_id" id="to_party_id" data-index="3" >
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="amount" class="control-label">Amount</label><span class="text-danger">*</span>
                    <input class="form-control positive_num_only" data-index="4" name="amount" type="text" value="" id="amount">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="narration" class="control-label">Narration</label>
                    <textarea class="form-control " rows="2" data-index="5" name="narration" cols="50" id="narration"></textarea>
                </div>
            </div>
            <div class="col-md-2">
                <label>&nbsp;</label><br>
                <input class="btn btn-primary module_save_btn" data-index="6" type="submit" value="Save">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label><a href="javascript:void(0);" class="selected_party_link">Selected From Cash/Bank Balance</a></label> :
                    <div id="cash_bank_account_balance" style="font-size: 18px;"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label><a href="javascript:void(0);" class="selected_to_party_link">Selected To Cash/Bank Balance</a></label> :
                    <div id="to_cash_bank_account_balance" style="font-size: 18px;"></div>
                </div>
            </div>
        </div>
        </form>
    </div>
</div>

                </div>
            </div>

        </div>

        
        
        
        
    </div>
    
    
    <style>

/* ===== MODERN DATEPICKER ===== */

.datepicker {
    border-radius: 10px !important;
    border: 1px solid #dcdcdc !important;
    padding: 10px !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.12) !important;
    font-size: 14px !important;
}

.datepicker-dropdown {
    min-width: 280px !important;
}

.datepicker table {
    width: 100% !important;
}

.datepicker table tr td,
.datepicker table tr th {
    width: 38px !important;
    height: 38px !important;
    text-align: center !important;
    border-radius: 6px !important;
    border: none !important;
}

.datepicker table tr td.day:hover,
.datepicker table tr td.focused {
    background: #f1f5f9 !important;
    cursor: pointer;
}

.datepicker table tr td.active.active,
.datepicker table tr td.active:hover,
.datepicker table tr td.active {
    background: #007bff !important;
    color: #fff !important;
}

.datepicker table tr td.today {
    background: #fff3cd !important;
    color: #000 !important;
}

.datepicker .datepicker-switch {
    font-weight: 700 !important;
    font-size: 22px !important;
}

.datepicker .prev,
.datepicker .next {
    font-size: 18px !important;
    font-weight: bold !important;
}

</style>
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<?php include 'includes/scripts_start.php'; ?>
    <script>
        var isSubmitting = false;
        var from_party_id = '';
        var entry_objectdata = [];
        var gold_not_in_stock = 0;
        var silver_not_in_stock = 0;
        $(document).ready( function(){
            $('.select2').select2();
            $('#bankcontra_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});

            // REMOVED: old Enter key handler replaced by includes/erp_keyboard_nav.js
            
            $(document).bind("keydown", function(e){
                if(e.ctrlKey && e.which == 83){
                    e.preventDefault();
                    //$("#save_bankcontra").submit();
                    if(!isSubmitting){
                        isSubmitting = true; // 🔥 prevent double trigger
                        $('#save_bankcontra').trigger('submit');
                    }
                    return false;
                }
            });

            initAjaxSelect2($("#from_party_id"), "api/cash_bank_select2_source.php");

                                  initAjaxSelect2($("#to_party_id"), "api/cash_bank_select2_source.php");
                                    
            $('#from_party_id').select2('open');

            var edit_id =
new URLSearchParams(window.location.search).get('id');

if(edit_id != null){

    $.ajax({

        url: 'api/get_bank_contra_details.php?id=' + edit_id,

        type: 'GET',

        dataType: 'json',

        success: function(json){

            console.log(json);

            $('#bankcontra_id').val(json.id);

            $('#bankcontra_date').val(json.entry_date);

            $('#amount').val(json.amount);

            $('#narration').val(json.narration);

            if(json.from_account){

                var from_option = new Option(
                    json.from_account_name,
                    json.from_account,
                    true,
                    true
                );

                $('#from_party_id')
                    .append(from_option)
                    .trigger('change');

            }

            if(json.to_account){

                var to_option = new Option(
                    json.to_account_name,
                    json.to_account,
                    true,
                    true
                );

                $('#to_party_id')
                    .append(to_option)
                    .trigger('change');

            }

        }

    });

}
            

            $(document).on('click', '.reset_party_filter', function(){
                $('#from_party_id').val(null).trigger('change');
            });

            $(document).on('click', '.reset_to_party_filter', function(){
                $('#to_party_id').val(null).trigger('change');
            });
            
            $(document).on('change', '#from_party_id', function(){
                get_account_balance($(this).val(), 'cash_bank_account_balance');
                $('.selected_party_link').attr('href', "javascript:void(0);");
                if($(this).val() != null){
                    $('.selected_party_link').attr('href', "cashbank-ledger.php?id="+ $(this).val());
                }
            });
            $(document).on('change', '#to_party_id', function(){
                get_account_balance($(this).val(), 'to_cash_bank_account_balance');
                $('.selected_to_party_link').attr('href', "javascript:void(0);");
                if($(this).val() != null){
                    $('.selected_to_party_link').attr('href', "cashbank-ledger.php?id="+ $(this).val());
                }
            });
            
            $(document).on('submit', '#save_bankcontra', function (e) {
                 e.preventDefault();
                if (isSubmitting) {
                    return false; // stop multiple clicks
                }
                isSubmitting = true; // 🔥 MOVE HERE (VERY IMPORTANT)
                if ($.trim($("#bankcontra_date").val()) == '') {
                    isSubmitting = false;
                    bootbox.alert('<span class="text-danger">Select Date !</span>');
                    return false;
                }
                if ($.trim($("#from_party_id").val()) == '') {
                    isSubmitting = false;
                    bootbox.alert('<span class="text-danger">Select From Party required !</span>');
                    return false;
                }
                if ($.trim($("#to_party_id").val()) == '') {
                    isSubmitting = false;
                    bootbox.alert('<span class="text-danger">Select To Party required !</span>');
                    return false;
                }
                if ($.trim($("#amount").val()) == '' || $.trim($("#amount").val()) == '0') {
                    isSubmitting = false;
                    bootbox.alert('<span class="text-danger">Amount required !</span>');
                    return false;
                }
                $('.module_save_btn').attr('disabled', 'disabled');
                var postData = new FormData(this);
                $.ajax({
                    url: "api/save_bank_contra.php",
                    type: "POST",
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: postData,
                    datatype: 'json',
                    async: false,
                    success: function (response) {

    isSubmitting = false;

    $('.module_save_btn').removeAttr('disabled');

    if(response.status == true){

        alert(response.message);

        $('#save_bankcontra')[0].reset();

        $('#from_party_id').val(null).trigger('change');

        $('#to_party_id').val(null).trigger('change');

        $('#cash_bank_account_balance').html('');

        $('#to_cash_bank_account_balance').html('');

    }
    else{

        alert(response.message);

    }

},
                });
                return false;
            });

        });function get_account_balance(account_id, id_attr){
        if(account_id != '' && account_id != null){
            $.ajax({
                url: "api/get_cash_bank_balance.php?account_id="+ account_id,
                type: 'GET',
                data: '',
                success: function (response) {

    var amount = parseFloat(response.amount);

    var html_data = '';

    html_data += '<strong>';

    html_data += 'Amount : ';

    if(amount >= 0){

        html_data += '<span class="text-blue">' + amount + ' Cr</span>';

    }
    else{

        html_data += '<span class="text-red">' + amount + ' Dr</span>';

    }

    html_data += '</strong>';

    $('#' + id_attr).html(html_data);

}
            });
        } else {
            $('#'+ id_attr ).html('');
        }
    }
    
    
    </script>

    <script>
   $(document).on('keydown', function(e) {

    if (e.altKey && !e.ctrlKey && !e.shiftKey) {

        var url = '';

        switch (e.key.toLowerCase()) {

            case 'a':
                url = "accounts.php";
            break;

            case 's':
                url = "soda-entry.php";
            break;

            case 't':
                url = "transaction.php";
            break;

            case 'p':
                url = "party-ledger.php";
            break;

            case 'r':
                url = "rani.php";
            break;

            case 'u':
                url = "rupu.php";
            break;

            case 'h':
                url = "havaalo.php";
            break;

            case 'm':
                url = "items.php";
            break;

            case 'b':
                url = "bank-contra.php";
            break;

            case 'o':
                url = "soda-report.php";
            break;

            case 'd':
                url = "tds-report.php";
            break;

        }

        if (url) {

            e.preventDefault();

            window.location.href = url;

        }

    }
        
        // Global Form Save (Ctrl+S)
        if (e.ctrlKey && e.which === 83) { // 83 is 's'
            e.preventDefault();
            
            // Find if there is a primary save button with the class 'module_save_btn'
            if ($('.module_save_btn').length > 0) {
                $('.module_save_btn').first().click(); // Click the save button to trigger validation and submit
            } else if ($('form').length > 0) {
                $('form').first().submit(); // Fallback to submit the first form on the page normally
            }
            
            return false;
        }
    });

    $(document).ready(function(){
        $("input").attr("autocomplete", "off");
        $("textarea").attr("autocomplete", "off");

        $(document).on('input', ".num_only", function () {
            this.value = this.value.replace(/[^\d\.\-]/g, '');
        });
        
        $(document).on('input', ".positive_num_only", function () {
            this.value = this.value.replace(/[^\d\.]/g, '');
        });
        
        $(document).on('input', ".positive_num_without_decimal_only", function () {
            positive_num_only_value = parseInt(this.value.replace(/[^\d\.]/g, ''));
            if(isNaN(positive_num_only_value)){
                this.value = '';
            } else {
                this.value = positive_num_only_value;
            }
        });

        $(document).on('click', ".data_blank_class", function () {
            if (confirm('Are you sure, You want to blank all Data?!!!')) {
                alert('Please Wait, Data Blank in Progress...');
                $.ajax({
                    url: "#",
                    type: "GET",
                    cache: false,
                    async: false,
                    success: function (response) {
                        var json = $.parseJSON(response);
                        if (json['success'] == 'Added') {
                            redirectWithFlashMessage("index.php", 'success', json['msg']);
                        } else {
                            alert('Something error occurred');
                        }
                        return false;
                    },
                });
            } else {
                return false;
            }
        });
    });
        
        function redirectWithFlashMessage ( redirect_url, status, message ) {
            $.ajax({
                type: 'POST',
                url: "#",
                data: { 'status' : status , 'message' : message, "_token": "pievynsYOomb2eSvst98DvM3oq4Po4LdBdrC1sYa" }
            }).done(function () {
                window.location.href = redirect_url;
            });
        }
        function redirectWithFlashMessageAndDataId ( redirect_url, status, message, data_id ) {
            $.ajax({
                type: 'POST',
                url: "#",
                data: { 'status' : status , 'message' : message, "_token": "pievynsYOomb2eSvst98DvM3oq4Po4LdBdrC1sYa", 'data_id' : data_id }
            }).done(function () {
                window.location.href = redirect_url;
            });
        }
// Select2 helpers: includes/select2_helpers.js

                }
            });
        } else {
            $selector.empty().append($('<option/>').val('').text('--select--')).val('').trigger("change");
        }
    }

    function data_table_export_icon_style(){
        $('.dt-button.buttons-pdf').css('border', 'none');
        var pdf_icon = "https://img.icons8.com/color/48/pdf.png";
        $('.dt-button.buttons-pdf').html('<img src="' + pdf_icon + '" style="width:25px;" alt="PDF" title="PDF" >');
        $('.dt-button.buttons-excel').css('border', 'none');
        var excel_icon = "https://img.icons8.com/color/48/ms-excel.png";
        $('.dt-button.buttons-excel').html('<img src="' + excel_icon + '" style="width:25px;" alt="Excel" title="Excel" >');
    }

    var oldExportAction = function (self, e, dt, button, config) {
        if (button[0].className.indexOf('buttons-copy') >= 0) {
            $.fn.dataTable.ext.buttons.copyHtml5.action(e, dt, button, config);
        } else if (button[0].className.indexOf('buttons-pdf') >= 0) {
//            $.fn.dataTable.ext.buttons.pdfHtml5.action(e, dt, button, config);
            if ($.fn.dataTable.ext.buttons.pdfHtml5.available(dt, config)) {
                $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config);
            }
            else {
                $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config);
            }
        } else if (button[0].className.indexOf('buttons-csv') >= 0) {
            $.fn.dataTable.ext.buttons.csvHtml5.action(e, dt, button, config);
        } else if (button[0].className.indexOf('buttons-excel') >= 0) {
            if ($.fn.dataTable.ext.buttons.excelHtml5.available(dt, config)) {
                $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config);
            }
            else {
                $.fn.dataTable.ext.buttons.excelFlash.action.call(self, e, dt, button, config);
            }
        } else if (button[0].className.indexOf('buttons-print') >= 0) {
            $.fn.dataTable.ext.buttons.print.action(e, dt, button, config);
        }
    };

    var newExportAction = function (e, dt, button, config) {
        var self = this;
        var oldStart = dt.settings()[0]._iDisplayStart;
//            $(".box-body").addClass('overlay clearfix');
//            $(".box-body").append('<i class="fa fa-refresh fa-spin"></i>');
        $('#ajax-loader').show();
        dt.one('preXhr', function (e, s, data) {
            // Just this once, load all data from the server...
            data.start = 0;
            data.length = 2147483647;

            dt.one('preDraw', function (e, settings) {
                // Call the original action function
                oldExportAction(self, e, dt, button, config);

                dt.one('preXhr', function (e, s, data) {
                    // DataTables thinks the first item displayed is index 0, but we're not drawing that.
                    // Set the property to what it was before exporting.
                    settings._iDisplayStart = oldStart;
                    data.start = oldStart;
                });

                // Reload the grid with the original page. Otherwise, API functions like table.cell(this) don't work properly.
                setTimeout(dt.ajax.reload, 0);
//
//                    $(".box-body").removeClass('overlay');
//                    $(".box-body i.fa.fa-refresh.fa-spin").remove();
                $('#ajax-loader').hide();
                // Prevent rendering of the full data to the DOM
                return false;
            });
        });

        // Requery the server with the new one-time export settings
        dt.ajax.reload();
    };

    $(document).ready(function(){
        $("#header_party_id").select2({
            placeholder: " -- Go to Ledger -- ",
            width:"100%",
            minimumInputLength: 0,
//                tags: true,
            ajax: {
                url: "api/get_party_select2.php",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term, // search term
                        page: params.page
                    };
                },
                processResults: function (data,params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results,
                        pagination: {
                            more: (params.page * 5) < data.total_count
                        }
                    };
                },
                cache: true
            }
        });
                $(document).on('change', "#header_party_id", function () {
            var header_party_id = $('#header_party_id').val();
            if(header_party_id != '' && header_party_id != null){
                $.ajax({
                    url: "api/get_account_balance.php?account_id="+ header_party_id,
                    type: 'GET',
                    data: '',
                    success: function (response) {
                        var json = $.parseJSON(response);
                        if(json.account_data.account_group_id == '2' || json.account_data.account_group_id == '3'){
                            window.location.href = "party-ledger.php?id=" + header_party_id;
                        } else {
                            window.location.href = "cashbank-ledger.php?id=" + header_party_id;
                        }
                    }
                });
            }
        });
    });

    </script>
<?php include 'includes/scripts_end.php'; ?>
