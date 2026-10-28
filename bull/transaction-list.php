<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>

        
        <div class="content-wrapper">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    Transactions
    <a href="transaction.php" class="btn btn-primary float-right">Add New</a>
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="transaction_date" class="control-label">Date</label>
                    <input type="text" name="transaction_date" id="transaction_date" class="form-control" placeholder="" data-index="1" > 
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="party_id" class="control-label">Account</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="party_id" id="party_id">
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="cash_bank_id" class="control-label">Against Account</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_cash_bank_filter">Reset</span>
                    <select class="form-control select2" name="cash_bank_id" id="cash_bank_id">
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="custom_alert_by_js d-none"></div>
        <div class="row">
        <div class="col-md-12">
            <div class="col-md-12">
               
                    <table id="dataTable-transactionTable"
class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th class="text-nowrap" width="100px">Action</th>
                                <th>Date</th>
                                <th>Account</th>
                                <th>Against Account</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
           
        </div>
        </div>
    </div>
</div>
<input type="hidden" id="clicked_id" value="-1" >
<div class="modal fade" id="detailModel" role="basic" aria-hidden="true">
<div class="modal-dialog modal-lg">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title">Transaction Details</h4>
        </div>
        <div class="modal-body">
            <div class="table-responsive" style="width: 100%;">
            <table id="dataTable-detailtable" class="table table-bordered" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Is Soda Entry</th>
                        <th>Item</th>
                        <th>Rate</th>
                        <th>Is Cut Rate</th>
                        <th>Qty</th>
                        <th>MCX</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
    </div>
</div>
</div>
<div class="modal fade" id="deletetransactionModel" role="basic" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmation</h4>
            </div>
            <form action="#" method="post" id="dform">
                <div class="modal-body">
                    <p>Are you sure you want remove this Transaction ?</p>
                    <input type="hidden" name="_token" value="QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T">                    <input type="hidden" name="delete_transaction_id" id="delete_transaction_id">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Yes</button>
                    <button type="button" class="btn btn-default btn-outline" data-dismiss="modal">No</button>
                </div>
            </form>
        </div>
    </div>
</div>
                </div>
            </div>

        </div>

        
        
        
        
    </div>
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>

/* ── Table cell sizing & alignment ── */
#dataTable-transactionTable td,
#dataTable-transactionTable th {
    padding: 10px 12px !important;
    vertical-align: middle !important;
    font-size: 15px;
}

/* Amount column right-aligned */
#dataTable-transactionTable td:last-child,
#dataTable-transactionTable th:last-child {
    text-align: right;
}

/* Account column link color */
#dataTable-transactionTable td a {
    color: #007bff;
    text-decoration: none;
}
#dataTable-transactionTable td a:hover {
    text-decoration: underline;
}

/* ── Action buttons ── */
#dataTable-transactionTable .btn-info {
    background-color: #17a2b8 !important;
    border-color: #17a2b8 !important;
    color: #fff !important;
    padding: 4px 8px !important;
    border-radius: 4px;
}
#dataTable-transactionTable .btn-info:hover {
    background-color: #138496 !important;
    border-color: #117a8b !important;
}
#dataTable-transactionTable .btn-danger {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: #fff !important;
    padding: 4px 8px !important;
    border-radius: 4px;
}
#dataTable-transactionTable .btn-danger:hover {
    background-color: #c82333 !important;
    border-color: #bd2130 !important;
}

/* ── Card spacing ── */
.card-body {
    padding: 1rem !important;
}

.btn-sm {
    padding: 4px 8px !important;
}

/* ── Filter card: label + Reset on same line ── */
.card:first-child .form-group label.control-label {
    font-weight: 600;
    font-size: 13px;
    color: #555;
}
.reset_party_filter,
.reset_cash_bank_filter {
    font-size: 11px !important;
    color: #007bff !important;
    padding: 0 !important;
    background: none !important;
    border: none !important;
    cursor: pointer;
    line-height: 1;
}
.reset_party_filter:hover,
.reset_cash_bank_filter:hover {
    text-decoration: underline;
}


.dataTables_scrollBody {
    height: 520px !important;
}


.content-header {
    padding-bottom: 5px !important;
}
.content {
    padding-top: 0px !important;
}
.card {
    margin-bottom: 12px !important;
}

/* ── Filter row inputs ── */
#transaction_date {
    height: 34px;
}
.select2-container .select2-selection--single {
    height: 34px !important;
    line-height: 34px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 34px !important;
    color: #555;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 34px !important;
}

/* ── DataTables header (thead) styling ── */
#dataTable-transactionTable thead th {
    background-color: #fff;
    border-bottom: 2px solid #dee2e6 !important;
    font-weight: 700;
    color: #333;
}

/* ── Zebra / hover on rows ── */
#dataTable-transactionTable tbody tr:hover {
    background-color: #f8f9fa !important;
}

/* ─────────────────────────────
   DATEPICKER MODERN UI
───────────────────────────── */

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

/* ── PDF export button icon ── */
.dt-button.buttons-pdf {
    border: none !important;
    background: none !important;
    box-shadow: none !important;
    padding: 0 !important;
}

</style>
<?php include 'includes/scripts_start.php'; ?>


    <script>
        var transactionTable;
        var detailtable;
        var party_id = '';
        $(document).ready( function(){
            $('#transaction_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});
            initAjaxSelect2(
    $("#party_id"),
    "api/accounts_select2.php"
);
                        initAjaxSelect2(
    $("#cash_bank_id"),
    "api/accounts_select2.php"
);
                        
            $(document).on(
    'change',
    '#transaction_date, #party_id, #cash_bank_id',
    function () {

        transactionTable.ajax.reload();

    }
);

            $(document).on('click', '.reset_party_filter', function(){
                $('#party_id').val(null).trigger('change');
            });

            $(document).on('click', '.reset_cash_bank_filter', function(){
                $('#cash_bank_id').val(null).trigger('change');
            });
            
            $(document).on('click', '.transaction_detail', function(){
                var transaction_id = $(this).attr('data-transaction_id');
                $('#clicked_id').val(transaction_id);
                $('#detailModel').modal('show');
                detailtable.draw();
                return false;
            });
            
            $(document).on('click', '.delete_transaction', function(){
                var delete_transaction_id = $(this).attr('data-transaction_id');
                $('#delete_transaction_id').val(delete_transaction_id);
                $('#deletetransactionModel').modal('show');
                return false;
            });

            transactionTable = $('#dataTable-transactionTable').DataTable({
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'pdf',
                    download: 'open',
                    text: 'Transaction List',
//                    orientation: 'landscape',
    //                footer: true,
                    title: function () { return ('Transaction_List_2026_05_23_12_10_03') },
                    action: newExportAction,
                    customize: function(doc) {
                        var objLayout = {};
                        objLayout['hLineWidth'] = function(i) { return .5; };
                        objLayout['vLineWidth'] = function(i) { return .5; };
                        doc.content[1].layout = objLayout;
                        doc.defaultStyle.fontSize = 10;
                    },
                    exportOptions: {
                        columns: [ 1, 2, 3, 4],
                    },
                }],
                serverSide: false,
                ordering: false,
                searching: true,
               "ajax": {

    "url": "api/get_transactions.php",

    "type": "GET",

    "data": function(d){

        d.date =
            $('#transaction_date').val();

        d.party_id =
            $('#party_id').val();

        d.cash_bank_id =
            $('#cash_bank_id').val();

    },

    "dataSrc": "data"

},
                "columns": [
                {
    "data": 'id',
    "render": function(data){

        return `

<a
href="transaction.php?id=${data}"
class="btn btn-info btn-sm mr-1">

    <i class="fa fa-edit"></i>

</a>

<button
class="btn btn-danger btn-sm delete_transaction"
data-transaction_id="${data}">

   <i class="fa fa-times"></i>

</button> 

`;
    }
},
                {
                    "data": "transaction_date",
                    "defaultContent": '-',
                    "searchable": false,
                    "sClass": 'text-nowrap',
                },
                {
                    "data": 'id',
                    "sClass": 'text-nowrap',
                    "render": function( data, type, full, meta ) {
                        return '<a href="javascript:void(0);" class="transaction_detail" data-transaction_id="'+ data +'">'+full.party_name+'</a>';
                    }
                },
                {
                    "data": "cash_bank_name",
                    "defaultContent": '-',
                    "searchable": false,
                },
                {
    "data": 'amount',
    "defaultContent": '-',
    "className": 'text-right',
},
                ],
                scrollY: "520px",
scrollX: true,
scrollCollapse: true,
responsive: false,
autoWidth: false,
                scroller: {
                    loadingIndicator: true
                },
                stateSave: true
            });
            data_table_export_icon_style();
            
            detailtable = $('#dataTable-detailtable').DataTable({
                "bServerSide": true,
                "processing": true,
                "bRetrieve": true,
                "pageLength": 10,
                "ajax": {
                    "url": "api/get_transaction_details.php",
                    "type": "POST",
                    "data": function (d) {
                        d._token = 'QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T';
                        d.transaction_id = $('#clicked_id').val();
                    },
                },
                "columns": [
                {
                    "data": "from_entry_id",
                    "defaultContent": '-',
                    "searchable": false,
                    "render": function( data, type, full, meta ) {
                        if(data != null){
                            return "Yes";
                        } else {
                            return '';
                        }
                    }
                },
                {
                    "data": "item_id",
                    "defaultContent": '-',
                    "searchable": false,
                    "render": function( data, type, full, meta ) {
                        if(data == '1'){
                            return "Gold";
                        } else if(data == '2'){
                            return "Silver";
                        } else if(data == '5'){
                            return "Extra Gold";
                        } else {
                            return '';
                        }
                    }
                },
                {
                    "data": "rate",
                    "defaultContent": '-',
                    "searchable": false,
                    "sClass": 'text-nowrap',
                    "sClass": 'text-right',
                },
                {
                    "data": "is_cut_rate",
                    "defaultContent": '-',
                    "searchable": false,
                    "render": function( data, type, full, meta ) {
                        if(data == '1'){
                            return "Yes";
                        } else {
                            return '';
                        }
                    }
                },
                {
                    "data": "qty",
                    "defaultContent": '-',
                    "searchable": false,
                    "sClass": 'text-nowrap',
                    "sClass": 'text-right',
                },
                {
                    "data": "mcx",
                    "defaultContent": '-',
                    "searchable": false,
                    "sClass": 'text-nowrap',
                    "sClass": 'text-right',
                },
                ],
                "ordering": false,
                "searching": false,
            });

        });

    </script>

    <script>

        $(document).on(
    'click',
    '.delete_transaction',
    function(){

        if(
            !confirm(
                'Are you sure want to delete this transaction?'
            )
        ){
            return false;
        }

        var transaction_id =
            $(this).data('transaction_id');

        $.ajax({

            url: 'api/delete_transaction.php',

            type: 'POST',

            data: {
                transaction_id: transaction_id
            },

            success: function(response){

                if(response.success){

                    alert(response.msg);

                    $('#dataTable-transactionTable')
                        .DataTable()
                        .ajax
                        .reload();

                } else {

                    alert(response.msg);
                }
            },

            error: function(){

                alert('Server Error');
            }
        });

        return false;
    }
);


    $(document).on('keydown', function(e) {

    // Global Form Save (Ctrl+S)
    if (e.ctrlKey && e.which === 83) {

        e.preventDefault();

        if ($('.module_save_btn').length > 0) {

            $('.module_save_btn').first().click();

        } else if ($('form').length > 0) {

            $('form').first().submit();
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
                data: { 'status' : status , 'message' : message, "_token": "QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T" }
            }).done(function () {
                window.location.href = redirect_url;
            });
        }
        function redirectWithFlashMessageAndDataId ( redirect_url, status, message, data_id ) {
            $.ajax({
                type: 'POST',
                url: "#",
                data: { 'status' : status , 'message' : message, "_token": "QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T", 'data_id' : data_id }
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
                setTimeout(function () {
    dt.ajax.reload(null, false);
}, 0);
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
