<?php

require('../../../../config.php');

global $DB, $CFG;

require_once('../../../../stork2/storkSignResponse.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

// Read stork saml response
$stork_attributes = parseStorkResponse();

$submission = unserialize($_SESSION['submission']);
$submitted = (isset($_SESSION['submitted']) ? $_SESSION['submitted'] : null);
$event_params = (isset($_SESSION['event_params']) ? unserialize($_SESSION['event_params']) : null);
$cmid = $_SESSION['cmid'];
$data = (isset($_SESSION['data']) ? unserialize($_SESSION['data']) : null);
unset($_SESSION['submission']);
unset($_SESSION['event_params']);
unset($_SESSION['cmid']);
unset($_SESSION['data']);
unset($_SESSION['submitted']);

if ($stork_attributes) {
	$stork_token = $stork_attributes['eIdentifier'];

	$esign = $DB->get_records('assignsubmission_esign', array('submission' => $submission->id));

	foreach ($esign as $e) {
		$e->signedtoken = $stork_token;
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

	$_SESSION['submission_signed'] = true;
	$assignment = new assign($context, $cm, $course);
	$notices = null;

	$event = \assignsubmission_esign\event\submission_signed::create($event_params);
	$event->trigger();

    $nextpageparams = array();
    $nextpageparams['id'] = $assignment->get_course_module()->id;
    $nextpageparams['sesskey'] = sesskey();
	if ($submitted) {
		$nextpageparams['action'] = 'confirmsubmit';
		$nextpageurl = new moodle_url('/mod/assign/view.php', $nextpageparams);
		redirect($nextpageurl);
	} else {
		$assignment->save_submission($submission, $notices);
		$nextpageparams['action'] = 'savesubmission';
		$nextpageurl = new moodle_url('/mod/assign/view.php', $nextpageparams);
		redirect('../../view.php?id='.$cmid);
	}

}
