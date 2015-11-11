<?php

require('../../../../config.php');

// Insert reading of PEPS SAML response data here

// Define the returned token and redirect back to original submission form page
$_SESSION['esign_token'] = 'returned_token';
redirect($_SESSION['esign_returnpath']);

