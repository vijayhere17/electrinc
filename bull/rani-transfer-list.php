<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    Rani Transfer List
    <a href="rani-list.phptransfer/create" class="btn btn-primary float-right">Add New Rani Transfer</a>
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="card">
<!--    <div class="card-body">
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
                    <label for="from_party_id" class="control-label">From</label> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="from_party_id" id="from_party_id">
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="to_party_id" class="control-label">To</label><span class="btn float-right btn-xs reset_cash_bank_filter">Reset</span>
                    <select class="form-control select2" name="to_party_id" id="to_party_id">
                    </select>
                </div>
            </div>
        </div>
    </div>-->
</div>
<div class="card">
    <div class="card-body">
        <div class="custom_alert_by_js d-none"></div>
        <div class="row">
        <div class="col-md-12">
            <div class="col-md-12">
                <div class="table-responsive" style="width: 100%;">
                    <table id="dataTable-optable" class="table table-bordered" style="width:100%;">
                        <thead>
                            <tr>
                                <th class="text-nowrap" width="100px">Action</th>
                                <th>Date</th>
                                <th>Transfer Party</th>
                                <th>Total Gross</th>
                                <th>Total Fine</th>
                                <th>Narration</th>
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
<input type="hidden" id="clicked_id" value="-1" >
<div class="modal fade" id="detailModel" role="basic" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Rani Transfer Lineitems</h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive" style="width: 100%;">
                    <table id="dataTable-detailtable" class="table table-bordered" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Rani Gross</th>
                                <th>Touch</th>
                                <th>Fine</th>
                                <th>Receive Party</th>
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
<div class="modal fade" id="deleteranitransferModel" role="basic" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmation</h4>
            </div>
            <form action="index.php/admin/RaniTransferDelete" method="post" id="dform">
                <div class="modal-body">
                    <p>Are you sure you want remove this Rani Transfer ?</p>
                    <input type="hidden" name="_token" value="LTxPxCNCx2qW2xPtINBjFS0P8bzNXqbLpsVR4mvG">                    <input type="hidden" name="delete_ranitransfer_id" id="delete_ranitransfer_id">
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
        var detailtable;
        var from_party_id = '';
        $(document).ready( function(){
            $('#transaction_date').datepicker({
                format: 'dd-mm-yyyy',
                todayBtn: "linked",
                autoclose: true
            });
            initAjaxSelect2($("#from_party_id"), "api/cash_bank_select2_source.php");
                        initAjaxSelect2($("#to_party_id"), "api/cash_bank_select2_source.php");
                        
            $(document).on('change', '#transaction_date, #from_party_id, #to_party_id', function () {
                optable.draw();
            });

            $(document).on('click', '.reset_party_filter', function(){
                $('#from_party_id').val(null).trigger('change');
            });

            $(document).on('click', '.reset_cash_bank_filter', function(){
                $('#to_party_id').val(null).trigger('change');
            });
            
            $(document).on(
    'click',
    '.delete_rani_transfer',
    function(){

    var id =
    $(this).attr('data-id');

    bootbox.confirm({

        message:
        "Are you sure you want to delete this Rani Transfer ?",

        buttons: {

            confirm: {

                label: 'Yes',

                className:
                'btn-success'

            },

            cancel: {

                label: 'No',

                className:
                'btn-danger'

            }

        },

        callback: function(result){

            if(result){

                $.ajax({

                    url:
                    "api/delete_rani_transfer.php",

                    type: "POST",

                    data: {

                        id : id

                    },

                    dataType: 'json',

                    success: function(response){

                        bootbox.alert(
                            response.msg
                        );

                        $('#dataTable-optable')
                        .DataTable()
                        .ajax
                        .reload();

                    }

                });

            }

        }

    });

});
            
            $(document).on('click', '.tr_detail', function(){
                var ranitransfer_id = $(this).attr('data-ranitransfer_id');
                $('#clicked_id').val(ranitransfer_id);
                $('#detailModel').modal('show');
                detailtable.draw();
                setTimeout(function(){
                    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
                }, 500);
                return false;
            });
            
            
            optable = $('#dataTable-optable').DataTable({

    "serverSide": false,

    "scrollY": "480px",

    "scrollX": true,

    "paging": true,

    "order": [],

    scroller: {
        loadingIndicator: true
    },

    "ajax": {

        "url": "api/get_rani_transfer_list.php",

        "type": "GET",

        "data": function (d) {

        },

    },

    "ordering": false,

    "searching": true,

});
            
            detailtable = $('#dataTable-detailtable').DataTable({
                "serverSide": false,
                "scrollY": "280px",
                "scrollX": true,
                "search": true,
                "paging": true,
                "order": [],
                "searching": true,
                scroller: {
                    loadingIndicator: true
                },
                "ajax": {
                    "url": "index.php/admin/getRaniTransferdetailDatatable",
                    "type": "POST",
                    "data": function (d) {
                        d._token = 'LTxPxCNCx2qW2xPtINBjFS0P8bzNXqbLpsVR4mvG';
                        d.ranitransfer_id = $('#clicked_id').val();
                    },
                },
                "columns": [
                {
                    "data": "rani_gross",
                    "defaultContent": '-',
                    "searchable": false,
                    "sClass": 'text-nowrap',
                    "sClass": 'text-right',
                },
                {
                    "data": "changed_touch",
                    "defaultContent": '-',
                    "searchable": false,
                    "sClass": 'text-nowrap',
                    "sClass": 'text-right',
                },
                {
                    "data": "changed_fine",
                    "defaultContent": '-',
                    "searchable": false,
                    "sClass": 'text-nowrap',
                    "sClass": 'text-right',
                },
                {
                    "data": "party_name",
                    "defaultContent": '-',
                    "searchable": false,
                },
                ],
                "ordering": false,
                "searching": false,
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
                data: { 'status' : status , 'message' : message, "_token": "LTxPxCNCx2qW2xPtINBjFS0P8bzNXqbLpsVR4mvG" }
            }).done(function () {
                window.location.href = redirect_url;
            });
        }
        function redirectWithFlashMessageAndDataId ( redirect_url, status, message, data_id ) {
            $.ajax({
                type: 'POST',
                url: "#",
                data: { 'status' : status , 'message' : message, "_token": "LTxPxCNCx2qW2xPtINBjFS0P8bzNXqbLpsVR4mvG", 'data_id' : data_id }
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
