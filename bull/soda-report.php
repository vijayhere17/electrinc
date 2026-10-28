<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>Soda Report</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="custom_alert_by_js d-none"></div>
<div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="filter_party_id" class="control-label">Party</label> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="filter_party_id" id="filter_party_id">
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="filter_from_date" class="control-label">From Date</label>
                    <input type="text" name="filter_from_date" id="filter_from_date" class="form-control" placeholder="" data-index="1" value="<?php echo date('d-m-Y'); ?>"> 
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="filter_to_date" class="control-label">To Date</label>
                    <input type="text" name="filter_to_date" id="filter_to_date" class="form-control" placeholder="" data-index="1" value="<?php echo date('d-m-Y'); ?>"> 
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h4 class="text-center bg-warning">Gold</h4>
                <div class="table-responsive" style="width:100%;">
                    <table id="dataTable-entryTableGold" class="table table-bordered" style="width:100%;">
                        <thead>
                            <tr>
                                <th colspan="3" class="text-right">Total Qty : <span id="total_gold_qty"></span></th>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                            <tr>
                                
                                <th>Party</th>
                                <th>Date</th>
                                <th>Qty</th>
                                <th>Bill / Cash</th>
                                <th>Rate</th>
                                <th>MCX</th>
                                <th style="max-width:120px;">Narration</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h4 class="text-center bg-gray">Silver</h4>
                <div class="table-responsive" style="width:100%;">
                    <table id="dataTable-entryTableSilver" class="table table-bordered" style="width:100%;">
                        <thead>
                            <tr>
    <th colspan="3" class="text-right">
        Total Qty : <span id="total_silver_qty"></span>
    </th>

    <th></th>
    <th></th>
    <th></th>
    <th></th>
</tr>
                            <tr>
                                
                                <th>Party</th>
                                <th>Date</th>
                                <th>Qty</th>
                                <th>Bill / Cash</th>
                                <th>Rate</th>
                                <th>MCX</th>
                                <th style="max-width:120px;">Narration</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal fade" id="deleteentryModel" role="basic" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Confirmation</h4>
                    </div>
                        <div class="modal-body">
                            <p>Are you sure you want remove this Soda Entry ?</p>
                            <input type="hidden" name="delete_entry_id" id="delete_entry_id">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success delete_entry_btn">Yes</button>
                            <button type="button" class="btn btn-default btn-outline" data-dismiss="modal">No</button>
                        </div>
                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
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

.table-responsive{
    overflow-x:hidden !important;
}

table.dataTable{
    width:100% !important;
}

.dataTables_wrapper{
    width:100%;
}

#dataTable-entryTableGold td,
#dataTable-entryTableSilver td{
    vertical-align:middle;
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
//        initAjaxSelect2($("#city_id"), "index.php/city_select2_source");
    });
        var entryTableGold;
        var entryTableSilver;
        $(document).ready( function(){
            $('.select2').select2();
            
            $('#entry_date').datepicker({
                format: 'dd-mm-yyyy',
                todayBtn: "linked",
                autoclose: true
            });
            
           $('#filter_from_date, #filter_to_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});
            // REMOVED: old Enter key handler replaced by includes/erp_keyboard_nav.js
            
            $(document).bind("keydown", function(e){
            if(e.ctrlKey && e.which == 83){
                e.preventDefault();
                $("#save_entry").submit();
                return false;
            }
        });
            
            initAjaxSelect2($("#party_id"), "api/party_select2_source.php");
            initAjaxSelect2($("#filter_party_id"), "api/party_select2_source.php");
                            
            $(document).on('change', '#party_id', function(){
                get_account_balance($(this).val(), 'party_account_balance');
                $('.selected_party_link').attr('href', "javascript:void(0);");
                if($(this).val() != null){
                    $('.selected_party_link').attr('href', "party-ledger.php?id="+ $(this).val());
                }
            });
            
            var rateIsDeleting = false;
            $(document).on('keydown', '#rate', function (e) {
                rateIsDeleting = (e.key === 'Backspace' || e.key === 'Delete' || e.keyCode === 8 || e.keyCode === 46);
            });

            $(document).on('input', '#rate', function () {
                if ($('#item_id').val() == '1') {
                    var $rate = $(this);
                    var raw = ($rate.val() || '').toString();

                    // keep only digits (treat decimal as formatting)
                    var digits = raw.replace(/\D/g, '');

                    if (digits === '') {
                        $rate.val('');
                        rateIsDeleting = false;
                        return;
                    }

                    if (rateIsDeleting) {
                        if (digits.length <= 5) {
                            $rate.val(digits);
                        } else {
                            $rate.val(digits.slice(0, 5) + '.' + digits.slice(5));
                        }
                    } else {
                        if (digits.length <= 5) {
                            $rate.val(digits);
                        } else {
                            $rate.val(digits.slice(0, 5) + '.' + digits.slice(5));
                        }
                    }
                    rateIsDeleting = false;
                }
            });
            $(document).on('input', '#mcx', function(){
                if($('#item_id').val() == 1){
                    if ($.trim($('#mcx').val()) != '') {
                        var value = $('#mcx').val();
                        if (value.length == 5){
                            var mcx_val = $(this).val();
                            var substr = mcx_val.substr(0, 5);
                            var substr_last = mcx_val.substr(5);
                            var lastChar = mcx_val.substr(mcx_val.length - 1);
                            if(lastChar == '.'){ } else {
                                $(this).val(substr+'.'+substr_last);
                            }
                        }
                    }
                }
            });
            
            
            $(document).on('change', '#item_id', function(){
                $('#rate').val('');
                if($('#item_id').val() == 1){
                    $('#range_1').val($('#gold_min').val());
                    $('#range_2').val($('#gold_max').val());
                }
                if($('#item_id').val() == 2){
                    $('#range_1').val($('#silver_min').val());
                    $('#range_2').val($('#silver_max').val());
                }
            });
            
            $(document).on('change', '#range_1', function(){
                if($('#item_id').val() == 1){
                    $('#gold_min').val($('#range_1').val());
                }
                if($('#item_id').val() == 2){
                    $('#silver_min').val($('#range_1').val());
                }
            });
            
            $(document).on('change', '#range_2', function(){
                if($('#item_id').val() == 1){
                    $('#gold_max').val($('#range_2').val());
                }
                if($('#item_id').val() == 2){
                    $('#silver_max').val($('#range_2').val());
                }
            });
            
            // $(document).on('change', '#type_id', function(){
            //     if($(this).val() == 1){
            //         $('#tds').val('');
            //     }
            // });
            $(document).on('change', '#type_id', function(){
                if($(this).val() == 1){
                    $('#tds').val('');
                    $('#tds_container').hide();
                } else if($(this).val() == 2) {
                    $('#tds_container').show();
                    if ($('#party_id').val() != '') {
                       $('#party_id').trigger('change');
                    }
                }
            });
            // Clear TDS on page load since Cash is default
            $('#type_id').trigger('change');
            
            $('#item_id').change();
            
            $(document).on('submit', '#save_entry', function(){
                if ($.trim($("#party_id").val()) == '') {
                    bootbox.alert('<span class="text-danger">Party required !</span>');
                    return false;
                }
                var rate = parseFloat($.trim($("#rate").val()));
                var range_1 = parseFloat($.trim($("#range_1").val()));
                var range_2 = parseFloat($.trim($("#range_2").val()));
                if ((rate >= range_1) && (rate <= range_2)) {
                    
                } else {
                    bootbox.alert('<span class="text-danger">Rate must be between Range !</span>');
                    return false;
                }
                var postData = new FormData(this);
                $('.module_save_btn').attr('disabled', 'disabled');
                $.ajax({
                    url: "api/save_soda_entry.php",
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
                        $('#error_msg').html('');
                        if (json['errors']) {
                            var error_msg_html = '<div class="alert alert-danger"><ul>';
                            $(json['errors']).each(function( index,value ) {
                                error_msg_html += '<li>'+value+'</li>';
                            });
                            error_msg_html += '</ul></div>';
                            $('#error_msg').html(error_msg_html);
                        } else if (json['success'] == 'Added') {
                            $("#party_id").val('').trigger("change");
                            $('#entry_id').val('');
                            $('#rate').val('');
                            $('#qty').val('');
                            $('#mcx').val('');
                            $("#type_id").val('1').trigger("change");
                            $('#tds').val('');
                            $('#narration').val('');
                            $('[data-index="4"]').select2('open');
                            entryTableGold.draw();
                            entryTableSilver.draw();
                            $('.custom_alert_by_js').removeClass('d-none');
                            $('.custom_alert_by_js').html('<div class="alert alert-success" style="background: #ffffff; color: #000000;"><button data-dismiss="alert" class="close">&times;</button>'+json['msg']+'</div>');
                            setTimeout(function(){
                                $('.alert_flash_message').hide('slow');
                                $('.custom_alert_by_js').addClass('d-none');
                            }, 10000);
//                            bootbox.alert('<span class="text-success">Soda Successfully Created</span>');
                        } else if (json['success'] == 'Updated') {
                            $('.delete_entry').show();
                            $("#party_id").val('').trigger("change");
                            $('#entry_id').val('');
                            $('#rate').val('');
                            $('#qty').val('');
                            $('#mcx').val('');
//                            $('#type_id option[value=1]').attr('selected','selected');
                            $("#type_id").val('1').trigger("change");
                            $('#tds').val('');
                            $('#narration').val('');
                            $('[data-index="4"]').select2('open');
                            entryTableGold.draw();
                            entryTableSilver.draw();
                            $('.custom_alert_by_js').removeClass('d-none');
                            $('.custom_alert_by_js').html('<div class="alert alert-success" style="background: #ffffff; color: #000000;"><button data-dismiss="alert" class="close">&times;</button>'+json['msg']+'</div>');
                            setTimeout(function(){
                                $('.alert_flash_message').hide('slow');
                                $('.custom_alert_by_js').addClass('d-none');
                            }, 10000);
//                            bootbox.alert('<span class="text-success">Soda Successfully Updated</span>');
                            $('.module_save_btn').val('Save');
                        } else {
                            bootbox.alert('<span class="text-danger">Something error occurred</span>');
                            return false;
                        }
                        return false;
                    },
                });
                return false;
            });

            $(document).on('click', '.edit_area', function(){
                var area_name_id = $(this).attr('data-area_id');
                $.ajax({
                    url: "area/" + area_name_id + "/edit",
                    type: "GET",
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: {},
                    datatype: 'json',
                    async: false,
                    success: function (response) {
                        $('.module_save_btn').removeAttr('disabled', 'disabled');
                        var json = $.parseJSON(response);
                        $('#area_id').val(json['area_data'].id);
                        $('#area_name').val(json['area_data'].area_name);
                        $('city_id').val(json['area_data'].city_id);
                        $('.module_save_btn').val('Update');
                        $("html, body").animate({scrollTop: 0}, "slow");
                        return false;
                    },
                });
                return false;
            });

            $(document).on('click', '.edit_entry', function(){
                var entry_id = $(this).attr('data-entry_id');
                $.ajax({
                    url: "api/get_soda_entry_details.php?id="+entry_id,
                    type: "GET",
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: {},
                    async: false,
                    success: function (response) {
                        var json = $.parseJSON(response);
                        console.log(json);
                        if(json != ''){
                            $('.delete_entry').hide();
                            $('#entry_id').val('');
                            $('#entry_id').val(json['id']);
                            $('#entry_date').val('');
                            $('#entry_date').val(json['entry_date']);
                            setSelect2Value($("#party_id"),"api/set_party_select2_val_by_id.php?id="+json['party_id']);
                            $('#item_id option[value='+json["item_id"]+']').attr('selected','selected');
                            $('#item_id').change(); // Trigger item_id change and clear rate early
                            $('#rate').val(json['rate']);
                            $('#qty').val('');
                            $('#qty').val(json['qty']);
                            $('#mcx').val('');
                            $('#mcx').val(json['mcx']);
                            $('#tds').val(json['tds']);
                            $("#type_id").val(json["type_id"]).trigger("change");
//                            $('#type_id option[value='+json["type_id"]+']').attr('selected','selected'); 
                            if(json['type_id'] == 2) {
                                $('#tds_container').show();
                            } else {
                                $('#tds_container').hide();
                            }
                            $('#narration').val(json['narration']);
                        }
                        return false;
                    },
                });
            });
            
            $(document).on('click', '.delete_entry', function(){
                var delete_entry_id = $(this).attr('data-entry_id');
                $('#delete_entry_id').val(delete_entry_id);
                $('#deleteentryModel').modal('show');
                return false;
            });

            entryTableGold = $('#dataTable-entryTableGold').DataTable({
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'pdf',
                    download: 'open',
                    text: 'Soda Entry Gold List',
//                    orientation: 'landscape',
    //                footer: true,
                    title: function () { return ('Soda_Entry_Gold_List_2026_05_27_04_56_24') },
                    action: newExportAction,
                    customize: function(doc) {
                        var objLayout = {};
                        objLayout['hLineWidth'] = function(i) { return .5; };
                        objLayout['vLineWidth'] = function(i) { return .5; };
                        doc.content[1].layout = objLayout;
                        doc.defaultStyle.fontSize = 10;
                    },
                    exportOptions: {
                        columns: [ 0, 1, 2, 3, 4, 5, 6],
                    },
                }],
                serverSide: true,
                ordering: false,
                searching: false,
                "ajax": {
                    "url": "api/get_soda_report.php",
                    "type": "POST",
                    "data": function (d) {
                        d._token = 'hshYqTPNvCD5G0JefRbDG2ngrg9y7ttr0LWUzIOI';
                        d.filter_party_id = $('#filter_party_id').val();
                        d.filter_from_date = $('#filter_from_date').val();
                        d.filter_to_date = $('#filter_to_date').val();
                        d.item_id = '1';
                    },
                    "dataSrc": function ( json ) {
                        $('#total_gold_qty').html('');
                        $('#total_gold_qty').html(json.total_qty);
                        return json.data;
                    }
                },
                "columns": [

                {
                    "data": "account_name",
                    "sClass": 'text-nowrap',
                    "defaultContent": '-',
                    "searchable": false,
                    "render": function( data, type, full, meta ) {
                           return '<a href="ledger.php?id='+full.party_id+'" target="_blank">'+data+'</a>';
                    }
                },
                {
                    "data": "entry_date",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-nowrap",
                    "render": function(data, type, full, meta) {
                        if (!data) return '-';
                        // Expect server gives YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
                        var parts = data.toString().split(' ')[0].split('-');
                        if (parts.length !== 3) return data;
                        return parts[2] + '-' + parts[1] + '-' + parts[0]; // dd-mm-yyyy
                    }
                },
                {
                    "data": "qty",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-nowrap text-right",
                },
                {
                    "data": "cash_bill",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-nowrap",
                },
                {
                    "data": "rate",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-nowrap text-right",
                },
                {
                    "data": "mcx",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-right",
                },
                {
                    "data": "narration",
                    "defaultContent": '-',
                    "searchable": false,
                },
                ],
                scrollY: "600px",
                scroller: {
                    loadingIndicator: true
                },
                stateSave: false,
                "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                    if ( parseFloat(aData.qty) < '0' ){
                        $('td', nRow).css('background-color', '#F2DEDE');
                    } else {
                        $('td', nRow).css('background-color', '#D8EAF0');
                    }
                },
            });

            entryTableSilver = $('#dataTable-entryTableSilver').DataTable({
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'pdf',
                    download: 'open',
                    text: 'Soda Entry Silver List',
//                    orientation: 'landscape',
    //                footer: true,
                    title: function () { return ('Soda_Entry_Gold_List_2026_05_27_04_56_24') },
                    action: newExportAction,
                    customize: function(doc) {
                        var objLayout = {};
                        objLayout['hLineWidth'] = function(i) { return .5; };
                        objLayout['vLineWidth'] = function(i) { return .5; };
                        doc.content[1].layout = objLayout;
                        doc.defaultStyle.fontSize = 10;
                    },
                    exportOptions: {
                        columns: [ 0, 1, 2, 3, 4, 5, 6],
                    },
                }],
                serverSide: true,
                ordering: false,
                searching: false,
                "ajax": {
                    "url": "api/get_soda_report.php",
                    "type": "POST",
                    "data": function (d) {
                        d._token = 'hshYqTPNvCD5G0JefRbDG2ngrg9y7ttr0LWUzIOI';
                        d.filter_party_id = $('#filter_party_id').val();
                        d.filter_from_date = $('#filter_from_date').val();
                        d.filter_to_date = $('#filter_to_date').val();
                        d.item_id = '2';
                    },
                    "dataSrc": function ( json ) {
                        $('#total_silver_qty').html('');
                        $('#total_silver_qty').html(json.total_qty);
                        return json.data;
                    }
                },
                "columns": [
                {
                    "data": "account_name",
                    "sClass": 'text-nowrap',
                    "defaultContent": '-',
                    "searchable": false,
                    "render": function( data, type, full, meta ) {
                            return '<a href="ledger.php?id='+full.party_id+'" target="_blank">'+data+'</a>';
                    }
                },
                {
                    "data": "entry_date",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-nowrap",
                    "render": function(data, type, full, meta) {
                        if (!data) return '-';
                        // Expect server gives YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
                        var parts = data.toString().split(' ')[0].split('-');
                        if (parts.length !== 3) return data;
                        return parts[2] + '-' + parts[1] + '-' + parts[0]; // dd-mm-yyyy
                    }
                },
                {
                    "data": "qty",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-nowrap text-right",
                },
                {
                    "data": "cash_bill",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-nowrap",
                },
                {
                    "data": "rate",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-nowrap text-right",
                },
                {
                    "data": "mcx",
                    "defaultContent": '-',
                    "searchable": false,
                    "className": "text-right",
                },
                {
                    "data": "narration",
                    "defaultContent": '-',
                    "searchable": false,
                },
                ],
                scrollY: "600px",
                scroller: {
                    loadingIndicator: true
                },
                stateSave: true,
                "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
                    if ( parseFloat(aData.qty) < '0' ){
                        $('td', nRow).css('background-color', '#F2DEDE');
                    } else {
                        $('td', nRow).css('background-color', '#D8EAF0');
                    }
                },
            });
            data_table_export_icon_style();

            $(document).on('change', '#filter_party_id, #filter_from_date, #filter_to_date', function(){
                entryTableGold.draw();
                entryTableSilver.draw();
            });
                           
            
            $(document).on('click', '.reset_party_filter', function(){
                $('#filter_party_id').val(null).trigger('change');;
            });

            $(document).on('click', '.delete_entry_btn', function(){
                var delete_entry_id = $('#delete_entry_id').val();
                $('#deleteentryModel').modal('hide');
                $.ajax({
                    url: "api/delete_soda_entry.php?id="+delete_entry_id,
                    type: "GET",
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: {},
                    async: false,
                    success: function (response) {
                        var json = $.parseJSON(response);
                        console.log(json);
                        var json = $.parseJSON(response);
                        if (json['success'] == 'Deleted') {
                            bootbox.alert('<span class="text-success">Soda Entry Deleted</span>');
                        } else {
                            bootbox.alert('<span class="text-danger">Soda Entry not allow to delete. Because it is Used.</span>');
                        }
                        entryTableGold.draw();
                        entryTableSilver.draw();
                        return false;
                    },
                });
            });
            $('[data-index="4"]').select2('open');
        });function get_account_balance(account_id, id_attr){
        if(account_id != '' && account_id != null){
            $.ajax({
                url: "api/get_account_balance.php?account_id="+ account_id,
                type: 'GET',
                data: '',
                success: function (response) {
                    var json = $.parseJSON(response);
                    
                    // Populate TDS field based on selected party account TDS data
                    if(id_attr == 'party_account_balance'){
                         if($('#tds').length){
                             $('#tds').val(json.account_data.tds);
                             
                         }
                    }

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
            
            // Clean up TDS input if no party is selected
            if(id_attr == 'party_account_balance'){
                 if($('#tds').length){
                     $('#tds').val('');
                     $('#tds').removeAttr('readonly');
                 }
            }
        }
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
                data: { 'status' : status , 'message' : message, "_token": "hshYqTPNvCD5G0JefRbDG2ngrg9y7ttr0LWUzIOI" }
            }).done(function () {
                window.location.href = redirect_url;
            });
        }
        function redirectWithFlashMessageAndDataId ( redirect_url, status, message, data_id ) {
            $.ajax({
                type: 'POST',
                url: "#",
                data: { 'status' : status , 'message' : message, "_token": "hshYqTPNvCD5G0JefRbDG2ngrg9y7ttr0LWUzIOI", 'data_id' : data_id }
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
