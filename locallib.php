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
        if (!$this->assignment->get_instance()->submissiondrafts) {
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
     * Gets the files from the file submission plugin.
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    function get_files_to_sign($submission) {
        global $DB;

        $files = $this->get_submitted_files($submission);
        if (empty($files)) {
            $files = array();
            $file = new stdClass();
        }

        if (count($files)) {

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

            return $filestosign;
        }
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

        $cmid = $this->assignment->get_course_module()->id;
        if (isset($_SESSION['assing'.$cmid]['submission_signed'])
            && $_SESSION['assing'.$cmid]['submission_signed']) {
            unset($_SESSION['assing'.$cmid]['submission_signed']);
            return true;
        }

        if ($this->assignment->get_instance()->submissiondrafts) {
            //If this assignment allows drafts, delay the e-signing until the user sends it.
            return true;
        }

        $this->process_initial_esigning($submission, $data);

        redirect('submission/esign/peps-sign-request.php?country='.$data->country);
    }

    /**
     * Process the initial e-signing of the submission and populates SESSION.
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    function process_initial_esigning($submission, $data = null) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $submission->userid));

        if ($this->file_submission_enabled()) {
            $filestosign = $this->get_files_to_sign($submission);
            if (!$filestosign) {
                $this->set_error(get_string('filemissing', 'assignsubmission_esign'));
                return false; 
            }
        }

        $esign = $this->get_signature($submission);
        if (!$esign) {
            $esign = new stdClass();
            $esign->signedtoken = 'empty_token';
            $esign->contextid = $this->assignment->get_context()->id;
            $esign->submission = $submission->id;
            $esign->userid = $submission->userid;
            $esign->signee = fullname($user);
            $esign->timesigned = time();

            if ($this->file_submission_enabled() && $filestosign) {
                foreach ($filestosign as $file) {
                    $esign->checksum = $file->get_contenthash();
                    $esign->area = 'submission_files';
                    $DB->insert_record('assignsubmission_esign', $esign);
                }
            } else {
                $esign->checksum = 'empty_checksum';
                $esign->area = 'submissions';
                $DB->insert_record('assignsubmission_esign', $esign);
            }
        }

        $cmid = $this->assignment->get_course_module()->id;

        $_SESSION['assing'.$cmid] = array();
        $_SESSION['assing'.$cmid]['submission'] = serialize($submission);
        if ($data) {
            $_SESSION['assing'.$cmid]['data'] = serialize($data);
        }

        $params = array(
            'context' => $this->assignment->get_context(),
            'courseid' => $this->assignment->get_course()->id
        );
        $params['other']['submissionid'] = $submission->id;
        $params['other']['submissionattempt'] = $submission->attemptnumber;
        $params['other']['submissionstatus'] = $submission->status;

        $_SESSION['assing'.$cmid]['event_params'] = serialize($params);
        $_SESSION['cmid'] = $this->assignment->get_course_module()->id;

        return;
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

    /**
     * Check if the submission plugin has all the required data to allow the work
     * to be submitted for grading
     * @param stdClass $submission the assign_submission record being submitted.
     * @return bool|string 'true' if OK to proceed with submission, otherwise a
     *                        a message to display to the user
     */
    public function precheck_submission($submission) {
        global $DB, $CFG;

        if (!$this->is_empty($submission)) {
            return true;
        }

        $this->process_initial_esigning($submission);

        $_SESSION['assing'.$this->assignment->get_course_module()->id]['submitted'] = true;

        redirect(new moodle_url('submission/esign/esign.php',
                                    array('id'=>$this->assignment->get_course_module()->id)));
    }
}
