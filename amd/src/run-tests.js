// This file is part of Moodle - http://moodle.org/

/**
 * Show a loading state while PHPUnit test suites are running.
 *
 * @module     tool_phpunitchecker/run-tests
 * @copyright  2026 Alissa Cenga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Check whether the suite autocomplete has at least one selected value.
     *
     * @param {HTMLFormElement} form
     * @return {Boolean}
     */
    var hasSelectedSuites = function(form) {
        var select = form.querySelector('select[name="testsuites[]"], select[name="testsuites"]');

        if (!select) {
            return true;
        }

        return Array.prototype.some.call(select.options, function(option) {
            return option.selected && option.value !== '_qf__force_multiselect_submission';
        });
    };

    /**
     * Add a loading message below the form.
     *
     * @param {HTMLFormElement} form
     * @param {String} message
     */
    var showLoader = function(form, message) {
        var loader = document.createElement('div');
        loader.className = 'alert alert-info mt-3';
        loader.setAttribute('role', 'status');
        loader.setAttribute('aria-live', 'polite');
        loader.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + message;

        form.parentNode.insertBefore(loader, form.nextSibling);
    };

    /**
     * Remove any report already shown on the page.
     */
    var removeExistingReport = function() {
        document.querySelectorAll('.tool-phpunitchecker-report').forEach(function(report) {
            report.remove();
        });
    };

    return {
        /**
         * Initialise the submit listener.
         *
         * @param {String} message Loading message.
         */
        init: function(message) {
            // eslint-disable-next-line max-len
            var form = document.querySelector('form input[name="testsuites[]"], form select[name="testsuites[]"], form select[name="testsuites"]');

            if (!form) {
                return;
            }

            form = form.closest('form');

            form.addEventListener('submit', function() {
                if (!hasSelectedSuites(form) || form.dataset.phpunitcheckerSubmitting === '1') {
                    return;
                }

                form.dataset.phpunitcheckerSubmitting = '1';
                form.querySelectorAll('input[type="submit"], button[type="submit"]').forEach(function(button) {
                    button.disabled = true;
                });

                removeExistingReport();
                showLoader(form, message);
            });
        }
    };
});
