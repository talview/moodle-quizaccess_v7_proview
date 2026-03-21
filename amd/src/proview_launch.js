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
 * The Proview CDN script (loaded as a blocking <script> tag by PHP) registers
 * a queue function on window as 'tv' (backed by window.TalviewProctor).
 * All SDK calls go through tv().
 *
 * Two entry points:
 *
 *  redirectToTsb(wrapperUrl)
 *    Redirects the browser to the TSB wrapper URL (Modes 1 & 2 first visit).
 *    Called from the preflight page before the quiz attempt is created.
 *
 *  init(config)
 *    Initialises a Proview session via tv('init', token, params).
 *
 *    Preflight page (config.preflight = true, config.skipHardwareTest = false):
 *      - SDK presents camera / ID checks to the candidate.
 *      - initCallback fires when preflight is complete; the Moodle preflight
 *        form is then submitted, starting the quiz and the timer.
 *
 *    Quiz attempt page (config.preflight = false, config.skipHardwareTest = true):
 *      - SDK resumes monitoring silently (no hardware checks repeated).
 *      - Session is stopped via ProctorClient3.stop() on quiz submit and
 *        on page unload.
 *
 * @module     quizaccess_proview/proview_launch
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log', 'core/notification'], function(Log, Notification) {

    /** @type {boolean} Guard against double-initialisation. */
    var initialised = false;

    /**
     * Redirect the browser to the Talview Secure Browser wrapper URL.
     *
     * @param {string} wrapperUrl TSB wrapper URL returned by the API.
     */
    var redirectToTsb = function(wrapperUrl) {
        Log.debug('[proview_launch] Redirecting to TSB: ' + wrapperUrl);
        window.location.href = wrapperUrl;
    };

    /**
     * Stop the active Proview session via ProctorClient3.
     */
    var stopSession = function() {
        if (window.ProctorClient3 && window.ProviewStatus === 'start') {
            ProctorClient3.stop(function() {
                window.ProviewStatus = 'stop';
            });
        }
    };

    /**
     * Initialise a Proview session.
     *
     * @param {Object}   config
     * @param {string}   config.token                 Proview bearer token.
     * @param {number}   config.profileId             Moodle user ID.
     * @param {string}   config.sessionId             '{quizId}-{userId}[-{attemptNo}]'.
     * @param {string}   config.sessionType           'ai_proctor' | 'record_and_review' | 'live_proctor'.
     * @param {string}   config.candidateInstructions Additional instructions shown to candidate.
     * @param {Array}    config.referenceLinks        [{caption, url}, ...].
     * @param {boolean}  config.skipHardwareTest      Skip camera/hardware preflight checks.
     * @param {boolean}  config.preflight             True on preflight page, false on attempt page.
     */
    var init = function(config) {
        if (initialised) {
            Log.debug('[proview_launch] Already initialised — skipping.');
            return;
        }
        initialised = true;

        if (typeof tv === 'undefined') {
            Notification.exception({message: '[proview_launch] Proview SDK (tv) not available.'});
            return;
        }

        Log.debug('[proview_launch] Initialising session: ' + config.sessionId);

        var params = {
            profileId:            config.profileId,
            session:              config.sessionId,
            session_type:         config.sessionType,
            additionalInstruction: config.candidateInstructions || '',
            referenceLinks:       JSON.stringify(config.referenceLinks || []),
            clear:                false,
            skipHardwareTest:     config.skipHardwareTest,
            previewStyle:         'position: fixed; bottom: 0px;',
            initCallback:         function(err, sessionUuid) {
                if (err) {
                    Log.error('[proview_launch] Proview init error: ' + JSON.stringify(err));
                    Notification.addNotification({
                        message: (err.message || 'Proview encountered an error.'),
                        type:    'error',
                    });
                    return;
                }

                Log.debug('[proview_launch] Proview ready, session UUID: ' + sessionUuid);
                window.ProviewStatus = 'start';

                if (config.preflight) {
                    var form = document.querySelector('form.quizaccess_preflight_check');
                    if (form) {
                        Log.debug('[proview_launch] Preflight done — submitting quiz start form.');
                        form.submit();
                    }
                }
            },
        };

        tv('init', config.token, params);

        var form = document.getElementById('responseform');
        if (form) {
            form.addEventListener('submit', function() {
                stopSession();
            });
        }

        window.addEventListener('beforeunload', function() {
            stopSession();
        });
    };

    return {
        init:          init,
        redirectToTsb: redirectToTsb,
    };
});
