<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    Add New Rupu
    <a href="rupu-list.php" class="btn btn-primary float-right">Rupu List</a> 
    <span class="float-right">&nbsp;  &nbsp; </span>
    <a href="transaction.php" class="btn btn-primary float-right">Go To Transaction</a>
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="custom_alert_by_js d-none"></div>
<div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <form method="POST"
id="save_rupu" enctype="multipart/form-data" data-parsley-validate=""><input name="_token" type="hidden" value="DtwpNa4VCI4rxbpfyN386tosKnuIgmtCXJgfj5Qw">
        <input type="hidden" name="_token" value="DtwpNa4VCI4rxbpfyN386tosKnuIgmtCXJgfj5Qw">
        <input id="rupu_id" name="id" type="hidden" value="">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="rupu_date" class="control-label">Date</label><span class="text-danger">*</span>
                    <input type="text" name="date" id="rupu_date" class="form-control" placeholder="" data-index="1" value="<?= date('d-m-Y') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="party_id" class="control-label">Party</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="party_id" id="party_id" data-index="2" >
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="silver_fine" class="control-label">Silver</label>
                    <input class="form-control" data-index="3" name="silver_fine" type="text" value="" id="silver_fine">
                </div>
            </div>
            <div class="col-md-3">
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="silver_qty" class="control-label">Silver Qty</label>
                    <input class="form-control num_only" data-index="4" name="silver_qty" type="text" value="" id="silver_qty">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="silver_rate" class="control-label">Silver Rate</label>
                    <input class="form-control positive_num_only" data-index="5" name="silver_rate" type="text" value="" id="silver_rate">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="silver_rate_amount" class="control-label">Silver Rate Amount</label>
                    <input class="form-control num_only" readonly="" data-index="" name="silver_rate_amount" type="text" value="" id="silver_rate_amount">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="cash_bank_id" class="control-label">Cash/Bank</label><span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="cash_bank_id" id="cash_bank_id" data-index="6" >
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="amount" class="control-label">Amount</label>
                    <input class="form-control num_only" data-index="7" name="amount" type="text" value="" id="amount">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="badlo" class="control-label">Badlo</label>
                    <input class="form-control positive_num_only" data-index="8" name="badlo" type="text" value="" id="badlo">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="after_badlo" class="control-label">After Badlo</label>
                    <input class="form-control num_only" readonly="" data-index="" name="after_badlo" type="text" value="" id="after_badlo">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="fine_badlo" class="control-label">Fine Badlo</label>
                    <input class="form-control num_only" readonly="" data-index="" name="fine_badlo" type="text" value="" id="fine_badlo">
                </div>
            </div>
            <div class="col-md-5">
                <div class="form-group">
                    <label for="narration" class="control-label">Narration</label>
                    <textarea class="form-control " rows="2" data-index="9" name="narration" cols="50" id="narration"></textarea>
                </div>
            </div>
            <div class="col-md-1">
                <label>&nbsp;</label><br>
                <input class="btn btn-primary module_save_btn" data-index="10" type="submit" value="Save">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label><a href="javascript:void(0);" class="selected_party_link">Selected Party Balance</a></label> :
                    <div id="party_account_balance" style="font-size: 18px;"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Selected Cash/Bank </label> :
                    <div id="cash_bank_account_balance" style="font-size: 18px;"></div>
                </div>
            </div>
        </div>
        <div class="row" style="padding: 10px;">
            <label for="" class="control-label">Lineitems : </label>
                <input type="hidden" id="tds" value="">
                <input type="hidden" id="edit_line" value="0">
                <input type="hidden" name="line_items_data[line_items_index]" id="line_items_index" />
                <input type="hidden" name="line_items_data[lineitem_id]" id="lineitem_id" value="" autofocus=""/>
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="rupu_gross" class="control-label">Rupu Gross</label><span class="text-danger"> *</span>
                                <input type="text" name="line_items_data[rupu_gross]" id="rupu_gross" class="rupu_gross form-control positive_num_only" data-index="11">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="touch" class="control-label">Touch</label><span class="text-danger"> *</span>
                                <input type="text" name="line_items_data[touch]" id="touch" class="touch form-control positive_num_only" data-index="12">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="fine" class="control-label">Fine</label>
                                <input type="text" name="line_items_data[fine]" id="fine" class="fine form-control num_only" readonly="">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="rupu_narration" class="control-label">Narration</label>
                                <textarea name="rupu_narration" id="rupu_narration" class="form-control rupu_narration" rows="2" data-index="13"></textarea>
                            </div>
                        </div>
                        <div class="col-md-2 pull-right">
                            <div class="form-group pull-right">
                                <br/>
                                <button class="btn btn-primary save_btn pull-right" id="add_lineitem" data-index="14" type="button">Add</button>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
            <div class="row" style="padding: 10px;">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table id="dataTable-optable" class="table table-bordered" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th width="100px"></th>
                                    <th style="white-space: nowrap">Pending</th>
                                    <input type="hidden" name="total_pending" id="total_pending_input">
                                    <th style="white-space: nowrap" id="total_pending" class="text-right"></th>
                                    <th style="white-space: nowrap"></th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th width="100px">Action</th>
                                    <th style="white-space: nowrap">Rupu</th>
                                    <th style="white-space: nowrap">Touch</th>
                                    <th style="white-space: nowrap">Fine</th>
                                    <th>Narration</th>
                                </tr>
                                <tr>
                                    <th width="100px">Total</th>
                                    <input type="hidden" name="total_rupu_gross_input" id="total_rupu_gross_input">
                                    <th style="white-space: nowrap" id="total_rupu_gross" class="text-right"></th>
                                    <th style="white-space: nowrap" class="text-right"></th>
                                    <input type="hidden" name="total_fine_input" id="total_fine_input">
                                    <th style="white-space: nowrap" id="total_fine" class="text-right"></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="lineitem_list">
                            </tbody>
                        </table>
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

.datepicker table tr td.active,
.datepicker table tr td.active:hover,
.datepicker table tr td.active.active {
    background: #007bff !important;
    color: #fff !important;
}

.datepicker table tr td.today {
    background: #fff3cd !important;
    color: #000 !important;
}

.datepicker .datepicker-switch {
    font-size: 16px !important;
    font-weight: 700 !important;
}

.datepicker .prev,
.datepicker .next {
    font-size: 18px !important;
    font-weight: bold !important;
}

.datepicker table tr th:hover,
.datepicker table tr td.day:hover {
    border-radius: 6px !important;
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
        
        var lineitem_objectdata = [];
                display_lineitem_html(lineitem_objectdata);
        
        $(document).ready( function(){
            $('.select2').select2();
            $('#rupu_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});
            
            initAjaxSelect2($("#party_id"), "api/party_select2_source.php");
                                    initAjaxSelect2($("#cash_bank_id"), "api/cash_bank_select2_source.php");
                        
            // REMOVED: old Enter key handler replaced by includes/erp_keyboard_nav.js
            
            
            
            
            

            $(document).on('input', '#silver_rate, #silver_qty', function(){
                var silver_rate = $('#silver_rate').val() || 0;
                var silver_qty = $('#silver_qty').val() || 0;
                silver_rate_amount_t = silver_rate * silver_qty;
                if(isNaN(silver_rate_amount_t)){ 
                    silver_rate_amount_t = 0;
                }
                silver_rate_amount = round(silver_rate_amount_t, 2).toFixed(2);
                $('#silver_rate_amount').val(silver_rate_amount);
            });
            
            $(document).on('input', '#rupu_gross, #touch', function(){
                var rupu_gross = $('#rupu_gross').val() || 0;
                var touch = $('#touch').val() || 0;
                fine_t = rupu_gross * touch / 100;
                if(isNaN(fine_t)){ 
                    fine_t = 0;
                }
                fine = fine_t.toFixed(3);
//                fine = round(fine_t, 3).toFixed(3);
//                fine = Math.floor(fine);
                $('#fine').val(fine);
            });
            
            $(document).on('click', '.reset_party_filter', function(){
                $('#party_id').val(null).trigger('change');
            });
            
            $(document).on('change', '#party_id', function(){
                get_account_balance($(this).val(), 'party_account_balance');
                $('.selected_party_link').attr('href', "javascript:void(0);");
                if($(this).val() != null){
                    $('.selected_party_link').attr('href', "party-ledger.php?id="+ $(this).val());
                }
            });
           $(document).on('change', '#cash_bank_id', function(){

    get_account_balance(
        $(this).val(),
        'cash_bank_account_balance'
    );

});
            
            $(document).on('input', '#silver_fine, #badlo', function(){
                display_lineitem_html(lineitem_objectdata);
            });
            
            $(document).on('click', '#add_lineitem', function () {
//            return false;
            var rupu_gross = $.trim($("#rupu_gross").val());
            if (rupu_gross == '' || rupu_gross == null || rupu_gross == '0') {
                bootbox.alert('<span class="text-danger">Rupu Gross is required !</span>');
                return false;
            }
            var touch = $.trim($("#touch").val());
            if (touch == '' || touch == null || touch == '0') {
                bootbox.alert('<span class="text-danger">Touch is required !</span>');
                return false;
            }
            var line_items_index = $("#line_items_index").val();
            $("#add_lineitem").attr('disabled', 'disabled');
            var key = '';
            var value = '';
            var lineitem = {};
            $('select[name^="line_items_data"]').each(function (e) {
                key = $(this).attr('name');
                key = key.replace("line_items_data[", "");
                key = key.replace("]", "");
                value = $(this).val();
                lineitem[key] = value;
            });
            $('input[name^="line_items_data"]').each(function () {
                key = $(this).attr('name');
                key = key.replace("line_items_data[", "");
                key = key.replace("]", "");
                value = $(this).val();
                lineitem[key] = value;
            });
            $('textarea[name^="line_items_data"]').each(function () {
                key = $(this).attr('name');
                key = key.replace("line_items_data[", "");
                key = key.replace("]", "");
                value = $(this).val();
                lineitem[key] = value;
            });
            lineitem['rupu_narration'] = $('#rupu_narration').val();
            
//            console.log(lineitem);
            var new_lineitem = JSON.parse(JSON.stringify(lineitem));
            var line_items_index = $("#line_items_index").val();
            if (line_items_index != '') {
                lineitem_objectdata.splice(line_items_index, 1, new_lineitem);
            } else {
                lineitem_objectdata.push(new_lineitem);
            }
            display_lineitem_html(lineitem_objectdata);
            $("#rupu_gross").val('');
            $("#touch").val('');
            $("#fine").val('');
            $("#rupu_narration").val('');
            $("#line_items_index").val('');
            $("#add_lineitem").removeAttr('disabled', 'disabled');
            $(".delete_lineitem").show();
            $('#rupu_gross').focus();
        });

            $(document).on('submit', '#save_rupu', function (e) {
                if ($.trim($("#rupu_date").val()) == '') {
                    bootbox.alert('<span class="text-danger">Select Date !</span>');
                    return false;
                }
                if ($.trim($("#party_id").val()) == '') {
                    bootbox.alert('<span class="text-danger">Select Party required !</span>');
                    return false;
                }
                var silver_balance = get_item_balance();
                var silver_fine = $('#silver_fine').val() || 0;
                silver_fine_new = parseFloat(silver_balance) + parseFloat(silver_fine);
                if(silver_fine_new < 0){
                    bootbox.alert('<span class="text-danger">Not Allow Greater than Silver Current Stock</span>');
                    return false;
                }
                if (($.trim($("#silver_qty").val()) !== '') && (parseFloat($.trim($("#silver_qty").val())) !== parseFloat(0))) {
                    if (($.trim($("#silver_rate").val())) == '' || (parseFloat($.trim($("#silver_rate").val())) == parseFloat(0))) {
                        bootbox.alert('<span class="text-danger">Silver Rate required !</span>');
                        return false;
                    }
                }
                if (($.trim($("#silver_rate").val()) !== '') && (parseFloat($.trim($("#silver_rate").val())) !== parseFloat(0))) {
                    if (($.trim($("#silver_qty").val())) == '' || (parseFloat($.trim($("#silver_qty").val())) == parseFloat(0))) {
                        bootbox.alert('<span class="text-danger">Silver Qty required !</span>');
                        return false;
                    }
                }
                if ($.trim($("#amount").val()) != '') {
                    if ($.trim($("#cash_bank_id").val()) == '') {
                        bootbox.alert('<span class="text-danger">Select Cash/Bank required !</span>');
                        return false;
                    }
                }
                if (lineitem_objectdata == '') {
                    bootbox.alert('<span class="text-danger">Add atleast One  Lineitem !</span>');
                    return false;
                }
                $('.module_save_btn').attr('disabled', 'disabled');
                var postData = new FormData(this);
                var lineitem_objectdata_stringify = JSON.stringify(lineitem_objectdata);
                postData.append('line_items_data', lineitem_objectdata_stringify);
                $.ajax({
                    url: "api/save_rupu.php",
                    type: "POST",
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: postData,
                    dataType: 'json',
                    async: false,
                    success: function (json) {

    $('.module_save_btn')
    .removeAttr('disabled', 'disabled');

    if (json.success == 'Added') {

        bootbox.alert({

            message:
            '<span class="text-success">'
            + json.msg +
            '</span>',

            callback: function(){

                window.location.reload();

            }

        });

    } else {

        bootbox.alert({

            message:
            '<span class="text-danger">'
            + json.msg +
            '</span>'

        });

    }

},
                });
                return false;
            });

        });function display_lineitem_html(lineitem_objectdata) {
        var new_lineitem_html = '';
        total_rupu = 0;
        total_fine = 0;
        $.each(lineitem_objectdata, function (index, value) {
            lineitem_edit_btn = '<a class="btn btn-xs btn-primary btn-edit-item edit_lineitem1_' + index + '" href="javascript:void(0);" onclick="edit_lineitem(' + index + ')"><i class="fa fa-edit"></i></a> ';
            lineitem_delete_btn = '<a class="btn btn-xs btn-danger btn-delete-item remove_lineitem delete_lineitem" data-id="'+ index +'" href="javascript:void(0);" onclick="remove_lineitem(' + index + ')"><span class="fa fa-times-circle"></span></a>';
            var row_html = '<tr class="lineitem_index_' + index + '"><td class="" style="white-space:nowrap">'  +
                    lineitem_edit_btn +
                    lineitem_delete_btn +
                    '</td>' +
                    '<td class="text-right">' + value.rupu_gross + '</td>' +
                    '<td class="text-right">' + value.touch + '</td>' +
                    '<td class="text-right">' + value.fine + '</td>' +
                    '<td>' + value.rupu_narration + '</td>';
            new_lineitem_html += row_html;
            total_rupu = parseFloat(total_rupu) + parseFloat(value.rupu_gross);
            total_fine = parseFloat(total_fine) + parseFloat(value.fine);
        });
        total_rupu = round(total_rupu, 3).toFixed(3);
        total_fine = round(total_fine, 3).toFixed(3);
        var total_silver = $('#silver_fine').val() || 0;
        new_pending = parseFloat(total_silver) + parseFloat(total_fine);
        if(isNaN(new_pending)){ 
            new_pending = 0;
        }
        new_pending = round(new_pending, 3).toFixed(3);
        badlo = $('#badlo').val() || 0;
        after_badlo = parseFloat(total_fine) * parseFloat(badlo) / 1000;
        after_badlo = round(after_badlo, 3).toFixed(3);
        fine_badlo = parseFloat(total_fine) + parseFloat(after_badlo);
        fine_badlo = round(fine_badlo, 3).toFixed(3);
        $('#after_badlo').val(after_badlo);
        $('#fine_badlo').val(fine_badlo);
        
        $('#total_pending_input').val(new_pending);
        $('#total_rupu_gross_input').val(total_rupu);
        $('#total_fine_input').val(total_fine);
        $('#total_pending').html(new_pending);
        $('tbody#lineitem_list').html(new_lineitem_html);
        $('#total_rupu_gross').html(total_rupu);
        $('#total_fine').html(total_fine);
    }
    
    function edit_lineitem(index) {
        $('#edit_line').val('1');
        $(".delete_lineitem").hide();
        var value = lineitem_objectdata[index];
        if(typeof(value.lineitem_id) != "undefined" && value.lineitem_id !== null) {
            $("#lineitem_id").val(value.lineitem_id);
        }
        $("#line_items_index").val(index);
        $("#rupu_gross").val(value.rupu_gross);
        $("#touch").val(value.touch);
        $("#fine").val(value.fine);
        $("#rupu_narration").val(value.rupu_narration);
        $('#edit_line').val('0');
    }
    
    function remove_lineitem(index) {
        if (confirm('Are you sure ?')) {
            value = lineitem_objectdata[index];
            if ((typeof (value.lineitem_id) != "undefined") && (value.lineitem_id !== null) && (value.lineitem_id !== '')) {
                $('#save_rupu').append('<input type="hidden" name="deleted_lineitem_id[]" id="deleted_lineitem_id" value="' + value.lineitem_id + '" />');
            }
            lineitem_objectdata.splice(index, 1);
            display_lineitem_html(lineitem_objectdata);
        }
    }
    
    function set_amount() {
        var rate = $.trim($("#rate").val()) || 0;
        var qty  = $.trim($("#qty").val()) || 0;
        var amount = parseFloat(rate)*parseFloat(qty);
        $('#amount').val(parseFloat(amount));
    }
    
    function round(value, exp) {
        if (typeof exp === 'undefined' || +exp === 0)
          return Math.round(value);

        value = +value;
        exp = +exp;

        if (isNaN(value) || !(typeof exp === 'number' && exp % 1 === 0))
          return NaN;

        // Shift
        value = value.toString().split('e');
        value = Math.round(+(value[0] + 'e' + (value[1] ? (+value[1] + exp) : exp)));

        // Shift back
        value = value.toString().split('e');
        return +(value[0] + 'e' + (value[1] ? (+value[1] - exp) : -exp));
    }
    
    function get_account_balance(account_id, id_attr){
        if(account_id != '' && account_id != null){
            $.ajax({
    url: "api/get_account_balance.php?account_id="+ account_id,
    type: 'GET',
    dataType: 'json',
    success: function (json) {
                    
                    var html_data = '<span>';
                    html_data += '<strong>';
                    if(id_attr == 'party_account_balance'){
                        html_data += 'Amount : ';
                        if(parseFloat(json.account_data.amount) >= 0){
                            html_data += '<span class="text-blue">' + json.account_data.amount + ' Cr</span>, ';
                        } else {
                            html_data += '<span class="text-red">' + json.account_data.amount + ' Dr</span>, ';
                        }
                        html_data += 'Gold : ';
                        if(parseFloat(json.account_data.gold_fine) >= 0){
                            html_data += '<span class="text-blue">' + json.account_data.gold_fine + ' Cr</span>, ';
                        } else {
                            html_data += '<span class="text-red">' + json.account_data.gold_fine + ' Dr</span>, ';
                        }
                        html_data += 'Silver : ';
                        if(parseFloat(json.account_data.silver_fine) >= 0){
                            html_data += '<span class="text-blue">' + json.account_data.silver_fine + ' Cr</span>';
                        } else {
                            html_data += '<span class="text-red">' + json.account_data.silver_fine + ' Dr</span>';
                        }
                    } else if(id_attr == 'cash_bank_account_balance'){
                        html_data += 'Amount : ';
                        if(parseFloat(json.account_data.amount) >= 0){
                            html_data += '<span class="text-blue">' + json.account_data.amount + ' Cr</span>';
                        } else {
                            html_data += '<span class="text-red">' + json.account_data.amount + ' Dr</span>';
                        }
                    }
                    html_data += '</strong>';
                    html_data += '</span>';
                    $('#'+ id_attr ).html(html_data);
                }
            });
        } else {
            $('#'+ id_attr ).html('');
        }
    }
    
    function get_item_balance(){
        var item_id = "2";
        
        main_balance = 0;
        $.ajax({
            url: "api/get_item_balance.php?item_id="+ item_id,
            type: 'GET',
            data: '',
            dataType: 'json',
            async: false,
            success: function (json) {
                main_balance = json.balance;
            }
        });
        return main_balance;
    }
    
    </script>

    <script>
    $(document).on('keydown', function(e) {
        
        
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
                data: { 'status' : status , 'message' : message, "_token": "DtwpNa4VCI4rxbpfyN386tosKnuIgmtCXJgfj5Qw" }
            }).done(function () {
                window.location.href = redirect_url;
            });
        }
        function redirectWithFlashMessageAndDataId ( redirect_url, status, message, data_id ) {
            $.ajax({
                type: 'POST',
                url: "#",
                data: { 'status' : status , 'message' : message, "_token": "DtwpNa4VCI4rxbpfyN386tosKnuIgmtCXJgfj5Qw", 'data_id' : data_id }
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
