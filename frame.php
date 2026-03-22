<?php
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
 * Outer wrapper page that owns the Proview SDK and wraps the quiz attempt in an iframe.
 *
 * Two modes:
 *  - New attempt (no in-progress attempt in DB): Proview hardware check runs, then
 *    initCallback creates the attempt via AJAX and reveals the quiz iframe. The quiz
 *    timer therefore starts only after Proview initialises.
 *  - Continue attempt (in-progress attempt exists): Proview hardware check runs,
 *    then initCallback reveals the already-loaded iframe.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

require_login();
require_sesskey();

$quizid = required_param('quizid', PARAM_INT);
$cmid   = optional_param('cmid', 0, PARAM_INT);

global $DB, $USER, $PAGE, $OUTPUT, $CFG;

$config = $DB->get_record('quizaccess_proview', ['quizid' => $quizid], '*', MUST_EXIST);
$quiz   = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$cm     = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);

if (!$cmid) {
    $cmid = $cm->id;
}

$inprogress = $DB->get_record_select(
    'quiz_attempts',
    "quiz = :quiz AND userid = :userid AND state IN ('inprogress', 'overdue')",
    ['quiz' => $quizid, 'userid' => $USER->id]
);

if ($inprogress) {
    $isnewattempt = false;
    $attemptno    = (int) $inprogress->attempt;
    $urlwithflag  = $CFG->wwwroot . '/mod/quiz/attempt.php'
                  . '?attempt=' . $inprogress->id . '&cmid=' . $cmid . '&page=0&proview_iframe=1';
} else {
    $isnewattempt = true;
    $attemptno    = (int) $DB->count_records_select(
        'quiz_attempts',
        'quiz = :quiz AND userid = :userid AND state <> :abandoned',
        ['quiz' => $quizid, 'userid' => $USER->id, 'abandoned' => 'abandoned']
    ) + 1;
    $urlwithflag  = '';
}

$islive    = ($config->proctoringtype === 'live');
$sessionid = $quizid . '-' . $USER->id . ($islive ? '' : '-' . $attemptno);

$sessiontypemap = [
    'ai'            => 'ai_proctor',
    'record_review' => 'record_and_review',
    'live'          => 'live_proctor',
];
$sessiontype = $sessiontypemap[$config->proctoringtype] ?? 'ai_proctor';

$cdnurl          = (string) get_config('quizaccess_proview', 'proview_cdn_url');
$playbackbaseurl = (string) get_config('quizaccess_proview', 'proview_admin_url');

$reflinksraw = (string) ($config->referencelinks ?? '');
$reflinks    = [];
if (!empty($reflinksraw)) {
    preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $reflinksraw, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $reflinks[] = ['caption' => $match[1], 'url' => $match[2]];
    }
}

$PAGE->set_context(context_module::instance($cm->id));
$PAGE->set_pagelayout('embedded');
$PAGE->set_url(new moodle_url('/mod/quiz/accessrule/proview/frame.php', [
    'quizid'  => $quizid,
    'cmid'    => $cmid,
    'sesskey' => sesskey(),
]));
$PAGE->set_title(get_string('pluginname', 'quizaccess_proview'));

$jstoken              = json_encode((string) ($config->proview_token ?? ''));
$jsprofileid          = json_encode((string) $USER->id);
$jssessionid          = json_encode($sessionid);
$jssessiontype        = json_encode($sessiontype);
$jsadditionalinstruct = json_encode((string) ($config->candidateinstructions ?? ''));
$jsreferencelinks     = json_encode(json_encode($reflinks));
$jscdnurl             = json_encode($cdnurl);
$jsquizid             = json_encode((int) $quizid);
$jscmid               = json_encode((int) $cmid);
$jsuserid             = json_encode((int) $USER->id);
$jsattemptno          = json_encode((int) $attemptno);
$jsplaybackbaseurl    = json_encode($playbackbaseurl);
$jssesskey            = json_encode(sesskey());
$jsdatastoreurl       = json_encode($CFG->wwwroot . '/mod/quiz/accessrule/proview/datastore.php');
$jsajaxurl            = json_encode($CFG->wwwroot . '/mod/quiz/accessrule/proview/startattempt_ajax.php');
$jsisnewattempt       = $isnewattempt ? 'true' : 'false';

echo $OUTPUT->header();

$iframesrc = s($urlwithflag);
echo <<<HTML
<style>
  body, html { margin: 0; padding: 0; overflow: hidden; }
  #proview-quiz-frame { width: 100vw; height: 100vh; border: none; display: none; }
</style>
<iframe id="proview-quiz-frame" src="{$iframesrc}"
        style="width:100vw;height:100vh;border:none;display:none"></iframe>
<script>
(function(i, s, o, g, r, a, m) {
    i['TalviewProctor'] = r;
    i[r] = i[r] || function() { (i[r].q = i[r].q || []).push(arguments); };
    i[r].l = 1 * new Date();
    a = s.createElement(o);
    m = s.getElementsByTagName(o)[0];
    a.async = 1;
    a.src = g;
    m.parentNode.insertBefore(a, m);
})(window, document, 'script', {$jscdnurl}, 'tv');

tv('init', {$jstoken}, {
    profileId:             {$jsprofileid},
    session:               {$jssessionid},
    session_type:          {$jssessiontype},
    additionalInstruction: {$jsadditionalinstruct},
    referenceLinks:        {$jsreferencelinks},
    clear:                 false,
    skipHardwareTest:      false,
    previewStyle:          'position: fixed; bottom: 0px;',
    initCallback:          function(err, sessionUuid) {
        if (err) { return; }
        window.ProviewStatus = 'start';
        var isNew        = {$jsisnewattempt};
        var playbackUrl  = {$jsplaybackbaseurl} + '/' + sessionUuid;
        var iframe       = document.getElementById('proview-quiz-frame');
        var sesskey      = {$jssesskey};
        var quizId       = {$jsquizid};
        var userId       = {$jsuserid};
        var datastoreUrl = {$jsdatastoreurl};
        function saveDatastore(attemptNo) {
            fetch(datastoreUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    quizid:     quizId,
                    userid:     userId,
                    attemptno:  attemptNo,
                    proviewurl: playbackUrl,
                    sesskey:    sesskey
                })
            });
        }
        if (isNew) {
            fetch({$jsajaxurl}, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    'quizid=' + quizId + '&cmid=' + {$jscmid} + '&sesskey=' + sesskey
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.url) { return; }
                saveDatastore(data.attemptno);
                var src = data.url + (data.url.indexOf('?') !== -1 ? '&' : '?') + 'page=0&proview_iframe=1';
                iframe.src = src;
                iframe.style.display = 'block';
            });
        } else {
            saveDatastore({$jsattemptno});
            iframe.style.display = 'block';
        }
    }
});

window.addEventListener('message', function(event) {
    if (!event.data || event.data.type !== 'stopProview') { return; }
    var dest = event.data.url;
    if (window.ProctorClient3 && window.ProviewStatus === 'start') {
        window.ProctorClient3.stop(function() {
            window.ProviewStatus = 'stop';
            window.location.href = dest;
        });
    } else {
        window.location.href = dest;
    }
});
</script>
HTML;

echo $OUTPUT->footer();
