<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
    Add New Rani Transfer
    <a href="rani-transfer-list.php" class="btn btn-primary float-right">Rani Transfer List</a> 
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="custom_alert_by_js d-none"></div>
<div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <form method="POST" action="rani-transfer-save.php" accept-charset="UTF-8" id="save_rani_transfer" enctype="multipart/form-data" data-parsley-validate=""><input name="_token" type="hidden" value="LTxPxCNCx2qW2xPtINBjFS0P8bzNXqbLpsVR4mvG">
        <input type="hidden" name="_token" value="LTxPxCNCx2qW2xPtINBjFS0P8bzNXqbLpsVR4mvG">
        <input id="rani_id" name="id" type="hidden" value="">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="rani_transfer_date" class="control-label">Date</label><span class="text-danger">*</span>
                    <input type="text" name="date" id="rani_transfer_date" class="form-control" placeholder="" data-index="1" value="<?= date('d-m-Y') ?>"> 
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="party_id" class="control-label">Party</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="party_id" id="party_id" data-index="2" >
                    </select>
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
                                <th style="white-space: nowrap">Rani Date</th>
                                <th style="white-space: nowrap">Rani Gross</th>
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
                                    <input type="hidden" name="total_rani_input" id="total_rani_input" value="" >
                                <th style="white-space: nowrap" class="text-right" id="total_rani"></th>
                                <th style="white-space: nowrap"></th>
                                <th style="white-space: nowrap"></th>
                                <th style="white-space: nowrap" class="text-right" id="total_actual_fine"></th>
                                    <input type="hidden" name="total_changed_fine_input" id="total_changed_fine_input" value="" >
                                <th style="white-space: nowrap" class="text-right" id="total_changed_fine"></th>
                                <th style="white-space: nowrap"></th>
                                <th style="white-space: nowrap"></th>
                            </tr>
                            </thead>
                                                                
                                 <tbody id="rani_transfer_tbody">

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


       var edit_id =
'<?= $_GET["id"] ?? "" ?>';

if(edit_id != ''){

    load_rani_transfer_edit();

}

    $('.select2').select2();

    $('#rani_transfer_date').datepicker({
    format: 'dd-mm-yyyy',
    todayHighlight: true,
    autoclose: true
});

    initAjaxSelect2(
        $("#party_id"),
        "api/party_select2_source.php"
    );

setTimeout(function(){

    $('#party_id').select2('open');

}, 300);

    $('form').on(
        'keydown',
        'input,select',
        function (event) {

        if (event.which === 13) {

            event.preventDefault();

            if($(this).hasClass('module_save_btn')){

                $(this).click();

            }
            else{

                event.preventDefault();

                var $this =
                    $(event.target);

                var index =
                    parseFloat(
                        $this.attr(
                            'data-index'
                        )
                    );

                setTimeout(function(){

    $('[data-index="' +
    (index + 1).toString()
    + '"]').focus();

}, 50);

            }

        }

    });


    $(document).on(
        'click',
        '.reset_party_filter',
        function(){

        $('#party_id')
        .val(null)
        .trigger('change');

    });


    // TOUCH CHANGE

    $(document).on(
        'input',
        '.changed_touch',
        function(){

        var item_id =
            $(this).data('id');

        var rani_gross =
            $('#rani_gross_'+item_id)
            .val() || 0;

        var touch =
            $('#changed_touch_'+item_id)
            .val() || 0;

        fine_t =
            rani_gross * touch / 100;

        if(isNaN(fine_t)){

            fine_t = 0;

        }

        fine1 =
            round(fine_t, 3)
            .toFixed(3);

        fine2 =
            Math.floor(fine1* 100)
            / 100;

        fine =
            round(fine2, 3)
            .toFixed(3);

        $('#changed_fine_'+item_id)
        .val(fine);

        set_total();

    });


    // CHECKBOX CHANGE

    $(document).on(
        'input',
        '.check_box',
        function(){

        set_total();

    });


    $(document).on(
        'click',
        '.go_to_ledger',
        function(){

        var party_id =
            $(this).attr(
                'data-party_id'
            );

        window.location.href =
            "ledger.php?id=" + party_id;

        return false;

    });


    // SAVE FORM

    $(document).on(
    'submit',
    '#save_rani_transfer',
    function () {

    if (
        $.trim(
            $("#rani_transfer_date")
            .val()
        ) == ''
    ) {

        bootbox.alert(
            '<span class="text-danger">Select Date !</span>'
        );

        return false;

    }

    if (
        $.trim(
            $("#party_id").val()
        ) == ''
    ) {

        bootbox.alert(
            '<span class="text-danger">Select Party required !</span>'
        );

        return false;

    }

    is_one_cheked = '';

    $('.main_tr').each(function(){

        $(this)
        .find(".check_box")
        .each(function(){

            if (
                $(this)
                .prop('checked') == true
            ){

                is_one_cheked = '1';

            }

        });

    });

    if (is_one_cheked == '') {

        bootbox.alert(
            '<span class="text-danger">Tick atleast One Rani !</span>'
        );

        return false;

    }

    $('.module_save_btn')
    .attr('disabled', 'disabled');

    var postData =
        new FormData(this);

    $.ajax({

        url:
        "api/save_rani_transfer.php",

        type: "POST",

        processData: false,

        contentType: false,

        cache: false,

        data: postData,

        dataType: 'json',

        async: true,

        success: function(response){

    console.log(response);

    $('.module_save_btn')
    .removeAttr('disabled');

    if(response.success == 'Added'){

        bootbox.alert({

            title: 'Success',

            message:
            response.msg,

            callback: function(){

                window.location.reload();

            }

        });

    }
    else{

        bootbox.alert({

            title: 'Error',

            message:
            response.msg ||
            'Something error occurred'

        });

    }

},

        error: function(xhr){

            console.log(xhr.responseText);

            $('.module_save_btn')
            .removeAttr('disabled');

            bootbox.alert(
                'Save API Error'
            );

        }

    });

    return false;

});



    function load_rani_transfer_items(party_id) {

        party_id = party_id || '';

        $.ajax({

            url: 'api/get_rani_transfer_items.php?party_id=' + party_id,

            type: 'GET',

            dataType: 'json',

            success: function(response) {

                var html = '';

                $.each(response.data, function(index, row) {

                    html += '<tr class="main_tr">'
                        + '<td>'
                        + '<input'
+ ' type="checkbox"'
+ ' class="check_box"'
+ ' id="check_box_' + row.id + '"'
                        + ' data-id="' + row.id + '"'
                        + ' name="checked_ids[]"'
                        + ' value="' + row.id + '"'
                        + '>'
                        + '</td>'
                        + '<td>' + row.rani_date + '</td>'
                        + '<td>'
                        + '<input'
                        + ' type="text"'
                        + ' class="form-control rani_gross_input"'
                        + ' id="rani_gross_' + row.id + '"'
                        + ' value="' + row.rani_gross + '"'
                        + ' readonly'
                        + '>'
                        + '</td>'
                        + '<td>'
                        + '<input'
                        + ' type="text"'
                        + ' class="form-control"'
                        + ' value="' + row.actual_touch + '"'
                        + ' readonly'
                        + '>'
                        + '</td>'
                        + '<td>'
                        + '<input'
                        + ' type="text"'
                        + ' class="form-control changed_touch"'
                        + ' name="changed_touch[' + row.id + ']"'
                        + ' id="changed_touch_' + row.id + '"'
                        + ' data-id="' + row.id + '"'
                        + ' value="' + row.changed_touch + '"'
                        + '>'
                        + '</td>'
                        + '<td>'
                        + '<input'
                        + ' type="text"'
                        + ' class="form-control"'
                        + ' id="actual_fine_' + row.id + '"'
                        + ' value="' + row.actual_fine + '"'
                        + ' readonly'
                        + '>'
                        + '</td>'
                        + '<td>'
                        + '<input'
                        + ' type="text"'
                        + ' class="form-control changed_fine_input"'
                        + ' name="changed_fine[' + row.id + ']"'
                        + ' id="changed_fine_' + row.id + '"'
                        + ' value="' + row.changed_fine + '"'
                        + ' readonly'
                        + '>'
                        + '</td>'
                        + '<td>' + row.party_name + '</td>'
                        + '<td>' + row.narration + '</td>'
                        + '</tr>';

                }); // END $.each

                $('#rani_transfer_tbody').html(html);

                set_total();

            } // END success

        }); // END $.ajax

    } // END load_rani_transfer_items


    function load_rani_transfer_edit(){

    $.ajax({

        url:
        'api/get_rani_transfer_edit.php?id='
        + edit_id,

        type: 'GET',

        dataType: 'json',

        success: function(response){

            $('#rani_transfer_date')
            .val(
                response.main.entry_date
            );

            $('#narration')
            .val(
                response.main.narration
            );

            var option =
            new Option(

                response.items[0].party_name,

                response.main.party_id,

                true,

                true

            );

            $('#party_id')
            .append(option)
            .trigger('change');

            load_rani_transfer_items(
                response.main.party_id
            );

            setTimeout(function(){

                $.each(
                    response.items,
                    function(i,row){

                    $('#check_box_' + row.id)
.prop('checked', true);

                    $('#changed_touch_' + row.id)
                    .val(
                        row.changed_touch
                    );

                    $('#changed_fine_' + row.id)
                    .val(
                        row.changed_fine
                    );

                });

                set_total();

            },1000);

        }

    });

}


    load_rani_transfer_items();

    $(document).on('change', '#party_id', function(){

        var party_id = $(this).val();

        get_account_balance(
            party_id,
            'party_account_balance'
        );

        load_rani_transfer_items(
            party_id
        );

    });


            });function set_total(){
        total_rani = 0;
        total_actual_fine = 0;
        total_changed_fine = 0;
        $('.main_tr').each (function() {
            $(this).find(".check_box").each(function(){
                if ($(this).prop('checked')==true){ 
                    var item_id = $(this).data('id');
                    var rani_gross = $('#rani_gross_'+item_id).val() || 0;
                    var actual_fine = $('#actual_fine_'+item_id).val() || 0;
                    var changed_fine = $('#changed_fine_'+item_id).val() || 0;
                    total_rani = parseFloat(total_rani) + parseFloat(rani_gross);
                    total_actual_fine = parseFloat(total_actual_fine) + parseFloat(actual_fine);
                    total_changed_fine = parseFloat(total_changed_fine) + parseFloat(changed_fine);
                }
            });
        });
        total_rani = round(total_rani, 3).toFixed(3);
        $('#total_rani').html(total_rani);
        $('#total_rani_input').val(total_rani);
        total_actual_fine = round(total_actual_fine, 3).toFixed(3);
        $('#total_actual_fine').html(total_actual_fine);
        total_changed_fine = round(total_changed_fine, 3).toFixed(3);
        $('#total_changed_fine').html(total_changed_fine);
        $('#total_changed_fine_input').val(total_changed_fine);
    }
    
    function get_account_balance(account_id, id_attr){

    if(account_id != '' && account_id != null){

        $.ajax({

            url:
            "api/get_account_balance.php?account_id="
            + account_id,

            type: 'GET',

            dataType: 'json',

            success: function(response){

                var json = response;

                var html_data = `
<div style="
    font-size:22px;
    font-weight:700;
    line-height:38px;
">
`;

                html_data += '<strong>';

                if(id_attr == 'party_account_balance'){

                    html_data += 'Amount : ';

                    if(parseFloat(json.account_data.amount) >= 0){

                        html_data +=
                        '<span class="text-blue">'
                        + json.account_data.amount +
                        ' Cr</span>, ';

                    }
                    else{

                        html_data +=
                        '<span class="text-red">'
                        + json.account_data.amount +
                        ' Dr</span>, ';

                    }

                    html_data += 'Gold : ';

                    if(parseFloat(json.account_data.gold_fine) >= 0){

                        html_data +=
                        '<span class="text-blue">'
                        + json.account_data.gold_fine +
                        ' Cr</span>, ';

                    }
                    else{

                        html_data +=
                        '<span class="text-red">'
                        + json.account_data.gold_fine +
                        ' Dr</span>, ';

                    }

                    html_data += 'Silver : ';

                    if(parseFloat(json.account_data.silver_fine) >= 0){

                        html_data +=
                        '<span class="text-blue">'
                        + json.account_data.silver_fine +
                        ' Cr</span>';

                    }
                    else{

                        html_data +=
                        '<span class="text-red">'
                        + json.account_data.silver_fine +
                        ' Dr</span>';

                    }

                }

                html_data += '</strong>';

                html_data += '</div>';

                $('#' + id_attr).html(html_data);

            }

        });

    }
    else{

        $('#' + id_attr).html('');

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

   }); // END $(document).ready
        
    
    </script>

    <script>
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
        $('#ajax-loader').show();
        dt.one('preXhr', function (e, s, data) {
            data.start = 0;
            data.length = 2147483647;

            dt.one('preDraw', function (e, settings) {
                oldExportAction(self, e, dt, button, config);

                dt.one('preXhr', function (e, s, data) {
                    settings._iDisplayStart = oldStart;
                    data.start = oldStart;
                });

                setTimeout(dt.ajax.reload, 0);
                $('#ajax-loader').hide();
                return false;
            });
        });

        dt.ajax.reload();
    };

    $(document).ready(function(){
        $("#header_party_id").select2({
            placeholder: " -- Go to Ledger -- ",
            width:"100%",
            minimumInputLength: 0,
            ajax: {
                url: "api/get_party_select2.php",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
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
