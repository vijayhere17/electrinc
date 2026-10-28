
<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>
        
        
        <div class="content-wrapper ">

            
            <div class="content-header">
                <div class="container-fluid">
                    <h1>
     Add New  Transaction
    <a href="transaction-list.php" class="btn btn-primary float-right">Transaction List</a>
    <a href="rani.php" class="btn btn-primary float-right mr-1">Add New Rani</a>
</h1>
                </div>
            </div>

            
            <div class="content">
                <div class="container-fluid">
                    <div class="custom_alert_by_js d-none"></div>
<div class="card">
    <div class="card-body">
        <div id="error_msg"></div>
        <form method="POST" action="api/save_transaction.php" accept-charset="UTF-8" id="save_transaction" enctype="multipart/form-data" data-parsley-validate=""><input name="_token" type="hidden" value="QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T">
        <input type="hidden" name="_token" value="QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T">
        <input id="transaction_id" name="id" type="hidden" value="">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="transaction_date" class="control-label">Date</label><span class="text-danger">*</span>
                    <input type="text"
       name="transaction_date"
       id="transaction_date"
       class="form-control"
       placeholder=""
       data-index="1"
       value="<?php echo date('d-m-Y'); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="party_id" class="control-label">Account</label><span class="text-danger">*</span> <span class="btn float-right btn-xs reset_party_filter">Reset</span>
                    <select class="form-control select2" name="party_id" id="party_id" data-index="2" >
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="cash_bank_id" class="control-label">Against Account</label><span class="text-danger cash_bank_required"></span> <span class="btn float-right btn-xs reset_cash_bank_filter">Reset</span>
                    <select class="form-control select2" name="cash_bank_id" id="cash_bank_id" data-index="3" >
                    </select>
                </div>
            </div>
                        <div class="col-md-2">
                <div class="form-group">
                    <label for="amount" class="control-label">Amount</label>
                    <input class="form-control num_only" data-index="4" name="amount" type="text" value="" id="amount">
                </div>
            </div>
            <!-- <div class="col-md-2">
                <div class="form-group">
                    <label for="amount_round_off" class="control-label">Amount Round Off</label>
                    <input class="form-control num_only" data-index="4.1" name="amount_round_off" type="text" value="" id="amount_round_off">
                </div>
            </div> -->
        </div>
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="item_id" class="control-label">Item</label>
                    <select class="form-control select2" name="item_id" id="item_id" data-index="5">
                        <option value="1"  >Gold</option>
                        <option value="2"  >Silver</option>
                        <option value="5"  >Extra Gold</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="rate" class="control-label">Rate</label> &nbsp;&nbsp;&nbsp;
                    <input type="checkbox" name="is_cut_rate" id="is_cut_rate"  data-index="6" style="height: 15px; width: 15px;" class="" >
                    <label for="is_cut_rate" class="control-label">Cut Rate</label>
                    <input class="form-control positive_num_only" data-index="7" name="rate" type="text" value="" id="rate">
                    <b>Amount : <span id="amount_total"></span></b>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label for="qty" class="control-label">Qty</label>
                    <input class="form-control num_only" data-index="8" name="qty" type="text" value="" id="qty">
                    
                            <label for="qty_round_off" class="control-label" style="font-size:14px">Qty Round Off</label>
                            <input class="form-control num_only" data-index="8.1" name="qty_round_off" type="text" value="" id="qty_round_off">
                        
                    
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label for="mcx" class="control-label">MCX</label>
                    <input class="form-control positive_num_only" data-index="9" name="mcx" type="text" value="" id="mcx">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="narration" class="control-label">Narration</label>
                    <textarea name="narration" id="narration" class="form-control" rows="2" data-index="10" ></textarea>
                </div>
            </div>
            <div class="col-md-2">
                <label>&nbsp;</label><br>
                <input class="btn btn-primary module_save_btn" data-index="11" type="submit" value="Save">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <select class="form-control select2" name="static_filter" id="static_filter">
                        <option value="all" selected>All</option>
                        <option value="bill">Bill</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-5">
                <div class="form-group">
                    <label><a href="javascript:void(0);" class="selected_party_link">Selected Account Balance</a></label> :
                    <div id="party_account_balance" style="font-size: 18px;"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Selected Against Account </label> :
                    <div id="cash_bank_account_balance" style="font-size: 18px;"></div>
                </div>
            </div>
            
        </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="row">
        <div class="col-md-6">
            <h4 class="text-center bg-warning">Gold</h4>
            <div class="table-responsive">
                <table id="selected_party_entry_data_gold" class="table table-bordered">
                    <thead>
                        <tr>
                            <th></th>
                            <th class="text-right">Rate</th>
                            <th>Cash/Bill</th>
                            <th class="text-right">Original</th>
                            <th class="text-right" style="width: 150px;">Pending</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">MCX</th>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Narration</th>
                        </tr>
                    </thead>
                    <tbody id="selected_party_entry_rows_gold">
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <h4 class="text-center bg-gray">Silver</h4>
            <div class="table-responsive">
                <table id="selected_party_entry_data_silver" class="table table-bordered">
                    <thead>
                        <tr>
                            <th></th>
                            <th class="text-right">Rate</th>
                            <th>Cash/Bill</th>
                            <th class="text-right">Original</th>
                            <th class="text-right" style="width: 150px;">Pending</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">MCX</th>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Narration</th>
                        </tr>
                    </thead>
                    <tbody id="selected_party_entry_rows_silver">
                    </tbody>
                </table>
            </div>
            
            <div class="col-md-12 mt-3">
                <div class="table-responsive">
                    <table class="table table-bordered bg-light" style="max-width:700px;">
                        <thead>
                            <tr>
                                <th></th>
                                <th colspan="2" class="text-center">Original</th>
                                <th colspan="2" class="text-center">Pending</th>
                                <th colspan="2" class="text-center">Checked</th>
                            </tr>
                            <tr>
                                <th></th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="bg-gray">
                                <th>Silver</th>
                                <td class="text-right" id="single_original_silver_qty"></td>
                                <td class="text-right" id="single_original_silver_amount"></td>
                                <td class="text-right" id="single_pending_silver_qty"></td>
                                <td class="text-right" id="single_pending_silver_amount"></td>
                                <td class="text-right" id="single_checked_silver_qty"></td>
                                <td class="text-right" id="single_checked_silver_amount"></td>
                            </tr>
                            <tr>
                                <th>Total</th>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="text-right" id="single_checked_total_qty"></td>
                                <th class="text-right" id="single_checked_total_amount"></th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
        </div>
        
        <div class="row mt-3">
        <div class="col-md-12">
            <div class="table-responsive">
                <table id="" class="table table-bordered bg-light" style="max-width:700px;">
                    <thead>
                        <tr>
                            <th></th>
                            <th colspan="2" class="text-center">Original</th>
                            <th colspan="2" class="text-center">Pending</th>
                            <th colspan="2" class="text-center">Checked</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="">
                        <tr class="bg-warning">
                            <th>Gold</th>
                            <td class="text-right" id="original_gold_qty"></td>
                            <td class="text-right" id="original_gold_amount"></td>
                            <td class="text-right" id="pending_gold_qty"></td>
                            <td class="text-right" id="pending_gold_amount"></td>
                            <td class="text-right" id="checked_gold_qty"></td>
                            <td class="text-right" id="checked_gold_amount"></td>
                        </tr>
                        <tr class="bg-gray">
                            <th>Silver</th>
                            <td class="text-right" id="original_silver_qty"></td>
                            <td class="text-right" id="original_silver_amount"></td>
                            <td class="text-right" id="pending_silver_qty"></td>
                            <td class="text-right" id="pending_silver_amount"></td>
                            <td class="text-right" id="checked_silver_qty"></td>
                            <td class="text-right" id="checked_silver_amount"></td>
                        </tr>
                        <tr>
                            <th>Total</th>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-right" id="checked_total_qty_"></td>
                            <th class="text-right" id="checked_total_amount"></th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg" style="max-width: 1000px;">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Edit Record</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" style="max-height: 500px;overflow-y: auto;">
                <input type="hidden" id="entry_id">
                <div class="form-row">

                    <div class="form-group col-md-3">
                        <label>Date</label>
                        <input type="text" id="entry_date" class="form-control" readonly>
                    </div>

                    <div class="form-group col-md-3">
                        <label>Party</label>
                        <select class="form-control select2" id="entry_party_id"></select>
                    </div>

                    <div class="form-group col-md-2">
                        <label>Item</label>
                        <select class="form-control select2" id="entry_item_id">
                            <option value="1">Gold</option>
                            <option value="2">Silver</option>
                        </select>
                    </div>

                    <div class="form-group col-md-2">
                        <label>Rate</label>
                        <input type="text" id="entry_rate" class="form-control">
                    </div>

                    <div class="form-group col-md-2">
                        <label>Qty</label>
                        <input type="text" id="entry_qty" class="form-control">
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-3">
                        <label>MCX</label>
                        <input type="text" id="entry_mcx" class="form-control">
                    </div>

                    <div class="form-group col-md-3">
                        <label>Type</label>
                        <select class="form-control select2" id="entry_type_id">
                            <option value="1">Cash</option>
                            <option value="2">Bill</option>
                        </select>
                    </div>

                    <div class="form-group col-md-6">
                        <label>Narration</label>
                        <input type="text" id="entry_narration" class="form-control">
                    </div>

                </div>
                
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="updateRecord" onclick="editEntry()">Update</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
                </div>
            </div>

        </div>

        
        
        
        
    </div>

    
<?php include 'includes/scripts_start.php'; ?>
    <script>
        var party_id = '';
        var entry_objectdata = [];
        var gold_current_stock = 999999;
var silver_current_stock = 999999;
        var gold_not_in_stock = 0;
        var silver_not_in_stock = 0;

        var checked_gold_qty = 0;
        var checked_gold_amount = 0;
        var checked_silver_qty = 0;
        var checked_silver_amount = 0;
        var checked_total_qty = 0;
        var checked_total_amount = 0;
        var is_submitting = false;
        var is_modal_saving = false;
        

        $(document).ready( function(){
           
            initStaticSelect2($('#item_id'));
            initStaticSelect2($('#static_filter'));

            $('#transaction_date').datepicker({
                format: 'dd-mm-yyyy',
                todayBtn: "linked",
                autoclose: true
            });

            // REMOVED: old Enter key handler replaced by includes/erp_keyboard_nav.js

            $(document).bind("keydown", function(e){
                if(e.ctrlKey && e.which == 83){
                    e.preventDefault();
                    e.stopPropagation(); // ✅ IMPORTANT
                    e.stopImmediatePropagation(); // ✅ VERY IMPORTANT
                    if(is_submitting) {
                        return false;
                    }
                    //$("#save_transaction").submit();
                    if ($('#editModal').hasClass('show')) {
                        is_modal_saving = true;
                        editEntry(); // Call modal save
                    } else {
                        $("#save_transaction").submit(); // Normal save
                    }
                    return false;
                }
            });

            $.when(
                initAjaxSelect2($("#party_id"), "api/party_select2_source.php"),
                initAjaxSelect2($("#cash_bank_id"), "api/cash_bank_select2_source.php")
            ).always(function () {
                get_account_balance(1, 'cash_bank_account_balance');

                setTimeout(function () {
                    $('#party_id').select2('open');
                    focusOpenSelect2Search($('#party_id'));
                }, 100);
            });
            
            $(document).on('input', '#amount', function(){
                $('.cash_bank_required').html('');
                if ($.trim($('#amount').val()) != '') {
                    $('.cash_bank_required').html('*');
                }
            });

                        $(document).on('input', '#rate', function(){
                var item_id = $('#item_id').val();
                if(item_id == '1' || item_id == '5'){
                    if ($.trim($('#rate').val()) != '') {
                        var value = $('#rate').val();
                        if (value.length == 6){
                            var r_val = $(this).val();
                            var substr = r_val.substr(0, 5);
                            var substr_last = r_val.substr(5);
                            var lastChar = r_val.substr(r_val.length - 1);
                            if(lastChar == '.'){ } else {
                                $(this).val(substr+'.'+substr_last);
                            }
                        }
                    }
                }
                var rate = $('#rate').val() || 0;
                var qty = $('#qty').val() || 0;
                var amount_total = rate * qty;
                total_amount = amount_total.toFixed(2);
                $('#amount_total').html(total_amount);
            });

            $(document).on('input', '#qty', function(){
                var rate = $('#rate').val() || 0;
                var qty = $('#qty').val() || 0;
                var amount_total = rate * qty;
                total_amount = amount_total.toFixed(2);
                $('#amount_total').html(total_amount);
            });


            $(document).on('change', '#party_id', function(){
                party_id = $('#party_id').val();
                var from_entry_ids = '';
                                var transaction_items = {};
                                get_account_balance(party_id, 'party_account_balance');
                $('.selected_party_link').attr('href', "javascript:void(0);");
                if(party_id != null){
                    var transaction_type = 'create';
                    $('.selected_party_link').attr('href', "party-ledger.php?id="+ party_id);
                    $.ajax({
                        url: "api/get_entries.php?party_id=" + party_id + "&from_entry_ids=" + from_entry_ids + "&transaction_type=" + transaction_type,
                        type: "GET",
                        processData: false,
                        contentType: false,
                        cache: false,
                        data: {},
                        async: false,
                        success: function (response) {
                            var json = response;

if(typeof response !== 'object'){
    json = $.parseJSON(response);
}
                            console.log('response ',json);
                            entry_objectdata = json.entry_data;
                            console.log('entry_objectdata ',entry_objectdata);
                            var selected_party_entry_rows_gold = '';
                            var selected_party_entry_rows_silver = '';
                            var selected_party_entry_rows = '';
                            var row_html = '';
                            var original_gold_qty = 0;
                            var original_gold_amount = 0;
                            var original_silver_qty = 0;
                            var original_silver_amount = 0;
                            var pending_gold_qty = 0;
                            var pending_gold_amount = 0;
                            var pending_silver_qty = 0;
                            var pending_silver_amount = 0;
                            $.each(entry_objectdata, function (index, value) {
                                var value_id = value.id;
                                var entry_trans_qty = value.pending_qty;

if(parseFloat(entry_trans_qty) == 0){
    entry_trans_qty = value.qty;
}
                                var entry_checked = '';
                                if(transaction_items[value_id]){
                                    value.pending_qty = parseFloat(value.pending_qty) + parseFloat(transaction_items[value_id].qty);
                                    entry_trans_qty = transaction_items[value_id].qty;
                                    entry_checked = 'Checked';
                                }
                                var is_cut_rate_checked = '';
                                if(transaction_items[value_id]){
                                    if(transaction_items[value_id].is_cut_rate == 1){
                                        is_cut_rate_checked = 'Checked';
                                    }
                                }
                                var original_amount = parseFloat(value.rate) * parseFloat(value.qty);
                                var pending_amount = parseFloat(value.rate) * parseFloat(value.pending_qty);
                                var entry_trans_amount = parseFloat(value.rate) * parseFloat(entry_trans_qty);
                                if ( parseFloat(value.qty) < '0' ){
                                    original_amount = Math.abs(original_amount);
                                    pending_amount = Math.abs(pending_amount);
                                    entry_trans_amount = Math.abs(entry_trans_amount);
                                } else {
                                    original_amount = parseFloat(0) - parseFloat(original_amount);
                                    pending_amount = parseFloat(0) - parseFloat(pending_amount);
                                    entry_trans_amount = parseFloat(0) - parseFloat(entry_trans_amount);
                                }
                                var td_bg_style = '';
                                if(value.item_id == '1'){
                                    if ( parseFloat(value.qty) < '0' ){
                                        td_bg_style = 'style="background-color: #F2DEDE;"'; // Light Red
                                    } else {
                                        td_bg_style = 'style="background-color: #D8EAF0;"'; // Light Blue
                                    }
                                    original_gold_qty = parseFloat(original_gold_qty) + parseFloat(value.qty);
                                    original_gold_amount = parseFloat(original_gold_amount) + parseFloat(original_amount);
                                    pending_gold_qty = parseFloat(pending_gold_qty) + parseFloat(value.pending_qty);
                                    pending_gold_amount = parseFloat(pending_gold_amount) + parseFloat(pending_amount);
                                } else {
                                    if ( parseFloat(value.qty) < '0' ){
                                        td_bg_style = 'style="background-color: #F2DEDE;"'; // Light Red
                                    } else {
                                        td_bg_style = 'style="background-color: #D8EAF0;"'; // Light Blue
                                    }
                                    original_silver_qty = parseFloat(original_silver_qty) + parseFloat(value.qty);
                                    original_silver_amount = parseFloat(original_silver_amount) + parseFloat(original_amount);
                                    pending_silver_qty = parseFloat(pending_silver_qty) + parseFloat(value.pending_qty);
                                    pending_silver_amount = parseFloat(pending_silver_amount) + parseFloat(pending_amount);
                                }
                                entry_objectdata[index].entry_trans_qty = parseFloat(entry_trans_qty);
                                // tds at party change
                                    var amount = parseFloat(value.rate) * parseFloat(entry_trans_qty);
                                    let tds_percent = parseFloat(entry_objectdata[index].tds ?? 0);
                                    //let tds_rate = tds_percent / 100;
                                    if(entry_objectdata[index].tds >= 0){
                                        tds_amount = Math.abs(parseFloat(amount)) / 103;
                                        tds_amount = tds_amount * tds_percent;
                                        if(parseFloat(entry_trans_qty) < 0){
                                            tds_amount = -1 * tds_amount;
                                        }else{
                                            tds_amount = tds_amount;
                                        }
                                    } 
                                // tds 
                                var row_html = '';
                                var row_entry_type = value.entry_type ? value.entry_type.toLowerCase() : '';
                                row_html += '<tr class="entry-row" data-entry-type="' + row_entry_type + '">';
                                row_html += '<td ' + td_bg_style + ' > \
                                <button class="btn btn-xs btn-info edit_entry" \
                                data-entry_id="'+ value.id +'" \
                                data-entry_qty="'+ value.qty +'" \
                                data-entry_rate="'+ value.rate +'" \
                                data-entry_date="'+ value.entry_date +'" \
                                data-item_id="'+ value.item_id +'" \
                                data-type_id="'+ value.type_id +'" \
                                data-narration="'+ value.narration +'" \
                                data-mcx="'+ value.mcx +'" \
                                data-party_id="'+ party_id +'" \
                                >\
                                <i class="fa fa-edit"></i></button>&nbsp;&nbsp;<input type="checkbox" name= "entry_check[]" data-selected_index="' + index + '" class="selected_index" ' + entry_checked + ' ></td>';
                                row_html += '<td ' + td_bg_style + '  class="text-right">';
                                row_html += value.rate;
                                row_html += '<br><small><label><input type="checkbox" name="is_cut_rate[]" id="is_cut_rate_' + index + '" ' + is_cut_rate_checked + ' > Cut Rate</label></small>';
                                row_html += '</td>';
                                row_html += '<td ' + td_bg_style + ' >' + value.entry_type + '</td>';
                                var value_qty = value.qty;
                                var value_pending_qty = value.pending_qty;

if(parseFloat(value_pending_qty) == 0){
    value_pending_qty = value.qty;
}
                                row_html += '<td ' + td_bg_style + '  class="text-right">' + value_qty.toFixed(4) + '</td>';
                                row_html += '<td ' + td_bg_style + '  class="text-right"><input type="text" style="width: 60px;" name="entry_trans_qty[]" id="entry_trans_qty_' + index + '" value="' + entry_trans_qty.toFixed(4) + '" class="entry_trans_qty num_only" data-selected_index="' + index + '" data-rate_' + index + '="' + value.rate + '" data-pending_qty_' + index + '="' + value_pending_qty.toFixed(4) + '" > <br>' + value_pending_qty.toFixed(4) + '</td>';
                                var tds_html = '';
                                if(value.type_id == 2) {
                                   value.tds = value.tds ?? 0;
                                    tds_html = '<br>\
                                        <span id="tds_' + index + '" style="font-size: 13px; color: #555;">TDS : ' + value.tds + '%</span>\
                                        <input type="hidden" name="tds_amount[]" id="tds_amount_' + index + '" class="tds_amount" style="width: 60px;" value="' + (tds_amount ? tds_amount.toFixed(0) : 0) + '">';
                                }
                                row_html += '<td ' + td_bg_style + '  class="text-right"><span id="checked_amount_' + index + '">' + entry_trans_amount.toFixed(0) + '</span>' + tds_html + '</td>';
                                row_html += '<td ' + td_bg_style + '  class="text-right">' + value.mcx + '</td>';
                                row_html += '<td ' + td_bg_style + ' >' + value.entry_date_new + '</td>';
                                row_html += '<td ' + td_bg_style + ' >' + value.gold_silver + '</td>';
                                var value_narration = (value.narration == null) ? '' : value.narration;
                                row_html += '<td ' + td_bg_style + ' >' + value_narration + '</td>';
                                row_html += '</tr>';

                                if(value.item_id == '1'){
                                    selected_party_entry_rows_gold += row_html;
                                } else {
                                    selected_party_entry_rows_silver += row_html;
                                }
                            });
                            $('#selected_party_entry_rows_gold').html(selected_party_entry_rows_gold);
                            $('#selected_party_entry_rows_silver').html(selected_party_entry_rows_silver);
                            $('#original_gold_qty').html(original_gold_qty.toFixed(4));
                            $('#single_original_silver_qty').html(original_silver_qty.toFixed(4));
                            $('#original_gold_amount').html(original_gold_amount.toFixed(0));
                            $('#single_original_silver_amount').html(original_silver_amount.toFixed(0));
                            $('#original_silver_qty').html(original_silver_qty.toFixed(4));
                            $('#original_silver_amount').html(original_silver_amount.toFixed(0));
                            $('#pending_gold_qty').html(pending_gold_qty.toFixed(4));
                            $('#pending_gold_amount').html(pending_gold_amount.toFixed(0));
                            $('#pending_silver_qty').html(pending_silver_qty.toFixed(4));
                            $('#single_pending_silver_qty').html(pending_silver_qty.toFixed(4));
                            $('#pending_silver_amount').html(pending_silver_amount.toFixed(0));
                             $('#single_pending_silver_amount').html(pending_silver_amount.toFixed(0));

                            var current_filter = $('#static_filter').val() || 'all';
                            if(current_filter.toLowerCase() !== 'all') {
                                $('#static_filter').trigger('change');
                            }
                            
                                                        return false;
                        },
                    });
                }
            });

            $(document).on('change', '#static_filter', function(){
                var filter_val = $(this).val().toLowerCase();
                
                if(filter_val === 'all'){
                    $('.entry-row').show();
                } else {
                    $('.entry-row').hide();
                    $('.entry-row[data-entry-type="' + filter_val + '"]').show();
                    $('.entry-row:hidden .selected_index').prop('checked', false);
                }
                
                recalculate_totals_by_filter(filter_val);
                checked_average_value();
            });

            $(document).on('change', '#cash_bank_id', function(){
                cash_bank_id = $('#cash_bank_id').val();
                get_account_balance(cash_bank_id, 'cash_bank_account_balance');
            });

           $(document).on('click', '.selected_index', function () {

    /*
    |--------------------------------------------------------------------------
    | AUTO SELECT ITEM
    |--------------------------------------------------------------------------
    */

    var row = $(this).closest('tr');

    var item_name =
        row.find('td:eq(8)').text().trim();

    if(item_name == 'Gold'){

        $('#item_id').val(1).trigger('change');

    } else {

        $('#item_id').val(2).trigger('change');
    }

    /*
    |--------------------------------------------------------------------------
    | RECALCULATE
    |--------------------------------------------------------------------------
    */

    checked_average_value();
});

            $(document).on('keyup', '.entry_trans_qty', function () {
                var selected_index = $(this).data('selected_index');
                var entry_trans_qty = $('#entry_trans_qty_' + selected_index).val() || 0;
                var pending_qty = $(this).data('pending_qty_' + selected_index);
                if ( parseFloat(entry_trans_qty) < '0' ){
                    if ( parseFloat(entry_trans_qty) < parseFloat(pending_qty) ){
                        bootbox.alert('<span class="text-danger">Not Allow Less Than Pending Qty! Zero(0) to Allow Up to ' + pending_qty + ' </span>');
                        $('#entry_trans_qty_' + selected_index).val(pending_qty);
                        return false;
                    }
                } else {
                    if ( parseFloat(entry_trans_qty) > parseFloat(pending_qty) ){
                        bootbox.alert('<span class="text-danger">Not Allow Greater Than Pending Qty! Zero(0) to Allow Up to ' + pending_qty + ' </span>');
                        $('#entry_trans_qty_' + selected_index).val(pending_qty);
                        return false;
                    }
                }

                entry_objectdata[selected_index].entry_trans_qty = parseFloat(entry_trans_qty);
                var rate = $(this).data('rate_' + selected_index);
                var amount = parseFloat(rate) * parseFloat(entry_trans_qty);
                if ( parseFloat(entry_trans_qty) < '0' ){
                    amount = Math.abs(amount);
                } else {
                    amount = parseFloat(0) - parseFloat(amount);
                }
                $('#checked_amount_' + selected_index).html(amount.toFixed(0));
                entry_objectdata[selected_index].checked_amount = parseFloat(amount.toFixed(0));
                // Calculate TDS
                if(entry_objectdata[selected_index].tds >= 0){
                    tds_amount = Math.abs(parseFloat(amount)) / 103;
                    tds_amount = tds_amount * parseFloat(entry_objectdata[selected_index].tds);
                    console.log('aaaaa ',tds_amount.toFixed(0));
                    if ( parseFloat(entry_trans_qty) < '0' ){
                        tds_amount = -1 * tds_amount;
                    }else{
                        tds_amount = tds_amount;
                    }
                    $('#tds_amount_'+ selected_index).val(tds_amount.toFixed(0));
                }                
                checked_average_value();
            });

            $(document).on('click', '.reset_party_filter', function(){
                resetAjaxSelect2($('#party_id'));
                $('#selected_party_entry_rows_gold').html('');
                $('#selected_party_entry_rows_silver').html('');
                $('#party_account_balance').html('');
            });

            $(document).on('click', '.reset_cash_bank_filter', function(){
                resetAjaxSelect2($('#cash_bank_id'));
                $('#cash_bank_account_balance').html('');
            });

            $(document).on('submit', '#save_transaction', function (e) {
                // ✅ BLOCK if modal is open
                if ($('#editModal').hasClass('show')) {
                    e.preventDefault();
                    return false;
                }
                if (is_modal_saving) {
                    is_modal_saving = false;
                    return false;
                }
                e.preventDefault();
                if (is_submitting) {
                    return false;
                }
                
                if ($.trim($("#transaction_date").val()) == '') {
                    bootbox.alert('<span class="text-danger">Select Date !</span>');
                    return false;
                }
                if ($.trim($("#party_id").val()) == '') {
                    bootbox.alert('<span class="text-danger">Select Account required !</span>');
                    return false;
                }
                var amount = $("#amount").val();
                var cash_bank_id = $("#cash_bank_id").val();
                if (amount != '' && amount != null) {
                    if ($.trim($("#cash_bank_id").val()) == '') {
                        bootbox.alert('<span class="text-danger">Select Against Account required !</span>');
                        return false;
                    }
                }

                var trans_gold_qty = checked_gold_qty;
                var trans_silver_qty = checked_silver_qty;
                if($("#is_cut_rate").prop('checked') == true){
                    var rate = $("#rate").val();
                    if (rate == '' || rate == null) {
                        bootbox.alert('<span class="text-danger">Enter Rate!</span>');
                        return false;
                    }
                } else {
                    var item_id = $("#item_id").val();
                    var qty = $('#qty').val() || 0;
                    if(item_id == '1'){
                        if ( parseFloat(qty) < 0 ){
                            trans_gold_qty = parseFloat(trans_gold_qty) - parseFloat(qty);
                        }
                    } else {
                        if ( parseFloat(qty) < 0 ){
                            trans_silver_qty = parseFloat(trans_silver_qty) - parseFloat(qty);
                        }
                    }
                }

                // In Stock Validation
                gold_not_in_stock = 0;
                var checked_gold_qty_diff = parseFloat(gold_current_stock) - parseFloat(trans_gold_qty);
                if(checked_gold_qty_diff < 0){
                    gold_not_in_stock = 1;
                }
                silver_not_in_stock = 0;
                var checked_silver_qty_diff = parseFloat(silver_current_stock) - parseFloat(trans_silver_qty);
                if(checked_silver_qty_diff < 0){
                    silver_not_in_stock = 1;
                }

                if (gold_not_in_stock == '1') {
                    bootbox.alert('<span class="text-danger">Not Allow Greater than Gold Current Stock</span>');
                    return false;
                }
                if (silver_not_in_stock == '1') {
                    bootbox.alert('<span class="text-danger">Not Allow Greater than Silver Current Stock</span>');
                    return false;
                }

                var amount_p = $.trim($("#amount").val()) || 0;
                var qty_p = $.trim($("#qty").val()) || 0;
                is_one_lineitem = 0;
                $.each($(".selected_index:checked"), function(){
                    is_one_lineitem = 1;
                });
                if((amount_p == 0) && (qty_p == 0) && (is_one_lineitem == 0)){
                    bootbox.alert('<span class="text-danger">Please Enter Amount or Qty or Select atleast one Lineitem</span>');
                    return false;
                }

                is_submitting = true;
                $('.module_save_btn').attr('disabled', 'disabled');
                var postData = new FormData(this);

                var selected_index_lineitems = [];
                $.each($(".selected_index:checked"), function(){
                    var selected_index = $(this).data('selected_index');
                     // ✅ Get TDS amount from input
                    var tds_amount = $('#tds_amount_' + selected_index).val() || 0;
                    entry_objectdata[selected_index].tds_amount = parseFloat(tds_amount);
                    // cut rate input
                    entry_objectdata[selected_index].is_cut_rate = 0;
                    if($('#is_cut_rate_' + selected_index).prop('checked') == true){
                        entry_objectdata[selected_index].is_cut_rate = 1;
                    }
                    selected_index_lineitems.push(entry_objectdata[selected_index]);
                });
                var selected_index_lineitems_stringify = JSON.stringify(selected_index_lineitems);
                postData.append('line_items_data', selected_index_lineitems_stringify);
                
                $.ajax({
                    url: "api/save_transaction.php",
                    type: "POST",
                    processData: false,
                    contentType: false,
                    cache: false,
                    data: postData,
                    datatype: 'json',
                    async: true,
                    success: function (response) {

    var json = response;

    if (typeof response !== 'object') {
        json = $.parseJSON(response);
    }

    console.log(json);

    if (json['success'] == 'Added') {

    is_submitting = false;

    $('.module_save_btn')
        .removeAttr('disabled')
        .prop('value', 'Saved');

    var success_html = '';

    success_html +=
    '<div class="alert alert-success alert-dismissible" ' +
    'style="font-size:18px;font-weight:bold;">';

    success_html +=
    '<button type="button" class="close" data-dismiss="alert">&times;</button>';

    success_html +=
    'Transaction Successfully Added. :: ';

    success_html +=
    'Party : ' + json.party_name;

    success_html +=
    ' :: Balance : ';

    success_html +=
    'Gold : <span class="badge badge-primary">' +
    json.gold_balance +
    '</span>, ';

    success_html +=
    'Silver : <span class="badge badge-primary">' +
    json.silver_balance +
    '</span>, ';

    success_html +=
    'Amount : <span class="badge badge-danger">' +
    json.amount_balance +
    '</span>';

    success_html += '</div>';

    $('.custom_alert_by_js')
        .removeClass('d-none')
        .html(success_html);

    setTimeout(function () {

        window.location.reload();

    }, 2500);

} else if (json['success'] == 'Updated') {

    is_submitting = false;

    $('.module_save_btn')
        .removeAttr('disabled')
        .prop('value', 'Updated');

   var success_html = '';

success_html +=
'<div class="alert alert-success alert-dismissible" ' +
'style="font-size:18px;font-weight:bold;">';

success_html +=
'<button type="button" class="close" data-dismiss="alert">&times;</button>';

success_html +=
'Transaction Successfully Updated';

success_html += '</div>';

$('.custom_alert_by_js')
    .removeClass('d-none')
    .html(success_html);

/*
|--------------------------------------------------------------------------
| LIVE REFRESH
|--------------------------------------------------------------------------
*/

setTimeout(function(){

    /*
    |--------------------------------------------------------------------------
    | RELOAD PARTY ENTRIES
    |--------------------------------------------------------------------------
    */

    $('#party_id').trigger('change');

    /*
    |--------------------------------------------------------------------------
    | REFRESH BALANCES
    |--------------------------------------------------------------------------
    */

    get_account_balance(
        $('#party_id').val(),
        'party_account_balance'
    );

    get_account_balance(
        $('#cash_bank_id').val(),
        'cash_bank_account_balance'
    );

    

    $('#rate').val('');
    $('#qty').val('');
    $('#amount').val('');
    $('#mcx').val('');
    $('#narration').val('');

    /*
    |--------------------------------------------------------------------------
    | RESET CHECKBOXES
    |--------------------------------------------------------------------------
    */

    $('.selected_index').prop('checked', false);

    /*
    |--------------------------------------------------------------------------
    | RESET TOTALS
    |--------------------------------------------------------------------------
    */

    checked_average_value();

    /*
    |--------------------------------------------------------------------------
    | RESET SAVE BUTTON
    |--------------------------------------------------------------------------
    */

    $('.module_save_btn')
        .removeAttr('disabled')
        .prop('value', 'Save');

}, 1000);

} else {

    is_submitting = false;

    $('.module_save_btn')
        .removeAttr('disabled')
        .prop('value', 'Save');

    var errorMsg = json['msg'] || 'Something error occurred';

    bootbox.alert(
        '<span class="text-danger">' + errorMsg + '</span>'
    );

    return false;
}

return false;

},

error: function() {

    is_submitting = false;

    $('.module_save_btn')
        .removeAttr('disabled')
        .prop('value', 'Save');

    bootbox.alert(
        '<span class="text-danger">Server error occurred</span>'
    );

    return false;
}

});

return false;

});

});

        function recalculate_totals_by_filter(filter_val) {
            var original_gold_qty = 0;
            var original_gold_amount = 0;
            var original_silver_qty = 0;
            var original_silver_amount = 0;
            var pending_gold_qty = 0;
            var pending_gold_amount = 0;
            var pending_silver_qty = 0;
            var pending_silver_amount = 0;

            $.each(entry_objectdata, function (index, value) {
                var entry_type = value.entry_type ? value.entry_type.toLowerCase() : '';
                if (filter_val === 'all' || entry_type === filter_val) {
                    var original_amount = parseFloat(value.rate) * parseFloat(value.qty);
                    var pending_amount = parseFloat(value.rate) * parseFloat(value.pending_qty);
                    
                    if (parseFloat(value.qty) < 0) {
                        original_amount = Math.abs(original_amount);
                        pending_amount = Math.abs(pending_amount);
                    } else {
                        original_amount = parseFloat(0) - parseFloat(original_amount);
                        pending_amount = parseFloat(0) - parseFloat(pending_amount);
                    }

                    if(value.item_id == '1') {
                        original_gold_qty += parseFloat(value.qty);
                        original_gold_amount += original_amount;
                        pending_gold_qty += parseFloat(value.pending_qty);
                        pending_gold_amount += pending_amount;
                    } else {
                        original_silver_qty += parseFloat(value.qty);
                        original_silver_amount += original_amount;
                        pending_silver_qty += parseFloat(value.pending_qty);
                        pending_silver_amount += pending_amount;
                    }
                }
            });

            $('#original_gold_qty').html(original_gold_qty.toFixed(4));
            $('#single_original_silver_qty').html(original_silver_qty.toFixed(4));
            $('#original_gold_amount').html(original_gold_amount);
            $('#single_original_silver_amount').html(original_silver_amount);
            $('#original_silver_qty').html(original_silver_qty.toFixed(4));
            $('#original_silver_amount').html(original_silver_amount);
            $('#pending_gold_qty').html(pending_gold_qty.toFixed(4));
            $('#pending_gold_amount').html(pending_gold_amount);
            $('#pending_silver_qty').html(pending_silver_qty.toFixed(4));
            $('#single_pending_silver_qty').html(pending_silver_qty.toFixed(4));
            $('#pending_silver_amount').html(pending_silver_amount);
            $('#single_pending_silver_amount').html(pending_silver_amount);
        }

        function checked_average_value() {
            checked_gold_qty = 0;
            checked_gold_amount = 0;
            checked_silver_qty = 0;
            checked_silver_amount = 0;
            checked_total_qty = 0;
            checked_total_amount = 0;

            $.each($(".selected_index:checked"), function(){
                var selected_index = $(this).data('selected_index');
                
                // Ensure we don't calculate rows that are hidden
                if ($(this).closest('tr').is(':hidden')) {
                    return true; // continue next iteration in $.each
                }
                
                var entry_trans_qty = $('#entry_trans_qty_' + selected_index).val() || 0;
                entry_objectdata[selected_index].entry_trans_qty = parseFloat(entry_trans_qty);
                checked_total_qty = checked_total_qty + parseFloat(entry_trans_qty);

                var checked_amount = $('#checked_amount_' + selected_index).html() || 0;
                entry_objectdata[selected_index].checked_amount = parseFloat(checked_amount);
                checked_total_amount = checked_total_amount + parseFloat(checked_amount);

                if(entry_objectdata[selected_index].item_id == '1'){
                    checked_gold_qty = checked_gold_qty + parseFloat(entry_trans_qty);
                    checked_gold_amount = checked_gold_amount + parseFloat(checked_amount);
                } else {
                    checked_silver_qty = checked_silver_qty + parseFloat(entry_trans_qty);
                    checked_silver_amount = checked_silver_amount + parseFloat(checked_amount);
                }
            });
            checked_gold_qty = checked_gold_qty.toFixed(4);
            checked_gold_amount = checked_gold_amount.toFixed(0);
            $('#checked_gold_qty').html(checked_gold_qty);
            $('#checked_gold_amount').html(checked_gold_amount);
            checked_silver_qty = checked_silver_qty.toFixed(4);
            checked_silver_amount = checked_silver_amount.toFixed(0);
            $('#checked_silver_qty').html(checked_silver_qty);
            $('#single_checked_silver_qty').html(checked_silver_qty);
            $('#single_checked_total_qty').html(checked_silver_qty);
            $('#checked_silver_amount').html(checked_silver_amount);
            $('#single_checked_silver_amount').html(checked_silver_amount);
            $('#single_checked_total_amount').html(checked_silver_amount);
            $('#checked_total_qty').html(checked_total_qty.toFixed(0));
            $('#checked_total_amount').html(checked_total_amount.toFixed(0));

            // In Stock Validation
            gold_not_in_stock = 0;
            var checked_gold_qty_diff = parseFloat(gold_current_stock) - parseFloat(checked_gold_qty);
            if(checked_gold_qty_diff < 0){
                gold_not_in_stock = 1;
            }
            silver_not_in_stock = 0;
            var checked_silver_qty_diff = parseFloat(silver_current_stock) - parseFloat(checked_silver_qty);
            if(checked_silver_qty_diff < 0){
                silver_not_in_stock = 1;
            }
        }

       function get_account_balance(account_id, id_attr){

    if(account_id != '' && account_id != null){

        $.ajax({

            url: "api/get_account_balance.php?account_id=" + account_id + "&get_amount_from=transaction",

            type: 'GET',

            data: '',

            success: function (response) {

                var json = response;

                if(typeof response !== 'object'){
                    json = $.parseJSON(response);
                }

                console.log('balance response => ', json);

                var amount =
                    parseFloat(json.account_data.amount || 0);

                var gold =
                    parseFloat(json.account_data.gold_fine || 0);

                var silver =
                    parseFloat(json.account_data.silver_fine || 0);
                    
                    function formatBalance(value){

    value = parseFloat(value || 0);

    if(Number.isInteger(value)){

        return value;

    }

    return value.toFixed(4).replace(/\.?0+$/, '');
}

                var html_data = '<span>';
                html_data += '<strong>';

                /*
                |--------------------------------------------------------------------------
                | PARTY ACCOUNT BALANCE
                |--------------------------------------------------------------------------
                */

                if(id_attr == 'party_account_balance'){

                    /*
                    |--------------------------------------------------------------------------
                    | Amount
                    |--------------------------------------------------------------------------
                    */

                    html_data += 'Amount : ';

                    if(amount >= 0){

                        html_data +=
                        '<span class="text-blue">' +
                        formatBalance(amount) +
                        ' Cr</span>, ';

                    } else {

                        html_data +=
                        '<span class="text-red">' +
                        Math.abs(amount).toFixed(2) +
                        ' Dr</span>, ';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Gold
                    |--------------------------------------------------------------------------
                    */

                    html_data += 'Gold : ';

                    if(gold >= 0){

                        html_data +=
                        '<span class="text-blue">' +
                        formatBalance(gold) +
                        ' Cr</span>, ';

                    } else {

                        html_data +=
                        '<span class="text-red">' +
                        formatBalance(Math.abs(gold)) +
                        ' Dr</span>, ';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Silver
                    |--------------------------------------------------------------------------
                    */

                    html_data += 'Silver : ';

                    if(silver >= 0){

                        html_data +=
                        '<span class="text-blue">' +
                        formatBalance(silver) +
                        ' Cr</span>';

                    } else {

                        html_data +=
                        '<span class="text-red">' +
                        formatBalance(Math.abs(silver)) +
                        ' Dr</span>';
                    }

                }

                /*
                |--------------------------------------------------------------------------
                | CASH/BANK BALANCE
                |--------------------------------------------------------------------------
                */

                else if(id_attr == 'cash_bank_account_balance'){

                    html_data += 'Amount : ';

                    if(amount >= 0){

                        html_data +=
                        '<span class="text-blue">' +
                        formatBalance(amount) +
                        ' Cr</span>';

                    } else {

                        html_data +=
                        '<span class="text-red">' +
                        formatBalance(Math.abs(amount)) +
                        ' Dr</span>';
                    }
                }

                html_data += '</strong>';
                html_data += '</span>';

                $('#' + id_attr).html(html_data);
            },

            error: function(){

                $('#' + id_attr).html(
                    '<span class="text-danger">Balance Load Failed</span>'
                );
            }

        });

    } else {

        $('#' + id_attr).html('');
    }
}

        //ami code for edit entry 16-03-2026
         $(document).on('click', '.edit_entry', function(){
            // var entry_id = $(this).attr('data-entry_id');
            // window.location.href = "index.php/admin/entry/" + entry_id;
            
            //using popup
           var entry_id = $(this).data('entry_id');
           var party_id = $(this).data('party_id');
            var entry_qty = $(this).data('entry_qty');
            var entry_rate = $(this).data('entry_rate');
            var entry_date = $(this).data('entry_date');
            var item_id = $(this).data('item_id');
            var type_id = $(this).data('type_id');
            var narration = $(this).data('narration');
            var entry_mcx = $(this).data('mcx');

            $('#entry_id').val(entry_id);
            $('#entry_qty').val(entry_qty);
            $('#entry_rate').val(entry_rate);
            $('#entry_date').val(entry_date);
            $('#entry_qty').val(entry_qty);
            $('#entry_item_id').val(item_id).trigger('change');
            $('#entry_type_id').val(type_id).trigger('change');
            // initialize select2
            initAjaxSelect2($("#entry_party_id"), "api/get_accounts.php");

            // set selected value
            if (party_id) {
                // var option = new Option("Loading...", party_id, true, true);
                // $('#entry_party_id').append(option).trigger('change');
                $.ajax({
                    url: "api/get_account_by_id.php",
                    type: "post",
                    data: {'party_id': party_id ,_token: "QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T"},
                    
                    success: function (response) {
                        var option = new Option(response.account_name, response.id, true, true);
                        $('#entry_party_id').append(option).trigger('change');
                    }
                });
            }
            $('#entry_narration').val(narration);
            $('#entry_mcx').val(entry_mcx);

            $('#editModal').modal('show');

        });
        function editEntry(){
            $.ajax({
                url: "api/save_soda_entry.php",
                type: "POST",
                
                data: {
                    id: $('#entry_id').val(),
                    entry_date: $('#entry_date').val(),
                    party_id: $('#entry_party_id').val(),
                    item_id: $('#entry_item_id').val(),
                    rate: $('#entry_rate').val(),
                    qty: $('#entry_qty').val(),
                    mcx: $('#entry_mcx').val(),
                    type_id: $('#entry_type_id').val(),
                    narration: $('#entry_narration').val(),
                    _token: "QDv3mJqGOEGGh8taBN9uW7ro4ttFh583ELnA384T"
                },
                datatype: 'json',
                success: function (response) {
                    var json = $.parseJSON(response);
                    console.log(json);
                    if(json){
                        $('#editModal').modal('hide');
                        $('#party_id').val(json.party_id).trigger('change');
                        //window.location.reload();
                    }
                    return false;
                },
            });
        }
        function tdsTranscationEdit(){
            let transaction_items = '{}';
            let from_entry_ids = '[]';

            transaction_items = JSON.parse(transaction_items);
            from_entry_ids = JSON.parse(from_entry_ids);
            let firstItem = null;
            // tds transaction apply for edit
            if (
                (transaction_items && Object.keys(transaction_items).length > 0) ||
                (from_entry_ids && from_entry_ids.length > 0)
            ) {
                if (Array.isArray(transaction_items)) {
                    let firstLevel = transaction_items[0]; // {109: {...}}
                    if (firstLevel) {
                        firstItem = Object.values(firstLevel)[0];
                    }
                }
                // case 2: direct object
                else if (typeof transaction_items === 'object') {
                    firstItem = Object.values(transaction_items)[0];
                }
                //let firstItem = Object.values(transaction_items)[0];
                if (firstItem) {
                    $('#qty').val(firstItem.qty);
                    var firstItem_amount = -1 * firstItem.amount;
                    $('#amount').val(firstItem_amount);
                    $('#rate').val(firstItem.rate);
                }
            }
            // end logic of tds transcation edit
        }
    </script>
    <script>
   

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
