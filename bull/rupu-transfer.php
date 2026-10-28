<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    Add New Rupu Transfer
    <a href="rupu-list.phptransfer" class="btn btn-primary float-right">Rupu Transfer List</a> 
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="custom_alert_by_js d-none"></div>
<div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <form method="POST" action="rupu-list.phptransfer/create" accept-charset="UTF-8" id="save_rupu_transfer" enctype="multipart/form-data" data-parsley-validate=""><input name="_token" type="hidden" value="DtwpNa4VCI4rxbpfyN386tosKnuIgmtCXJgfj5Qw">
        <input type="hidden" name="_token" value="DtwpNa4VCI4rxbpfyN386tosKnuIgmtCXJgfj5Qw">
        <input id="rupu_id" name="id" type="hidden" value="">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="rupu_transfer_date" class="control-label">Date</label><span class="text-danger">*</span>
                    <input type="text" name="rupu_transfer_date" id="rupu_transfer_date" class="form-control" placeholder="" data-index="1" value="<?= date('d-m-Y') ?>"> 
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="party_id" class="control-label">Party</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="party_id" id="party_id" data-index="2" >
                    </select>
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
            <div class="col-md-3">
                <div class="form-group">
                    <label for="narration" class="control-label">Narration</label>
                    <textarea class="form-control " rows="2" data-index="3" name="narration" cols="50" id="narration"></textarea>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label><a href="javascript:void(0);" class="selected_party_link">Selected Party Balance</a></label> :
                    <div id="party_account_balance" style="font-size: 18px;"></div>
                </div>
            </div>
        </div>
        <div class="row" style="padding: 10px;">
            <div class="col-md-12">
                <div class="table-responsive">
                    <table id="dataTable-optable" class="table table-bordered" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="white-space: nowrap">&nbsp;</th>
                                <th style="white-space: nowrap">Rupu Date</th>
                                <th style="white-space: nowrap">Rupu Gross</th>
                                <th style="white-space: nowrap">Actual Touch</th>
                                <th style="white-space: nowrap">Changed Touch</th>
                                <th style="white-space: nowrap">Actual Fine</th>
                                <th style="white-space: nowrap">Changed Fine</th>
                                <th style="white-space: nowrap">Party</th>
                                <th style="white-space: nowrap">Narration</th>
                            </tr>
                            <tr>
                                <th style="white-space: nowrap">Total</th>
                                <th style="white-space: nowrap"></th>
                                    <input type="hidden" name="total_rupu_input" id="total_rupu_input" value="" >
                                <th style="white-space: nowrap" class="text-right" id="total_rupu"></th>
                                <th style="white-space: nowrap"></th>
                                <th style="white-space: nowrap"></th>
                                <th style="white-space: nowrap" class="text-right" id="total_actual_fine"></th>
                                    <input type="hidden" name="total_changed_fine_input" id="total_changed_fine_input" value="" >
                                <th style="white-space: nowrap" class="text-right" id="total_changed_fine"></th>
                                <th style="white-space: nowrap"></th>
                                <th style="white-space: nowrap"></th>
                            </tr>
                                                                                           
                                                    </thead>
                        <tbody id="lineitem_list">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-1">
                <label>&nbsp;</label><br>
                <input class="btn btn-primary module_save_btn" data-index="10" type="submit" value="Save">
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
.datepicker {
    border-radius: 12px !important;
    border: 1px solid #e5e7eb !important;
    box-shadow: 0 10px 25px rgba(0,0,0,.12) !important;
    padding: 10px !important;
}

.datepicker table tr td,
.datepicker table tr th {
    width: 40px !important;
    height: 40px !important;
    border-radius: 8px !important;
}

.datepicker table tr td.active,
.datepicker table tr td.active:hover {
    background: #0d6efd !important;
    color: #fff !important;
}

.datepicker table tr td.today {
    background: #fff3cd !important;
    color: #000 !important;
}

.datepicker .datepicker-switch {
    font-size: 18px !important;
    font-weight: 700 !important;
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
        
        
        
        $(document).ready( function(){
            $('.select2').select2();
            $('#rupu_transfer_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});
            
            initAjaxSelect2($("#party_id"), "api/party_select2_source.php");
                        
            // REMOVED: old Enter key handler replaced by includes/erp_keyboard_nav.js
            
            

            

           setTimeout(function(){

    $('#party_id').trigger('change');

},1000);

            
            $(document).on('click', '.reset_party_filter', function(){
                $('#party_id').val(null).trigger('change');
            });
            
            $(document).on('change', '#party_id', function(){
                var pid = $(this).val();
                get_account_balance(pid, 'party_account_balance');
                $('.selected_party_link').attr('href', "javascript:void(0);");
                if(pid != null && pid != ''){
                    $('.selected_party_link').attr('href', "party-ledger.php?id="+ pid);
                }
                // reload rupu items filtered by selected party
                load_rupu_transfer_items(pid);
            });
            

            $(document).on('input', '.changed_touch', function(){
                var item_id = $(this).data('id');
                var rupu_gross = $('#rupu_gross_'+item_id).val() || 0;
                var touch = $('#changed_touch_'+item_id).val() || 0;
                fine_t = rupu_gross * touch / 100;
                if(isNaN(fine_t)){ 
                    fine_t = 0;
                }
                fine = fine_t.toFixed(3);
                $('#changed_fine_'+item_id).val(fine);
                set_total();
                set_badlo();
            });
            
            $(document).on('input', '#badlo', function(){
                set_badlo();
            });
            
            $(document).on('input', '.check_box', function(){
                set_total();
                set_badlo();
            });
            
            $(document).on('click', '.go_to_ledger', function(){
                var party_id = $(this).attr('data-party_id');
                window.location.href = "party-ledger.php?id=" + party_id;
                return false;
            });

            $(document).on('submit', '#save_rupu_transfer', function () {
//                return false;
                if ($.trim($("#rupu_transfer_date").val()) == '') {
                    bootbox.alert('<span class="text-danger">Select Date !</span>');
                    return false;
                }
                if ($.trim($("#party_id").val()) == '') {
                    bootbox.alert('<span class="text-danger">Select Party required !</span>');
                    return false;
                }
                is_one_cheked = '';
                $('.main_tr').each (function() {
                    $(this).find(".check_box").each(function(){
                        if ($(this).prop('checked')==true){ 
                            is_one_cheked = '1';
                        }
                    });
                });
                if (is_one_cheked == '') {
                    bootbox.alert('<span class="text-danger">Tick atleast One Rupu !</span>');
                    return false;
                }
                $('.module_save_btn').attr('disabled', 'disabled');
                var postData = new FormData(this);
                $.ajax({
                    url: "api/save_rupu_transfer.php",
                    type: "POST",
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: postData,
                    datatype: 'json',
                    async: false,
                    success: function (response) {
                        $('.module_save_btn').removeAttr('disabled', 'disabled');
                        var json = $.parseJSON(response);
                        if (json['success'] == 'Added') {
                            redirectWithFlashMessage("rupu-list.phptransfer/create", 'success_with_balance', json['msg']);
                        } else if (json['success'] == 'Updated') {
                            redirectWithFlashMessage("rupu-list.phptransfer", 'success_with_balance', json['msg']);
                        } else {
                            bootbox.alert('<span class="text-danger">Something error occurred</span>');
                            return false;
                        }
                        return false;
                    },
                });
                return false;
            });
           load_rupu_transfer_items($('#party_id').val());
        });function set_total(){
        total_rupu = 0;
        total_actual_fine = 0;
        total_changed_fine = 0;
        $('.main_tr').each (function() {
            $(this).find(".check_box").each(function(){
                if ($(this).prop('checked')==true){ 
                    var item_id = $(this).data('id');
                    var rupu_gross = $('#rupu_gross_'+item_id).val() || 0;
                    var actual_fine = $('#actual_fine_'+item_id).val() || 0;
                    var changed_fine = $('#changed_fine_'+item_id).val() || 0;
                    total_rupu = parseFloat(total_rupu) + parseFloat(rupu_gross);
                    total_actual_fine = parseFloat(total_actual_fine) + parseFloat(actual_fine);
                    total_changed_fine = parseFloat(total_changed_fine) + parseFloat(changed_fine);
                }
            });
        });
        total_rupu = round(total_rupu, 3).toFixed(3);
        $('#total_rupu').html(total_rupu);
        $('#total_rupu_input').val(total_rupu);
        total_actual_fine = round(total_actual_fine, 3).toFixed(3);
        $('#total_actual_fine').html(total_actual_fine);
        total_changed_fine = round(total_changed_fine, 3).toFixed(3);
        $('#total_changed_fine').html(total_changed_fine);
        $('#total_changed_fine_input').val(total_changed_fine);
    }
    
    function get_account_balance(account_id, id_attr){
        if(account_id != '' && account_id != null){
            $.ajax({
                url: "api/get_account_balance.php?account_id="+ account_id,
                type: 'GET',
                data: '',
                success: function (response) {
                    var json = response;
                    try { if (typeof response === 'string') json = JSON.parse(response); } catch(e) { json = response; }
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
    
    function set_badlo(){
        total_changed_fine_input = $('#total_changed_fine_input').val() || 0;
        badlo = $('#badlo').val() || 0;
        after_badlo = parseFloat(total_changed_fine_input) * parseFloat(badlo) / 1000;
        after_badlo = round(after_badlo, 3).toFixed(3);
        $('#after_badlo').val(after_badlo);
        
        fine_badlo = parseFloat(total_changed_fine_input) + parseFloat(after_badlo);
        fine_badlo = round(fine_badlo, 3).toFixed(3);
        $('#fine_badlo').val(fine_badlo);
    }
    
    function load_rupu_transfer_items(partyId){
        var transferId = (new URLSearchParams(window.location.search)).get('id');
        var apiUrl = "api/get_rupu_transfer_items.php";
        if (typeof partyId !== 'undefined' && partyId != null && partyId != '') {
            apiUrl += "?party_id=" + partyId;
        }

        $.ajax({
            url: apiUrl,
            type: "GET",
            success: function(response){
                var data = response;
                try { if (typeof response === 'string') data = JSON.parse(response); } catch(e) { data = response; }
                var items = data.data || [];
                var html = '';
                $.each(items, function(i, item){
                    var item_id = item.id;
                    var rupu_id = (typeof item.rupu_id !== 'undefined') ? item.rupu_id : '';
                    var rupu_date = item.rupu_date || '';
                    var gross = parseFloat(item.rupu_gross || 0).toFixed(3);
                    var actual_touch = parseFloat(item.actual_touch || 0).toFixed(3);
                    var changed_touch = (typeof item.changed_touch !== 'undefined' && item.changed_touch !== null && item.changed_touch !== '') ? item.changed_touch : actual_touch;
                    var actual_fine = parseFloat(item.actual_fine || 0).toFixed(3);
                    var changed_fine = (typeof item.changed_fine !== 'undefined' && item.changed_fine !== null && item.changed_fine !== '') ? parseFloat(item.changed_fine).toFixed(3) : actual_fine;
                    var party = item.party_name || '';
                    var narration = item.narration || '';

                    html += '<tr class="main_tr">';
                    html += '<td><input type="checkbox" name="rupu_items['+item_id+'][cheked]" id="checkbox_'+item_id+'" data-id="'+item_id+'" class="form-control check_box" ></td>';
                    html += '<td style="white-space: nowrap">'+ rupu_date +'</td>';
                    html += '<td><input type="text" name="rupu_items['+item_id+'][rupu_gross]" id="rupu_gross_'+item_id+'" data-id="'+item_id+'" class="rupu_gross form-control num_only" readonly="" value="'+gross+'"></td>';
                    html += '<td><input type="text" name="rupu_items['+item_id+'][actual_touch]" id="actual_touch_'+item_id+'" data-id="'+item_id+'" class=" actual_touch form-control num_only" readonly="" value="'+actual_touch+'"></td>';
                    html += '<td><input type="text" name="rupu_items['+item_id+'][changed_touch]" id="changed_touch_'+item_id+'" data-id="'+item_id+'" class="changed_touch form-control positive_num_only" value="'+changed_touch+'"></td>';
                    html += '<td><input type="text" name="rupu_items['+item_id+'][actual_fine]" id="actual_fine_'+item_id+'" data-id="'+item_id+'" class="actual_fine form-control num_only" readonly="" value="'+actual_fine+'"></td>';
                    html += '<td><input type="text" name="rupu_items['+item_id+'][changed_fine]" id="changed_fine_'+item_id+'" data-id="'+item_id+'" class="changed_fine form-control num_only" readonly="" value="'+changed_fine+'"></td>';
                    html += '<input type="hidden" name="rupu_items['+item_id+'][rupu_id]" id="rupu_id_'+item_id+'" data-id="'+item_id+'" class="rupu_id form-control num_only" readonly="" value="'+rupu_id+'">';
                    html += '<td><a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+(item.party_id||'')+'">'+party+'</a></td>';
                    html += '<td>'+narration+'</td>';
                    html += '</tr>';
                });

                $('#lineitem_list').html(html);

                if (transferId) {
                    $.ajax({
                        url: "api/get_rupu_transfer_edit.php?id=" + transferId,
                        type: "GET",
                        success: function(resp){
                            var json = resp;
                            try { if (typeof resp === 'string') json = JSON.parse(resp); } catch(e) { json = resp; }

                            if (json.success) {
                                var main = json.data;
                                if (main.entry_date) {
                                    var ed = main.entry_date;
                                    if (ed.match(/^\d{4}-\d{2}-\d{2}$/)) {
                                        var parts = ed.split('-');
                                        ed = parts[2] + '-' + parts[1] + '-' + parts[0];
                                    }
                                    $('#rupu_transfer_date').val(ed);
                                }
                                if (typeof main.party_id !== 'undefined' && main.party_id !== null && main.party_id !== '') {
                                    setSelect2Value($('#party_id'), "api/get_account_by_id.php?id=" + main.party_id);
                                    // fetch and show party balance
                                    get_account_balance(main.party_id, 'party_account_balance');
                                }
                                $('#narration').val(main.narration || '');
                                $('#badlo').val(main.badlo || '');
                                $('#after_badlo').val(main.after_badlo || '');
                                $('#fine_badlo').val(main.fine_badlo || '');

                                var saved = json.items || json.selected_items || [];
                                $.each(saved, function(i, si){
                                    var item_id = si.rupu_item_id;
                                    if (item_id === null || typeof item_id === 'undefined') return;
                                    $('#checkbox_' + item_id).prop('checked', true);
                                    if (typeof si.changed_touch !== 'undefined') {
                                        $('#changed_touch_' + item_id).val(si.changed_touch);
                                    }
                                    if (typeof si.changed_fine !== 'undefined') {
                                        $('#changed_fine_' + item_id).val(parseFloat(si.changed_fine).toFixed(3));
                                    }
                                });

                                set_total();
                                set_badlo();

                                if (main.party_id && $('#party_id').val() == '') {
                                    setSelect2Value($('#party_id'), "api/get_account_by_id.php?id=" + main.party_id);
                                }
                            } else {
                                set_total();
                                set_badlo();
                            }
                        }
                    });
                } else {
                    set_total();
                    set_badlo();
                }
            },
            error: function(){
                set_total();
                set_badlo();
            }
        });
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
