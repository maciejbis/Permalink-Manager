/* Permalink Manager - setup wizard */
(function () {
    'use strict';

    var cfg = window.pmSetupWizard || {};

    /**
     * Persist the notice dismissal when its "x" is clicked. This runs on any
     * admin page where the setup notice may appear, so it is bound globally.
     */
    document.addEventListener('click', function (e) {
        var dismiss = e.target.closest('.notice-dismiss');
        if (!dismiss || !cfg.ajaxUrl) {
            return;
        }

        var nag = dismiss.closest('.permalink-manager-notice[data-alert_id="' + (cfg.nagId || 'setup-wizard') + '"]');
        if (!nag) {
            return;
        }

        var data = new FormData();
        data.append('action', 'pm_dismiss_setup_wizard');
        data.append('nonce', cfg.nonce || '');
        fetch(cfg.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data});
    });

    /**
     * Checkbox toggle handling for setup box labels.
     */
    document.addEventListener('click', function (ev) {
        var label = ev.target.closest('.pm-setup-box .checkboxes label');
        if (!label) {
            return;
        }

        // If the clicked target is the input itself, let the browser handle it natively
        if (ev.target.tagName.toLowerCase() === 'input') {
            return;
        }

        var input = label.querySelector('input');
        if (input) {
            // Prevent the default label click behavior to avoid double-toggling
            ev.preventDefault();

            // Toggle the checked state
            input.checked = !input.checked;

            // Dispatch a change event in case other scripts are listening
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    /**
     * Multi-step navigation. Only runs on the wizard page.
     */
    var form = document.querySelector('.pm-setup-form');
    if (!form) {
        return;
    }

    var wrap = document.querySelector('.pm-setup-wrap');
    var back = form.querySelector('.pm-setup-back');
    var next = form.querySelector('.pm-setup-next');
    var finish = form.querySelector('.pm-setup-finish');
    var steps = form.querySelectorAll('.pm-setup-step');
    var labels = wrap ? wrap.querySelectorAll('.pm-setup-progress [data-step-name]') : [];
    var last = steps.length;

    // Index of the step currently on screen (1-based). Any number of steps is
    // supported: the free wizard has 2, the Pro wizard adds a 3rd for the license.
    var current = 1;

    function showStep(step) {
        // Keep the requested step within bounds.
        current = Math.min(Math.max(step, 1), last);

        Array.prototype.forEach.call(steps, function (el) {
            el.hidden = (parseInt(el.getAttribute('data-step'), 10) !== current);
        });

        Array.prototype.forEach.call(labels, function (el) {
            el.hidden = (parseInt(el.getAttribute('data-step-name'), 10) !== current);
        });

        // Back is hidden on the first step; Next is hidden on the last step,
        // where only "Finish setup" remains.
        back.hidden = (current === 1);
        next.hidden = (current === last);
        finish.hidden = (current !== last);

        window.scrollTo(0, 0);
    }

    next.addEventListener('click', function () {
        showStep(current + 1);
    });
    back.addEventListener('click', function () {
        showStep(current - 1);
    });

    showStep(1);
}());