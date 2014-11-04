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
 * Internal library of functions for module ratingallocate
 *
 * All the ratingallocate specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package mod_ratingallocate
 * @copyright 2014 M Schulze
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// namespace is mandatory!

namespace ratingallocate\strategy_points;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once(dirname(__FILE__) . '/../locallib.php');
require_once(dirname(__FILE__) . '/strategy_template.php');

class strategy extends \strategytemplate {

    const STRATEGYNAME = 'Points';
    const STRATEGYID = 'strategy_points';
    const MAXZERO = 'maxzero';
    const TOTALPOINTS = 'totalpoints';

    public static function get_settingfields() {
        return array(
            self::MAXZERO => array(// maximale Anzahl 'kannnicht'
                'text',
                get_string(self::STRATEGYID . '_setting_maxzero', 'ratingallocate')
            ),
            self::TOTALPOINTS => array(// wie viele Felder es gibt
                'text',
                get_string(self::STRATEGYID . '_setting_totalpoints', 'ratingallocate')
            )
        );
    }

}

// register with the strategymanager
\strategymanager::add_strategy(strategy::STRATEGYID);

class mod_ratingallocate_view_form extends \ratingallocate_strategyform {

    public function definition() {
        global $COURSE, $PAGE, $DB, $USER;

        $mform = $this->_form;

        $ratingdata = $this->ratingallocate->get_rating_data_for_user($USER->id);

        $renderer = $PAGE->get_renderer('mod_ratingallocate');

        $mform->addElement('hidden', 'action', RATING_ALLOC_ACTION_RATE);
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('courseid', PARAM_INT);

        foreach ($ratingdata as $data) {
            $headerelem = 'head_ratingallocate_' . $data->choiceid;
            $elemprefix = 'data[' . $data->choiceid . ']';
            $ratingelem = $elemprefix . '[rating]';
            $groupsidelem = $elemprefix . '[choiceid]';

            // choiceid ablegen
            $mform->addElement('hidden', $groupsidelem, $data->choiceid);
            $mform->setType($groupsidelem, PARAM_INT);

            // title anzeigen
            $mform->addElement('header', $headerelem, $data->title);
            $mform->setExpanded($headerelem);

            // Beschreibungstext anzeigen
            $mform->addElement('html', '<div>' . $data->explanation . '</div>');

            $mform->addElement('text', $ratingelem, $data->title);
            $mform->setType($ratingelem, PARAM_INT);

            // try to restore previous ratings
            if (is_numeric($data->rating) && $data->rating >= 0) {
                $mform->setDefault($ratingelem, $data->rating);
            } else {
                $mform->setDefault($ratingelem, 1);
            }
        }
        // If there are no choices to rate, notify the user.
        if (count($ratingdata) > 0) {
            $this->add_action_buttons();
        } else {
            $box = $renderer->notification(get_string('no_groups_to_rate', 'ratingallocate'));
            $mform->addElement('html', $box);
        }
    }

    public function describe_strategy() {
        $strategyoptions = json_decode($this->ratingallocate->ratingallocate->setting, true);

        $output = get_string('strategyname', 'ratingallocate', strategy::STRATEGYNAME) . '<br />';
        $output .= get_string(strategy::STRATEGYID . '_explain_distribute_points', 'ratingallocate', $strategyoptions [strategy::STRATEGYID] [strategy::TOTALPOINTS]) . '<br />';
        $output .= get_string(strategy::STRATEGYID . '_explain_max_zero', 'ratingallocate', $strategyoptions [strategy::STRATEGYID] [strategy::MAXZERO]);

        return $output;
    }

    public function validation($data, $files) {
        $maxcrossout = json_decode($this->ratingallocate->ratingallocate->setting, true)[strategy::STRATEGYID][strategy::MAXZERO];
        $totalpoints = json_decode($this->ratingallocate->ratingallocate->setting, true)[strategy::STRATEGYID][strategy::TOTALPOINTS];
        $errors = parent::validation($data, $files);

        if (!array_key_exists('data', $data) or count($data ['data']) < 2) {
            return $errors;
        }

        $impossibles = 0;
        $ratings = $data ['data'];
        $currentpoints = 0;
        foreach ($ratings as $rating) {
            if ($rating ['rating'] == 0) {
                $impossibles ++;
            }
            $currentpoints += $rating['rating'];
        }

        if ($impossibles > $maxcrossout) {
            foreach ($ratings as $cid => $rating) {
                if ($rating ['rating'] == 0) {
                    $errors ['data[' . $cid . '][rating]'] = get_string(strategy::STRATEGYID . '_max_count_zero', 'ratingallocate', $maxcrossout);
                }
            }
        }

        if ($currentpoints <> $totalpoints) {
            foreach ($ratings as $cid => $rating) {
                $errors ['data[' . $cid . '][rating]'] = get_string(strategy::STRATEGYID . '_incorrect_totalpoints', 'ratingallocate', $totalpoints);
            }
        }
        return $errors;
    }

}
