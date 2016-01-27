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
        if ($this->file_submission_enabled()) {
            $choices = get_string_manager()->get_list_of_countries();
            $choices = array('' => get_string('selectacountry') . '...') + $choices;
            $mform->addElement('select', 'country', 'Country for E-signature', $choices);
            $mform->addElement('static', 'description', '', get_string('savechanges', 'assignsubmission_esign'));
            $mform->setDefault('country', 'SE');
            $mform->addRule('country', get_string('selectacountry'), 'required', '', 'client', false, false);
        }
        return true;
    }

    public function view_summary(stdClass $submission, & $showviewlink) {
        global $DB;

        // Never show a link to view full submission.
        $showviewlink = false;
        // Let's try to display signed token info.
        $esign = $this->get_signature($submission);
        if ($esign) {
            $esign = array_pop($esign);
            if ($esign->signedtoken <> 'empty_token') {
                $esign->timesigned = userdate($esign->timesigned);
                $output = get_string('signedby', 'assignsubmission_esign', $esign);
                return $output;
            }
        }
        return false;
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

        if (isset($_SESSION['submission_signed']) && $_SESSION['submission_signed']) {
            unset($_SESSION['submission_signed']);
            return true;
        }

        $files = $this->get_submitted_files($submission);

        $user = $DB->get_record('user', array('id' => $submission->userid));

        if (empty($files)) {
            $files = array();
            $file = new stdClass();
        }

        if ($this->file_submission_enabled() && count($files)) {

            // Check which files to sign, and which signatures to delete.
            $filestosign = array();
            $esignstodelete = array();
            $esignstosave = array();

            $esigns = $this->get_signature($submission);

            if ($esigns) {
                foreach ($files as $file) {
                    if (!$esign = $DB->get_record('assignsubmission_esign', array(
                        'checksum' => $file->get_contenthash(),
                        'submission' => $submission->id
                        ))) {
                        $filestosign[] = $file;
                    } else {
                        $esignstosave[] = $esign;
                    }
                }

                $esignstodelete = array_udiff($esigns, $esignstosave,
                    function ($obj_a, $obj_b) {
                        return $obj_a->id - $obj_b->id;
                    }
                );

                if ($esignstodelete) {
                    foreach ($esignstodelete as $esign) {
                        $DB->delete_records('assignsubmission_esign', array(
                        'checksum' => $esign->checksum,
                        'submission' => $submission->id
                        ));
                    }
                }
            } else {
                $filestosign = $files;
            }

            if ($filestosign) {
                foreach ($filestosign as $file) {
                    // Creating a dummy value.
                    $esign = new stdClass();
                    $esign->checksum = $file->get_contenthash();
                    $esign->signedtoken = 'empty_token';
                    $esign->contextid = $this->assignment->get_context()->id;
                    $esign->area = 'submission_files';
                    $esign->submission = $submission->id;
                    $esign->userid = $submission->userid;
                    $esign->signee = fullname($user);
                    $esign->timesigned = time();

                    $DB->insert_record('assignsubmission_esign', $esign);
                }
            }

            $_SESSION['submission'] = serialize($submission);
            $_SESSION['data'] = serialize($data);

            $params = array(
                'context' => $this->assignment->get_context(),
                'courseid' => $this->assignment->get_course()->id
            );
            $params['other']['submissionid'] = $submission->id;
            $params['other']['submissionattempt'] = $submission->attemptnumber;
            $params['other']['submissionstatus'] = $submission->status;

            $_SESSION['event_params'] = serialize($params);
            $_SESSION['cmid'] = $this->assignment->get_course_module()->id;

            redirect('submission/esign/peps-sign-request.php?country='.$data->country);

        } else if (!$this->file_submission_enabled()) {
            return true;
        } else {
            $this->set_error(get_string('filemissing', 'assignsubmission_esign'));
            return false;
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
        $esign = $this->get_signature($submission);
        if ($esign) {
            return (array_pop($esign)->signedtoken == 'empty_token');
        } else {
            return true;
        }
    }

    /**
     * Returns esign if the submission is signed.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function get_signature(stdClass $submission) {
        global $DB;

        $esign = $DB->get_records('assignsubmission_esign', array('submission' => $submission->id));
        if ($esign) {
            return $esign;
        } else {
            return false;
        }
    }

    /**
     * Checks whether file submission plugin is enabled for this assignment.
     *
     * @return bool
     */
    public function file_submission_enabled() {
        $plugins = $this->assignment->get_submission_plugins();
        $fileplugin = '';

        foreach ($plugins as $plugin) {
            if ($plugin->is_enabled() && ($plugin->get_name() == 'File submissions')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns files that are associated with the submission.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function get_submitted_files(stdClass $submission) {
        if ($this->file_submission_enabled()) {
            $fs = get_file_storage();

            $files = $fs->get_area_files($this->assignment->get_context()->id,
                                         'assignsubmission_file',
                                         ASSIGNSUBMISSION_FILE_FILEAREA,
                                         $submission->id,
                                         'timemodified',
                                         false);
            return $files;
        }

        return 0;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        $DB->delete_records('assignsubmission_esign', array(
            'contextid' => $this->assignment->get_context()->id
        ));
        return true;
    }
}
