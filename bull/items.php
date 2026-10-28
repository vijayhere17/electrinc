<?php
include 'includes/config.php';
include 'includes/sidebar.php';
?>

<div class="content-wrapper">

<div class="content-header">
    <div class="container-fluid">
        <h1>Items</h1>
    </div>
</div>

<div class="content">

<div class="container-fluid">

<div class="card">

<div class="card-body form_card d-none">

    <div id="error_msg"></div>

    <form id="save_item">

        <input type="hidden"
               id="item_id"
               name="id">

        <div class="row">

            <div class="col-md-2">

                <div class="form-group">

                    <label>
                        Item :
                    </label>

                    <br>

                    <label id="item_name"></label>

                </div>

            </div>

            <div class="col-md-2">

                <div class="form-group">

                    <label>
                        Opening Stock
                    </label>

                    <input type="text"
                           class="form-control num_only"
                           data-index="1"
                           id="opening_stock"
                           name="opening_stock">

                </div>

            </div>

            <div class="col-md-2">

                <label>&nbsp;</label>

                <br>

                <button type="submit"
                        class="btn btn-primary module_save_btn"
                        data-index="2">

                    Save

                </button>

            </div>

        </div>

    </form>

</div>

</div>

<div class="card">

<div class="card-body">

<div class="table-responsive">

<table id="dataTable-item"
       class="table table-bordered">

    <thead>

        <tr>

            <th width="100">
                Action
            </th>

            <th>
                Item
            </th>

            <th>
                Opening
            </th>

            <th>
                Current Stock
            </th>

        </tr>

    </thead>

</table>

</div>

</div>

</div>

</div>

</div>

</div>
<?php include 'includes/scripts_start.php'; ?>

<script>

var itemTable;

$(document).ready(function(){

    $('form').on(
        'keydown',
        'input,select',
        function(event){

            if(event.which === 13){

                event.preventDefault();

                if($(this).hasClass('module_save_btn')){

                    $(this).click();

                } else {

                    var $this =
                        $(event.target);

                    var index =
                        parseFloat(
                            $this.attr('data-index')
                        );

                    $('[data-index="' + (index + 1) + '"]')
                    .focus();

                }

            }

        }
    );

    $(document).on(
        'submit',
        '#save_item',
        function(e){

            e.preventDefault();

            if($.trim($("#item_id").val()) == ''){

                alert('Edit any item first');

                return false;

            }

            $('.module_save_btn')
            .attr('disabled', true);

            $.ajax({

                url:
                'api/save_item.php',

                type:
                'POST',

                data:
                $(this).serialize(),

                success: function(response){

                    $('.module_save_btn')
                    .removeAttr('disabled');

                    $('.form_card')
                    .addClass('d-none');

                    $('#item_id').val('');

                    $('#item_name').html('');

                    $('#opening_stock').val('');

                    itemTable.ajax.reload(
                        null,
                        false
                    );

                    alert(
                        'Item Stock Updated'
                    );

                }

            });

        }
    );

    $(document).on(
        'click',
        '.edit_item',
        function(){

            var item_id =
                $(this).attr('data-item_id');

           $.ajax({

    url:
    'api/get_item_details.php?id=' + item_id,

    type:
    'GET',

    dataType: 'json',

    success: function(response){

        var json = response;

                    $('.form_card')
                    .removeClass('d-none');

                    $('#item_id')
                    .val(json.id);

                    $('#item_name')
                    .html(json.item_name);

                    $('#opening_stock')
                    .val(json.opening_stock);

                    $('.module_save_btn')
                    .html('Update');

                    $('#opening_stock')
                    .focus();

                    $("html, body")
                    .animate({
                        scrollTop: 0
                    }, "slow");

                }

            });

        }
    );

    itemTable =
        $('#dataTable-item').DataTable({

            ajax: {

                url:
                'api/get_items.php',

                type:
                'GET',

                dataSrc:
                'data'

            },

            columns: [

                {

                    data: 'id',

                    render: function(data){

                        return `
                        <button
                            class="btn btn-sm btn-info edit_item"
                            data-item_id="${data}">

                            <i class="fa fa-edit"></i>

                        </button>
                        `;

                    }

                },

                {
                    data: 'item_name'
                },

                {
                    data: 'opening_stock',
                    className: 'text-right'
                },

                {
                    data: 'current_stock',
                    className: 'text-right'
                }

            ],

            ordering: false,

            searching: false,

            paging: false,

            responsive: false,

            autoWidth: false

        });

    $(document).on(
        'keydown',
        function(e){

            if(e.ctrlKey && e.which === 83){

                e.preventDefault();

                $('.module_save_btn')
                .first()
                .click();

                return false;

            }

        }
    );

});

</script>
<?php include 'includes/scripts_end.php'; ?>
