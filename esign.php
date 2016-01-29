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
 * Template editing view for the mailsimulator submission plugin.
 *
 * @package assignsubmission_mailsimulator
 * @copyright 2013 Department of Computer and System Sciences,
 *                  Stockholm University  {@link http://dsv.su.se}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../../config.php');
global $CFG, $DB, $PAGE, $COURSE;

$id      = required_param('id', PARAM_INT);
$cm      = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course);
require_capability('mod/assign:submit', $context);

$PAGE->set_url('/mod/assign/view.php', array('id' => $id));
$PAGE->set_title(get_string('pluginname', 'assignsubmission_esign'));
$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);

require_once($CFG->dirroot.'/mod/assign/locallib.php');
$assignment = new assign($context, $cm, $course);

require_once($CFG->dirroot.'/mod/assign/submission/esign/esignform.php');

$formparams = array('cm'=>$assignment->get_course_module()->id,
        'context'=>$assignment->get_context());

$mform = new assignsubmission_esign_esign_form(null, $formparams);

if ($mform->is_cancelled()) {
    unset($_SESSION['assign'.$id]['submission_signed']);
    redirect(new moodle_url('view.php', array('id'=>$id)));
    return;
} else if ($data = $mform->get_data()) {
    $_SESSION['assign'.$id]['submission_signed'] = true;
    redirect('peps-sign-request.php?country='.$data->country);

    return;
} else {

    $header = new assign_header($assignment->get_instance(),
                                $assignment->get_context(),
                                false,
                                $assignment->get_course_module()->id,
                                get_string('addesign', 'assignfeedback_esign'));
    $o = '';
    $o .= $assignment->get_renderer()->render($header);
    $o .= $assignment->get_renderer()->render(new assign_form('esign', $mform));
    $o .= $assignment->get_renderer()->render_footer();
    echo $o;
}
