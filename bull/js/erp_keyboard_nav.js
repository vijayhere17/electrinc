/**
 * ============================================================
 * FORCEFUL KEYBOARD NAVIGATION SYSTEM — ERP Edition
 * Compatible with: jQuery + Select2 + AdminLTE / Bootstrap ERP
 * 
 * Strategy:
 *   1. Uses native addEventListener with { capture: true }
 *      — fires BEFORE all bubble-phase handlers,
 *        bypassing Select2's stopImmediatePropagation.
 *   2. Uses Select2's own event API (select2:select / select2:close)
 *      instead of fighting its keydown handler.
 *   3. Manages a "pending focus" state machine so delayed
 *      Select2 DOM injection never causes timing gaps.
 *   4. Self-contained: drop this one file in, call
 *      ERPNav.init(config) — no other changes needed.
 * ============================================================
 */

;(function (window, $) {

  'use strict';

  // ─────────────────────────────────────────────
  // INTERNAL STATE
  // ─────────────────────────────────────────────

  var _fields        = [];      // Ordered nav fields (resolved at runtime)
  var _formSelector  = null;    // e.g. '#save_account'
  var _saveBtn       = null;    // e.g. '.module_save_btn'
  var _pendingFocus  = false;   // True while waiting for Select2 to close
  var _select2Open   = false;   // True while any Select2 dropdown is open
  var _debug         = false;   // Set true to get console traces
  var _captureRef    = null;    // Reference to the capture listener (for cleanup)

  // ─────────────────────────────────────────────
  // LOGGER — toggled by _debug flag
  // ─────────────────────────────────────────────

  function log() {
    if (_debug) {
      var args = ['[ERPNav]'].concat(Array.prototype.slice.call(arguments));
      console.log.apply(console, args);
    }
  }

  // ─────────────────────────────────────────────
  // FIELD REGISTRY
  // Reads data-index attributes on all form fields
  // and sorts them, so you control order via HTML.
  // ─────────────────────────────────────────────

  function buildFieldList(formSelector) {
    var $form = $(formSelector);
    var items = [];

    $form.find('[data-index]').each(function () {
      var $el  = $(this);
      var idx  = parseInt($el.attr('data-index'), 10);
      var type = resolveFieldType($el);
      items.push({ index: idx, $el: $el, type: type });
    });

    // Also catch save buttons that are OUTSIDE the form but have data-index
    $(_saveBtn + '[data-index]').each(function () {
      var $el = $(this);
      var idx = parseInt($el.attr('data-index'), 10);
      items.push({ index: idx, $el: $el, type: 'button' });
    });

    items.sort(function (a, b) { return a.index - b.index; });
    log('Field list built:', items.map(function (f) {
      return f.index + ':' + (f.$el.attr('id') || f.$el.attr('name') || f.type);
    }));
    return items;
  }

  function resolveFieldType($el) {
    if ($el.hasClass('select2') || $el.hasClass('select2-hidden-accessible')) {
      return 'select2';
    }
    if ($el.is('select') && $el.next('.select2-container').length) {
      return 'select2';
    }
    var tag  = $el.prop('tagName').toLowerCase();
    var type = ($el.attr('type') || '').toLowerCase();
    if (tag === 'textarea')           return 'textarea';
    if (type === 'checkbox')          return 'checkbox';
    if (type === 'button' || type === 'submit') return 'button';
    if ($el.hasClass('module_save_btn')) return 'button';
    return 'text';
  }

  // ─────────────────────────────────────────────
  // FOCUS ENGINE
  // ─────────────────────────────────────────────

  function focusField(field) {
    if (!field) return;
    var $el  = field.$el;
    var type = field.type;

    log('Focusing field', field.index, type, $el.attr('id'));

    if (type === 'select2') {
      // Trigger Select2 to open + highlight first option
      _pendingFocus = false;
      _select2Open  = true;

      // Give the DOM a tick to settle (Select2 needs it)
      setTimeout(function () {
        $el.select2('open');

        // After Select2's search box is injected, focus it and
        // trigger a keydown ArrowDown to highlight first option.
        setTimeout(function () {

    var $search = $('.select2-container--open .select2-search__field');

    if ($search.length) {

        $search.focus();
        $search.select();

        highlightFirstSelect2Option($el);

    }

}, 80);
      }, 30);

      return;
    }

    if (type === 'checkbox') {
      $el[0].focus();
      return;
    }

    if (type === 'button') {
      $el[0].focus();
      return;
    }

    // text / textarea
    $el[0].focus();
  }

  function highlightFirstSelect2Option($select) {
    // Select2 renders a live-region list; wait for it
    var attempts = 0;
    var timer = setInterval(function () {
      attempts++;
      var $opts = $select
        .next('.select2-container')
        .find('.select2-results__option');

      if ($opts.length) {
        clearInterval(timer);
        // Simulate keyboard ArrowDown so Select2 marks first item
        var $search = $select
          .next('.select2-container')
          .find('.select2-search__field');
        var ev = $.Event('keydown', { keyCode: 40, which: 40 });
        $search.trigger(ev);
        log('Highlighted first option');
      } else if (attempts > 20) {
        clearInterval(timer);
        log('No options found after wait');
      }
    }, 50);
  }

  // ─────────────────────────────────────────────
  // FIELD NAVIGATION HELPERS
  // ─────────────────────────────────────────────

  function getCurrentIndex() {
    var active = document.activeElement;
    if (!active) return -1;

    // Check if we're inside a Select2 search input
    if (
      active.classList.contains('select2-search__field') ||
      $(active).closest('.select2-container').length
    ) {
      // Find which select2 is open
      for (var i = 0; i < _fields.length; i++) {
        if (_fields[i].type === 'select2') {
          var container = _fields[i].$el.next('.select2-container')[0];
          if (container && container.classList.contains('select2-container--open')) {
            log('Active is inside select2 at index', i);
            return i;
          }
        }
      }
    }

    // Match by element
    for (var j = 0; j < _fields.length; j++) {
      if (_fields[j].$el[0] === active) {
        return j;
      }
    }

    return -1;
  }

  function moveToNext() {
    var cur = getCurrentIndex();
    log('moveToNext: current =', cur);
    if (cur === -1) {
      // Default: go to first field
      if (_fields.length) focusField(_fields[0]);
      return;
    }
    var next = cur + 1;
    if (next < _fields.length) {
      focusField(_fields[next]);
    } else {
      // Wrap to first or trigger save
      if (_saveBtn) {
        $(_saveBtn).trigger('click');
      }
    }
  }

  // ─────────────────────────────────────────────
  // THE CAPTURE-PHASE KEYDOWN HANDLER
  // This fires BEFORE any bubble-phase handler, including
  // Select2's internal stopImmediatePropagation.
  // ─────────────────────────────────────────────

  function captureKeydown(e) {

    // ── CTRL + S → Save ──────────────────────────
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      log('CTRL+S → Save');
      $(_saveBtn).trigger('click');
      return;
    }

    // ── ENTER key logic ───────────────────────────
    if (e.key !== 'Enter' && e.keyCode !== 13) return;

    var active = document.activeElement;
    if (!active) return;

    var $active  = $(active);
    var tagName  = active.tagName.toLowerCase();
    var inputType = (active.type || '').toLowerCase();

    // Don't intercept Enter inside <textarea> (allow newlines)
    if (tagName === 'textarea') return;

    // Don't intercept Enter on real submit buttons
    if (inputType === 'submit') return;

    // ── Inside a Select2 search field ────────────
    if (
    $active.hasClass('select2-search__field') ||
    $active.closest('.select2-container').length
) {

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    var highlighted =
    $('.select2-results__option--highlighted');

    if(highlighted.length){

        highlighted.trigger('mouseup');

    }else{

        var first =
        $('.select2-results__option[aria-selected]');

        if(first.length){

            first.first().trigger('mouseup');

        }

    }

    return;
}

    // ── Checkbox: toggle + move ───────────────────
    if (inputType === 'checkbox') {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      active.checked = !active.checked;
      $(active).trigger('change');
      log('Checkbox toggled, moving next');
      moveToNext();
      return;
    }

    // ── Button (Save) ────────────────────────────
    if (inputType === 'button' || $active.hasClass('module_save_btn')) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      log('Enter on save button — triggering save');
      $active.trigger('click');
      return;
    }

    // ── Normal text/number inputs ─────────────────
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    log('Enter on text input — moving to next field');
    moveToNext();
  }

  // ─────────────────────────────────────────────
  // SELECT2 EVENT HOOKS
  // These use Select2's own API — reliable, no conflicts
  // ─────────────────────────────────────────────

  function bindSelect2Events() {
    _fields.forEach(function (field) {
      if (field.type !== 'select2') return;

      field.$el.on('select2:open', function () {
        _select2Open = true;
        log('select2:open', field.index);
      });

 field.$el.on('select2:select', function () {

    _pendingFocus = false;
    _select2Open  = false;

    var currentField = field;

    setTimeout(function () {

        for(var i = 0; i < _fields.length; i++){

            if(_fields[i].index == currentField.index){

                if(i < (_fields.length - 1)){

                    focusField(_fields[i + 1]);

                }

                break;

            }

        }

    },50);

});

      field.$el.on('select2:close', function () {
        log('select2:close');
        _select2Open = false;

        // If closed without selection (Escape, click outside), cancel pending
        setTimeout(function () {
          if (_pendingFocus) {
            _pendingFocus = false;
            log('select2:close without selection — no nav');
          }
        }, 200);
      });
    });
  }

  // ─────────────────────────────────────────────
  // POST-SAVE RESET HOOK
  // Call ERPNav.onSaveSuccess() from your AJAX success handler
  // ─────────────────────────────────────────────

  function onSaveSuccess() {
    _pendingFocus = false;
    _select2Open  = false;

    // Rebuild field list (form may have dynamic elements)
    _fields = buildFieldList(_formSelector);
    bindSelect2Events();

    // Return focus to first field
    setTimeout(function () {
      if (_fields.length) {
        log('Post-save: focusing first field');
        focusField(_fields[0]);
      }
    }, 120);
  }

  // ─────────────────────────────────────────────
  // INIT
  // ─────────────────────────────────────────────

  function init(config) {
    _formSelector = config.formSelector || '#save_account';
    _saveBtn      = config.saveBtn      || '.module_save_btn';
    _debug        = config.debug        || false;

    // Wait for DOM + Select2 to fully initialize
    $(function () {
      setTimeout(function () {

        _fields = buildFieldList(_formSelector);
        bindSelect2Events();

        // Install the capture-phase listener on DOCUMENT
        // { capture: true } means we fire first — before ANY
        // bubble-phase handler including Select2's internal ones.
        if (_captureRef) {
          document.removeEventListener('keydown', _captureRef, true);
        }
        _captureRef = captureKeydown;
        document.addEventListener('keydown', _captureRef, true);

        log('ERPNav initialized. Fields:', _fields.length);

        // Auto-focus first field
        if (_fields.length) {
          focusField(_fields[0]);
        }

      }, 600); // Wait for Select2 AJAX init to complete
    });
  }

  // ─────────────────────────────────────────────
  // DEBUGGING UTILITIES
  // Call these from DevTools console to diagnose conflicts
  // ─────────────────────────────────────────────

  var Debug = {

    /**
     * Lists ALL keydown handlers registered via jQuery on document.
     * Run in console: ERPNav.Debug.listHandlers()
     */
    listHandlers: function () {
      var events = $._data(document, 'events');
      if (!events || !events.keydown) {
        console.log('[ERPNav Debug] No jQuery keydown handlers on document.');
        return;
      }
      console.group('[ERPNav Debug] jQuery keydown handlers on document:');
      events.keydown.forEach(function (h, i) {
        console.log(
          'Handler #' + i,
          '| namespace:', h.namespace || '(none)',
          '| selector:', h.selector  || '(none)',
          '\n', h.handler.toString().substring(0, 200)
        );
      });
      console.groupEnd();
    },

    /**
     * Temporarily monkey-patches stopImmediatePropagation to trace
     * which handler is eating events.
     * Run in console: ERPNav.Debug.traceSwallow()
     */
    traceSwallow: function () {
      var orig = Event.prototype.stopImmediatePropagation;
      Event.prototype.stopImmediatePropagation = function () {
        if (this.type === 'keydown') {
          console.warn(
            '[ERPNav Debug] stopImmediatePropagation called on keydown!',
            '\nCaller stack:', new Error().stack
          );
        }
        return orig.apply(this, arguments);
      };
      console.log('[ERPNav Debug] Tracing stopImmediatePropagation. Press Enter now.');
    },

    /**
     * Removes ALL jQuery keydown handlers from document except ERPNav.
     * Use as last resort if a legacy script is interfering.
     * Run in console: ERPNav.Debug.nukeConflicts()
     */
    nukeConflicts: function () {
      $(document).off('keydown');
      console.warn('[ERPNav Debug] All jQuery keydown handlers removed from document.');
      console.warn('Re-binding ERPNav capture handler...');
      document.removeEventListener('keydown', _captureRef, true);
      document.addEventListener('keydown', _captureRef, true);
    },

    /**
     * Shows which field ERPNav thinks is currently active.
     * Run in console: ERPNav.Debug.currentField()
     */
    currentField: function () {
      var idx = getCurrentIndex();
      console.log('[ERPNav Debug] Current field index:', idx, _fields[idx] || 'not found');
      console.log('document.activeElement:', document.activeElement);
    },

    /**
     * Logs all registered fields in order.
     * Run in console: ERPNav.Debug.listFields()
     */
    listFields: function () {
      console.table(_fields.map(function (f) {
        return {
          index: f.index,
          type:  f.type,
          id:    f.$el.attr('id') || f.$el.attr('name') || '(no id)',
          tag:   f.$el.prop('tagName')
        };
      }));
    }
  };

  // ─────────────────────────────────────────────
  // PUBLIC API
  // ─────────────────────────────────────────────

  window.ERPNav = {
    init:           init,
    onSaveSuccess:  onSaveSuccess,
    focusFirst:     function () { if (_fields.length) focusField(_fields[0]); },
    moveToNext:     moveToNext,
    rebuild:        function () {
      _fields = buildFieldList(_formSelector);
      bindSelect2Events();
    },
    Debug: Debug
  };

}(window, jQuery));



