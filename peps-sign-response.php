<?php

require('../../../../config.php');

require_once('../../../../stork2/storkSignResponse.php');

// Read stork saml response
$stork_attributes = parseStorkResponse();
if ($stork_attributes) {
	$stork_token = $stork_attributes['eIdentifier'];
	$_SESSION['esign_token'] = $stork_token;
	echo "stork_token: " . $stork_token . "<br>";
}

