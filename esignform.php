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
 * This file contains the definition for the e-sign form class for e-signature assign submission plugin
 *
 *
 * @package   assignsubmission_esign
 * @copyright 2016 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/mod/assign/submission/esign/locallib.php');

/**
 * Assignment grading options form
 *
 * @package   assignsubmission_esign
 * @copyright 2016 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignsubmission_esign_esign_form extends moodleform {
    /**
     * Define this form - called by the parent constructor
     */
    public function definition() {
        global $COURSE, $USER;

        $mform = $this->_form;
        $params = $this->_customdata;

        $choices = get_string_manager()->get_list_of_countries();
        $choices = array('' => get_string('selectacountry') . '...') + $choices;
        $mform->addElement('select', 'country', 'Country for E-signature', $choices);
        $mform->setDefault('country', 'SE');
        if (isset($_SESSION['signedtoken']) && $_SESSION['signedtoken']) {
            $mform->addElement('static', 'description', '', get_string('esignalreadyadded', 'assignfeedback_esign'));
        } else {
            $mform->addElement('static', 'description', '', get_string('addesignforall', 'assignfeedback_esign'));
        }
        $mform->addRule('country', get_string('selectacountry'), 'required', '', 'client', false, false);

        $mform->addElement('hidden', 'id', $params['cm']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'viewpluginpage');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'pluginaction', 'addesign');
        $mform->setType('pluginaction', PARAM_ALPHA);
        $mform->addElement('hidden', 'plugin', 'esign');
        $mform->setType('plugin', PARAM_PLUGIN);
        $mform->addElement('hidden', 'pluginsubtype', 'assignsubmission');
        $mform->setType('pluginsubtype', PARAM_PLUGIN);
        if (isset($_SESSION['signedtoken']) && $_SESSION['signedtoken']) {
            $this->add_action_buttons(true, get_string('updateesign', 'assignfeedback_esign'));
        } else {
            $this->add_action_buttons(true, get_string('addesign', 'assignfeedback_esign'));
        }

    }

}

