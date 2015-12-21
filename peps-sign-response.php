<?php

require('../../../../config.php');

global $DB, $CFG;

require_once('../../../../stork2/storkSignResponse.php');

// Read stork saml response
$stork_attributes = parseStorkResponse();
if ($stork_attributes) {
	$stork_token = $stork_attributes['eIdentifier'];

	$submission = unserialize($_SESSION['submission']);
	$event_params = unserialize($_SESSION['event_params']);
	$cmid = $_SESSION['cmid'];

	$esign = $DB->get_record('esign', array('contextid' => context_module::instance($cmid)->id, 'userid' => $submission->userid));
	$esign->signedtoken = $stork_token; //Some manipulation is needed?
	$esign->timesigned = time();

	$DB->update_record('esign', $esign);

	$event = \assignsubmission_esign\event\submission_signed::create($event_params);
	$event->trigger();

	redirect('../../view.php?id='.$cmid);

}
