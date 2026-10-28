
        <?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    Add New Havaalo
    <a href="havaalo-list.php" class="btn btn-primary float-right">Havaalo List</a>
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="custom_alert_by_js d-none"></div>
<div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <form method="POST" action="" accept-charset="UTF-8" id="save_havaalo" enctype="multipart/form-data" data-parsley-validate=""><input name="_token" type="hidden" value="d1hs2i0fmcmttYTWGscqaZkoL0nTsidTwAPbMqid">
        <input type="hidden" name="_token" value="d1hs2i0fmcmttYTWGscqaZkoL0nTsidTwAPbMqid">
        <input id="havaalo_id" name="id" type="hidden" value="">
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
value="<?php echo date('d-m-Y'); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="from_party_id" class="control-label">From</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="from_party_id" id="from_party_id" data-index="2" >
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="to_party_id" class="control-label">To</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_to_party_filter">Reset</span>
                    <select class="form-control select2" name="to_party_id" id="to_party_id" data-index="3" >
                    </select>
                </div>
            </div>
            <div class="col-md-2">
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="gold_fine" class="control-label">Gold</label>
                    <input class="form-control positive_num_only" data-index="4" name="gold_fine" type="text" value="" id="gold_fine">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="silver_fine" class="control-label">Silver</label>
                    <input class="form-control positive_num_only" data-index="5" name="silver_fine" type="text" value="" id="silver_fine">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="amount" class="control-label">Amount</label>
                    <input class="form-control positive_num_only" data-index="6" name="amount" type="text" value="" id="amount">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="narration" class="control-label">Narration</label>
                    <textarea class="form-control " rows="2" data-index="7" name="narration" cols="50" id="narration"></textarea>
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
                    <label><a href="javascript:void(0);" class="selected_party_link">Selected From Party Balance</a></label> :
                    <div id="party_account_balance" style="font-size: 18px;"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label><a href="javascript:void(0);" class="selected_to_party_link">Selected To Party Balance</a></label> :
                    <div id="to_party_account_balance" style="font-size: 18px;"></div>
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

/* PARTY BALANCE STYLE */

.party-balance-box{

    font-size: 18px;

    font-weight: 700;

    margin-top: 5px;

    line-height: 32px;

}

.balance-label{

    color: #111;

    font-weight: 700;

}

.balance-value{

    font-size: 18px;

    font-weight: 700;

}

</style>
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
        var isSubmitting = false;
        var from_party_id = '';
        var entry_objectdata = [];
        var gold_not_in_stock = 0;
        var silver_not_in_stock = 0;

        function getUrlParameter(name){

    name = name.replace(/[\[]/, '\\[')
               .replace(/[\]]/, '\\]');

    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');

    var results = regex.exec(location.search);

    return results === null
        ? ''
        : decodeURIComponent(results[1].replace(/\+/g, ' '));

}

function formatDate(dateString){

    var date = new Date(dateString);

    var day = ("0" + date.getDate()).slice(-2);

    var month = ("0" + (date.getMonth() + 1)).slice(-2);

    var year = date.getFullYear();

    return day + '-' + month + '-' + year;

}
        $(document).ready( function(){
            $('.select2').select2();
            $('#havaalo_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});

            // REMOVED: old Enter key handler replaced by includes/erp_keyboard_nav.js
            
            $(document).bind("keydown", function(e){
                if(e.ctrlKey && e.which == 83){
                    e.preventDefault();
                    //$("#save_havaalo").submit();
                    if(!isSubmitting){
                        isSubmitting = true; // 🔥 prevent double trigger
                        $('#save_havaalo').trigger('submit');
                    }
                    return false;
                }
            });

            initAjaxSelect2(
    $("#from_party_id"),
    "api/party_select2_source.php"
);

initAjaxSelect2(
    $("#to_party_id"),
    "api/party_select2_source.php"
);
                                    
            $('#from_party_id').select2('open');

            var edit_id = getUrlParameter('id');

if(edit_id != '' && edit_id != undefined){

    $.ajax({

        url: 'api/get_havaalo_details.php?id=' + edit_id,

        type: 'GET',

        dataType: 'json',

        success: function(json){

            console.log(json);

            $('#havaalo_id').val(json.id);

            $('#havaalo_date').val(formatDate(json.entry_date));

            $('#gold_fine').val(json.gold);

            $('#silver_fine').val(json.silver);

            $('#amount').val(json.amount);

            $('#narration').val(json.narration);

            var fromOption = new Option(
                json.from_account_name,
                json.from_account,
                true,
                true
            );

            $('#from_party_id')
                .append(fromOption)
                .trigger('change');

            var toOption = new Option(
                json.to_account_name,
                json.to_account,
                true,
                true
            );

            $('#to_party_id')
                .append(toOption)
                .trigger('change');

            get_account_balance(
                json.from_account,
                'party_account_balance'
            );

            get_account_balance(
                json.to_account,
                'to_party_account_balance'
            );

        }

    });

}
            

            $(document).on('click', '.reset_party_filter', function(){
                $('#from_party_id').val(null).trigger('change');
            });

            $(document).on('click', '.reset_to_party_filter', function(){
                $('#to_party_id').val(null).trigger('change');
            });
            
            $('#from_party_id').on('select2:select', function (e) {

    var account_id = $(this).val();

    console.log(account_id);

    get_account_balance(
        account_id,
        'party_account_balance'
    );

});

$('#to_party_id').on('select2:select', function (e) {

    var account_id = $(this).val();

    console.log(account_id);

    get_account_balance(
        account_id,
        'to_party_account_balance'
    );

});
            
            $(document).on('submit', '#save_havaalo', function (e) {
                e.preventDefault();
                if (isSubmitting) {
                    return false; // stop multiple clicks
                }
                isSubmitting = true; // 🔥 MOVE HERE (VERY IMPORTANT)
                if ($.trim($("#havaalo_date").val()) == '') {
                    isSubmitting = false;
                    bootbox.alert('<span class="text-danger">Select Date !</span>');
                    return false;
                }
                if ($.trim($("#from_party_id").val()) == '') {
                    isSubmitting = false;
                    bootbox.alert('<span class="text-danger">Select From Party required !</span>');
                    return false;
                }
                if ($.trim($("#to_party_id").val()) == '') {
                    isSubmitting = false;
                    bootbox.alert('<span class="text-danger">Select To Party required !</span>');
                    return false;
                }
                if ($("#from_party_id").val() == $("#to_party_id").val()) {

    isSubmitting = false;

    bootbox.alert(
        '<span class="text-danger">From and To Party cannot be same !</span>'
    );

    return false;

}
                
                $('.module_save_btn').attr('disabled', 'disabled');
                var postData = new FormData();

postData.append(
    'id',
    $('#havaalo_id').val()
);

postData.append(
    'entry_date',
    $('#havaalo_date').val()
);

postData.append(
    'from_account',
    $('#from_party_id').val()
);

postData.append(
    'to_account',
    $('#to_party_id').val()
);

postData.append(
    'gold',
    $('#gold_fine').val()
);

postData.append(
    'silver',
    $('#silver_fine').val()
);

postData.append(
    'amount',
    $('#amount').val()
);

postData.append(
    'narration',
    $('#narration').val()
);
                $.ajax({
                    url: "api/save_havaalo.php",
                    type: "POST",
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: postData,
                    dataType: 'json',
                    //async: false,
                    success: function (response) {
                        isSubmitting = false; // reset
                        $('.module_save_btn').removeAttr('disabled', 'disabled');
                        var json = response;
                       if (json['success'] == 'Added') {

    var success_html = '';

    success_html +=
    '<div class="alert alert-success alert-dismissible" ' +
    'style="font-size:22px;font-weight:bold;">';

    success_html +=
    '<button type="button" class="close" ' +
    'data-dismiss="alert">&times;</button>';

    success_html += json['msg'];

    success_html += '</div>';

    $('#error_msg').html(success_html);

    $('#save_havaalo')[0].reset();

    $('#from_party_id').val(null).trigger('change');

    $('#to_party_id').val(null).trigger('change');

    $('#party_account_balance').html('');

    $('#to_party_account_balance').html('');

}
else if (json['success'] == 'Updated') {

    var success_html = '';

    success_html +=
    '<div class="alert alert-success alert-dismissible" ' +
    'style="font-size:22px;font-weight:bold;">';

    success_html +=
    '<button type="button" class="close" ' +
    'data-dismiss="alert">&times;</button>';

    success_html += json['msg'];

    success_html += '</div>';

    $('#error_msg').html(success_html);

} else {
                            bootbox.alert('<span class="text-danger">Something error occurred</span>');
                            return false;
                        }
                        return false;
                    },
                });
                return false;
            });

        });function get_account_balance(account_id, id_attr){

    if(account_id != '' && account_id != null){

        $.ajax({

            url: "api/get_account_balance.php?account_id=" + account_id,

            type: 'GET',

            dataType: 'json',

            data: '',

            success: function (response) {

                var json = response;

                if(typeof response == 'string'){

                    json = $.parseJSON(response);

                }

                var html_data = '';

                html_data += '<div class="party-balance-box">';

                /* ================= AMOUNT ================= */

                html_data += '<span class="balance-label">Amount :</span> ';

                if(parseFloat(json.account_data.amount) >= 0){

                    html_data +=
                    '<span class="text-primary balance-value">' +
                    json.account_data.amount +
                    ' Cr</span>';

                } else {

                    html_data +=
                    '<span class="text-danger balance-value">' +
                    json.account_data.amount +
                    ' Dr</span>';

                }

                html_data += ', ';

                /* ================= GOLD ================= */

                html_data += '<span class="balance-label">Gold :</span> ';

                if(parseFloat(json.account_data.gold_fine) >= 0){

                    html_data +=
                    '<span class="text-primary balance-value">' +
                    json.account_data.gold_fine +
                    ' Cr</span>';

                } else {

                    html_data +=
                    '<span class="text-danger balance-value">' +
                    json.account_data.gold_fine +
                    ' Dr</span>';

                }

                html_data += ', ';

                /* ================= SILVER ================= */

                html_data += '<span class="balance-label">Silver :</span> ';

                if(parseFloat(json.account_data.silver_fine) >= 0){

                    html_data +=
                    '<span class="text-primary balance-value">' +
                    json.account_data.silver_fine +
                    ' Cr</span>';

                } else {

                    html_data +=
                    '<span class="text-danger balance-value">' +
                    json.account_data.silver_fine +
                    ' Dr</span>';

                }

                html_data += '</div>';

                $('#' + id_attr).html(html_data);

            }

        });

    } else {

        $('#' + id_attr).html('');

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
