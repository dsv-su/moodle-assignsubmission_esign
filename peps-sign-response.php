<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains peps response handling for e-sign plugin.
 *
 * @package    assignsubmission_esign
 * @copyright  2016 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../../config.php');

global $DB, $CFG;

require_once('../../../../stork2/storkSignResponse.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

// Read stork saml response.
$storkattributes = parseStorkResponse();

$cmid = $_SESSION['cmid'];
$submission = (isset($_SESSION['assign'.$cmid]['submission']) ? unserialize($_SESSION['assign'.$cmid]['submission']) : null);
$submitted = (isset($_SESSION['assign'.$cmid]['submitted']) ? $_SESSION['assign'.$cmid]['submitted'] : null);
$eventparams = (isset($_SESSION['assign'.$cmid]['event_params']) ? unserialize($_SESSION['assign'.$cmid]['event_params']) : null);
$data = (isset($_SESSION['assign'.$cmid]['data']) ? unserialize($_SESSION['assign'.$cmid]['data']) : null);
unset($_SESSION['assign'.$cmid]);
unset($_SESSION['cmid']);

if ($storkattributes) {
    $storktoken = $storkattributes['eIdentifier'];

    $esign = $DB->get_records('assignsubmission_esign', array('submission' => $submission->id));

    foreach ($esign as $e) {
        $e->signedtoken = $storktoken;
        $e->timesigned = time();
        $DB->update_record('assignsubmission_esign', $e);
    }

    $context = context_module::instance($cmid);
    $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

    $PAGE->set_context($context);
    $PAGE->set_url('/mod/assign/view.php', array('id' => $cmid));
    $PAGE->set_course($course);
    $PAGE->set_cm($cm);
    $PAGE->set_title(get_string('pluginname', 'assignsubmission_esign'));
    $PAGE->set_pagelayout('standard');

    $_SESSION['assign'.$cmid]['submission_signed'] = true;
    $assignment = new assign($context, $cm, $course);
    $notices = null;

    $event = \assignsubmission_esign\event\submission_signed::create($eventparams);
    $event->trigger();

    $nextpageparams = array();
    $nextpageparams['id'] = $assignment->get_course_module()->id;
    $nextpageparams['sesskey'] = sesskey();
    if ($submitted) {
        $nextpageparams['action'] = 'confirmsubmit';
        $nextpageurl = new moodle_url('/mod/assign/view.php', $nextpageparams);
        redirect($nextpageurl);
    } else {
        $assignment->save_submission($data, $notices);
        $nextpageparams['action'] = 'savesubmission';
        $nextpageurl = new moodle_url('/mod/assign/view.php', $nextpageparams);
        redirect('../../view.php?id='.$cmid);
    }

}
