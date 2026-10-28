<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    TDS List
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
                    <label for="from_date" class="control-label">From Date</label>
                   <input type="text"
name="from_date"
id="from_date"
class="form-control"
value="<?= date('d-m-Y') ?>" data-index="1">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="to_date" class="control-label">To Date</label>
                    <input type="text"
name="to_date"
id="to_date"
class="form-control"
value="<?= date('d-m-Y') ?>" data-index="2">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="party_id" class="control-label">Party</label> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="party_id" id="party_id" data-index="3">
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
                <div class="table-responsive">
                    <table id="dataTable-optable" class="table table-bordered">
                        <thead>
                            <tr>
                                <th rowspan="2" class="text-nowrap" width="100px">Action</th>
                                <th rowspan="2">Date</th>
                                <th rowspan="2">From</th>
                                <th rowspan="2">To</th>
                                <th colspan="2" class="text-center">Amount</th>
                                <th rowspan="2">Narration</th>
                            </tr>
                            <tr>
                                <th class="text-right"></th>
                                <th class="text-right"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" style="text-align:right">Total Amount:</th>
                                <th id="total_amount_in_footer" class="text-right">0</th>
                                <th id="total_amount_out_footer" class="text-right">0</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
<div class="modal fade" id="deletehavaaloModel" role="basic" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmation</h4>
            </div>
            <form action="api/tds_delete.php" method="post" id="dform">
                <div class="modal-body">
                    <p>Are you sure you want remove this TDS entry?</p>
                    <input type="hidden" name="_token" value="5t7z3lPhVjG6LVkISwDgqySaOPq39iVlKZfPcabd">                    <input type="hidden" name="delete_havaalo_id" id="delete_havaalo_id">
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


<style>

.datepicker{
    border-radius:10px!important;
    border:1px solid #dcdcdc!important;
    padding:10px!important;
    box-shadow:0 8px 25px rgba(0,0,0,.12)!important;
}

.datepicker-dropdown{
    min-width:280px!important;
}

.datepicker table tr td,
.datepicker table tr th{
    width:38px!important;
    height:38px!important;
    border-radius:6px!important;
    text-align:center!important;
}

.datepicker table tr td.active,
.datepicker table tr td.active:hover{
    background:#007bff!important;
    color:#fff!important;
}

.datepicker table tr td.today{
    background:#fff3cd!important;
    color:#000!important;
}

.datepicker .datepicker-switch{
    font-weight:700!important;
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
        var optable;
        var party_id = '';
        $(document).ready( function(){
            $('#from_date, #to_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});
            initAjaxSelect2($("#party_id"), "api/party_select2_source.php");
                        
            $(document).on('change', '#from_date, #to_date, #party_id', function () {
                optable.draw();
            });

            $(document).on('click', '.reset_party_filter', function(){

    $('#party_id').val(null).trigger('change');

    $('#from_date').val('');
    $('#to_date').val('');

    optable.draw();

});


            
            $(document).on('click', '.delete_havaalo', function(){
                var delete_havaalo_id = $(this).attr('data-havaalo_id');
                $('#delete_havaalo_id').val(delete_havaalo_id);
                $('#deletehavaaloModel').modal('show');
                return false;
            });
            
            
            optable = $('#dataTable-optable').DataTable({
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'pdf',
                    download: 'open',
                    text: 'TDS List',
//                    orientation: 'landscape',
    //                footer: true,
                    title: function () { return ('TDS_List_2026_05_26_15_55_41') },
                    action: newExportAction,
                    customize: function(doc) {
                        var objLayout = {};
                        objLayout['hLineWidth'] = function(i) { return .5; };
                        objLayout['vLineWidth'] = function(i) { return .5; };
                        doc.content[1].layout = objLayout;
                        doc.defaultStyle.fontSize = 10;
                    },
                    exportOptions: {
                        columns: [ 1, 2, 3, 4, 5, 6],
                    },
                }],
                serverSide: true,
                ordering: false,
                searching: false,
                "ajax": {
                    "url": "api/get_tds_datatable.php",
                    "type": "POST",
                    "data": function (d) {
                        d._token = '5t7z3lPhVjG6LVkISwDgqySaOPq39iVlKZfPcabd';
                        d.from_date = $('#from_date').val();
                        d.to_date = $('#to_date').val();
                        d.party_id = $('#party_id').val();
                    },
                },
                "columns": [
                {
                    "data": 'id',
                    "sClass": 'text-nowrap',
                    "render": function( data, type, full, meta ) {
                        var edit_button = '';
                                edit_button = '<a href="tds-edit.php?id='+ data +'"><button class="btn btn-sm btn-info edit_havaalo" ><i class="fa far fa-edit"></i></button></a>';
                        var delete_button = '';
                                delete_button = '<button class="btn btn-sm btn-danger delete_havaalo" data-havaalo_id="'+ data +'" title="Delete"><span class="fa fa-times-circle"></span></button>';
                        return edit_button+' '+delete_button;
                    }
                },
                {
                    "data": "havaalo_date_new",
                    "defaultContent": '-',
                    "searchable": false,
                    "sClass": 'text-nowrap',
                },
                {
                    "data": "from_name",
                    "defaultContent": '-',
                    "searchable": false,
                },
                {
                    "data": "to_name",
                    "defaultContent": '-',
                    "searchable": false,
                },
                {
                    "data": 'amount_in',
                    "defaultContent": '-',
                    "sClass": 'text-nowrap text-right',
                    "render": function( data, type, full, meta ) {
                        return data != 0 ? data : '-';
                    }
                },
                {
                    "data": 'amount_out',
                    "defaultContent": '-',
                    "sClass": 'text-nowrap text-right',
                    "render": function( data, type, full, meta ) {
                        return data != 0 ? data : '-';
                    }
                },
                {
                    "data": 'narration',
                    "defaultContent": '-',
                    "sClass": 'text-nowrap',
                },
                ],
                scrollY: 480,
                scroller: {
                    loadingIndicator: true
                },
                stateSave: true,
                "footerCallback": function (row, data, start, end, display) {
                    var api = this.api();
                    var json = api.ajax.json();
                    
                    if (json && json.total_amount_in !== undefined) {
                        $(api.column(4).footer()).html('<b>' + json.total_amount_in + '</b>');
                    }
                    if (json && json.total_amount_out !== undefined) {
                        $(api.column(5).footer()).html('<b>' + json.total_amount_out + '</b>');
                    }
                }
            });
            data_table_export_icon_style();

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
