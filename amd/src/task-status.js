// This file is part of Moodle - http://moodle.org/

/**
 * Poll a PHPUnit adhoc task and report its progress to the user.
 *
 * @module     tool_phpunitchecker/task-status
 * @copyright  2026 MoodleMoot DACH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {

    // How often, in milliseconds, to re-check the task while it is still running.
    var POLL_INTERVAL = 3000;

    // Task statuses that mean we should keep waiting and poll again.
    var INPROGRESS = ['queued', 'running'];

    /**
     * Ask the server for the current status of the task.
     *
     * @param {String} taskid The unique id stored in the task custom data.
     * @return {Promise} Resolves with an object {status, message}.
     */
    var fetchStatus = function(taskid) {
        return Ajax.call([{
            methodname: 'tool_phpunitchecker_get_task_status',
            args: {id: taskid}
        }])[0];
    };

    /**
     * Update the text shown inside the progress container, if it still exists.
     *
     * @param {HTMLElement|null} container
     * @param {String} message
     */
    var setMessage = function(container, message) {
        if (container && message) {
            container.textContent = message;
        }
    };

    /**
     * Remove the progress container from the page.
     *
     * @param {HTMLElement|null} container
     */
    var removeContainer = function(container) {
        if (container && container.parentNode) {
            container.parentNode.removeChild(container);
        }
    };

    /**
     * Check the task once, then either schedule another poll or report the outcome.
     *
     * @param {String} taskid
     * @param {HTMLElement|null} container
     */
    var poll = function(taskid, container) {
        fetchStatus(taskid).then(function(result) {
            // Still queued or running: show any progress message and check again shortly.
            if (INPROGRESS.indexOf(result.status) !== -1) {
                setMessage(container, result.message);
                setTimeout(function() {
                    poll(taskid, container);
                }, POLL_INTERVAL);
                return result;
            }

            // The task is no longer in progress, so we are done with the live region.
            removeContainer(container);

            if (result.status === 'failed') {
                return Str.get_string('phpunitreadinessfailed', 'tool_phpunitchecker').then(function(str) {
                    Notification.addNotification({message: result.message || str, type: 'error'});
                    return str;
                });
            }

            // Any other status - including the task no longer being queued - means success.
            return Str.get_string('phpunitready', 'tool_phpunitchecker').then(function(str) {
                Notification.addNotification({message: result.message || str, type: 'success'});
                return str;
            });
        }).catch(Notification.exception);
    };

    return {
        /**
         * Start polling the given task and report its outcome once it finishes.
         *
         * @param {String} taskid The id returned when the adhoc task was queued.
         * @param {String} containerid Id of the element used to show progress.
         */
        init: function(taskid, containerid) {
            var container = document.getElementById(containerid);
            poll(taskid, container);
        }
    };
});
