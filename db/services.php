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
 * Roleplay external functions and service definitions.
 *
 * @package    mod_roleplay
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(

    'mod_roleplay_get_roleplay_results' => array(
        'classname'     => 'mod_roleplay_external',
        'methodname'    => 'get_roleplay_results',
        'description'   => 'Retrieve users results for a given roleplay.',
        'type'          => 'read',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_roleplay_get_roleplay_options' => array(
        'classname'     => 'mod_roleplay_external',
        'methodname'    => 'get_roleplay_options',
        'description'   => 'Retrieve options for a specific roleplay.',
        'type'          => 'read',
        'capabilities'  => 'mod/roleplay:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_roleplay_submit_roleplay_response' => array(
        'classname'     => 'mod_roleplay_external',
        'methodname'    => 'submit_roleplay_response',
        'description'   => 'Submit responses to a specific roleplay item.',
        'type'          => 'write',
        'capabilities'  => 'mod/roleplay:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_roleplay_view_roleplay' => array(
        'classname'     => 'mod_roleplay_external',
        'methodname'    => 'view_roleplay',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_roleplay_get_roleplays_by_courses' => array(
        'classname'     => 'mod_roleplay_external',
        'methodname'    => 'get_roleplays_by_courses',
        'description'   => 'Returns a list of roleplay instances in a provided set of courses,
                            if no courses are provided then all the roleplay instances the user has access to will be returned.',
        'type'          => 'read',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_roleplay_delete_roleplay_responses' => array(
        'classname'     => 'mod_roleplay_external',
        'methodname'    => 'delete_roleplay_responses',
        'description'   => 'Delete the given submitted responses in a roleplay',
        'type'          => 'write',
        'capabilities'  => 'mod/roleplay:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
