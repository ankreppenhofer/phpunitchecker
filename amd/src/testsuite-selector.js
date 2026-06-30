// This file is part of Moodle - http://moodle.org/

/**
 * PHPUnit test suite autocomplete data source.
 *
 * @module     tool_phpunitchecker/testsuite-selector
 * @copyright  2026 Alissa Cenga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax'], function($, Ajax) {
    return {
        processResults: function(selector, results) {
            return $.map(results, function(testsuite) {
                return {
                    value: testsuite.name,
                    label: testsuite.name + ' (' + testsuite.testcount + ' tests)',
                };
            });
        },

        transport: function(selector, query, success, failure) {
            var promises = Ajax.call([{
                methodname: 'tool_phpunitchecker_get_testsuites',
                args: {
                    name: query
                }
            }]);

            promises[0].then(success).fail(failure);
        }
    };
});
