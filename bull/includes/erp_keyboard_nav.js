/**
 * BUL — Universal Keyboard Navigation (merged ERPNav features)
 */
(function ($) {
    'use strict';

    var BASE = window.location.pathname.replace(/\/[^\/]*\.php.*$/, '/');

    var SHORTCUTS = {
        'a': 'accounts.php',
        'm': 'items.php',
        's': 'soda-entry.php',
        't': 'transaction.php',
        'p': 'party-ledger.php',
        'r': 'rani.php',
        'u': 'rupu.php',
        'h': 'havaalo.php',
        'b': 'bank-contra.php',
        'o': 'sodahistorygold.php',
        'd': 'tds-report.php'
    };

    function isSelect2Search($el) {
        return $el.hasClass('select2-search__field') || $el.closest('.select2-container').length > 0;
    }

    function getFields() {
        var fields = [];
        var $scope = $('#save_entry, #save_transaction, #save_account').first();
        $scope = $scope.length ? $scope : $(document);
        $scope.find('[data-index]').each(function () {
            var $el = $(this);
            var idx = parseFloat($el.attr('data-index'));
            if (isNaN(idx)) return;
            if ($el.is(':disabled')) return;
            if ($el.is('[readonly]') && !$el.is('select') && !$el.hasClass('module_save_btn')) return;
            var style = window.getComputedStyle(this);
            if (style.display === 'none' || style.visibility === 'hidden') return;
            if ($el.closest('.modal').length && !$el.closest('.modal.show, .modal.in').length) return;
            fields.push({ idx: idx, $el: $el });
        });
        fields.sort(function (a, b) { return a.idx - b.idx; });
        return fields;
    }

    function findFieldIndex($current) {
        var fields = getFields();
        var i, $searchSelect = null;

        if ($current.hasClass('select2-search__field')) {
            $searchSelect = $('.select2-container--open').prev('select');
            if (!$searchSelect.length) {
                $searchSelect = $current.closest('.select2-container').prev('select');
            }
            $current = $searchSelect.length ? $searchSelect : $current;
        }

        for (i = 0; i < fields.length; i++) {
            if (fields[i].$el.is($current) || fields[i].$el.attr('id') === $current.attr('id')) {
                return { fields: fields, index: i };
            }
        }
        return { fields: fields, index: -1 };
    }

    function focusField($el) {
        if (!$el || !$el.length) return;
        if ($el.hasClass('select2') || $el.next('.select2-container').length) {
            try {
                $el.select2('open');
                setTimeout(function () {
                    var $search = $el.next('.select2-container').find('.select2-search__field');
                    if ($search.length) $search[0].focus();
                }, 50);
            } catch (e) {
                $el.focus();
            }
        } else {
            $el.focus();
            if ($el.is('input:not([type=checkbox]):not([type=radio])')) {
                try { $el[0].select(); } catch (e2) {}
            }
        }
    }

    function move($current, dir) {
        var found = findFieldIndex($current);
        var fields = found.fields;
        var idx = found.index;

        if (idx === -1) {
            if (dir === 1 && fields.length) focusField(fields[0].$el);
            return false;
        }

        var next = fields[idx + dir];
        if (!next) {
            if (dir === 1) {
                var $save = $('.module_save_btn:visible').first();
                if ($save.length) $save.focus();
            }
            return false;
        }

        focusField(next.$el);
        return true;
    }

    function selectHighlightedSelect2Option() {
        var $highlighted = $('.select2-container--open .select2-results__option--highlighted');
        var $first = $('.select2-container--open .select2-results__option[aria-selected]').first();
        if ($highlighted.length || $first.length) {
            ($highlighted.length ? $highlighted : $first).trigger('mouseup');
            return true;
        }
        return false;
    }

    // Enter on inputs
    $(document).on('keydown', 'input, select', function (e) {
        if (e.which !== 13) return;
        var $el = $(this);

        if ($el.hasClass('select2-search__field')) {
            e.preventDefault();
            selectHighlightedSelect2Option();
            return;
        }
        if ($el.closest('.select2-container').length) return;

        if ($el.attr('type') === 'checkbox') {
            e.preventDefault();
            $el.prop('checked', !$el.prop('checked')).trigger('change');
            move($el, 1);
            return;
        }

        if ($el.hasClass('module_save_btn') || $el.attr('type') === 'submit') return;

        e.preventDefault();
        move($el, e.shiftKey ? -1 : 1);
    });

    // Ctrl+Enter on textarea = next field
    $(document).on('keydown', 'textarea', function (e) {
        if (e.which === 13 && e.ctrlKey) {
            e.preventDefault();
            move($(this), 1);
        }
    });

    // Arrow up/down between fields (not inside textarea/select2 search)
    $(document).on('keydown', 'input, select, textarea', function (e) {
        if (e.which !== 38 && e.which !== 40) return;
        if (isSelect2Search($(this))) return;
        if ($(this).is('textarea')) return;

        e.preventDefault();
        move($(this), e.which === 38 ? -1 : 1);
    });

    // Enter on save button
    $(document).on('keydown', 'button.module_save_btn, input.module_save_btn', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    // Select2 close -> next/prev field (Shift = one step up)
    var s2ShiftPressed = false;
    var s2ClickedOutside = false;
    var s2SelectionHandled = false;

    $(document).on('keydown', function (e) {
        if (e.which === 16) s2ShiftPressed = true;
    });
    $(document).on('keyup', function (e) {
        if (e.which === 16) s2ShiftPressed = false;
    });
    $(document).on('mousedown', function (e) {
        s2ClickedOutside = !$(e.target).is('[class*="select2"]');
    });
    $(document).on('select2:opening', function (e) {
        s2ClickedOutside = false;
        s2SelectionHandled = false;
        $(e.target).attr('data-s2open', 1);
    });
    $(document).on('select2:closing', function (e) {
        $(e.target).removeAttr('data-s2open');
    });
    $(document).on('select2:select', function (e) {
        var $sel = $(e.target);
        s2SelectionHandled = true;
        setTimeout(function () {
            if (!s2ClickedOutside) move($sel, 1);
        }, 80);
    });
    $(document).on('select2:close', function (e) {
        var $sel = $(e.target);
        $sel.removeAttr('data-s2open');
        if ($(document.activeElement).hasClass('select2-search__field')) return;
        if (s2ClickedOutside) return;
        if ($sel.closest('form').find('[data-s2open]').length > 0) return;
        if (s2SelectionHandled) {
            s2SelectionHandled = false;
            return;
        }
        setTimeout(function () {
            move($sel, s2ShiftPressed ? -1 : 1);
        }, 50);
    });

    // Ctrl+S save
    $(document).on('keydown', function (e) {
        if (!(e.ctrlKey && e.which === 83)) return;
        e.preventDefault();
        e.stopImmediatePropagation();

        var $modal = $('.modal.show, .modal.in');
        if ($modal.length) {
            var $modalBtn = $modal.find('.module_save_btn, #updateRecord, [type="submit"]').first();
            if ($modalBtn.length) { $modalBtn.trigger('click'); return false; }
        }

        var $btn = $('.module_save_btn:visible').first();
        if ($btn.length) {
            if ($btn.attr('type') === 'submit') $btn.closest('form').submit();
            else $btn.trigger('click');
        }
        return false;
    });

    // Alt+key shortcuts
    $(document).on('keydown', function (e) {
        if (!e.altKey || e.ctrlKey || e.shiftKey) return;
        if (isSelect2Search($(e.target))) return;
        var key = e.key.toLowerCase();
        if (!SHORTCUTS[key]) return;
        e.preventDefault();
        window.location.href = BASE + SHORTCUTS[key];
    });

    // Input validators
    $(document).on('input', '.num_only', function () {
        this.value = this.value.replace(/[^\d.\-]/g, '');
    });
    $(document).on('input', '.positive_num_only', function () {
        this.value = this.value.replace(/[^\d.]/g, '');
    });
    $(document).on('input', '.positive_num_without_decimal_only', function () {
        var v = parseInt(this.value.replace(/[^\d]/g, ''), 10);
        this.value = isNaN(v) ? '' : v;
    });

    $(document).ready(function () {
        $('input, textarea').attr('autocomplete', 'off');

        // Auto-focus first field on form pages (skip pages that handle their own focus)
        setTimeout(function () {
            if ($('form.bul-skip-autofocus').length) {
                return;
            }
            var fields = getFields();
            if (fields.length && $('form [data-index]').length) {
                focusField(fields[0].$el);
            }
        }, 400);
    });

    // Line-item Enter
    $(document).on('keydown', '.rani_gross, .touch, .rani_narration, .rupu_gross', function (e) {
        if (e.which !== 13) return;
        var $btn = $(this).closest('.card, .row, .form-group').find('#add_lineitem, .save_btn[type="button"]').first();
        if ($btn.length) { e.preventDefault(); $btn.trigger('click'); }
    });

    window.BULNav = {
        focusFirst: function () {
            var fields = getFields();
            if (fields.length) focusField(fields[0].$el);
        },
        moveNext: function ($el) { move($el || $(document.activeElement), 1); },
        movePrev: function ($el) { move($el || $(document.activeElement), -1); }
    };

})(jQuery);
