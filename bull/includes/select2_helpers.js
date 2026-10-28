/**
 * Shared Select2 helpers used across BUL pages.
 */
function focusOpenSelect2Search($select) {
    function tryFocus(attempt) {
        var $container = $select.next('.select2-container');
        var $search = $container.find('.select2-search__field');

        if (!$search.length) {
            $search = $('.select2-container--open .select2-search__field').last();
        }

        if ($search.length) {
            $search[0].focus();
            return;
        }

        if (attempt < 10) {
            setTimeout(function () {
                tryFocus(attempt + 1);
            }, 40);
        }
    }

    setTimeout(function () {
        tryFocus(0);
    }, 0);
}

function bindSelect2Keyboard($selector) {
    $selector.off('select2:open.bulKeyboard').on('select2:open.bulKeyboard', function () {
        focusOpenSelect2Search($(this));
    });
}

function resetAjaxSelect2($selector) {
    if (!$selector.length || !$selector.hasClass('select2-hidden-accessible')) {
        return;
    }

    $selector.select2('close');
    $selector.val(null).trigger('change.select2');

    setTimeout(function () {
        $selector.select2('open');
        focusOpenSelect2Search($selector);
    }, 80);
}

function initAjaxSelect2($selector, $source_url) {
    if ($selector.hasClass('select2-hidden-accessible')) {
        $selector.select2('destroy');
    }

    $selector.select2({
        placeholder: ' --Select-- ',
        width: '100%',
        allowClear: true,
        minimumInputLength: 0,
        minimumResultsForSearch: 0,
        dropdownParent: $(document.body),
        ajax: {
            url: $source_url,
            dataType: 'json',
            delay: 150,
            data: function (params) {
                return {
                    q: params.term || '',
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return {
                    results: data.results || [],
                    pagination: {
                        more: (params.page * 5) < (data.total_count || 0)
                    }
                };
            },
            cache: true
        }
    });

    bindSelect2Keyboard($selector);
    return $.Deferred().resolve($selector).promise();
}

function initStaticSelect2($selector, options) {
    options = options || {};

    if ($selector.hasClass('select2-hidden-accessible')) {
        $selector.select2('destroy');
    }

    $selector.select2({
        placeholder: options.placeholder || '--Select--',
        width: options.width || '100%',
        minimumResultsForSearch: 0,
        allowClear: !!options.allowClear,
        dropdownParent: $(document.body)
    });

    bindSelect2Keyboard($selector);
}

function initAjaxSelect2_with_tags($selector, $source_url) {
    if ($selector.hasClass('select2-hidden-accessible')) {
        $selector.select2('destroy');
    }

    $selector.select2({
        placeholder: ' --Select-- ',
        width: '100%',
        minimumInputLength: 0,
        minimumResultsForSearch: 0,
        tags: true,
        dropdownParent: $(document.body),
        ajax: {
            url: $source_url,
            dataType: 'json',
            delay: 150,
            data: function (params) {
                return {
                    q: params.term || '',
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return {
                    results: data.results || [],
                    pagination: {
                        more: (params.page * 5) < (data.total_count || 0)
                    }
                };
            },
            cache: true
        }
    });

    bindSelect2Keyboard($selector);
}

function setSelect2Value($selector, $source_url) {
    if ($source_url !== '') {
        $.ajax({
            url: $source_url,
            type: 'GET',
            data: null,
            contentType: false,
            cache: false,
            async: false,
            processData: false,
            dataType: 'json',
            success: function (data) {
                if (data.success === true) {
                    $selector.empty()
                        .append($('<option/>').val(data.id).text(data.text))
                        .val(data.id)
                        .trigger('change');
                }
            }
        });
    } else {
        $selector.empty()
            .append($('<option/>').val('').text('--select--'))
            .val('')
            .trigger('change');
    }
}

function setSelect2MultiValue($selector, $source_url) {
    if ($source_url !== '') {
        $.ajax({
            url: $source_url,
            type: 'GET',
            data: null,
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function (data) {
                if (data.success === true) {
                    var selectValues = data[0];
                    $.each(selectValues, function (key, value) {
                        $selector.select2('trigger', 'select', { data: value });
                    });
                }
            }
        });
    } else {
        $selector.empty()
            .append($('<option/>').val('').text('--select--'))
            .val('')
            .trigger('change');
    }
}

$(document).on('keydown', '.select2-search__field', function (e) {
    e.stopPropagation();

    if (e.which !== 13) {
        return;
    }

    var $highlighted = $('.select2-container--open .select2-results__option--highlighted');
    var $first = $('.select2-container--open .select2-results__option[aria-selected]').first();

    if ($highlighted.length || $first.length) {
        e.preventDefault();
        e.stopImmediatePropagation();
        ($highlighted.length ? $highlighted : $first).trigger('mouseup');
    }
});
