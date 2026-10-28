
</div><!-- /.wrapper -->

<script>

function loadHeaderSummary(){
    $.ajax({
        url: 'api/get_header_summary.php',
        type: 'GET',
        dataType: 'json',
        success: function(response){
            $('#header_gold').html(response.gold);
            $('#header_silver').html(response.silver);
            $('#header_cash').html(response.cash);
            $('#header_bank_total').html(response.bank_total);
        }
    });
}

$(document).ready(function(){
    loadHeaderSummary();

    if ($('#header_party_id').length && !$('#header_party_id').hasClass('select2-hidden-accessible')) {
        initAjaxSelect2($('#header_party_id'), 'api/get_party_select2.php');
        $('#header_party_id').on('change', function(){
            var party_id = $(this).val();
            if (party_id) {
                $.getJSON('api/get_account_balance.php?account_id=' + party_id, function (json) {
                    var gid = json.account_data ? json.account_data.account_group_id : 0;
                    if (gid == 2 || gid == 3) {
                        window.location.href = 'party-ledger.php?id=' + party_id;
                    } else {
                        window.location.href = 'cashbank-ledger.php?id=' + party_id;
                    }
                });
            }
        });
    }
});

</script>

</body>
</html>
