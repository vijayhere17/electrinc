
   <?php
include 'includes/config.php';
include 'includes/sidebar.php';
$id = $_GET['id'] ?? 0;

$query = mysqli_query($conn, "

SELECT *

FROM transactions

WHERE id='$id'

LIMIT 1

");

$row = mysqli_fetch_assoc($query);

if(!$row){

    die("Invalid TDS ID");

}
?>     
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    Edit TDS
    <a href="tds-report.php" class="btn btn-primary float-right">TDS List</a>
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="custom_alert_by_js d-none"></div>
<div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <form method="POST" action="tds-report.php/31/edit" accept-charset="UTF-8" id="save_tds" enctype="multipart/form-data" data-parsley-validate=""><input name="_token" type="hidden" value="5t7z3lPhVjG6LVkISwDgqySaOPq39iVlKZfPcabd">
        <input type="hidden" name="_token" value="5t7z3lPhVjG6LVkISwDgqySaOPq39iVlKZfPcabd">
        <input id="tds_id" name="id" type="hidden" value="<?php echo $row['id']; ?>">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="havaalo_date" class="control-label">Date</label><span class="text-danger">*</span>
                    <input type="text"
name="havaalo_date"
id="havaalo_date"
class="form-control"
placeholder=""
data-index="1"
value="<?php echo date('d-m-Y', strtotime($row['transaction_date'])); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="party_id" class="control-label">Party</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="party_id" id="party_id" data-index="2" >
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="item_id" class="control-label">Item</label><span class="text-danger">*</span>
                    <select class="form-control select2" name="item_id" id="item_id" data-index="3">
                        <option value="1"
<?php if($row['item_id']==1){ echo 'selected'; } ?>
>
Gold
</option>

<option value="2"
<?php if($row['item_id']==2){ echo 'selected'; } ?>
>
Silver
</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="form-group">
                    <label for="rate" class="control-label">Rate</label><span class="text-danger">*</span>
                    <input class="form-control positive_num_only" data-index="4" required="required" name="rate" type="text" value="<?php echo $row['rate']; ?>" id="rate">
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label for="qty" class="control-label">Qty</label><span class="text-danger">*</span>
                    <input class="form-control num_only" data-index="5" required="required" name="qty" type="text" value="<?php echo $row['qty']; ?>" id="qty">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="tds_val" class="control-label">TDS (%)</label><span class="text-danger">*</span>
                    <input class="form-control positive_num_only" data-index="6" required="required" name="tds_val" type="text" value="<?php echo abs($row['amount']); ?>" id="tds_val">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="narration" class="control-label">Narration</label>
                    <textarea class="form-control " rows="2" data-index="7" name="narration" cols="50" id="narration"><?php echo $row['narration']; ?></textarea>
                </div>
            </div>
            <div class="col-md-2">
                <label>&nbsp;</label><br>
                <input class="btn btn-primary module_save_btn" data-index="8" type="submit" value="Save">
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
        </form>
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
        var from_party_id = '';
        var entry_objectdata = [];
        var gold_not_in_stock = 0;
        var silver_not_in_stock = 0;
        $(document).ready( function(){
            $('.select2').select2();
            $('#havaalo_date').datepicker({
                format: 'dd-mm-yyyy',
                todayBtn: "linked",
                autoclose: true
            });

            $('form').on('keydown', 'input,select', function (event) {
                if (event.which === 13) {
                    event.preventDefault();
                    if($(this).hasClass('module_save_btn')){
                        $(this).click();
//                        $('[data-index="4"]').select2('open');
                    } else {
                        event.preventDefault();
                        var $this = $(event.target);
                        var index = parseFloat($this.attr('data-index'));
                        $('[data-index="' + (index + 1).toString() + '"]').focus();
                    }
                }
            });
            
            $(document).bind("keydown", function(e){
                if(e.ctrlKey && e.which == 83){
                    e.preventDefault();
                    $("#save_havaalo").submit();
                    return false;
                }
            });

            initAjaxSelect2($("#party_id"), "api/party_select2_source.php");
            
                        
                            setSelect2Value(
    $("#party_id"),
    "api/set_party_select2_val_by_id.php?id=<?php echo $row['party_id']; ?>"
);
                get_account_balance($('#party_id').val(), 'party_account_balance');
                        
            $('#party_id').select2('open');
            

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
            
            $(document).on('submit', '#save_tds', function () {
                if ($.trim($("#havaalo_date").val()) == '') {
                    bootbox.alert('<span class="text-danger">Select Date !</span>');
                    return false;
                }
                if ($.trim($("#party_id").val()) == '') {
                    bootbox.alert('<span class="text-danger">Select Party required !</span>');
                    return false;
                }
                if ($.trim($("#rate").val()) == '') {
                    bootbox.alert('<span class="text-danger">Rate required !</span>');
                    return false;
                }
                if ($.trim($("#qty").val()) == '') {
                    bootbox.alert('<span class="text-danger">Qty required !</span>');
                    return false;
                }
                if ($.trim($("#tds_val").val()) == '') {
                    bootbox.alert('<span class="text-danger">TDS % required !</span>');
                    return false;
                }
                
                // Pre-calculate the result to append to narration as per user's layout
                

                $('.module_save_btn').attr('disabled', 'disabled');
                var postData = new FormData(this);
                $.ajax({
                    url: "api/update_tds.php",
                    type: "POST",
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: postData,
                    datatype: 'json',
                    async: false,
                    success: function (response) {

    $('.module_save_btn').removeAttr('disabled', 'disabled');

    var json = response;
                        if (json['success'] == 'Added') {

    alert(json['msg']);

    window.location.href = "tds-report.php";

} else if (json['success'] == 'Updated') {

    alert(json['msg']);

    window.location.href = "tds-report.php";

}
                        return false;
                    },
                });
                return false;
            });

        });function get_account_balance(account_id, id_attr){
        if(account_id != '' && account_id != null){
            $.ajax({
    url: "api/get_account_balance.php?account_id="+ account_id,
    type: 'GET',
    dataType: 'json',
    data: '',
    success: function (response) {

        var json = response;
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
                    } else if(id_attr == 'to_party_account_balance'){
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
