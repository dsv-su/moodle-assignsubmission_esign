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
 * This file contains simulation of token generation for e-sign plugin.
 *
 * @package    assignsubmission_esign
 * @copyright  2015 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../../config.php');

/* PEPS communication */
require_once('../../../../stork2/storkRequest.php');

$s = '';
if(isset($_SERVER['HTTPS'])) {
    if ($_SERVER['HTTPS'] == "on") {
        $s = 's';
    }
}

$url = "http$s://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

$postDetails = array(
    "spcountry" => "SE",
    "country" => $_GET["country"],
    "qaaLevel" => "3",
    "assertionUrl" => str_replace(strrchr($url, '/'), '', $url)."/peps-sign-response.php",
    "eIdentifierType" => "true",
);

sendStorkRequest($postDetails);
