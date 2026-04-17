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

global $DB, $USER, $PAGE, $OUTPUT, $CFG, $SESSION;

$config = $DB->get_record('quizaccess_proview', ['quizid' => $quizid], '*', MUST_EXIST);
$quiz   = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$cm     = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);

if (!empty($quiz->password)) {
    if (!isset($SESSION->passwordcheckedquizzes)) {
        $SESSION->passwordcheckedquizzes = [];
    }
    $SESSION->passwordcheckedquizzes[$quizid] = true;
}

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

$cdnurl = (string) get_config('quizaccess_proview', 'proview_cdn_url');

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
$jssesskey            = json_encode(sesskey());
$jsajaxurl            = json_encode($CFG->wwwroot . '/mod/quiz/accessrule/proview/startattempt_ajax.php');
$jsisnewattempt       = $isnewattempt ? 'true' : 'false';
$jsurlwithflag        = json_encode($urlwithflag);
$showpasswordnotice   = !empty($quiz->password);

echo $OUTPUT->header();

$iframesrc = s($urlwithflag);
$passwordnoticehtml = $showpasswordnotice ? '
<div id="proview-password-overlay" style="
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    font-family: sans-serif;">
  <div style="
      background: #fff; border-radius: 8px;
      padding: 40px 48px; text-align: center;
      max-width: 420px; width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,0.2);">
    <div style="
        width: 56px; height: 56px; border-radius: 50%;
        background: #1cb841; display: flex; align-items: center;
        justify-content: center; margin: 0 auto 20px;">
      <span style="font-size: 26px; line-height: 1;">&#128274;</span>
    </div>
    <h2 style="margin: 0 0 10px; font-size: 18px; color: #222;">Password Verified</h2>
    <p style="margin: 0 0 8px; font-size: 14px; color: #555; line-height: 1.5;">
      This is a <strong>proctored exam</strong>. Your session will be monitored by Proview.
    </p>
    <p style="margin: 0 0 28px; font-size: 13px; color: #888; line-height: 1.5;">
      Quiz password has been automatically verified. Click Continue to continue with your exam.
    </p>
    <button onclick="startProview()" style="
        background: #1cb841; color: #fff; border: none; border-radius: 4px;
        padding: 10px 32px; font-size: 15px; font-weight: bold; cursor: pointer;">
      Continue
    </button>
  </div>
</div>' : '';
$autostartjs = $showpasswordnotice ? '' : 'startProview();';
echo <<<HTML
<style>
  body, html { margin: 0; padding: 0; overflow: hidden; }
  #proview-quiz-frame { width: 100vw; height: 100vh; border: none; display: none; }
</style>
{$passwordnoticehtml}
<iframe id="proview-quiz-frame" src="{$iframesrc}"
        style="width:100vw;height:100vh;border:none;display:none"></iframe>
<script>

if (window.self !== window.top) {
    var _iframeSrc = {$jsurlwithflag};
    if (_iframeSrc) {
        window.location.replace(_iframeSrc);
    }
}

function startProview() {
    var notice = document.getElementById('proview-password-overlay');
    if (notice) { notice.style.display = 'none'; }
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
        enforceTSB:            true,
        previewStyle:          'position: fixed; bottom: 0px;',
        initCallback:          function(err, sessionUuid) {
            if (err) { return; }
            window.ProviewStatus = 'start';
            var isNew   = {$jsisnewattempt};
            var iframe  = document.getElementById('proview-quiz-frame');
            var sesskey = {$jssesskey};
            var quizId  = {$jsquizid};
            if (isNew) {
                fetch({$jsajaxurl}, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    'quizid=' + quizId + '&cmid=' + {$jscmid} + '&sesskey=' + sesskey
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.url) { return; }
                    var src = data.url + (data.url.indexOf('?') !== -1 ? '&' : '?') + 'page=0&proview_iframe=1';
                    iframe.src = src;
                    iframe.style.display = 'block';
                });
            } else {
                iframe.style.display = 'block';
            }
        }
    });
}

{$autostartjs}

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
