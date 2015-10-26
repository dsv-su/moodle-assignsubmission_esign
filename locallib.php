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
 * This file contains the definition for the library class for e-signature submission plugin
 *
 * @package   assignsubmission_esign
 * @copyright 2015 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die();

 require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

/**
 * Library class for e-signature submission plugin extending submission plugin base class
 *
 * @package    assignsubmission_esign
 * @copyright  2015 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_esign extends assign_submission_plugin {

    /**
     * Get the name of the online comment submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignsubmission_esign');
    }

    /**
     * Add elements to submission form
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        if ($this->get_signedtoken($submission)) {
            $mform->addElement('static', 'description', 'E-signature status', 'You have already signed your submission');
        } else {
            $mform->addElement('static', 'description', 'E-signature status', 'You can sign your submission by clicking the button below.');
            $mform->addElement('button', 'intro', 'Sign my submission');
        }
        return true;
    }

    public function view_summary(stdClass $submission, & $showviewlink) {
        global $DB;

        // Never show a link to view full submission.
        $showviewlink = false;
        // Let's try to display signed token info.
        $output = 'The submission is signed by '.$signedtoken->signee.
            ' on '.userdate($signedtoken->timesigned);
        return $output;
    }

    /**
     * Save the files and sign them with the token gotten from PEPS.
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $DB;

        $plugins = $this->assignment->get_submission_plugins();
        $fileplugin = '';

        foreach ($plugins as $plugin) {
            if ($plugin->is_enabled() && ($plugin->get_name() == 'File submissions')) {
                $fileplugin = $plugin;
            }
        }

        if ($fileplugin) {
            $fs = get_file_storage();

            $files = $fs->get_area_files($this->assignment->get_context()->id,
                                         'assignsubmission_file',
                                         ASSIGNSUBMISSION_FILE_FILEAREA,
                                         $submission->id,
                                         'timemodified',
                                         false);
            //var_dump($files);
        }

        //Imagine that we already have the token.
        $token = '456abc';

        if ($token) {
            //Check if it is already signed.
            $signedtoken = $this->get_signedtoken($submission);
            if (!$signedtoken) {
                $esign = new stdClass();
                $esign->checksum = '123abc';
                $esign->signedtoken = 'abcdef';
                $esign->contextid = $this->assignment->get_context()->id;
                $esign->component = 'assignsubmission_file';
                $esign->area = 'submission_files';
                $esign->itemid = $submission->id;
                $esign->userid = $submission->userid;
                $esign->signee = 'Adam Andersson';
                $esign->timesigned = time();

                $DB->insert_record('esign', $esign);
            } else {
                // Just update some info. But what info...? only time?
                // TO DO.
                // maybe the files changed.
                $esign->checksum = 'checksum';
            }
        }

        return true;
    }

    /**
     * Returns false if the submission is signed.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        return $this->get_signedtoken($submission) == false;
    }

    /**
     * Returns signedtoken if the submission is signed.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function get_signedtoken(stdClass $submission) {
        global $DB;

        $signedtoken = $DB->get_record('esign', array(
            'contextid' => $this->assignment->get_context()->id,
            'userid' => $submission->userid));
        if ($signedtoken) {
            return $signedtoken;
        } else {
            return false;
        }
    }

}
