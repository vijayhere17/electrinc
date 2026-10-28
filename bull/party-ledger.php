<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>Party Ledger</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="party_id" class="control-label">Party</label>
                    <select class="form-control select2" name="party_id" id="party_id">
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="from_date" class="control-label">From Date</label>
                    <input type="text"
name="from_date"
id="from_date"
class="form-control"
placeholder=""
data-index="1"
value="<?php echo date('d-m-Y'); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="to_date" class="control-label">To Date</label>
                    <input type="text"
name="to_date"
id="to_date"
class="form-control"
placeholder=""
data-index="1"
value="<?php echo date('d-m-Y'); ?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-1"><br><label for="current" class="control-label">Current</label></div>
            <div class="col-md-1">
                <div class="form-group">
                    <label for="total_balance_amount" class="control-label">Amount</label>
                    <br/><a href="cashbank-ledger.php?id=0" ><span type="text" id="total_balance_amount" >0</span></a>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label for="total_balance_gold" class="control-label">Gold</label>
                    <br/><a href="index.php/admin/itemLedger/1" ><span type="text" id="total_balance_gold" >0</span></a>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label for="total_balance_silver" class="control-label">Silver</label>
                    <br/><a href="index.php/admin/itemLedger/2" ><span type="text" id="total_balance_silver" >0</span></a>
                </div>
            </div>
            <div class="col-md-2 bg-warning">
                <div class="form-group">
                    <label for="total_net_gold_soda" class="control-label">Gold Soda</label>
                    <br/><span type="text" id="total_net_gold_soda" >0<br>@0</span>
                </div>
            </div>
            <div class="col-md-1 bg-warning">
                <div class="form-group">
                    <label for="total_net_gold_bill_soda" class="control-label">Bill Soda</label>
                    <br/><span type="text" id="total_net_gold_bill_soda" >0<br>@0</span>
                </div>
            </div>
            <div class="col-md-1 bg-warning">
                <div class="form-group">
                    <label for="total_net_gold_cash_soda" class="control-label">Cash Soda</label>
                    <br/><span type="text" id="total_net_gold_cash_soda" >0<br>@0</span>
                </div>
            </div>
            <div class="col-md-2 bg-gray">
                <div class="form-group">
                    <label for="total_net_silver_soda" class="control-label">Silver Soda</label>
                    <br/><span type="text" id="total_net_silver_soda" >0<br>@0</span>
                </div>
            </div>
            <div class="col-md-1 bg-gray">
                <div class="form-group">
                    <label for="total_net_silver_bill_soda" class="control-label">Bill Soda</label>
                    <br/><span type="text" id="total_net_silver_bill_soda" >0<br>@0</span>
                </div>
            </div>
            <div class="col-md-1 bg-gray">
                <div class="form-group">
                    <label for="total_net_silver_cash_soda" class="control-label">Cash Soda</label>
                    <br/><span type="text" id="total_net_silver_cash_soda" >0<br>@0</span>
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
            <div style="width:100%;overflow-x:hidden;">
                <div id="ledger_total_section" class="mb-3" style="display:none;">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="border rounded p-2 bg-light h-100">
                                <h6 class="mb-1"><strong>Tr Total</strong></h6>
                                <span id="outside_total_tr_amount"></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-2 bg-light h-100">
                                <h6 class="mb-1"><strong>Net Total</strong></h6>
                                <span id="outside_total_net_amount"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <table id="dataTable-ledgerTable" class="table table-bordered">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="select_all_ledger">
                            </th>
                            <th>Action</th>
                            <th>Date</th>
                            <th>Particular</th>
                            <th class="text-right">Tr Amount</th>
                            <th class="text-right">Net Amount</th>
                            <th class="text-right">Tr Gold</th>
                            <th class="text-right">Net Gold</th>
                            <th class="text-right">Tr Silver</th>
                            <th class="text-right">Net Silver</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <table id="dataTable-summaryTable"></table>
            </div>
            </div>
        </div>
        </div>
    </div>
</div>
<div class="modal fade" id="deleteModel" role="basic" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmation</h4>
            </div>
                <div class="modal-body">
                    <p>Are you sure you want remove this Record ?</p>
                    <input type="hidden" name="delete_url" id="delete_url" value="">
                    <input type="hidden" name="" id="delete_id_name" value="">
                    <input type="hidden" name="" id="delete_id" value="">
                    <input type="hidden" id="type_id" name="type_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success confirmed_delete_btn">Yes</button>
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
     var summaryTable;
     var ledgerTable;
        $(document).ready( function(){
            
           $('#from_date, #to_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});
            initAjaxSelect2(
    $("#party_id"),
    "api/get_accounts.php"
);
                            
            $(document).on('change', '#party_id', function(){
                summaryTable.draw();
                ledgerTable.draw();
            });
            $(document).on('change', '#from_date', function(){
                ledgerTable.draw();
            });
            $(document).on('change', '#to_date', function(){
                ledgerTable.draw();
            });

            $(document).on('click', '.delete_button', function(){
                $('#delete_url').val($(this).data('href'));
                $('#delete_id_name').val($(this).data('delete_id_name'));
                $('#delete_id').val($(this).data('delete_id'));
                $('#type_id').val($(this).data('type_id'));
                $('#deleteModel').modal('show');
                return false;
            });

            $(document).on('click', '.confirmed_delete_btn', function(){
                $('#deleteModel').modal('hide');
                var delete_module_id = $('#delete_id_name').val();
                var delete_module_url = $('#delete_url').val();
                var type_id = $('#type_id').val();
                var postData = {};
                postData[delete_module_id] = $('#delete_id').val();
                postData['type_id'] = type_id;
                postData['_token'] = "5t7z3lPhVjG6LVkISwDgqySaOPq39iVlKZfPcabd";
//                console.log(postData);
                $.ajax({
                    url: delete_module_url,
                    type: "POST",
                    cache: false,
                    data : postData,
                    async: false,
                    success: function (response) {
                        var json = $.parseJSON(response);
                        console.log(json);
                        var json = $.parseJSON(response);
                        $('.custom_alert_by_js').removeClass('d-none');
                        if (json == '1') {
                            summaryTable.draw();
                            ledgerTable.draw();
                            $('.custom_alert_by_js').html('<div class="alert alert-success"><button data-dismiss="alert" class="close">&times;</button>Record Deleted Successfully!</div>');
                        } else {
                            $('.custom_alert_by_js').html('<div class="alert alert-success"><button data-dismiss="alert" class="close">&times;</button>Record not allow to delete. Because it is Used.</div>');
                        }
                        setTimeout(function(){
                            $('.alert_flash_message').hide('slow');
                            $('.custom_alert_by_js').addClass('d-none');
                        }, 5000);
                        return false;
                    },
                });
            });

            summaryTable = $('#dataTable-summaryTable').DataTable({
                "serverSide": true,
                "scrollY": "480px",
                "scrollX": true,
                "search": true,
                "paging": false,
                "ordering": [1, "desc"],
                "order": [],
                "searching": false,
                "ajax": {
                   "url": "api/get_party_summary.php",
                    "type": "POST",
                    "data": function (d) {
                        
                        d.party_id = $('#party_id').val();
                       d.as_on_date = $('#to_date').val(); //$('#to_date').val();
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
                            total_balance_amount = '<span class="text-blue">' + json.total_balance_amount + ' Cr</span>';
                        } else {
                            total_balance_amount = '<span class="text-red">' + json.total_balance_amount + ' Dr</span>';
                        }
                        $('#total_balance_amount').html(total_balance_amount);
                        var total_balance_gold = '';
                        if(parseFloat(json.total_balance_gold) >= 0){
                            total_balance_gold = '<span class="text-blue">' + json.total_balance_gold + ' Cr</span>';
                        } else {
                            total_balance_gold = '<span class="text-red">' + json.total_balance_gold + ' Dr</span>';
                        }
                        $('#total_balance_gold').html(total_balance_gold);
                        var total_balance_silver = '';
                        if(parseFloat(json.total_balance_silver) >= 0){
                            total_balance_silver = '<span class="text-blue">' + json.total_balance_silver + ' Cr</span>';
                        } else {
                            total_balance_silver = '<span class="text-red">' + json.total_balance_silver + ' Dr</span>';
                        }
                        $('#total_balance_silver').html(total_balance_silver);
                        return json.data;
                    },
                    complete : function(){
                        $('#dataTable-summaryTable_wrapper').hide();
                    },
                },
            });

            ledgerTable = $('#dataTable-ledgerTable').DataTable({
                dom: 'lBfrtip',
                buttons: [
                    {
                        extend: 'pdf',
                        download: 'open',
                        text: 'Party Ledger PDF',
                        name: 'Party Ledger PDF',
//                        orientation: 'landscape',
                        title: function () { return ('Party Ledger PDF') },
                        header: true,
                        customize: function(doc) {
                            var objLayout = {};
                            objLayout['hLineWidth'] = function(i) { return .5; };
                            objLayout['vLineWidth'] = function(i) { return .5; };
                            doc.content[1].layout = objLayout;
                            doc.defaultStyle.fontSize = 10;
                            doc.content.splice(0, 1, {
                                text: [{
                                  text: 'Bul : Party Ledger \n',
                                  bold: true,
                                  fontSize: 20
                                }, {
                                  text: 'Party : '+ $("#party_id option:selected").text() +' \n',
                                  bold: true,
                                  fontSize: 11
                                }, {
                                  text: 'From Date : ' + $('#from_date').val() + '           To Date : ' + $('#to_date').val() + '\n',
                                  bold: true,
                                  fontSize: 11
                                }],
                                margin: [0, 0, 0, 12],
                                alignment: 'center'
                            });
                        },
                        exportOptions: {
                            columns: [ 2, 3, 4, 5, 6, 7, 8, 9],
                            columnDefs: [
                                {"className": "text-right", "targets": [4, 5, 6, 7, 8, 9]},
                            ],
                        },
                    },
                ],
                "serverSide": true,
                "scrollY": "480px",
                "scrollX": false,
                "search": true,
                "paging": false,
                "ordering": [1, "desc"],
                "order": [],
                drawCallback: function(settings) {

                    $('#select_all_ledger').prop('checked', false);

                    // if any checkbox selected then calculate checkbox totals
                    if ($('.ledger_row_checkbox:checked').length > 0) {

                        calculateCheckedTotals();

                    } else {

                        var api = this.api();
                        var json = api.ajax.json();

                        var searchValue = $('.dataTables_filter input').val() || '';

                        // show backend totals when searching
                        if (searchValue.trim().length > 0) {

                            $('#ledger_total_section').show();

                            $('#outside_total_tr_amount').html(
                                json.total_tr_amount ?? ''
                            );

                            $('#outside_total_net_amount').html(
                                json.total_net_amount ?? ''
                            );

                        } else {

                            $('#ledger_total_section').hide();

                        }
                    }
                },
                // "searching": false,
                "ajax": {
                    "url": "api/get_party_ledger.php",
                    "type": "POST",
                    "data": function (d) {
                        
                        d.party_id = $('#party_id').val();
                        d.from_date = $('#from_date').val();
                        d.to_date = $('#to_date').val();
                        d.search = $('input[type="search"]').val();
                    },
                },
                "columnDefs": [
                    {"className": "text-right", "targets": [3, 4, 5, 6, 7, 8]},
                ],
                columns: [
                     {
                        data: 'checkbox',
                        name: 'checkbox',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action', 
                        name: 'action',
                        sClass: 'text-nowrap',
                        orderable: false,
                    },
                    {
                        data: 'row_date', 
                        name: 'row_date',
                        sClass: 'text-nowrap',
                        orderable: false,
                    },
                    {
                        data: 'particular', 
                        name: 'particular',
                        orderable: false,
                        "render": function( data, type, full, meta ) {
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return '<strong>' + data + '</strong>';
                            } else {
                                return data;
                            }
                        }

                    },
                    {
                        data: 'row_amount', 
                        name: 'row_amount',
                        orderable: false,
                        "render": function( data, type, full, meta ) {
                            var return_data = ''
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '<strong>';
                            }
                            if(parseFloat(data) >= 0){
                                return_data += '<span class="text-blue">' + data + ' Cr</span>';
                            } else if(parseFloat(data) < 0){
                                return_data += '<span class="text-red">' + data + ' Dr</span>';
                            } else {
                                return_data += data;
                            }
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '</strong>';
                            }
                            return return_data;
                        }
                    },
                    {
                        data: 'balance_amount', 
                        name: 'balance_amount',
                        orderable: false,
                        "render": function( data, type, full, meta ) {
                            var return_data = ''
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '<strong>';
                            }
                            if(parseFloat(data) >= 0){
                                return_data += '<span class="text-blue">' + data + ' Cr</span>';
                            } else if(parseFloat(data) < 0){
                                return_data += '<span class="text-red">' + data + ' Dr</span>';
                            } else {
                                return_data += data;
                            }
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '</strong>';
                            }
                            return return_data;
                        }
                    },
                    {
                        data: 'row_gold', 
                        name: 'row_gold',
                        orderable: false,
                        "render": function( data, type, full, meta ) {
                            var return_data = ''
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '<strong>';
                            }
                            if(parseFloat(data) >= 0){
                                return_data += '<span class="text-blue">' + data + ' Cr</span>';
                            } else if(parseFloat(data) < 0){
                                return_data += '<span class="text-red">' + data + ' Dr</span>';
                            } else {
                                return_data += data;
                            }
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '</strong>';
                            }
                            return return_data;
                        }
                    },
                    {
                        data: 'balance_gold', 
                        name: 'balance_gold',
                        orderable: false,
                        "render": function( data, type, full, meta ) {
                            var return_data = ''
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '<strong>';
                            }
                            if(parseFloat(data) >= 0){
                                return_data += '<span class="text-blue">' + data + ' Cr</span>';
                            } else if(parseFloat(data) < 0){
                                return_data += '<span class="text-red">' + data + ' Dr</span>';
                            } else {
                                return_data += data;
                            }
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '</strong>';
                            }
                            return return_data;
                        }
                    },
                    {
                        data: 'row_silver', 
                        name: 'row_silver',
                        orderable: false,
                        "render": function( data, type, full, meta ) {
                            var return_data = ''
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '<strong>';
                            }
                            if(parseFloat(data) >= 0){
                                return_data += '<span class="text-blue">' + data + ' Cr</span>';
                            } else if(parseFloat(data) < 0){
                                return_data += '<span class="text-red">' + data + ' Dr</span>';
                            } else {
                                return_data += data;
                            }
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '</strong>';
                            }
                            return return_data;
                        }
                    },
                    {
                        data: 'balance_silver', 
                        name: 'balance_silver',
                        orderable: false,
                        "render": function( data, type, full, meta ) {
                            var return_data = ''
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '<strong>';
                            }
                            if(parseFloat(data) >= 0){
                                return_data += '<span class="text-blue">' + data + ' Cr</span>';
                            } else if(parseFloat(data) < 0){
                                return_data += '<span class="text-red">' + data + ' Dr</span>';
                            } else {
                                return_data += data;
                            }
                            if(full.particular == 'Opening' || full.particular == 'Closing'){
                                return_data += '</strong>';
                            }
                            return return_data;
                        }
                    },
                ]
            });
//            ledgerTable.columns( [0] ).visible( false );
            data_table_export_icon_style();
        });
        $(document).on('change', '.opening_amount_checkbox', function () {

            var opening_amount = parseFloat($(this).data('opening_amount'));

            var current_credit = parseFloat(
                $('#total_credit_span').text().replace('Cr', '').trim()
            );

            var current_debit = parseFloat(
                $('#total_debit_span').text().replace('Dr', '').replace('-', '').trim()
            );

            if ($(this).is(':checked')) {

                if (opening_amount >= 0) {
                    current_credit += opening_amount;
                } else {
                    current_debit += Math.abs(opening_amount);
                }

            } else {

                if (opening_amount >= 0) {
                    current_credit -= opening_amount;
                } else {
                    current_debit -= Math.abs(opening_amount);
                }
            }

            $('#total_credit_span').html(current_credit + ' Cr');
            $('#total_debit_span').html('-' + current_debit + ' Dr');
        });
        $(document).on('change', '.ledger_row_checkbox', function () {
            calculateCheckedTotals();
        });
        $(document).on('change', '#select_all_ledger', function () {
            $('#dataTable-ledgerTable tbody .ledger_row_checkbox').prop(
                'checked',
                $(this).is(':checked')
            );
            calculateCheckedTotals();
        });
        function calculateCheckedTotals() {
            $('#ledger_total_section').show();
            let tr_credit = 0;
            let tr_debit = 0;
            let net_credit = 0;
            let net_debit = 0;

            $('.ledger_row_checkbox:checked').each(function () {
                
                let row_amount = parseFloat($(this).data('row_amount')) || 0;
                let balance_amount = parseFloat($(this).data('balance_amount')) || 0;
                // TR Amount
                if (row_amount < 0) {
                    tr_debit += Math.abs(row_amount);
                } else {
                    tr_credit += row_amount;
                }
                // Net Amount
                if (balance_amount < 0) {
                    net_debit += Math.abs(balance_amount);
                } else {
                    net_credit += balance_amount;
                }
            });
            console.log('TR Credit: ' + tr_credit);
            console.log('TR Debit: ' + tr_debit);
            console.log('Net Credit: ' + net_credit);
            console.log('Net Debit: ' + net_debit); 
            
            $('#outside_total_tr_amount').html(
                '<span class="text-blue">' + tr_credit.toFixed(0) + ' Cr</span><br>' +
                '<span class="text-red">-' + tr_debit.toFixed(0) + ' Dr</span>'
            );
            $('#outside_total_net_amount').html(
                '<span class="text-blue">' + net_credit.toFixed(0) + ' Cr</span><br>' +
                '<span class="text-red">-' + net_debit.toFixed(0) + ' Dr</span>'
            );
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
    <style>
    .datepicker {
    border-radius: 10px !important;
    border: 1px solid #dcdcdc !important;
    padding: 10px !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.12) !important;
}

.datepicker table tr td,
.datepicker table tr th {
    width: 38px !important;
    height: 38px !important;
    border-radius: 6px !important;
    text-align: center !important;
}

.datepicker table tr td.active.active {
    background: #007bff !important;
}

.datepicker table tr td.today {
    background: #fff3cd !important;
}
    </style>
<?php include 'includes/scripts_end.php'; ?>
