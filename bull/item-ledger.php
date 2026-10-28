<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>Item Ledger</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="item_id" class="control-label">Item</label>
                    <select name="item_id" id="item_id" class="form-control select2" data-index="1">
                        <option value="1"  >Gold</option>
                        <option value="2"  >Silver</option>
                        <option value="3"  >Rani</option>
                        <option value="4"  >Rupu</option>
                        <option value="5"  >Extra Gold</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="from_date" class="control-label">From Date</label>
                    <input type="text"
id="from_date"
name="from_date"
class="form-control"
value="<?php echo date('d-m-Y'); ?>" data-index="2">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="to_date" class="control-label">To Date</label>
                   <input type="text"
id="to_date"
name="to_date"
class="form-control"
value="<?php echo date('d-m-Y'); ?>" data-index="3">
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
            <div style="width:100%; overflow-x:hidden;">
                <table id="dataTable-itemLedgerTable"
class="table table-bordered table-striped"
style="width:100% !important;">
                    <thead>
                    <tr>
    <th></th>
    <th></th>
    <th>Opening</th>
    <th id="opening_qty" class="text-right"></th>
</tr>
                        <tr>
                            <th>Action</th>
                            <th>Date</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th class="text-right">Qty</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                  <tfoot>
                        <tr>
                            <th></th>
                            <th></th>
                            <th>Closing</th>
                            <th id="closing_qty" class="text-right"></th>
                        </tr>
</tfoot>
                </table>
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
        var itemLedgerTable;
        $(document).ready( function(){
            $('#item_id').select2();
            $('#from_date, #to_date').datepicker({
                format: 'dd-mm-yyyy',
               todayHighlight: true,
                autoclose: true
            });

            $(document).on('change', '#item_id', function(){

    itemLedgerTable.ajax.reload(null, false);

});
            $(document).on('change', '#from_date, #to_date', function(){

    if(
        $.trim($('#from_date').val()) != ''
        &&
        $.trim($('#to_date').val()) != ''
    ){

        itemLedgerTable.ajax.reload(null, false);

    }

});

            $(document).on('click', '.delete_button', function(){
                $('#delete_url').val($(this).data('href'));
                $('#delete_id_name').val($(this).data('delete_id_name'));
                $('#delete_id').val($(this).data('delete_id'));
                $('#deleteModel').modal('show');
                return false;
            });

            $(document).on('click', '.confirmed_delete_btn', function(){
                $('#deleteModel').modal('hide');
                var delete_module_id = $('#delete_id_name').val();
                var delete_module_url = $('#delete_url').val();
                var postData = {};
                postData[delete_module_id] = $('#delete_id').val();
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
                            itemLedgerTable.draw();
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

            itemLedgerTable = $('#dataTable-itemLedgerTable').DataTable({
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'pdf',
                    download: 'open',
                    text: 'Item Ledger',
                    header: true,
//                    orientation: 'landscape',
    //                footer: true,
                    title: function () { return ('Item_Ledger_2026_05_26_18_39_21') },
                    action: newExportAction,
                    customize: function(doc) {
                        var objLayout = {};
                        objLayout['hLineWidth'] = function(i) { return .5; };
                        objLayout['vLineWidth'] = function(i) { return .5; };
                        doc.content[1].layout = objLayout;
                        doc.defaultStyle.fontSize = 10;
                        doc.content.splice(0, 1, {
                            text: [{
                              text: 'Bul : Item Ledger \n',
                              bold: true,
                              fontSize: 20
                            }, {
                              text: 'Item : '+ $("#item_id option:selected").text() +' \n',
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
                        columns: [ 1, 2, 3, 4 ],
                    },
                }],
                "serverSide": false,
                "scrollY": "70vh",
               
                "search": true,
                "paging": false,
                "autoWidth": false,
                "ordering": [1, "desc"],
                "order": [],
                "searching": false,
                "responsive": false,
                "ajax": {
                    "url": "api/get_item_ledger.php",
                    "type": "POST",
                    "data": function (d) {
                        d._token = '5t7z3lPhVjG6LVkISwDgqySaOPq39iVlKZfPcabd';
                        d.item_id = $('#item_id').val();
                        d.from_date = $('#from_date').val();
                        d.to_date = $('#to_date').val();
                    },
                    "dataSrc": function ( json ) {

    $('#opening_qty').html(
        json.opening_qty
    );

    $('#closing_qty').html(
        json.closing_qty
    );

    return json.data;

},
                },
                "columnDefs": [
                    {"className": "dt-right", "targets": []},
                    {"className": "text-right", "targets": [4]},
//                    {"orderable": false, "targets": [0]},
                ],
                columns: [

    {
        data: 'action',
        name: 'action',
        width: '10%',
        sClass: 'text-nowrap',
    },

    {
        data: 'row_date',
        name: 'row_date',
        width: '14%',
        sClass: 'text-nowrap',
    },

    {
        data: 'account_name',
        name: 'account_name',
        width: '46%',
        "render": function( data, type, full, meta ) {

            if(data == null){
                return '';
            }

            return '<a href="javascript:void(0);" class="go_to_ledger" data-party_id="'+ full.party_id +'">' + data + '</a>';

        }
    },

    {
        data: 'row_type',
        name: 'row_type',
        width: '15%',
        sClass: 'text-nowrap',
        "render": function( data, type, full, meta ) {

            if(
                full.row_type == 'Opening'
                ||
                full.row_type == 'Closing'
            ){

                return '<strong style="font-size:24px;">' + data + '</strong>';

            }

            return data;

        }
    },

    {
        data: 'row_qty',
        name: 'row_qty',
        width: '15%',
        sClass: 'text-nowrap text-right',
        "render": function( data, type, full, meta ) {

            if(
                full.row_type == 'Opening'
                ||
                full.row_type == 'Closing'
            ){

                return '<strong class="text-blue" style="font-size:24px;">' + data + ' Cr</strong>';

            }

            if(parseFloat(data) >= 0){

                return '<span class="text-blue">' + data + ' Cr</span>';

            }

            return '<span class="text-red">' + data + ' Dr</span>';

        }
    },

]
            });
            data_table_export_icon_style();

            $(document).on('click', '.go_to_ledger', function(){
                var party_id = $(this).attr('data-party_id');
                window.location.href = "party-ledger.php?id=" + party_id;
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

    <style>

.dataTables_wrapper{
    width:100% !important;
}

.dataTables_scroll{
    width:100% !important;
}

.dataTables_scrollHead{
    width:100% !important;
}

.dataTables_scrollHeadInner{
    width:100% !important;
}

.dataTables_scrollBody{
    width:100% !important;
}

#dataTable-itemLedgerTable{
    width:100% !important;
}

#dataTable-itemLedgerTable th,
#dataTable-itemLedgerTable td{
    vertical-align: middle;
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
<?php include 'includes/scripts_end.php'; ?>
