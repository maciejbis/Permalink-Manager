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

    function showStep(step) {
        Array.prototype.forEach.call(steps, function (el) {
            el.hidden = (parseInt(el.getAttribute('data-step'), 10) !== step);
        });

        Array.prototype.forEach.call(labels, function (el) {
            el.hidden = (parseInt(el.getAttribute('data-step-name'), 10) !== step);
        });

        // Back is hidden on the first step; Next is hidden on the last step,
        // where only "Finish setup" remains.
        back.hidden = (step === 1);
        next.hidden = (step === last);
        finish.hidden = (step !== last);

        window.scrollTo(0, 0);
    }

    next.addEventListener('click', function () {
        showStep(2);
    });
    back.addEventListener('click', function () {
        showStep(1);
    });

    showStep(1);
}());