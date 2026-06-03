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
 * Proview launch AMD module.
 *
 * Proview SDK initialisation has moved to frame.php (the outer iframe wrapper).
 * This module is now minimal and is used only for the TSB preflight redirect.
 *
 *  redirectToTsb(wrapperUrl)
 *    Redirects the browser to the TSB wrapper URL (preflight, Modes 1 & 2 first visit).
 *    Called from the preflight page before the quiz attempt is created.
 *
 * @module     quizaccess_proview/proview_launch
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Redirect the browser to the Talview Secure Browser wrapper URL.
     *
     * @param {string} wrapperUrl TSB wrapper URL returned by the API.
     */
    var redirectToTsb = function(wrapperUrl) {
        window.location.href = wrapperUrl;
    };

    var hideNavigation = function() {
        var link = document.querySelector('.tertiary-navigation a');
        if (link) {
            link.style.display = 'none';
        }
    };

    var redirectToFrameIfTop = function(frameUrl) {
        if (window.self === window.top) {
            window.location.replace(frameUrl);
        }
    };

    var interceptReviewLinks = function() {
        if (window.self === window.top) {
            return;
        }
        document.addEventListener('click', function(e) {
            var a = e.target.closest('a[href]');
            if (a && a.href.indexOf('/mod/quiz/view.php') !== -1) {
                e.preventDefault();
                window.parent.postMessage({type: 'stopProview', url: a.href}, window.location.origin);
            }
        });
    };

    return {
        redirectToTsb: redirectToTsb,
        hideNavigation: hideNavigation,
        redirectToFrameIfTop: redirectToFrameIfTop,
        interceptReviewLinks: interceptReviewLinks,
    };
});
