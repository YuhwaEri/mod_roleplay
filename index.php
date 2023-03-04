<?php

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id',PARAM_INT);   // course

    $PAGE->set_url('/mod/roleplay/index.php', array('id'=>$id));

    if (!$course = $DB->get_record('course', array('id'=>$id))) {
        print_error('invalidcourseid');
    }

    require_course_login($course);
    $PAGE->set_pagelayout('incourse');

    $eventdata = array('context' => context_course::instance($id));
    $event = \mod_roleplay\event\course_module_instance_list_viewed::create($eventdata);
    $event->add_record_snapshot('course', $course);
    $event->trigger();

    $strroleplay = get_string("modulename", "roleplay");
    $strroleplays = get_string("modulenameplural", "roleplay");
    $PAGE->set_title($strroleplays);
    $PAGE->set_heading($course->fullname);
    $PAGE->navbar->add($strroleplays);
    echo $OUTPUT->header();

    if (! $roleplays = get_all_instances_in_course("roleplay", $course)) {
        notice(get_string('thereareno', 'moodle', $strroleplays), "../../course/view.php?id=$course->id");
    }

    $usesections = course_format_uses_sections($course->format);

    $sql = "SELECT cha.*
              FROM {roleplay} ch, {roleplay_answers} cha
             WHERE cha.roleplayid = ch.id AND
                   ch.course = ? AND cha.userid = ?";

    $answers = array () ;
    if (isloggedin() and !isguestuser() and $allanswers = $DB->get_records_sql($sql, array($course->id, $USER->id))) {
        foreach ($allanswers as $aa) {
            $answers[$aa->roleplayid] = $aa;
        }
        unset($allanswers);
    }


    $timenow = time();

    $table = new html_table();

    if ($usesections) {
        $strsectionname = get_string('sectionname', 'format_'.$course->format);
        $table->head  = array ($strsectionname, get_string("question"), get_string("answer"));
        $table->align = array ("center", "left", "left");
    } else {
        $table->head  = array (get_string("question"), get_string("answer"));
        $table->align = array ("left", "left");
    }

    $currentsection = "";

    foreach ($roleplays as $roleplay) {
        if (!empty($answers[$roleplay->id])) {
            $answer = $answers[$roleplay->id];
        } else {
            $answer = "";
        }
        if (!empty($answer->optionid)) {
            $aa = format_string(roleplay_get_option_text($roleplay, $answer->optionid));
        } else {
            $aa = "";
        }
        if ($usesections) {
            $printsection = "";
            if ($roleplay->section !== $currentsection) {
                if ($roleplay->section) {
                    $printsection = get_section_name($course, $roleplay->section);
                }
                if ($currentsection !== "") {
                    $table->data[] = 'hr';
                }
                $currentsection = $roleplay->section;
            }
        }

        //Calculate the href
        if (!$roleplay->visible) {
            //Show dimmed if the mod is hidden
            $tt_href = "<a class=\"dimmed\" href=\"view.php?id=$roleplay->coursemodule\">".format_string($roleplay->name,true)."</a>";
        } else {
            //Show normal if the mod is visible
            $tt_href = "<a href=\"view.php?id=$roleplay->coursemodule\">".format_string($roleplay->name,true)."</a>";
        }
        if ($usesections) {
            $table->data[] = array ($printsection, $tt_href, $aa);
        } else {
            $table->data[] = array ($tt_href, $aa);
        }
    }
    echo "<br />";
    echo html_writer::table($table);

    echo $OUTPUT->footer();


