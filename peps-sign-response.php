<?php

require('../../../../config.php');

global $DB, $CFG;

require_once('../../../../stork2/storkSignResponse.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

// Read stork saml response
$stork_attributes = parseStorkResponse();

$cmid = $_SESSION['cmid'];
$submission = (isset($_SESSION['assign'.$cmid]['submission']) ? unserialize($_SESSION['assign'.$cmid]['submission']) : null);
$submitted = (isset($_SESSION['assign'.$cmid]['submitted']) ? $_SESSION['assign'.$cmid]['submitted'] : null);
$event_params = (isset($_SESSION['assign'.$cmid]['event_params']) ? unserialize($_SESSION['assign'.$cmid]['event_params']) : null);
$data = (isset($_SESSION['assign'.$cmid]['data']) ? unserialize($_SESSION['assign'.$cmid]['data']) : null);
unset($_SESSION['assign'.$cmid]);
unset($_SESSION['cmid']);

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

	$_SESSION['assign'.$cmid]['submission_signed'] = true;
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
		$assignment->save_submission($data, $notices);
		$nextpageparams['action'] = 'savesubmission';
		$nextpageurl = new moodle_url('/mod/assign/view.php', $nextpageparams);
		redirect('../../view.php?id='.$cmid);
	}

}
