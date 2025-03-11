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
 * @package   mod_roleplay
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @global int $ROLEPLAY_COLUMN_HEIGHT */
global $ROLEPLAY_COLUMN_HEIGHT;
$ROLEPLAY_COLUMN_HEIGHT = 300;

/** @global int $ROLEPLAY_COLUMN_WIDTH */
global $ROLEPLAY_COLUMN_WIDTH;
$ROLEPLAY_COLUMN_WIDTH = 300;

define('ROLEPLAY_PUBLISH_ANONYMOUS', '0');
define('ROLEPLAY_PUBLISH_NAMES',     '1');

define('ROLEPLAY_SHOWRESULTS_NOT',          '0');
define('ROLEPLAY_SHOWRESULTS_AFTER_ANSWER', '1');
define('ROLEPLAY_SHOWRESULTS_AFTER_CLOSE',  '2');
define('ROLEPLAY_SHOWRESULTS_ALWAYS',       '3');

define('ROLEPLAY_DISPLAY_HORIZONTAL',  '0');
define('ROLEPLAY_DISPLAY_VERTICAL',    '1');

define('ROLEPLAY_EVENT_TYPE_OPEN', 'open');
define('ROLEPLAY_EVENT_TYPE_CLOSE', 'close');

/** @global array $ROLEPLAY_PUBLISH */
global $ROLEPLAY_PUBLISH;
$ROLEPLAY_PUBLISH = array (ROLEPLAY_PUBLISH_ANONYMOUS  => get_string('publishanonymous', 'roleplay'),
                         ROLEPLAY_PUBLISH_NAMES      => get_string('publishnames', 'roleplay'));

/** @global array $ROLEPLAY_SHOWRESULTS */
global $ROLEPLAY_SHOWRESULTS;
$ROLEPLAY_SHOWRESULTS = array (ROLEPLAY_SHOWRESULTS_NOT          => get_string('publishnot', 'roleplay'),
                         ROLEPLAY_SHOWRESULTS_AFTER_ANSWER => get_string('publishafteranswer', 'roleplay'),
                         ROLEPLAY_SHOWRESULTS_AFTER_CLOSE  => get_string('publishafterclose', 'roleplay'),
                         ROLEPLAY_SHOWRESULTS_ALWAYS       => get_string('publishalways', 'roleplay'));

/** @global array $ROLEPLAY_DISPLAY */
global $ROLEPLAY_DISPLAY;
$ROLEPLAY_DISPLAY = array (ROLEPLAY_DISPLAY_HORIZONTAL   => get_string('displayhorizontal', 'roleplay'),
                         ROLEPLAY_DISPLAY_VERTICAL     => get_string('displayvertical','roleplay'));

/// Standard functions /////////////////////////////////////////////////////////

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $roleplay
 * @return object|null
 */
function roleplay_user_outline($course, $user, $mod, $roleplay) {
    global $DB;
    if ($answer = $DB->get_record('roleplay_answers', array('roleplayid' => $roleplay->id, 'userid' => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(roleplay_get_option_text($roleplay, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * Callback for the "Complete" report - prints the activity summary for the given user
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $roleplay
 */
function roleplay_user_complete($course, $user, $mod, $roleplay) {
    global $DB;
    if ($answers = $DB->get_records('roleplay_answers', array("roleplayid" => $roleplay->id, "userid" => $user->id))) {
        $info = [];
        foreach ($answers as $answer) {
            $info[] = "'" . format_string(roleplay_get_option_text($roleplay, $answer->optionid)) . "'";
        }
        core_collator::asort($info);
        echo get_string("answered", "roleplay") . ": ". join(', ', $info) . ". " .
                get_string("updated", '', userdate($answer->timemodified));
    } else {
        print_string("notanswered", "roleplay");
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $roleplay
 * @return int
 */
function roleplay_add_instance($roleplay) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/roleplay/locallib.php');

    $roleplay->timemodified = time();

    //insert answers
    $roleplay->id = $DB->insert_record("roleplay", $roleplay);
    foreach ($roleplay->option as $key => $value) {
        $value = trim($value);
        if (isset($value) && $value <> '') {
            $option = new stdClass();
            $option->text = $value;
            $option->roleplayid = $roleplay->id;
            if (isset($roleplay->limit[$key])) {
                $option->maxanswers = $roleplay->limit[$key];
            }

            if (isset($roleplay->option_desc[$key])) {
                $option->option_desc = $roleplay->option_desc[$key];
            }
            $option->timemodified = time();
            $DB->insert_record("roleplay_options", $option);
        }
    }

    // Add calendar events if necessary.
    roleplay_set_events($roleplay);
    if (!empty($roleplay->completionexpected)) {
        \core_completion\api::update_completion_date_event($roleplay->coursemodule, 'roleplay', $roleplay->id,
                $roleplay->completionexpected);
    }

    return $roleplay->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $roleplay
 * @return bool
 */
function roleplay_update_instance($roleplay) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/roleplay/locallib.php');

    $roleplay->id = $roleplay->instance;
    $roleplay->timemodified = time();

    //update, delete or insert answers
    foreach ($roleplay->option as $key => $value) {
        $value = trim($value);
        $option = new stdClass();
        $option->text = $value;
        $option->roleplayid = $roleplay->id;
        if (isset($roleplay->limit[$key])) {
            $option->maxanswers = $roleplay->limit[$key];
        }

        if (isset($roleplay->option_desc[$key])) {
            $option->option_desc = $roleplay->option_desc[$key];
        }
      
        $option->timemodified = time();
        if (isset($roleplay->optionid[$key]) && !empty($roleplay->optionid[$key])){//existing roleplay record
            $option->id=$roleplay->optionid[$key];
            if (isset($value) && $value <> '') {
                $DB->update_record("roleplay_options", $option);
            } else {
                // Remove the empty (unused) option.
                $DB->delete_records("roleplay_options", array("id" => $option->id));
                // Delete any answers associated with this option.
                $DB->delete_records("roleplay_answers", array("roleplayid" => $roleplay->id, "optionid" => $option->id));
            }
        } else {
            if (isset($value) && $value <> '') {
                $DB->insert_record("roleplay_options", $option);
            }
        }
    }

    // Add calendar events if necessary.
    roleplay_set_events($roleplay);
    $completionexpected = (!empty($roleplay->completionexpected)) ? $roleplay->completionexpected : null;
    \core_completion\api::update_completion_date_event($roleplay->coursemodule, 'roleplay', $roleplay->id, $completionexpected);

    return $DB->update_record('roleplay', $roleplay);

}

/**
 * @global object
 * @param object $roleplay
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function roleplay_prepare_options($roleplay, $user, $coursemodule, $allresponses) {
    global $DB;
    $cdisplay = array('options'=>array());

    $cdisplay['limitanswers'] = true;
    $context = context_module::instance($coursemodule->id);

    foreach ($roleplay->option as $optionid => $text) {
        if (isset($text)) { //make sure there are no dud entries in the db with blank text values.
            $option = new stdClass;
            $option->attributes = new stdClass;
            $option->attributes->value = $optionid;
            $option->text = format_string($text);
            $option->maxanswers = $roleplay->maxanswers[$optionid];

            $option->option_desc = $roleplay->option_desc[$optionid];
          $option->displaylayout = $roleplay->display;

            if (isset($allresponses[$optionid])) {
                $option->countanswers = count($allresponses[$optionid]);
            } else {
                $option->countanswers = 0;
            }
            if ($DB->record_exists('roleplay_answers', array('roleplayid' => $roleplay->id, 'userid' => $user->id, 'optionid' => $optionid))) {
                $option->attributes->checked = true;
            }
            if ( $roleplay->limitanswers && ($option->countanswers >= $option->maxanswers) && empty($option->attributes->checked)) {
                $option->attributes->disabled = true;
            }
            $cdisplay['options'][] = $option;
        }
    }

    $cdisplay['hascapability'] = is_enrolled($context, NULL, 'mod/roleplay:choose'); //only enrolled users are allowed to make a roleplay

    if ($roleplay->allowupdate && $DB->record_exists('roleplay_answers', array('roleplayid'=> $roleplay->id, 'userid'=> $user->id))) {
        $cdisplay['allowupdate'] = true;
    }

    if ($roleplay->showpreview && $roleplay->timeopen > time()) {
        $cdisplay['previewonly'] = true;
    }

    return $cdisplay;
}

/**
 * Modifies responses of other users adding the option $newoptionid to them
 *
 * @param array $userids list of users to add option to (must be users without any answers yet)
 * @param array $answerids list of existing attempt ids of users (will be either appended or
 *      substituted with the newoptionid, depending on $roleplay->allowmultiple)
 * @param int $newoptionid
 * @param stdClass $roleplay roleplay object, result of {@link roleplay_get_roleplay()}
 * @param stdClass $cm
 * @param stdClass $course
 */
function roleplay_modify_responses($userids, $answerids, $newoptionid, $roleplay, $cm, $course) {
    // Get all existing responses and the list of non-respondents.
    $groupmode = groups_get_activity_groupmode($cm);
    $onlyactive = $roleplay->includeinactive ? false : true;
    $allresponses = roleplay_get_response_data($roleplay, $cm, $groupmode, $onlyactive);

    // Check that the option value is valid.
    if (!$newoptionid || !isset($roleplay->option[$newoptionid])) {
        return;
    }

    // First add responses for users who did not make any roleplay yet.
    foreach ($userids as $userid) {
        if (isset($allresponses[0][$userid])) {
            roleplay_user_submit_response($newoptionid, $roleplay, $userid, $course, $cm);
        }
    }

    // Create the list of all options already selected by each user.
    $optionsbyuser = []; // Mapping userid=>array of chosen roleplay options.
    $usersbyanswer = []; // Mapping answerid=>userid (which answer belongs to each user).
    foreach ($allresponses as $optionid => $responses) {
        if ($optionid > 0) {
            foreach ($responses as $userid => $userresponse) {
                $optionsbyuser += [$userid => []];
                $optionsbyuser[$userid][] = $optionid;
                $usersbyanswer[$userresponse->answerid] = $userid;
            }
        }
    }

    // Go through the list of submitted attemptids and find which users answers need to be updated.
    foreach ($answerids as $answerid) {
        if (isset($usersbyanswer[$answerid])) {
            $userid = $usersbyanswer[$answerid];
            if (!in_array($newoptionid, $optionsbyuser[$userid])) {
                $options = $roleplay->allowmultiple ?
                        array_merge($optionsbyuser[$userid], [$newoptionid]) : $newoptionid;
                roleplay_user_submit_response($options, $roleplay, $userid, $course, $cm);
            }
        }
    }
}

/**
 * Process user submitted answers for a roleplay,
 * and either updating them or saving new answers.
 *
 * @param int|array $formanswer the id(s) of the user submitted roleplay options.
 * @param object $roleplay the selected roleplay.
 * @param int $userid user identifier.
 * @param object $course current course.
 * @param object $cm course context.
 * @return void
 */
function roleplay_user_submit_response($formanswer, $comment, $roleplay, $userid, $course, $cm) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir.'/completionlib.php');

    $continueurl = new moodle_url('/mod/roleplay/view.php', array('id' => $cm->id));

    if (empty($formanswer)) {
        print_error('atleastoneoption', 'roleplay', $continueurl);
    }

    if (is_array($formanswer)) {
        if (!$roleplay->allowmultiple) {
            print_error('multiplenotallowederror', 'roleplay', $continueurl);
        }
        $formanswers = $formanswer;
    } else {
        $formanswers = array($formanswer);
    }

    $options = $DB->get_records('roleplay_options', array('roleplayid' => $roleplay->id), '', 'id');
    foreach ($formanswers as $key => $val) {
        if (!isset($options[$val])) {
            print_error('cannotsubmit', 'roleplay', $continueurl);
        }
    }
    // Start lock to prevent synchronous access to the same data
    // before it's updated, if using limits.
    if ($roleplay->limitanswers) {
        $timeout = 10;
        $locktype = 'mod_roleplay_roleplay_user_submit_response';
        // Limiting access to this roleplay.
        $resouce = 'roleplayid:' . $roleplay->id;
        $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);

        // Opening the lock.
        $roleplaylock = $lockfactory->get_lock($resouce, $timeout, MINSECS);
        if (!$roleplaylock) {
            print_error('cannotsubmit', 'roleplay', $continueurl);
        }
    }

    $current = $DB->get_records('roleplay_answers', array('roleplayid' => $roleplay->id, 'userid' => $userid));

    // Array containing [answerid => optionid] mapping.
    $existinganswers = array_map(function($answer) {
        return $answer->optionid;
    }, $current);

    $context = context_module::instance($cm->id);

    $roleplaysexceeded = false;
    $countanswers = array();
    foreach ($formanswers as $val) {
        $countanswers[$val] = 0;
    }
    if($roleplay->limitanswers) {
        // Find out whether groups are being used and enabled
        if (groups_get_activity_groupmode($cm) > 0) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            $currentgroup = 0;
        }

        list ($insql, $params) = $DB->get_in_or_equal($formanswers, SQL_PARAMS_NAMED);

        if($currentgroup) {
            // If groups are being used, retrieve responses only for users in
            // current group
            global $CFG;

            $params['groupid'] = $currentgroup;
            $sql = "SELECT ca.*
                      FROM {roleplay_answers} ca
                INNER JOIN {groups_members} gm ON ca.userid=gm.userid
                     WHERE optionid $insql
                       AND gm.groupid= :groupid";
        } else {
            // Groups are not used, retrieve all answers for this option ID
            $sql = "SELECT ca.*
                      FROM {roleplay_answers} ca
                     WHERE optionid $insql";
        }

        $answers = $DB->get_records_sql($sql, $params);
        if ($answers) {
            foreach ($answers as $a) { //only return enrolled users.
                if (is_enrolled($context, $a->userid, 'mod/roleplay:choose')) {
                    $countanswers[$a->optionid]++;
                }
            }
        }

        foreach ($countanswers as $opt => $count) {
            // Ignore the user's existing answers when checking whether an answer count has been exceeded.
            // A user may wish to update their response with an additional roleplay option and shouldn't be competing with themself!
            if (in_array($opt, $existinganswers)) {
                continue;
            }
            if ($count >= $roleplay->maxanswers[$opt]) {
                $roleplaysexceeded = true;
                break;
            }
        }
    }

    // Check the user hasn't exceeded the maximum selections for the roleplay(s) they have selected.
    $answersnapshots = array();
    $deletedanswersnapshots = array();
    if (!($roleplay->limitanswers && $roleplaysexceeded)) {
        if ($current) {
            // Update an existing answer.
            foreach ($current as $c) {
                if (in_array($c->optionid, $formanswers)) {
                    $DB->set_field('roleplay_answers', 'timemodified', time(), array('id' => $c->id));
                } else {
                    $deletedanswersnapshots[] = $c;
                    $DB->delete_records('roleplay_answers', array('id' => $c->id));
                }
            }

            // Add new ones.
            foreach ($formanswers as $f) {
                if (!in_array($f, $existinganswers)) {
                    $newanswer = new stdClass();
                    $newanswer->optionid = $f;
                    $newanswer->roleplayid = $roleplay->id;
                    $newanswer->userid = $userid;
                    $newanswer->timemodified = time();
                    $newanswer->id = $DB->insert_record("roleplay_answers", $newanswer);
                    $answersnapshots[] = $newanswer;
                }
            }
        } else {
            // Add new answer.
            foreach ($formanswers as $answer) {
                $newanswer = new stdClass();
                $newanswer->roleplayid = $roleplay->id;
                $newanswer->userid = $userid;
                $newanswer->optionid = $answer;
                $newanswer->timemodified = time();
                $newanswer->comment = $comment;
                $newanswer->id = $DB->insert_record("roleplay_answers", $newanswer);
                $answersnapshots[] = $newanswer;
            }

            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $roleplay->completionsubmit) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }
    } else {
        // This is a roleplay with limited options, and one of the options selected has just run over its limit.
        $roleplaylock->release();
        print_error('roleplayfull', 'roleplay', $continueurl);
    }

    // Release lock.
    if (isset($roleplaylock)) {
        $roleplaylock->release();
    }

    // Trigger events.
    foreach ($deletedanswersnapshots as $answer) {
        \mod_roleplay\event\answer_deleted::create_from_object($answer, $roleplay, $cm, $course)->trigger();
    }
    foreach ($answersnapshots as $answer) {
        \mod_roleplay\event\answer_created::create_from_object($answer, $roleplay, $cm, $course)->trigger();
    }
}

/**
 * @param array $user
 * @param object $cm
 * @return void Output is echo'd
 */
function roleplay_show_reportlink($user, $cm) {

    $userschosen = array();
    foreach($user as $optionid => $userlist) {
        if ($optionid) {
            $userschosen = array_merge($userschosen, array_keys($userlist));
        }
    }
    $responsecount = count(array_unique($userschosen));

    echo '<div class="reportlink">';
    echo "<a href=\"report.php?id=$cm->id\">".get_string("viewallresponses", "roleplay", $responsecount)."</a>";
    echo '</div>';
}

/**
 * @global object
 * @param object $roleplay
 * @param object $course
 * @param object $coursemodule
 * @param array $allresponses

 *  * @param bool $allresponses
 * @return object
 */
function prepare_roleplay_show_results($roleplay, $course, $cm, $allresponses) {
    global $OUTPUT;

    $display = clone($roleplay);
    $display->coursemoduleid = $cm->id;
    $display->courseid = $course->id;

    if (!empty($roleplay->showunanswered)) {
        $roleplay->option[0] = get_string('notanswered', 'roleplay');
        $roleplay->maxanswers[0] = 0;
    }

    // Remove from the list of non-respondents the users who do not have access to this activity.
    if (!empty($display->showunanswered) && $allresponses[0]) {
        $info = new \core_availability\info_module(cm_info::create($cm));
        $allresponses[0] = $info->filter_user_list($allresponses[0]);
    }

    //overwrite options value;
    $display->options = array();
    $allusers = [];
    foreach ($roleplay->option as $optionid => $optiontext) {
        $display->options[$optionid] = new stdClass;
        $display->options[$optionid]->text = format_string($optiontext, true,
            ['context' => context_module::instance($cm->id)]);
        $display->options[$optionid]->maxanswer = $roleplay->maxanswers[$optionid];

        if (array_key_exists($optionid, $allresponses)) {
            $display->options[$optionid]->user = $allresponses[$optionid];
            $allusers = array_merge($allusers, array_keys($allresponses[$optionid]));
        }
    }
    unset($display->option);
    unset($display->maxanswers);

    $display->numberofuser = count(array_unique($allusers));
    $context = context_module::instance($cm->id);
    $display->viewresponsecapability = has_capability('mod/roleplay:readresponses', $context);
    $display->deleterepsonsecapability = has_capability('mod/roleplay:deleteresponses',$context);
    $display->fullnamecapability = has_capability('moodle/site:viewfullnames', $context);

    if (empty($allresponses)) {
        echo $OUTPUT->heading(get_string("nousersyet"), 3, null);
        return false;
    }

    return $display;
}

/**
 * @global object
 * @param array $attemptids
 * @param object $roleplay Roleplay main table row
 * @param object $cm Course-module object
 * @param object $course Course object
 * @return bool
 */
function roleplay_delete_responses($attemptids, $roleplay, $cm, $course) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir.'/completionlib.php');

    if(!is_array($attemptids) || empty($attemptids)) {
        return false;
    }

    foreach($attemptids as $num => $attemptid) {
        if(empty($attemptid)) {
            unset($attemptids[$num]);
        }
    }

    $completion = new completion_info($course);
    foreach($attemptids as $attemptid) {
        if ($todelete = $DB->get_record('roleplay_answers', array('roleplayid' => $roleplay->id, 'id' => $attemptid))) {
            // Trigger the event answer deleted.
            \mod_roleplay\event\answer_deleted::create_from_object($todelete, $roleplay, $cm, $course)->trigger();
            $DB->delete_records('roleplay_answers', array('roleplayid' => $roleplay->id, 'id' => $attemptid));
        }
    }

    // Update completion state.
    if ($completion->is_enabled($cm) && $roleplay->completionsubmit) {
        $completion->update_state($cm, COMPLETION_INCOMPLETE);
    }

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function roleplay_delete_instance($id) {
    global $DB;

    if (! $roleplay = $DB->get_record("roleplay", array("id"=>"$id"))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("roleplay_answers", array("roleplayid"=>"$roleplay->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("roleplay_options", array("roleplayid"=>"$roleplay->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("roleplay", array("id"=>"$roleplay->id"))) {
        $result = false;
    }
    // Remove old calendar events.
    if (! $DB->delete_records('event', array('modulename' => 'roleplay', 'instance' => $roleplay->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Returns text string which is the answer that matches the id
 *
 * @global object
 * @param object $roleplay
 * @param int $id
 * @return string
 */
function roleplay_get_option_text($roleplay, $id) {
    global $DB;

    if ($result = $DB->get_record("roleplay_options", array("id" => $id))) {
        return $result->text;
    } else {
        return get_string("notanswered", "roleplay");
    }
}

/**
 * Gets a full roleplay record
 *
 * @global object
 * @param int $roleplayid
 * @return object|bool The roleplay or false
 */
function roleplay_get_roleplay($roleplayid) {
    global $DB;

    if ($roleplay = $DB->get_record("roleplay", array("id" => $roleplayid))) {
        if ($options = $DB->get_records("roleplay_options", array("roleplayid" => $roleplayid), "id")) {
            foreach ($options as $option) {
                $roleplay->option[$option->id] = $option->text;
                $roleplay->maxanswers[$option->id] = $option->maxanswers;

                $roleplay->option_desc[$option->id] = $option->option_desc;
            }
            return $roleplay;
        }
    }
    return false;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function roleplay_get_view_actions() {
    return array('view','view all','report');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function roleplay_get_post_actions() {
    return array('choose','choose again');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the roleplay.
 *
 * @param object $mform form passed by reference
 */
function roleplay_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'roleplayheader', get_string('modulenameplural', 'roleplay'));
    $mform->addElement('advcheckbox', 'reset_roleplay', get_string('removeresponses','roleplay'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function roleplay_reset_course_form_defaults($course) {
    return array('reset_roleplay'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * roleplay responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function roleplay_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'roleplay');
    $status = array();

    if (!empty($data->reset_roleplay)) {
        $roleplayssql = "SELECT ch.id
                       FROM {roleplay} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('roleplay_answers', "roleplayid IN ($roleplayssql)", array($data->courseid));
        $status[] = array('component'=>$componentstr, 'item'=>get_string('removeresponses', 'roleplay'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates('roleplay', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $roleplay
 * @param object $cm
 * @param int $groupmode
 * @param bool $onlyactive Whether to get response data for active users only.
 * @return array
 */
function roleplay_get_response_data($roleplay, $cm, $groupmode, $onlyactive) {
    global $CFG, $USER, $DB;

    $context = context_module::instance($cm->id);

/// Get the current group
    if ($groupmode > 0) {
        $currentgroup = groups_get_activity_group($cm);
    } else {
        $currentgroup = 0;
    }

/// Initialise the returned array, which is a matrix:  $allresponses[responseid][userid] = responseobject
    $allresponses = array();

/// First get all the users who have access here
/// To start with we assume they are all "unanswered" then move them later

    //$extrafields = get_extra_user_fields($context);
    $extrafields = \core_user\fields::for_identity($context)->get_required_fields();
    // Deprecated version
    
    //$allresponses[0] = get_enrolled_users($context, 'mod/roleplay:choose', $currentgroup,
            //user_picture::fields('u', $extrafields), null, 0, 0, $onlyactive);

    $allresponses[0] = get_enrolled_users($context, 'mod/roleplay:choose', $currentgroup,
    \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects, null, 0, 0, $onlyactive);

/// Get all the recorded responses for this roleplay
    $rawresponses = $DB->get_records('roleplay_answers', array('roleplayid' => $roleplay->id));

/// Use the responses to move users into the correct column

    if ($rawresponses) {
        $answeredusers = array();
        foreach ($rawresponses as $response) {
            if (isset($allresponses[0][$response->userid])) {   // This person is enrolled and in correct group
                $allresponses[0][$response->userid]->timemodified = $response->timemodified;
                $allresponses[$response->optionid][$response->userid] = clone($allresponses[0][$response->userid]);
                $allresponses[$response->optionid][$response->userid]->answerid = $response->id;
                $allresponses[$response->optionid][$response->userid]->comment = $response->comment;
                $answeredusers[] = $response->userid;
            }
        }
        foreach ($answeredusers as $answereduser) {
            unset($allresponses[0][$answereduser]);
        }
    }
    return $allresponses;
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function roleplay_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $roleplaynode The node to add module settings to
 */
function roleplay_extend_settings_navigation(settings_navigation $settings, navigation_node $roleplaynode) {
    global $PAGE;

    if (has_capability('mod/roleplay:readresponses', $PAGE->cm->context)) {

        $groupmode = groups_get_activity_groupmode($PAGE->cm);
        if ($groupmode) {
            groups_get_activity_group($PAGE->cm, true);
        }

        $roleplay = roleplay_get_roleplay($PAGE->cm->instance);

        // Check if we want to include responses from inactive users.
        $onlyactive = $roleplay->includeinactive ? false : true;

        // Big function, approx 6 SQL calls per user.
        $allresponses = roleplay_get_response_data($roleplay, $PAGE->cm, $groupmode, $onlyactive);

        $allusers = [];
        foreach($allresponses as $optionid => $userlist) {
            if ($optionid) {
                $allusers = array_merge($allusers, array_keys($userlist));
            }
        }
        $responsecount = count(array_unique($allusers));
        $roleplaynode->add(get_string("viewallresponses", "roleplay", $responsecount), new moodle_url('/mod/roleplay/report.php', array('id'=>$PAGE->cm->id)));
    }
}

/**
 * Obtains the automatic completion state for this roleplay based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function roleplay_get_completion_state($course, $cm, $userid, $type) {
    global $CFG,$DB;

    // Get roleplay details
    $roleplay = $DB->get_record('roleplay', array('id'=>$cm->instance), '*',
            MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false
    if($roleplay->completionsubmit) {
        return $DB->record_exists('roleplay_answers', array(
                'roleplayid'=>$roleplay->id, 'userid'=>$userid));
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function roleplay_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-roleplay-*'=>get_string('page-mod-roleplay-x', 'roleplay'));
    return $module_pagetype;
}

/**
 * @deprecated since Moodle 3.3, when the block_course_overview block was removed.
 */
function roleplay_print_overview() {
    throw new coding_exception('roleplay_print_overview() can not be used any more and is obsolete.');
}


/**
 * Get responses of a given user on a given roleplay.
 *
 * @param stdClass $roleplay Roleplay record
 * @param int $userid User id
 * @return array of roleplay answers records
 * @since  Moodle 3.6
 */
function roleplay_get_user_response($roleplay, $userid) {
    global $DB;
    return $DB->get_records('roleplay_answers', array('roleplayid' => $roleplay->id, 'userid' => $userid), 'optionid');
}

/**
 * Get my responses on a given roleplay.
 *
 * @param stdClass $roleplay Roleplay record
 * @return array of roleplay answers records
 * @since  Moodle 3.0
 */
function roleplay_get_my_response($roleplay) {
    global $USER;
    return roleplay_get_user_response($roleplay, $USER->id);
}


/**
 * Check if responses from user's groups have been posted.
 *
 * @param stdClass $roleplay Roleplay record
 * @return bool whether responses exist
 * @since  Moodle 3.0
 */
function roleplay_group_responses_exist($roleplay) {
    global $USER, $DB;
    $mygroups = $DB->get_fieldset_sql("
        SELECT DISTINCT(groupid)
        FROM {groups_members}
        WHERE userid = :userid
        ", ['userid' => $USER->id]
    );

    if (empty($mygroups)) {
        $sql = "
        SELECT *
        FROM {roleplay_answers} ra
        WHERE ra.roleplayid = ?";
        $exists = $DB->record_exists_sql($sql, [$roleplay->id]);
    } else {
        list($insql, $inparams) = $DB->get_in_or_equal($mygroups);
        $sql = "
        SELECT *
        FROM {roleplay_answers} ra
        LEFT JOIN {groups_members} gm ON ra.userid = gm.userid
        WHERE gm.groupid $insql AND ra.roleplayid = ?";
        $exists = $DB->record_exists_sql($sql, array_merge($inparams, [$roleplay->id]));
    }

    return $exists;
}

/**
 * Get all the responses on a given roleplay.
 *
 * @param stdClass $roleplay Roleplay record
 * @return array of roleplay answers records
 * @since  Moodle 3.0
 */
function roleplay_get_all_responses($roleplay) {
    global $DB;
    return $DB->get_records('roleplay_answers', array('roleplayid' => $roleplay->id));
}


/**
 * Return true if we are allowd to view the roleplay results.
 *
 * @param stdClass $roleplay Roleplay record
 * @param rows|null $current my roleplay responses
 * @param bool|null $roleplayopen if the roleplay is open
 * @return bool true if we can view the results, false otherwise.
 * @since  Moodle 3.0
 */
function roleplay_can_view_results($roleplay, $current = null, $roleplayopen = null) {

    if (is_null($roleplayopen)) {
        $timenow = time();

        if ($roleplay->timeopen != 0 && $timenow < $roleplay->timeopen) {
            // If the roleplay is not available, we can't see the results.
            return false;
        }

        if ($roleplay->timeclose != 0 && $timenow > $roleplay->timeclose) {
            $roleplayopen = false;
        } else {
            $roleplayopen = true;
        }
    }
    if (empty($current)) {
        $current = roleplay_get_my_response($roleplay);
    }

    if ($roleplay->showresults == ROLEPLAY_SHOWRESULTS_ALWAYS or
       ($roleplay->showresults == ROLEPLAY_SHOWRESULTS_AFTER_ANSWER and !empty($current)) or
       ($roleplay->showresults == ROLEPLAY_SHOWRESULTS_AFTER_CLOSE and !$roleplayopen)) {
        return true;
    }
    return false;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $roleplay     roleplay object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function roleplay_view($roleplay, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $roleplay->id
    );

    $event = \mod_roleplay\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('roleplay', $roleplay);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if a roleplay is available for the current user.
 *
 * @param  stdClass  $roleplay            roleplay record
 * @return array                       status (available or not and possible warnings)
 */
function roleplay_get_availability_status($roleplay) {
    $available = true;
    $warnings = array();

    $timenow = time();

    if (!empty($roleplay->timeopen) && ($roleplay->timeopen > $timenow)) {
        $available = false;
        $warnings['notopenyet'] = userdate($roleplay->timeopen);
    } else if (!empty($roleplay->timeclose) && ($timenow > $roleplay->timeclose)) {
        $available = false;
        $warnings['expired'] = userdate($roleplay->timeclose);
    }
    if (!$roleplay->allowupdate && roleplay_get_my_response($roleplay)) {
        $available = false;
        $warnings['roleplaysaved'] = '';
    }
    if ($roleplay->oneresponsepergroup && roleplay_group_responses_exist($roleplay)) {
        $available = false;
        $warnings['groupalreadyhasresponses'] = '';
    }

    // Roleplay is available.
    return array($available, $warnings);
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every roleplay event in the site is checked, else
 * only roleplay events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance Roleplay module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function roleplay_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/roleplay/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('roleplay', array('id' => $instance), '*', MUST_EXIST);
        }
        roleplay_set_events($instance);
        return true;
    }

    if ($courseid) {
        if (! $roleplays = $DB->get_records("roleplay", array("course" => $courseid))) {
            return true;
        }
    } else {
        if (! $roleplays = $DB->get_records("roleplay")) {
            return true;
        }
    }

    foreach ($roleplays as $roleplay) {
        roleplay_set_events($roleplay);
    }
    return true;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function roleplay_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB;

    $updates = new stdClass();
    $roleplay = $DB->get_record($cm->modname, array('id' => $cm->instance), '*', MUST_EXIST);
    list($available, $warnings) = roleplay_get_availability_status($roleplay);
    if (!$available) {
        return $updates;
    }

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    if (!roleplay_can_view_results($roleplay)) {
        return $updates;
    }
    // Check if there are new responses in the roleplay.
    $updates->answers = (object) array('updated' => false);
    $select = 'roleplayid = :id AND timemodified > :since';
    $params = array('id' => $roleplay->id, 'since' => $from);
    $answers = $DB->get_records_select('roleplay_answers', $select, $params, '', 'id');
    if (!empty($answers)) {
        $updates->answers->updated = true;
        $updates->answers->itemids = array_keys($answers);
    }

    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_roleplay_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory,
                                                       int $userid = 0) {
    global $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['roleplay'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    $now = time();

    if (!empty($cm->customdata['timeclose']) && $cm->customdata['timeclose'] < $now) {
        // The roleplay has closed so the user can no longer submit anything.
        return null;
    }

    // The roleplay is actionable if we don't have a start time or the start time is
    // in the past.
    $actionable = (empty($cm->customdata['timeopen']) || $cm->customdata['timeopen'] <= $now);

    if ($actionable && roleplay_get_user_response((object)['id' => $event->instance], $userid)) {
        // There is no action if the user has already submitted their roleplay.
        return null;
    }

    return $factory->create_instance(
        get_string('viewroleplays', 'roleplay'),
        new \moodle_url('/mod/roleplay/view.php', array('id' => $cm->id)),
        1,
        $actionable
    );
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The date must be after this date'],
 *     [1506741172, 'The date must be before this date']
 * ]
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $roleplay The module instance to get the range from
 */
function mod_roleplay_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $roleplay) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == ROLEPLAY_EVENT_TYPE_OPEN) {
        if (!empty($roleplay->timeclose)) {
            $maxdate = [
                $roleplay->timeclose,
                get_string('openafterclose', 'roleplay')
            ];
        }
    } else if ($event->eventtype == ROLEPLAY_EVENT_TYPE_CLOSE) {
        if (!empty($roleplay->timeopen)) {
            $mindate = [
                $roleplay->timeopen,
                get_string('closebeforeopen', 'roleplay')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the roleplay module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the roleplay instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $roleplay The module instance to get the range from
 */
function mod_roleplay_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $roleplay) {
    global $DB;

    if (!in_array($event->eventtype, [ROLEPLAY_EVENT_TYPE_OPEN, ROLEPLAY_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    // Something weird going on. The event is for a different module so
    // we should ignore it.
    if ($modulename != 'roleplay') {
        return;
    }

    if ($roleplay->id != $instanceid) {
        return;
    }

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == ROLEPLAY_EVENT_TYPE_OPEN) {
        // If the event is for the roleplay activity opening then we should
        // set the start time of the roleplay activity to be the new start
        // time of the event.
        if ($roleplay->timeopen != $event->timestart) {
            $roleplay->timeopen = $event->timestart;
            $modified = true;
        }
    } else if ($event->eventtype == ROLEPLAY_EVENT_TYPE_CLOSE) {
        // If the event is for the roleplay activity closing then we should
        // set the end time of the roleplay activity to be the new start
        // time of the event.
        if ($roleplay->timeclose != $event->timestart) {
            $roleplay->timeclose = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $roleplay->timemodified = time();
        // Persist the instance changes.
        $DB->update_record('roleplay', $roleplay);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_roleplay_get_fontawesome_icon_map() {
    return [
        'mod_roleplay:row' => 'fa-info',
        'mod_roleplay:column' => 'fa-columns',
    ];
}

/**
 * Add a get_coursemodule_info function in case any roleplay type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function roleplay_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionsubmit, timeopen, timeclose';
    if (!$roleplay = $DB->get_record('roleplay', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $roleplay->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('roleplay', $roleplay, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $roleplay->completionsubmit;
    }
    // Populate some other values that can be used in calendar or on dashboard.
    if ($roleplay->timeopen) {
        $result->customdata['timeopen'] = $roleplay->timeopen;
    }
    if ($roleplay->timeclose) {
        $result->customdata['timeclose'] = $roleplay->timeclose;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_roleplay_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionsubmit', 'roleplay');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}
