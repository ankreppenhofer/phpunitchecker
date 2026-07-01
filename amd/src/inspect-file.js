// This file is part of Moodle - http://moodle.org/

/**
 * Show the snippet of a file with the current line higlighted.
 *
 * @module     tool_phpunitchecker/inspect-file
 * @copyright  2026 MoodleMoot DACH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/modal', 'core/ajax', 'core/notification'], function(Modal, Ajax, Notification) {

    let modalHeadline = '';

    /**
     * Fetch the rendered file snippet from the server.
     *
     * @param {String} file Path of the file to inspect.
     * @param {Number} line Line number to highlight.
     * @return {Promise<String>} Resolves with the snippet HTML.
     */
    const getFileSnippet = function(file, line) {
        // Ajax.call() returns an array of promises, one per request. Each promise
        // resolves with the value returned by the external function, i.e. the
        // {content: '...'} structure declared in inspect_file::execute_returns().
        return Ajax.call([{
            methodname: 'tool_phpunitchecker_inspect_file',
            args: {
                file: file,
                line: line,
            },
        }])[0].then(function(response) {
            return response.content;
        });
    };

    /**
     * Fetch a snippet and show it inside a modal.
     *
     * @param {String} file Path of the file to inspect.
     * @param {Number} line Line number to highlight.
     */
    const showFileSnippet = async function(file, line) {
        try {
            const content = await getFileSnippet(file, line);
            await Modal.create({
                title: modalHeadline,
                body: '<div class="file-inspector">' + content + '</div>',
                large: true,
                show: true,
                removeOnClose: true,
            });
        } catch (error) {
            Notification.exception(error);
        }
    };

    return {
        /**
         * Initialise the file inspector.
         *
         * @param {String} headline Headline for the modal.
         */
        init: function(headline) {
            modalHeadline = headline;
            const files = document.querySelectorAll('.inspectfile');
            for (const file of files) {
                file.addEventListener('click', function(e) {
                    e.preventDefault();
                    const filePath = this.innerText.trim();
                    const lineNumber = parseInt(this.getAttribute('data-line'), 10);
                    showFileSnippet(filePath, lineNumber);
                });
            }
        }
    };
});
