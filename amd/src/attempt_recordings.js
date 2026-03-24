// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AMD module for the Proview recordings page.
 *
 * The table is rendered server-side. This module adds:
 *  - Client-side search filtering by name or email.
 *  - On-click playback token fetch + Bootstrap modal recording player.
 *
 * @module     quizaccess_proview/attempt_recordings
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    /**
     * Open the recording modal pointing at the given URL.
     *
     * @param {string} url Fully-qualified recording URL (token already appended).
     */
    function openModal(url) {
        var modal = document.getElementById('proview-recording-modal');
        if (!modal) {
            return;
        }
        document.getElementById('proview-recording-iframe').src = url;
        $(modal).modal('show');
    }

    $(document).on('hidden.bs.modal', '#proview-recording-modal', function() {
        document.getElementById('proview-recording-iframe').src = 'about:blank';
    });

    /**
     * Initialise the search input to filter recording rows by name or email.
     */
    function initSearch() {
        var input = document.getElementById('proview-recordings-search');
        var countEl = document.getElementById('proview-recordings-count');
        var rows = Array.prototype.slice.call(
            document.querySelectorAll('#proview-recordings-table .proview-attempt-row')
        );
        var total = rows.length;

        /**
         * Update the visible-row count label.
         *
         * @param {number} visible Number of rows currently visible.
         */
        function setCount(visible) {
            if (!countEl) {
                return;
            }
            countEl.textContent = visible === total
                ? 'Showing ' + total + ' session' + (total !== 1 ? 's' : '')
                : 'Showing ' + visible + ' of ' + total + ' sessions';
        }

        setCount(total);

        if (!input) {
            return;
        }

        input.addEventListener('input', function() {
            var term = this.value.trim().toLowerCase();
            var visible = 0;

            rows.forEach(function(row) {
                var match = term === ''
                    || (row.dataset.name || '').toLowerCase().indexOf(term) !== -1
                    || (row.dataset.email || '').toLowerCase().indexOf(term) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) {
                    visible++;
                }
            });

            setCount(visible);
        });
    }

    /**
     * Attach click handlers to recording links to fetch a playback token and open the modal.
     *
     * @param {number} quizid  Moodle quiz ID.
     * @param {string} sesskey Current Moodle session key.
     */
    function initLinks(quizid, sesskey) {
        document.querySelectorAll('.proview-recording-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                var proviewurl = link.dataset.proviewurl;
                var sessionuuid = link.dataset.sessionuuid;
                var proctortoken = link.dataset.proctortoken;
                var originalText = link.textContent.trim();

                link.textContent = '\u2026';
                link.style.pointerEvents = 'none';

                var endpoint = M.cfg.wwwroot
                    + '/mod/quiz/accessrule/proview/playback_token.php'
                    + '?quizid=' + encodeURIComponent(quizid)
                    + '&session_uuid=' + encodeURIComponent(sessionuuid)
                    + '&proctor_token=' + encodeURIComponent(proctortoken)
                    + '&sesskey=' + encodeURIComponent(sesskey);

                fetch(endpoint)
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        link.textContent = originalText;
                        link.style.pointerEvents = '';
                        var url = data.token
                            ? proviewurl + '?token=' + encodeURIComponent(data.token)
                            : proviewurl;
                        openModal(url);
                        return url;
                    })
                    .catch(function() {
                        link.textContent = originalText;
                        link.style.pointerEvents = '';
                    });
            });
        });
    }

    return {
        /**
         * Initialise search filtering and recording link handlers.
         * The table must already be rendered in the DOM (server-side).
         *
         * @param {number} quizid  Moodle quiz ID.
         * @param {string} sesskey Current session key.
         */
        init: function(quizid, sesskey) {
            initSearch();
            initLinks(quizid, sesskey);
        }
    };
});
