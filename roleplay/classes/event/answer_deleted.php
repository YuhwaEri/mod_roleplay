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
 * The mod_roleplay answer deleted event.
 *
 * @package    mod_roleplay
 * @copyright  2016 Stephen Bourget
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_roleplay\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_roleplay answer deleted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int roleplayid: id of roleplay.
 *      - int optionid: id of the option.
 * }
 *
 * @package    mod_roleplay
 * @since      Moodle 3.1
 * @copyright  2016 Stephen Bourget
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_deleted extends \core\event\base {

    /**
     * Creates an instance of the event from the records
     *
     * @param stdClass $roleplayanswer record from 'roleplay_answers' table
     * @param stdClass $roleplay record from 'roleplay' table
     * @param stdClass $cm record from 'course_modules' table
     * @param stdClass $course
     * @return self
     */
    public static function create_from_object($roleplayanswer, $roleplay, $cm, $course) {
        global $USER;
        $eventdata = array();
        $eventdata['objectid'] = $roleplayanswer->id;
        $eventdata['context'] = \context_module::instance($cm->id);
        $eventdata['userid'] = $USER->id;
        $eventdata['courseid'] = $course->id;
        $eventdata['relateduserid'] = $roleplayanswer->userid;
        $eventdata['other'] = array();
        $eventdata['other']['roleplayid'] = $roleplay->id;
        $eventdata['other']['optionid'] = $roleplayanswer->optionid;
        $event = self::create($eventdata);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('roleplay', $roleplay);
        $event->add_record_snapshot('roleplay_answers', $roleplayanswer);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has deleted the option with id '" . $this->other['optionid'] . "' for the
            user with id '$this->relateduserid' from the roleplay activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventanswerdeleted', 'mod_roleplay');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/roleplay/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'roleplay_answers';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['roleplayid'])) {
            throw new \coding_exception('The \'roleplayid\' value must be set in other.');
        }

        if (!isset($this->other['optionid'])) {
            throw new \coding_exception('The \'optionid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'roleplay_answers', 'restore' => \core\event\base::NOT_MAPPED);
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['roleplayid'] = array('db' => 'roleplay', 'restore' => 'roleplay');
        $othermapped['optionid'] = array('db' => 'roleplay_options', 'restore' => 'roleplay_option');

        return $othermapped;
    }
}
