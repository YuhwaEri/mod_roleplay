<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');

$id         = required_param('id', PARAM_INT);                 // Course Module ID
$action     = optional_param('action', '', PARAM_ALPHANUMEXT);
$attemptids = optional_param_array('attemptid', array(), PARAM_INT); // Get array of responses to delete or modify.
$userids    = optional_param_array('userid', array(), PARAM_INT); // Get array of users whose roleplays need to be modified.
$notify     = optional_param('notify', '', PARAM_ALPHA);

$url = new moodle_url('/mod/roleplay/view.php', array('id'=>$id));
if ($action !== '') {
    $url->param('action', $action);
}
$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('roleplay', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

require_course_login($course, false, $cm);

if (!$roleplay = roleplay_get_roleplay($cm->instance)) {
    print_error('invalidcoursemodule');
}

$strroleplay = get_string('modulename', 'roleplay');
$strroleplays = get_string('modulenameplural', 'roleplay');

$context = context_module::instance($cm->id);

list($roleplayavailable, $warnings) = roleplay_get_availability_status($roleplay);

if ($action == 'delroleplay' and confirm_sesskey() and is_enrolled($context, NULL, 'mod/roleplay:choose') and $roleplay->allowupdate
        and $roleplayavailable) {
    $answercount = $DB->count_records('roleplay_answers', array('roleplayid' => $roleplay->id, 'userid' => $USER->id));
    if ($answercount > 0) {
        $roleplayanswers = $DB->get_records('roleplay_answers', array('roleplayid' => $roleplay->id, 'userid' => $USER->id),
            '', 'id');
        $todelete = array_keys($roleplayanswers);
        roleplay_delete_responses($todelete, $roleplay, $cm, $course);
        redirect("view.php?id=$cm->id");
    }
}

$PAGE->set_title($roleplay->name);
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/mod/roleplay/style.css');

/// Submit any new data if there is any
if (data_submitted() && !empty($action) && confirm_sesskey()) {
    $timenow = time();
    if (has_capability('mod/roleplay:deleteresponses', $context)) {
        if ($action === 'delete') {
            // Some responses need to be deleted.
            roleplay_delete_responses($attemptids, $roleplay, $cm, $course);
            redirect("view.php?id=$cm->id");
        }
        if (preg_match('/^choose_(\d+)$/', $action, $actionmatch)) {
            // Modify responses of other users.
            $newoptionid = (int)$actionmatch[1];
            roleplay_modify_responses($userids, $attemptids, $newoptionid, $roleplay, $cm, $course);
            redirect("view.php?id=$cm->id");
        }
    }

    // Redirection after all POSTs breaks block editing, we need to be more specific!
    if ($roleplay->allowmultiple) {
        $answer = optional_param_array('answer', array(), PARAM_INT);
    } else {
        $answer = optional_param('answer', '', PARAM_INT);
    }
    $comment = optional_param('answer_comment', '', PARAM_TEXT);

    if (!$roleplayavailable) {
        $reason = current(array_keys($warnings));
        throw new moodle_exception($reason, 'roleplay', '', $warnings[$reason]);
    }

    if ($answer && is_enrolled($context, null, 'mod/roleplay:choose')) {
        roleplay_user_submit_response($answer, $comment, $roleplay, $USER->id, $course, $cm);
        redirect(new moodle_url('/mod/roleplay/view.php',
            array('id' => $cm->id, 'notify' => 'roleplaysaved', 'sesskey' => sesskey())));
    } else if (empty($answer) and $action === 'makeroleplay') {
        // We cannot use the 'makeroleplay' alone because there might be some legacy renderers without it,
        // outdated renderers will not get the 'mustchoose' message - bad luck.
        redirect(new moodle_url('/mod/roleplay/view.php',
            array('id' => $cm->id, 'notify' => 'mustchooseone', 'sesskey' => sesskey())));
    }
}

// Completion and trigger events.
roleplay_view($roleplay, $course, $cm, $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($roleplay->name), 2, null);

if ($notify and confirm_sesskey()) {
    if ($notify === 'roleplaysaved') {
        echo $OUTPUT->notification(get_string('roleplaysaved', 'roleplay'), 'notifysuccess');
    } else if ($notify === 'mustchooseone') {
        echo $OUTPUT->notification(get_string('mustchooseone', 'roleplay'), 'notifyproblem');
    }
}


if (!$roleplayavailable and !$notify) {
    $reason = current(array_keys($warnings));
    echo $OUTPUT->notification(get_string($reason, 'roleplay'), 'warning');
}

/// Display the roleplay and possibly results
$eventdata = array();
$eventdata['objectid'] = $roleplay->id;
$eventdata['context'] = $context;

/// Check to see if groups are being used in this roleplay
$groupmode = groups_get_activity_groupmode($cm);

if ($groupmode) {
    groups_get_activity_group($cm, true);
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/roleplay/view.php?id='.$id);
}

// Check if we want to include responses from inactive users.
$onlyactive = $roleplay->includeinactive ? false : true;

$allresponses = roleplay_get_response_data($roleplay, $cm, $groupmode, $onlyactive);   // Big function, approx 6 SQL calls per user.


if (has_capability('mod/roleplay:readresponses', $context)) {
    roleplay_show_reportlink($allresponses, $cm);
}

echo '<div class="clearer"></div>';

if ($roleplay->intro) {
    echo $OUTPUT->box(format_module_intro('roleplay', $roleplay, $cm->id), 'generalbox', 'intro');
}

$timenow = time();
$current = roleplay_get_my_response($roleplay);
//if user has already made a selection, and they are not allowed to update it or if roleplay is not open, show their selected answer.
if (isloggedin() && (!empty($current)) &&
    (empty($roleplay->allowupdate) || ($timenow > $roleplay->timeclose)) ) {
    $roleplaytexts = array();
    foreach ($current as $c) {
        $roleplaytexts[] = format_string(roleplay_get_option_text($roleplay, $c->optionid));
    }
    echo $OUTPUT->box(get_string("yourselection", "roleplay", userdate($roleplay->timeopen)).": ".implode('; ', $roleplaytexts), 'generalbox', 'yourselection');
}

/// Print the form
$roleplayopen = true;
if ((!empty($roleplay->timeopen)) && ($roleplay->timeopen > $timenow)) {
    if ($roleplay->showpreview) {
        echo $OUTPUT->box(get_string('previewonly', 'roleplay', userdate($roleplay->timeopen)), 'generalbox alert');
    } else {
        echo $OUTPUT->box(get_string("notopenyet", "roleplay", userdate($roleplay->timeopen)), "generalbox notopenyet");
        echo $OUTPUT->footer();
        exit;
    }
} else if ((!empty($roleplay->timeclose)) && ($timenow > $roleplay->timeclose)) {
    echo $OUTPUT->box(get_string("expired", "roleplay", userdate($roleplay->timeclose)), "generalbox expired");
    $roleplayopen = false;
}

if ( (!$current or $roleplay->allowupdate) and $roleplayopen and is_enrolled($context, NULL, 'mod/roleplay:choose')) {

    // Show information on how the results will be published to students.
    $publishinfo = null;
    switch ($roleplay->showresults) {
        case ROLEPLAY_SHOWRESULTS_NOT:
            $publishinfo = get_string('publishinfonever', 'roleplay');
            break;

        case ROLEPLAY_SHOWRESULTS_AFTER_ANSWER:
            if ($roleplay->publish == ROLEPLAY_PUBLISH_ANONYMOUS) {
                $publishinfo = get_string('publishinfoanonafter', 'roleplay');
            } else {
                $publishinfo = get_string('publishinfofullafter', 'roleplay');
            }
            break;

        case ROLEPLAY_SHOWRESULTS_AFTER_CLOSE:
            if ($roleplay->publish == ROLEPLAY_PUBLISH_ANONYMOUS) {
                $publishinfo = get_string('publishinfoanonclose', 'roleplay');
            } else {
                $publishinfo = get_string('publishinfofullclose', 'roleplay');
            }
            break;

        default:
            // No need to inform the user in the case of ROLEPLAY_SHOWRESULTS_ALWAYS since it's already obvious that the results are
            // being published.
            break;
    }

    // Show info if necessary.
    if (!empty($publishinfo) && $roleplayavailable) {
        echo $OUTPUT->notification($publishinfo, 'info');
    }

    // They haven't made their roleplay yet or updates allowed and roleplay is open.
    if ($roleplayavailable) {
        $options = roleplay_prepare_options($roleplay, $USER, $cm, $allresponses);
        $renderer = $PAGE->get_renderer('mod_roleplay');
        echo $renderer->display_options($options, $cm->id, $roleplay->display, $roleplay->allowmultiple, $roleplay->allowcomment);
        $roleplayformshown = true;
    } else {
        $roleplayformshown = false;
    }
} else {
    $roleplayformshown = false;
}

if (!$roleplayformshown) {
    $sitecontext = context_system::instance();

    if (isguestuser()) {
        // Guest account
        echo $OUTPUT->confirm(get_string('noguestchoose', 'roleplay').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/course/view.php', array('id'=>$course->id)));
    } else if (!is_enrolled($context)) {
        // Only people enrolled can make a roleplay
        $SESSION->wantsurl = qualified_me();
        $SESSION->enrolcancel = get_local_referer(false);

        $coursecontext = context_course::instance($course->id);
        $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

        echo $OUTPUT->box_start('generalbox', 'notice');
        echo '<p align="center">'. get_string('notenrolledchoose', 'roleplay') .'</p>';
        echo $OUTPUT->container_start('continuebutton');
        echo $OUTPUT->single_button(new moodle_url('/enrol/index.php?', array('id'=>$course->id)), get_string('enrolme', 'core_enrol', $courseshortname));
        echo $OUTPUT->container_end();
        echo $OUTPUT->box_end();

    }
}

// print the results at the bottom of the screen
if (roleplay_can_view_results($roleplay, $current, $roleplayopen)) {
    $results = prepare_roleplay_show_results($roleplay, $course, $cm, $allresponses);
    $renderer = $PAGE->get_renderer('mod_roleplay');
    $resultstable = $renderer->display_result($results);
    echo $OUTPUT->box($resultstable);

} else if (!$roleplayformshown) {
    echo $OUTPUT->box(get_string('noresultsviewable', 'roleplay'));
}

echo $OUTPUT->footer();
