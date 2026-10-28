<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper"
style="
margin-left:250px;
width:calc(100% - 250px);
overflow-x:hidden;
">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>Party Summary</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="card">
    <div class="card-body p-2">
        <div id="error_msg"></div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="party_id" class="control-label">Party</label> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select name="party_id" id="party_id" class="form-control select2" ></select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="as_on_date" class="control-label">As on Date</label>
                    <input type="text"
name="as_on_date"
id="as_on_date"
class="form-control"
placeholder=""
data-index="1"
value="<?php echo date('d-m-Y'); ?>">
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="custom_alert_by_js d-none"></div>
        <div class="row">
       
            <div class="col-md-12 p-0">
    <div class="table-responsive">
                <table
id="dataTable-summaryTable"
class="table table-bordered"
style="
width:100%;
table-layout:fixed;
">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th colspan="3" class="text-center">Balance</th>
                            <th colspan="3" class="text-center">Net Gold</th>
                            <th colspan="3" class="text-center">Net Silver</th>
                        </tr>
                        <tr>
                            <th class="text-center align-middle">
    Total
</th>
                            <th id="total_balance_amount" class="text-right"></th>
                            <th id="total_balance_gold" class="text-right"></th>
                            <th id="total_balance_silver" class="text-right"></th>
                            <th id="total_net_gold_soda"></th>
                            <th id="total_net_gold_bill_soda"></th>
                            <th id="total_net_gold_cash_soda"></th>
                            <th id="total_net_silver_soda"></th>
                            <th id="total_net_silver_bill_soda"></th>
                            <th id="total_net_silver_cash_soda"></th>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Gold</th>
                            <th class="text-right">Silver</th>
                            <th>Soda</th>
                            <th>Bill Soda</th>
                            <th>Cash Soda</th>
                            <th>Soda</th>
                            <th>Bill Soda</th>
                            <th>Cash Soda</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
           
        </div>
        </div>
    </div>
</div>
                </div>
            </div>

        </div>

        
        
        
        
    </div>

    <style>

.content-wrapper{

    overflow-x:hidden !important;

}

.card{

    width:100% !important;

}

.table-responsive{

    width:100% !important;
    overflow-x:auto !important;

}

.dataTables_wrapper{

    width:100% !important;

}

.dataTables_scroll{

    width:100% !important;

}

.dataTables_scrollHeadInner{

    width:100% !important;

}

.dataTables_scrollHeadInner table{

    width:100% !important;

}

#dataTable-summaryTable{

    width:100% !important;
    table-layout:fixed;

}

#dataTable-summaryTable th{

    font-size:14px;
    font-weight:700;
    text-align:center;
    vertical-align:middle;

}

#dataTable-summaryTable td{

    font-size:13px;
    vertical-align:middle;
    padding:8px 6px;
word-break:break-word;
    

}

.text-blue{

    color:#0066ff;
    font-weight:bold;

}

.text-red{

    color:#ff0000;
    font-weight:bold;

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
        var summaryTable;
        $(document).ready( function(){

           $('#as_on_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});

            initAjaxSelect2(
    $("#party_id"),
    "api/party_select2_source.php"
);
            
            $(document).on('change', '#party_id', function(){

    summaryTable.ajax.reload(
        null,
        false
    );

});
            $(document).on(
    'click',
    '.reset_party_filter',
    function(){

        $('#party_id')
        .val(null)
        .trigger('change');

        summaryTable.ajax.reload(
            null,
            false
        );

    }
);

            $(document).on('change', '#as_on_date', function(){

    summaryTable.ajax.reload(
        null,
        false
    );

});
            summaryTable = $('#dataTable-summaryTable').DataTable({
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'pdf',
                    download: 'open',
                    text: 'Party Summary',
                    header: true,
                    orientation: 'landscape',
                    pageSize: 'A4',
    //                footer: true,
                    title: function () { return ('Party_Summary_2026_05_26_17_52_17') },
                    action: newExportAction,
                    customize: function(doc) {
                        var objLayout = {};
                        objLayout['hLineWidth'] = function(i) { return .5; };
                        objLayout['vLineWidth'] = function(i) { return .5; };
                        doc.content[1].layout = objLayout;
                        doc.content[1].table.widths = ['*', 'auto', 'auto', 'auto', 'auto', 'auto', 'auto', 'auto', 'auto', 'auto'];
                        doc.defaultStyle.fontSize = 10;
                    },
                    exportOptions: {
                        columns: [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
                    },
                }],
                "serverSide": false,
                "scrollY": "650px",
                "scrollX": false,
"autoWidth": false,
                "search": true,
                "paging": false,
                "ordering": false,
                "order": [],
                "searching": false,
                "ajax": {
                   "url": "api/get_party_summary.php",
                    "type": "POST",
                    "data": function (d) {
                        
                        d.party_id = $('#party_id').val();
                        d.as_on_date = $('#as_on_date').val();
                    },
                    "dataSrc": function ( json ) {
                        var json_total_net_gold_soda_qty = json.total_net_gold_soda_qty;
                        json_total_net_gold_soda_qty = json_total_net_gold_soda_qty.toFixed(3);
                        var average_rate = (parseFloat(json.total_net_gold_soda_amount) / parseFloat(json_total_net_gold_soda_qty)) || 0;
                        $('#total_net_gold_soda').html(json_total_net_gold_soda_qty + '<br>@' + average_rate.toFixed(1));
                        var json_total_net_gold_bill_soda_qty = json.total_net_gold_bill_soda_qty;
                        json_total_net_gold_bill_soda_qty = json_total_net_gold_bill_soda_qty.toFixed(3);
                        var average_rate = (parseFloat(json.total_net_gold_bill_soda_amount) / parseFloat(json_total_net_gold_bill_soda_qty)) || 0;
                        $('#total_net_gold_bill_soda').html(json_total_net_gold_bill_soda_qty + '<br>@' + average_rate.toFixed(1));
                        var json_total_net_gold_cash_soda_qty = json.total_net_gold_cash_soda_qty;
                        json_total_net_gold_cash_soda_qty = json_total_net_gold_cash_soda_qty.toFixed(3);
                        var average_rate = (parseFloat(json.total_net_gold_cash_soda_amount) / parseFloat(json_total_net_gold_cash_soda_qty)) || 0;
                        $('#total_net_gold_cash_soda').html(json_total_net_gold_cash_soda_qty + '<br>@' + average_rate.toFixed(1));

                        var json_total_net_silver_soda_qty = json.total_net_silver_soda_qty;
                        json_total_net_silver_soda_qty = json_total_net_silver_soda_qty.toFixed(3);
                        var average_rate = (parseFloat(json.total_net_silver_soda_amount) / parseFloat(json_total_net_silver_soda_qty)) || 0;
                        $('#total_net_silver_soda').html(json_total_net_silver_soda_qty + '<br>@' + average_rate.toFixed(1));
                        var json_total_net_silver_bill_soda_qty = json.total_net_silver_bill_soda_qty;
                        json_total_net_silver_bill_soda_qty = json_total_net_silver_bill_soda_qty.toFixed(3);
                        var average_rate = (parseFloat(json.total_net_silver_bill_soda_amount) / parseFloat(json_total_net_silver_bill_soda_qty)) || 0;
                        $('#total_net_silver_bill_soda').html(json_total_net_silver_bill_soda_qty + '<br>@' + average_rate.toFixed(1));
                        var json_total_net_silver_cash_soda_qty = json.total_net_silver_cash_soda_qty;
                        json_total_net_silver_cash_soda_qty = json_total_net_silver_cash_soda_qty.toFixed(3);
                        var average_rate = (parseFloat(json.total_net_silver_cash_soda_amount) / parseFloat(json_total_net_silver_cash_soda_qty)) || 0;
                        $('#total_net_silver_cash_soda').html(json_total_net_silver_cash_soda_qty + '<br>@' + average_rate.toFixed(1));

                       var total_balance_amount = '';

if(parseFloat(json.total_balance_amount) >= 0){

    total_balance_amount =
    '<span class="text-blue">' +
    parseFloat(json.total_balance_amount).toFixed(2) +
    ' Cr</span>';

}
else{

    total_balance_amount =
    '<span class="text-red">' +
    Math.abs(parseFloat(json.total_balance_amount)).toFixed(2) +
    ' Dr</span>';

}

$('#total_balance_amount').html(total_balance_amount);


var total_balance_gold = '';

if(parseFloat(json.total_balance_gold) >= 0){

    total_balance_gold =
    '<span class="text-blue">' +
    parseFloat(json.total_balance_gold).toFixed(3) +
    ' Cr</span>';

}
else{

    total_balance_gold =
    '<span class="text-red">' +
    Math.abs(parseFloat(json.total_balance_gold)).toFixed(3) +
    ' Dr</span>';

}

$('#total_balance_gold').html(total_balance_gold);


var total_balance_silver = '';

if(parseFloat(json.total_balance_silver) >= 0){

    total_balance_silver =
    '<span class="text-blue">' +
    parseFloat(json.total_balance_silver).toFixed(3) +
    ' Cr</span>';

}
else{

    total_balance_silver =
    '<span class="text-red">' +
    Math.abs(parseFloat(json.total_balance_silver)).toFixed(3) +
    ' Dr</span>';

}

$('#total_balance_silver').html(total_balance_silver);

return json.data;
                    },
                },
                "columnDefs": [
                    {"className": "dt-right", "targets": []},
                    {"className": "text-right", "targets": [1,2,3]},
//                    {"orderable": false, "targets": [0]},
                ],
                columns: [
                    {
                        data: 'account_name', 
                        name: 'account_name',
                        "render": function( data, type, full, meta ) {
                            return '<a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+ full.id +'">' + data + '</a>';
                        }
                    },
                    {
                        data: 'balance_amount', 
                        name: 'balance_amount',
                        "render": function( data, type, full, meta ) {
                            if(parseFloat(data) >= 0){
                                return '<a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+ full.id +'"><span class="text-blue">' + data + ' Cr</span></a>';
                            } else {
                                return '<a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+ full.id +'"><span class="text-red">' + data + ' Dr</span></a>';
                            }
                        }
                    },
                    {
                        data: 'balance_gold', 
                        name: 'balance_gold',
                        "render": function( data, type, full, meta ) {
                            if(parseFloat(data) >= 0){
                                return '<a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+ full.id +'"><span class="text-blue">' + data + ' Cr</span></a>';
                            } else {
                                return '<a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+ full.id +'"><span class="text-red">' + data + ' Dr</span></a>';
                            }
                        }
                    },
                    {
                        data: 'balance_silver', 
                        name: 'balance_silver',
                        "render": function( data, type, full, meta ) {
                            if(parseFloat(data) >= 0){
                                return '<a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+ full.id +'"><span class="text-blue">' + data + ' Cr</span></a>';
                            } else {
                                return '<a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+ full.id +'"><span class="text-red">' + data + ' Dr</span></a>';
                            }
                        }
                    },
                    {
                        data: 'net_gold_soda_qty', 
                        name: 'net_gold_soda_qty',
                        "render": function( data, type, full, meta ) {
                            var average_rate = (parseFloat(full.net_gold_soda_amount) / parseFloat(full.net_gold_soda_qty)) || 0;
                            return '<a href="javascript:void(0);" class="go_to_transaction" data-party_id="'+ full.id +'">' + data.toFixed(3) + '<br>@' + average_rate.toFixed(1) + '</a>';
                        }
                    },
                    {
                        data: 'net_gold_bill_soda_qty', 
                        name: 'net_gold_bill_soda_qty',
                        "render": function( data, type, full, meta ) {
                            var average_rate = (parseFloat(full.net_gold_bill_soda_amount) / parseFloat(full.net_gold_bill_soda_qty)) || 0;
                            return '<a href="javascript:void(0);" class="go_to_transaction" data-party_id="'+ full.id +'">' + data.toFixed(3) + '<br>@' + average_rate.toFixed(1) + '</a>';
                        }
                    },
                    {
                        data: 'net_gold_cash_soda_qty', 
                        name: 'net_gold_cash_soda_qty',
                        "render": function( data, type, full, meta ) {
                            var average_rate = (parseFloat(full.net_gold_cash_soda_amount) / parseFloat(full.net_gold_cash_soda_qty)) || 0;
                            return '<a href="javascript:void(0);" class="go_to_transaction" data-party_id="'+ full.id +'">' + data.toFixed(3) + '<br>@' + average_rate.toFixed(1) + '</a>';
                        }
                    },
                    {
                        data: 'net_silver_soda_qty', 
                        name: 'net_silver_soda_qty',
                        "render": function( data, type, full, meta ) {
                            var average_rate = (parseFloat(full.net_silver_soda_amount) / parseFloat(full.net_silver_soda_qty)) || 0;
                            return '<a href="javascript:void(0);" class="go_to_transaction" data-party_id="'+ full.id +'">' + data.toFixed(3) + '<br>@' + average_rate.toFixed(1) + '</a>';
                        }
                    },
                    {
                        data: 'net_silver_bill_soda_qty', 
                        name: 'net_silver_bill_soda_qty',
                        "render": function( data, type, full, meta ) {
                            var average_rate = (parseFloat(full.net_silver_bill_soda_amount) / parseFloat(full.net_silver_bill_soda_qty)) || 0;
                            return '<a href="javascript:void(0);" class="go_to_transaction" data-party_id="'+ full.id +'">' + data.toFixed(3) + '<br>@' + average_rate.toFixed(1) + '</a>';
                        }
                    },
                    {
                        data: 'net_silver_cash_soda_qty', 
                        name: 'net_silver_cash_soda_qty',
                        "render": function( data, type, full, meta ) {
                            var average_rate = (parseFloat(full.net_silver_cash_soda_amount) / parseFloat(full.net_silver_cash_soda_qty)) || 0;
                            return '<a href="javascript:void(0);" class="go_to_transaction" data-party_id="'+ full.id +'">' + data.toFixed(3) + '<br>@' + average_rate.toFixed(1) + '</a>';
                        }
                    },
                ]
            });
            data_table_export_icon_style();

            $(document).on('click', '.go_to_ledger', function(){
                var party_id = $(this).attr('data-party_id');
                window.location.href =
"ledger.php?id=" + party_id;
                return false;
            });

            $(document).on('click', '.go_to_transaction', function(){
                var party_id = $(this).attr('data-party_id');
                window.location.href =
"transaction.php?party_id=" + party_id;
                return false;
            });

        });
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
                data: { 'status' : status , 'message' : message, "_token": "5t7z3lPhVjG6LVkISwDgqySaOPq39iVlKZfPcabd" }
            }).done(function () {
                window.location.href = redirect_url;
            });
        }
        function redirectWithFlashMessageAndDataId ( redirect_url, status, message, data_id ) {
            $.ajax({
                type: 'POST',
                url: "#",
                data: { 'status' : status , 'message' : message, "_token": "5t7z3lPhVjG6LVkISwDgqySaOPq39iVlKZfPcabd", 'data_id' : data_id }
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
