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
 * Roleplay module external API
 *
 * @package    mod_roleplay
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/roleplay/lib.php');

/**
 * Roleplay module external functions
 *
 * @package    mod_roleplay
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_roleplay_external extends external_api {

    /**
     * Describes the parameters for get_roleplays_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_roleplay_results_parameters() {
        return new external_function_parameters (array('roleplayid' => new external_value(PARAM_INT, 'roleplay instance id')));
    }
    /**
     * Returns user's results for a specific roleplay
     * and a list of those users that did not answered yet.
     *
     * @param int $roleplayid the roleplay instance id
     * @return array of responses details
     * @since Moodle 3.0
     */
    public static function get_roleplay_results($roleplayid) {
        global $USER, $PAGE;

        $params = self::validate_parameters(self::get_roleplay_results_parameters(), array('roleplayid' => $roleplayid));

        if (!$roleplay = roleplay_get_roleplay($params['roleplayid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($roleplay, 'roleplay');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $groupmode = groups_get_activity_groupmode($cm);
        // Check if we have to include responses from inactive users.
        $onlyactive = $roleplay->includeinactive ? false : true;
        $users = roleplay_get_response_data($roleplay, $cm, $groupmode, $onlyactive);
        // Show those who haven't answered the question.
        if (!empty($roleplay->showunanswered)) {
            $roleplay->option[0] = get_string('notanswered', 'roleplay');
            $roleplay->maxanswers[0] = 0;
        }
        $results = prepare_roleplay_show_results($roleplay, $course, $cm, $users);

        $options = array();
        $fullnamecap = has_capability('moodle/site:viewfullnames', $context);
        foreach ($results->options as $optionid => $option) {

            $userresponses = array();
            $numberofuser = 0;
            $percentageamount = 0;
            if (property_exists($option, 'user') and
                (has_capability('mod/roleplay:readresponses', $context) or roleplay_can_view_results($roleplay))) {
                $numberofuser = count($option->user);
                $percentageamount = ((float)$numberofuser / (float)$results->numberofuser) * 100.0;
                if ($roleplay->publish) {
                    foreach ($option->user as $userresponse) {
                        $response = array();
                        $response['userid'] = $userresponse->id;
                        $response['fullname'] = fullname($userresponse, $fullnamecap);

                        $userpicture = new user_picture($userresponse);
                        $userpicture->size = 1; // Size f1.
                        $response['profileimageurl'] = $userpicture->get_url($PAGE)->out(false);

                        // Add optional properties.
                        foreach (array('answerid', 'timemodified') as $field) {
                            if (property_exists($userresponse, 'answerid')) {
                                $response[$field] = $userresponse->$field;
                            }
                        }
                        $userresponses[] = $response;
                    }
                }
            }

            $options[] = array('id'               => $optionid,
                               'text'             => external_format_string($option->text, $context->id),
                               'maxanswer'        => $option->maxanswer,
                               'userresponses'    => $userresponses,
                               'numberofuser'     => $numberofuser,
                               'percentageamount' => $percentageamount
                              );
        }

        $warnings = array();
        return array(
            'options' => $options,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the get_roleplay_results return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_roleplay_results_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'roleplay instance id'),
                            'text' => new external_value(PARAM_RAW, 'text of the roleplay'),
                            'maxanswer' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'userresponses' => new external_multiple_structure(
                                 new external_single_structure(
                                     array(
                                        'userid' => new external_value(PARAM_INT, 'user id'),
                                        'fullname' => new external_value(PARAM_NOTAGS, 'user full name'),
                                        'profileimageurl' => new external_value(PARAM_URL, 'profile user image url'),
                                        'answerid' => new external_value(PARAM_INT, 'answer id', VALUE_OPTIONAL),
                                        'timemodified' => new external_value(PARAM_INT, 'time of modification', VALUE_OPTIONAL),
                                     ), 'User responses'
                                 )
                            ),
                            'numberofuser' => new external_value(PARAM_INT, 'number of users answers'),
                            'percentageamount' => new external_value(PARAM_FLOAT, 'percentage of users answers')
                        ), 'Options'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for mod_roleplay_get_roleplay_options.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_roleplay_options_parameters() {
        return new external_function_parameters (array('roleplayid' => new external_value(PARAM_INT, 'roleplay instance id')));
    }

    /**
     * Returns options for a specific roleplay
     *
     * @param int $roleplayid the roleplay instance id
     * @return array of options details
     * @since Moodle 3.0
     */
    public static function get_roleplay_options($roleplayid) {
        global $USER;
        $warnings = array();
        $params = self::validate_parameters(self::get_roleplay_options_parameters(), array('roleplayid' => $roleplayid));

        if (!$roleplay = roleplay_get_roleplay($params['roleplayid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($roleplay, 'roleplay');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/roleplay:choose', $context);

        $groupmode = groups_get_activity_groupmode($cm);
        $onlyactive = $roleplay->includeinactive ? false : true;
        $allresponses = roleplay_get_response_data($roleplay, $cm, $groupmode, $onlyactive);

        $timenow = time();
        $roleplayopen = true;
        $showpreview = false;

        if (!empty($roleplay->timeopen) && ($roleplay->timeopen > $timenow)) {
            $roleplayopen = false;
            $warnings[1] = get_string("notopenyet", "roleplay", userdate($roleplay->timeopen));
            if ($roleplay->showpreview) {
                $warnings[2] = get_string('previewonly', 'roleplay', userdate($roleplay->timeopen));
                $showpreview = true;
            }
        }
        if (!empty($roleplay->timeclose) && ($timenow > $roleplay->timeclose)) {
            $roleplayopen = false;
            $warnings[3] = get_string("expired", "roleplay", userdate($roleplay->timeclose));
        }

        $optionsarray = array();

        if ($roleplayopen or $showpreview) {

            $options = roleplay_prepare_options($roleplay, $USER, $cm, $allresponses);

            foreach ($options['options'] as $option) {
                $optionarr = array();
                $optionarr['id']            = $option->attributes->value;
                $optionarr['text']          = external_format_string($option->text, $context->id);
                $optionarr['maxanswers']    = $option->maxanswers;
                $optionarr['displaylayout'] = $option->displaylayout;
                $optionarr['countanswers']  = $option->countanswers;
                foreach (array('checked', 'disabled') as $field) {
                    if (property_exists($option->attributes, $field) and $option->attributes->$field == 1) {
                        $optionarr[$field] = 1;
                    } else {
                        $optionarr[$field] = 0;
                    }
                }
                // When showpreview is active, we show options as disabled.
                if ($showpreview or ($optionarr['checked'] == 1 and !$roleplay->allowupdate)) {
                    $optionarr['disabled'] = 1;
                }
                $optionsarray[] = $optionarr;
            }
        }
        foreach ($warnings as $key => $message) {
            $warnings[$key] = array(
                'item' => 'roleplay',
                'itemid' => $cm->id,
                'warningcode' => $key,
                'message' => $message
            );
        }
        return array(
            'options' => $optionsarray,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the get_roleplay_results return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function get_roleplay_options_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'option id'),
                            'text' => new external_value(PARAM_RAW, 'text of the roleplay'),
                            'maxanswers' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'displaylayout' => new external_value(PARAM_BOOL, 'true for orizontal, otherwise vertical'),
                            'countanswers' => new external_value(PARAM_INT, 'number of answers'),
                            'checked' => new external_value(PARAM_BOOL, 'we already answered'),
                            'disabled' => new external_value(PARAM_BOOL, 'option disabled'),
                            )
                    ), 'Options'
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for submit_roleplay_response.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function submit_roleplay_response_parameters() {
        return new external_function_parameters (
            array(
                'roleplayid' => new external_value(PARAM_INT, 'roleplay instance id'),
                'responses' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'answer id'),
                    'Array of response ids'
                ),
            )
        );
    }

    /**
     * Submit roleplay responses
     *
     * @param int $roleplayid the roleplay instance id
     * @param array $responses the response ids
     * @return array answers information and warnings
     * @since Moodle 3.0
     */
    public static function submit_roleplay_response($roleplayid, $responses) {
        global $USER;

        $warnings = array();
        $params = self::validate_parameters(self::submit_roleplay_response_parameters(),
                                            array(
                                                'roleplayid' => $roleplayid,
                                                'responses' => $responses
                                            ));

        if (!$roleplay = roleplay_get_roleplay($params['roleplayid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($roleplay, 'roleplay');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/roleplay:choose', $context);

        $timenow = time();
        if (!empty($roleplay->timeopen) && ($roleplay->timeopen > $timenow)) {
            throw new moodle_exception("notopenyet", "roleplay", '', userdate($roleplay->timeopen));
        } else if (!empty($roleplay->timeclose) && ($timenow > $roleplay->timeclose)) {
            throw new moodle_exception("expired", "roleplay", '', userdate($roleplay->timeclose));
        }

        if (!roleplay_get_my_response($roleplay) or $roleplay->allowupdate) {
            // When a single response is given, we convert the array to a simple variable
            // in order to avoid roleplay_user_submit_response to check with allowmultiple even
            // for a single response.
            if (count($params['responses']) == 1) {
                $params['responses'] = reset($params['responses']);
            }
            roleplay_user_submit_response($params['responses'], $roleplay, $USER->id, $course, $cm);
        } else {
            throw new moodle_exception('missingrequiredcapability', 'webservice', '', 'allowupdate');
        }
        $answers = roleplay_get_my_response($roleplay);

        return array(
            'answers' => $answers,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the submit_roleplay_response return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function submit_roleplay_response_returns() {
        return new external_single_structure(
            array(
                'answers' => new external_multiple_structure(
                     new external_single_structure(
                         array(
                             'id'           => new external_value(PARAM_INT, 'answer id'),
                             'roleplayid'     => new external_value(PARAM_INT, 'roleplayid'),
                             'userid'       => new external_value(PARAM_INT, 'user id'),
                             'optionid'     => new external_value(PARAM_INT, 'optionid'),
                             'timemodified' => new external_value(PARAM_INT, 'time of last modification')
                         ), 'Answers'
                     )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_roleplay_parameters() {
        return new external_function_parameters(
            array(
                'roleplayid' => new external_value(PARAM_INT, 'roleplay instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $roleplayid the roleplay instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_roleplay($roleplayid) {
        global $CFG;

        $params = self::validate_parameters(self::view_roleplay_parameters(),
                                            array(
                                                'roleplayid' => $roleplayid
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$roleplay = roleplay_get_roleplay($params['roleplayid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($roleplay, 'roleplay');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Trigger course_module_viewed event and completion.
        roleplay_view($roleplay, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_roleplay_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_roleplays_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_roleplays_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of roleplays in a provided list of courses,
     * if no list is provided all roleplays that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of roleplays details
     * @since Moodle 3.0
     */
    public static function get_roleplays_by_courses($courseids = array()) {
        global $CFG;

        $returnedroleplays = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_roleplays_by_courses_parameters(), array('courseids' => $courseids));

        $courses = array();
        if (empty($params['courseids'])) {
            $courses = enrol_get_my_courses();
            $params['courseids'] = array_keys($courses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $courses);

            // Get the roleplays in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $roleplays = get_all_instances_in_courses("roleplay", $courses);
            foreach ($roleplays as $roleplay) {
                $context = context_module::instance($roleplay->coursemodule);
                // Entry to return.
                $roleplaydetails = array();
                // First, we return information that any user can see in the web interface.
                $roleplaydetails['id'] = $roleplay->id;
                $roleplaydetails['coursemodule'] = $roleplay->coursemodule;
                $roleplaydetails['course'] = $roleplay->course;
                $roleplaydetails['name']  = external_format_string($roleplay->name, $context->id);
                // Format intro.
                $options = array('noclean' => true);
                list($roleplaydetails['intro'], $roleplaydetails['introformat']) =
                    external_format_text($roleplay->intro, $roleplay->introformat, $context->id, 'mod_roleplay', 'intro', null, $options);
                $roleplaydetails['introfiles'] = external_util::get_area_files($context->id, 'mod_roleplay', 'intro', false, false);

                if (has_capability('mod/roleplay:choose', $context)) {
                    $roleplaydetails['publish']  = $roleplay->publish;
                    $roleplaydetails['showresults']  = $roleplay->showresults;
                    $roleplaydetails['showpreview']  = $roleplay->showpreview;
                    $roleplaydetails['timeopen']  = $roleplay->timeopen;
                    $roleplaydetails['timeclose']  = $roleplay->timeclose;
                    $roleplaydetails['display']  = $roleplay->display;
                    $roleplaydetails['allowupdate']  = $roleplay->allowupdate;
                    $roleplaydetails['allowmultiple']  = $roleplay->allowmultiple;
                    $roleplaydetails['limitanswers']  = $roleplay->limitanswers;
                    $roleplaydetails['showunanswered']  = $roleplay->showunanswered;
                    $roleplaydetails['includeinactive']  = $roleplay->includeinactive;
                }

                if (has_capability('moodle/course:manageactivities', $context)) {
                    $roleplaydetails['timemodified']  = $roleplay->timemodified;
                    $roleplaydetails['completionsubmit']  = $roleplay->completionsubmit;
                    $roleplaydetails['section']  = $roleplay->section;
                    $roleplaydetails['visible']  = $roleplay->visible;
                    $roleplaydetails['groupmode']  = $roleplay->groupmode;
                    $roleplaydetails['groupingid']  = $roleplay->groupingid;
                }
                $returnedroleplays[] = $roleplaydetails;
            }
        }
        $result = array();
        $result['roleplays'] = $returnedroleplays;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_roleplay_get_roleplays_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_roleplays_by_courses_returns() {
        return new external_single_structure(
            array(
                'roleplays' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Roleplay instance id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_INT, 'Course id'),
                            'name' => new external_value(PARAM_RAW, 'Roleplay name'),
                            'intro' => new external_value(PARAM_RAW, 'The roleplay intro'),
                            'introformat' => new external_format_value('intro'),
                            'introfiles' => new external_files('Files in the introduction text', VALUE_OPTIONAL),
                            'publish' => new external_value(PARAM_BOOL, 'If roleplay is published', VALUE_OPTIONAL),
                            'showresults' => new external_value(PARAM_INT, '0 never, 1 after answer, 2 after close, 3 always',
                                                                VALUE_OPTIONAL),
                            'display' => new external_value(PARAM_INT, 'Display mode (vertical, horizontal)', VALUE_OPTIONAL),
                            'allowupdate' => new external_value(PARAM_BOOL, 'Allow update', VALUE_OPTIONAL),
                            'allowmultiple' => new external_value(PARAM_BOOL, 'Allow multiple roleplays', VALUE_OPTIONAL),
                            'showunanswered' => new external_value(PARAM_BOOL, 'Show users who not answered yet', VALUE_OPTIONAL),
                            'includeinactive' => new external_value(PARAM_BOOL, 'Include inactive users', VALUE_OPTIONAL),
                            'limitanswers' => new external_value(PARAM_BOOL, 'Limit unswers', VALUE_OPTIONAL),
                            'timeopen' => new external_value(PARAM_INT, 'Date of opening validity', VALUE_OPTIONAL),
                            'timeclose' => new external_value(PARAM_INT, 'Date of closing validity', VALUE_OPTIONAL),
                            'showpreview' => new external_value(PARAM_BOOL, 'Show preview before timeopen', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification', VALUE_OPTIONAL),
                            'completionsubmit' => new external_value(PARAM_BOOL, 'Completion on user submission', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'Course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'Visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'Group id', VALUE_OPTIONAL),
                        ), 'Roleplays'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for delete_roleplay_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function delete_roleplay_responses_parameters() {
        return new external_function_parameters (
            array(
                'roleplayid' => new external_value(PARAM_INT, 'roleplay instance id'),
                'responses' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'response id'),
                    'Array of response ids, empty for deleting all the current user responses.',
                    VALUE_DEFAULT,
                    array()
                ),
            )
        );
    }

    /**
     * Delete the given submitted responses in a roleplay
     *
     * @param int $roleplayid the roleplay instance id
     * @param array $responses the response ids,  empty for deleting all the current user responses
     * @return array status information and warnings
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function delete_roleplay_responses($roleplayid, $responses = array()) {

        $status = false;
        $warnings = array();
        $params = self::validate_parameters(self::delete_roleplay_responses_parameters(),
                                            array(
                                                'roleplayid' => $roleplayid,
                                                'responses' => $responses
                                            ));

        if (!$roleplay = roleplay_get_roleplay($params['roleplayid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($roleplay, 'roleplay');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/roleplay:choose', $context);

        $candeleteall = has_capability('mod/roleplay:deleteresponses', $context);
        if ($candeleteall || $roleplay->allowupdate) {

            // Check if we can delete our own responses.
            if (!$candeleteall) {
                $timenow = time();
                if (!empty($roleplay->timeclose) && ($timenow > $roleplay->timeclose)) {
                    throw new moodle_exception("expired", "roleplay", '', userdate($roleplay->timeclose));
                }
            }

            if (empty($params['responses'])) {
                // No responses indicated so delete only my responses.
                $todelete = array_keys(roleplay_get_my_response($roleplay));
            } else {
                // Fill an array with the responses that can be deleted for this roleplay.
                if ($candeleteall) {
                    // Teacher/managers can delete any.
                    $allowedresponses = array_keys(roleplay_get_all_responses($roleplay));
                } else {
                    // Students can delete only their own responses.
                    $allowedresponses = array_keys(roleplay_get_my_response($roleplay));
                }

                $todelete = array();
                foreach ($params['responses'] as $response) {
                    if (!in_array($response, $allowedresponses)) {
                        $warnings[] = array(
                            'item' => 'response',
                            'itemid' => $response,
                            'warningcode' => 'nopermissions',
                            'message' => 'Invalid response id, the response does not exist or you are not allowed to delete it.'
                        );
                    } else {
                        $todelete[] = $response;
                    }
                }
            }

            $status = roleplay_delete_responses($todelete, $roleplay, $cm, $course);
        } else {
            // The user requires the capability to delete responses.
            throw new required_capability_exception($context, 'mod/roleplay:deleteresponses', 'nopermissions', '');
        }

        return array(
            'status' => $status,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the delete_roleplay_responses return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function delete_roleplay_responses_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status, true if everything went right'),
                'warnings' => new external_warnings(),
            )
        );
    }

}
