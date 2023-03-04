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
 * Internal library of functions for roleplay module.
 *
 * All the roleplay specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_roleplay
 * @copyright 2016 Stephen Bourget
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * This creates new calendar events given as timeopen and timeclose by $roleplay.
 *
 * @param stdClass $roleplay
 * @return void
 */
function roleplay_set_events($roleplay) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/calendar/lib.php');

    // Get CMID if not sent as part of $roleplay.
    if (!isset($roleplay->coursemodule)) {
        $cm = get_coursemodule_from_instance('roleplay', $roleplay->id, $roleplay->course);
        $roleplay->coursemodule = $cm->id;
    }

    // Roleplay start calendar events.
    $event = new stdClass();
    $event->eventtype = ROLEPLAY_EVENT_TYPE_OPEN;
    // The ROLEPLAY_EVENT_TYPE_OPEN event should only be an action event if no close time is specified.
    $event->type = empty($roleplay->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
    if ($event->id = $DB->get_field('event', 'id',
            array('modulename' => 'roleplay', 'instance' => $roleplay->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($roleplay->timeopen)) && ($roleplay->timeopen > 0)) {
            // Calendar event exists so update it.
            $event->name         = get_string('calendarstart', 'roleplay', $roleplay->name);
            $event->description  = format_module_intro('roleplay', $roleplay, $roleplay->coursemodule);
            $event->timestart    = $roleplay->timeopen;
            $event->timesort     = $roleplay->timeopen;
            $event->visible      = instance_is_visible('roleplay', $roleplay);
            $event->timeduration = 0;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($roleplay->timeopen)) && ($roleplay->timeopen > 0)) {
            $event->name         = get_string('calendarstart', 'roleplay', $roleplay->name);
            $event->description  = format_module_intro('roleplay', $roleplay, $roleplay->coursemodule);
            $event->courseid     = $roleplay->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'roleplay';
            $event->instance     = $roleplay->id;
            $event->timestart    = $roleplay->timeopen;
            $event->timesort     = $roleplay->timeopen;
            $event->visible      = instance_is_visible('roleplay', $roleplay);
            $event->timeduration = 0;
            calendar_event::create($event, false);
        }
    }

    // Roleplay end calendar events.
    $event = new stdClass();
    $event->type = CALENDAR_EVENT_TYPE_ACTION;
    $event->eventtype = ROLEPLAY_EVENT_TYPE_CLOSE;
    if ($event->id = $DB->get_field('event', 'id',
            array('modulename' => 'roleplay', 'instance' => $roleplay->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($roleplay->timeclose)) && ($roleplay->timeclose > 0)) {
            // Calendar event exists so update it.
            $event->name         = get_string('calendarend', 'roleplay', $roleplay->name);
            $event->description  = format_module_intro('roleplay', $roleplay, $roleplay->coursemodule);
            $event->timestart    = $roleplay->timeclose;
            $event->timesort     = $roleplay->timeclose;
            $event->visible      = instance_is_visible('roleplay', $roleplay);
            $event->timeduration = 0;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($roleplay->timeclose)) && ($roleplay->timeclose > 0)) {
            $event->name         = get_string('calendarend', 'roleplay', $roleplay->name);
            $event->description  = format_module_intro('roleplay', $roleplay, $roleplay->coursemodule);
            $event->courseid     = $roleplay->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'roleplay';
            $event->instance     = $roleplay->id;
            $event->timestart    = $roleplay->timeclose;
            $event->timesort     = $roleplay->timeclose;
            $event->visible      = instance_is_visible('roleplay', $roleplay);
            $event->timeduration = 0;
            calendar_event::create($event, false);
        }
    }
}
