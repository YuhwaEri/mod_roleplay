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
 * Moodle renderer used to display special elements of the lesson module
 *
 * @package   mod_roleplay
 * @copyright 2010 Rossiani Wijaya
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
define ('DISPLAY_HORIZONTAL_LAYOUT', 0);
define ('DISPLAY_VERTICAL_LAYOUT', 1);

class mod_roleplay_renderer extends plugin_renderer_base {

    /**
     * Returns HTML to display roleplays of option
     * @param object $options
     * @param int  $coursemoduleid
     * @param bool $vertical
     * @return string
     */
    public function display_options($options, $coursemoduleid, $vertical = false, $multiple = false, $allowcomment = false,$allowoptiondesc = false) {
        $layoutclass = 'horizontal';
        if ($vertical) {
            $layoutclass = 'vertical';
        }
        $target = new moodle_url('/mod/roleplay/view.php');
        $attributes = array('method'=>'POST', 'action'=>$target, 'class'=> $layoutclass);
        $disabled = empty($options['previewonly']) ? array() : array('disabled' => 'disabled');

        $html = html_writer::start_tag('form', $attributes);
        $html .= html_writer::start_tag('ul', array('class' => 'roleplays list-unstyled unstyled'));

        $availableoption = count($options['options']);
        $roleplaycount = 0;
        foreach ($options['options'] as $option) {
            $roleplaycount++;
            $html .= html_writer::start_tag('li', array('class' => 'option mb-3'));
            if ($multiple) {
                $option->attributes->name = 'answer[]';
                $option->attributes->type = 'checkbox';
            } else {
                $option->attributes->name = 'answer';
                $option->attributes->type = 'radio';
            }
            $option->attributes->id = 'roleplay_' . $roleplaycount;
            $option->attributes->class = 'm-x-1';

            $labeltext = $option->text;
            if (!empty($option->attributes->disabled)) {
                $labeltext .= ' ' . get_string('full', 'roleplay');
                $availableoption--;
            }

            $html .= html_writer::empty_tag('input', (array)$option->attributes + $disabled);
            $html .= html_writer::start_tag('label', array('for' => $option->attributes->id, 'class' => 'bold mb-0', 'style' => 'vertical-align:top'));
            $html .= $labeltext;
            if ($allowoptiondesc){
                $html .= html_writer::tag('div', $option->option_desc, array('class' => 'small optiondescription'));
            }
            $html .= html_writer::end_tag('label');
            $html .= html_writer::end_tag('li');
        }

        if ($allowcomment) {
            $html .= html_writer::start_tag('li', array('class'=>'option'));
            $html .= html_writer::start_tag('textarea', array('class'=>'form-control answer-comment', 'name'=>'answer_comment', 'placeholder'=>get_string('commentlabel', 'roleplay')));
            $html .= html_writer::end_tag('textarea');
            $html .= html_writer::end_tag('li');
        }


        $html .= html_writer::tag('li','', array('class'=>'clearfloat'));
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::tag('div', '', array('class'=>'clearfloat'));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'makeroleplay'));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=>$coursemoduleid));

        if (empty($options['previewonly'])) {
            if (!empty($options['hascapability']) && ($options['hascapability'])) {
                if ($availableoption < 1) {
                    $html .= html_writer::tag('label', get_string('roleplayfull', 'roleplay'));
                } else {
                    $html .= html_writer::empty_tag('input', array(
                        'type' => 'submit',
                        'value' => get_string('savemyroleplay', 'roleplay'),
                        'class' => 'btn btn-primary'
                    ));
                }

                if (!empty($options['allowupdate']) && ($options['allowupdate'])) {
                    $url = new moodle_url('view.php',
                        array('id' => $coursemoduleid, 'action' => 'delroleplay', 'sesskey' => sesskey()));
                    $html .= html_writer::link($url, get_string('removemyroleplay', 'roleplay'), array('class' => 'm-l-1'));
                }
            } else {
                $html .= html_writer::tag('label', get_string('havetologin', 'roleplay'));
            }
        }

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_tag('form');

        return $html;
    }


    /**
     * Returns HTML to display roleplays result
     * @param object $roleplays
     * @param bool $forcepublish
     * @return string
     */
    public function display_result($roleplays, $forcepublish = false, $usergroups = null) {
        if (empty($forcepublish)) { //allow the publish setting to be overridden
            $forcepublish = $roleplays->publish;
        }

        $displaylayout = $roleplays->display;

        if ($forcepublish) {  //ROLEPLAY_PUBLISH_NAMES
            if ($usergroups) {
                return $this->display_publish_with_groups($roleplays, $usergroups);
            }
            return $this->display_publish_name_vertical($roleplays);
        } else {
            return $this->display_publish_anonymous($roleplays, $displaylayout);
        }
    }

    /**
     * Returns HTML to display roleplays result
     * New layout for mod_roleplay - with groups
     * @param object $roleplays
     * @return string
     */
    public function display_publish_with_groups($roleplays, $usergroups) {
        global $PAGE;
        $PAGE->requires->js_call_amd('mod_roleplay/comment', 'init');
        $html ='';
        $html .= html_writer::tag('h3',format_string(get_string("responses", "roleplay")));

        $attributes = array('method'=>'POST');
        $attributes['action'] = new moodle_url($PAGE->url);
        $attributes['id'] = 'attemptsform';

        if ($roleplays->viewresponsecapability) {
            $html .= html_writer::start_tag('form', $attributes);
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=> $roleplays->coursemoduleid));
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=> sesskey()));
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode', 'value'=>'overview'));
        }

        $table = new html_table();
        $table->cellpadding = 0;
        $table->cellspacing = 0;
        $table->attributes['class'] = 'results names table table-bordered table-striped';
        $table->tablealign = 'center';
        $table->summary = get_string('responsesto', 'roleplay', format_string($roleplays->name));
        $table->data = array();

        $count = 0;
        ksort($roleplays->options);

        $columns = array();
        $celldefault = new html_table_cell();
        $celldefault->attributes['class'] = 'data';

        // This extra cell is needed in order to support accessibility for screenreader. MDL-30816
        $accessiblecell = new html_table_cell();
        $accessiblecell->scope = 'row';
        $accessiblecell->text = '';
        $accessiblecell->style = "background-color:#337db8;color:white";
        $columns['groups'][] = $accessiblecell;

        $groupsnames = [];
        $columnusers = [];

        foreach ($usergroups as $gid => $g) {
            $cellgroup = clone($celldefault);

            $celltext = '';
            if ($roleplays->showunanswered && $gid == 0) {
                $celltext = get_string('nogroup', 'roleplay');
            } else if ($gid > 0) {
                $celltext = format_string($g->name);
            }

            $cellgroup->text = $celltext;
            $cellgroup->style = "background-color:#337db8;color:white;text-align:center;font-size:1.2em";
            $groupsnames[$gid] = $celltext;

            $columns['groups'][] = $cellgroup;
            $columnusers[] = $g->members;
        }

        $table->head = $columns['groups'];

        $columns = array();

        foreach ($roleplays->options as $optionid => $options) {
            $columns = [];
            $cell = new html_table_cell();
            $cell->attributes['class'] = 'header data';
            $cell->text = $options->text;
            $cell->header = true;
            $cell->scope = 'row';
            $columns[] = $cell;

            foreach ($columnusers as $col_i => $col_members) {

                $cell = new html_table_cell();
                $cell->attributes['class'] = 'data';

                if ($roleplays->showunanswered || $optionid > 0) {
                    if (!empty($options->user)) {
                        $optionusers = '';
                        foreach ($options->user as $user) {
                            if (!in_array($user->id, $col_members)) continue;
                            if (empty($user->imagealt)) {
                                $user->imagealt = '';
                            }

                            $userfullname = fullname($user, $roleplays->fullnamecapability);
                            $checkbox = '';
                            if ($roleplays->viewresponsecapability && $roleplays->deleterepsonsecapability) {
                                $checkboxid = 'attempt-user' . $user->id . '-option' . $optionid;
                                $checkbox .= html_writer::label($userfullname . ' ' . 123,
                                    $checkboxid, false, array('class' => 'accesshide'));
                                if ($optionid > 0) {
                                    $checkboxname = 'attemptid[]';
                                    $checkboxvalue = $user->answerid;
                                } else {
                                    $checkboxname = 'userid[]';
                                    $checkboxvalue = $user->id;
                                }
                                $checkbox .= html_writer::checkbox($checkboxname, $checkboxvalue, '', null,
                                    array('id' => $checkboxid, 'class' => 'm-r-1'));
                            }
                            $userimage = $this->output->user_picture($user, array('courseid' => $roleplays->courseid, 'link' => false));
                            $profileurl = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $roleplays->courseid));
                            $profilelink = html_writer::link($profileurl, $userimage . $userfullname);
                            $commentlink = $user->comment ? ' <i class="fa fa-commenting-o ml-2 show-comment" data-comment="'.$user->comment.'" data-comment-title="'.$userfullname.'" title="Show comment"></i>' : '';
                            $optionusers .= html_writer::div($checkbox . $profilelink . $commentlink, 'mb-1');

                        }
                        $cell->text = $optionusers;
                    }
                }
                $columns[] = $cell;
            }
            $row = new html_table_row($columns);
            $table->data[] = $row;
            $count++;
        }

        $html .= html_writer::tag('div', html_writer::table($table), array('class'=>'response'));

        $actiondata = '';
        if ($roleplays->viewresponsecapability && $roleplays->deleterepsonsecapability) {
            $selecturl = new moodle_url('#');

            $actiondata .= html_writer::start_div('selectallnone');
            $actiondata .= html_writer::link($selecturl, get_string('selectall'), ['data-select-info' => true]) . ' / ';

            $actiondata .= html_writer::link($selecturl, get_string('deselectall'), ['data-select-info' => false]);

            $actiondata .= html_writer::end_div();

            $actionurl = new moodle_url($PAGE->url, array('sesskey'=>sesskey(), 'action'=>'delete_confirmation()'));
            $actionoptions = array('delete' => get_string('delete'));

            // isn't needed
            // foreach ($roleplays->options as $optionid => $option) {
            //     if ($optionid > 0) {
            //         $actionoptions['choose_'.$optionid] = get_string('chooseoption', 'roleplay', $option->text);
            //     }
            // }

            $select = new single_select($actionurl, 'action', $actionoptions, null,
                array('' => get_string('chooseaction', 'roleplay')), 'attemptsform');
            $select->set_label(get_string('withselected', 'roleplay'));

            $PAGE->requires->js_call_amd('mod_roleplay/select_all_choices', 'init');

            $actiondata .= $this->output->render($select);
        }
        $html .= html_writer::tag('div', $actiondata, array('class'=>'responseaction'));

        if ($roleplays->viewresponsecapability) {
            $html .= html_writer::end_tag('form');
        }

        return $html;
    }

    /**
     * Returns HTML to display roleplays result
     * @param object $roleplays
     * @param bool $forcepublish
     * @return string
     */
    public function display_publish_name_vertical($roleplays) {
        global $PAGE;
        $PAGE->requires->js_call_amd('mod_roleplay/comment', 'init');
        $html ='';
        $html .= html_writer::tag('h3',format_string(get_string("responses", "roleplay")));

        $attributes = array('method'=>'POST');
        $attributes['action'] = new moodle_url($PAGE->url);
        $attributes['id'] = 'attemptsform';

        if ($roleplays->viewresponsecapability) {
            $html .= html_writer::start_tag('form', $attributes);
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=> $roleplays->coursemoduleid));
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=> sesskey()));
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode', 'value'=>'overview'));
        }

        $table = new html_table();
        $table->cellpadding = 0;
        $table->cellspacing = 0;
        $table->attributes['class'] = 'results names table table-bordered';
        $table->tablealign = 'center';
        $table->summary = get_string('responsesto', 'roleplay', format_string($roleplays->name));
        $table->data = array();

        $count = 0;
        ksort($roleplays->options);

        $columns = array();
        $celldefault = new html_table_cell();
        $celldefault->attributes['class'] = 'data';

        // This extra cell is needed in order to support accessibility for screenreader. MDL-30816
        $accessiblecell = new html_table_cell();
        $accessiblecell->scope = 'row';
        $accessiblecell->text = get_string('roleplayoptions', 'roleplay');
        $columns['options'][] = $accessiblecell;

        $usernumberheader = clone($celldefault);
        $usernumberheader->header = true;
        $usernumberheader->attributes['class'] = 'header data';
        $usernumberheader->text = get_string('numberofuser', 'roleplay');
        $columns['usernumber'][] = $usernumberheader;

        $optionsnames = [];
        foreach ($roleplays->options as $optionid => $options) {
            $celloption = clone($celldefault);
            $cellusernumber = clone($celldefault);
            $cellusernumber->style = 'text-align: center;';

            $celltext = '';
            if ($roleplays->showunanswered && $optionid == 0) {
                $celltext = get_string('notanswered', 'roleplay');
            } else if ($optionid > 0) {
                $celltext = format_string($roleplays->options[$optionid]->text);
            }
            $numberofuser = 0;
            if (!empty($options->user) && count($options->user) > 0) {
                $numberofuser = count($options->user);
            }

            $celloption->text = $celltext;
            $optionsnames[$optionid] = $celltext;
            $cellusernumber->text = $numberofuser;

            $columns['options'][] = $celloption;
            $columns['usernumber'][] = $cellusernumber;
        }

        $table->head = $columns['options'];
        $table->data[] = new html_table_row($columns['usernumber']);

        $columns = array();

        // This extra cell is needed in order to support accessibility for screenreader. MDL-30816
        $accessiblecell = new html_table_cell();
        $accessiblecell->text = get_string('userchoosethisoption', 'roleplay');
        $accessiblecell->header = true;
        $accessiblecell->scope = 'row';
        $accessiblecell->attributes['class'] = 'header data';
        $columns[] = $accessiblecell;

        foreach ($roleplays->options as $optionid => $options) {
            $cell = new html_table_cell();
            $cell->attributes['class'] = 'data';

            if ($roleplays->showunanswered || $optionid > 0) {
                if (!empty($options->user)) {
                    $optionusers = '';
                    foreach ($options->user as $user) {
                        $data = '';
                        if (empty($user->imagealt)) {
                            $user->imagealt = '';
                        }

                        $userfullname = fullname($user, $roleplays->fullnamecapability);
                        $checkbox = '';
                        if ($roleplays->viewresponsecapability && $roleplays->deleterepsonsecapability) {
                            $checkboxid = 'attempt-user' . $user->id . '-option' . $optionid;
                            $checkbox .= html_writer::label($userfullname . ' ' . $optionsnames[$optionid],
                                $checkboxid, false, array('class' => 'accesshide'));
                            if ($optionid > 0) {
                                $checkboxname = 'attemptid[]';
                                $checkboxvalue = $user->answerid;
                            } else {
                                $checkboxname = 'userid[]';
                                $checkboxvalue = $user->id;
                            }
                            $checkbox .= html_writer::checkbox($checkboxname, $checkboxvalue, '', null,
                                array('id' => $checkboxid, 'class' => 'm-r-1'));
                        }
                        $userimage = $this->output->user_picture($user, array('courseid' => $roleplays->courseid, 'link' => false));
                        $profileurl = new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $roleplays->courseid));
                        $profilelink = html_writer::link($profileurl, $userimage . $userfullname);
                        $commentlink = $user->comment ? ' <i class="fa fa-commenting-o ml-2 show-comment" data-comment="'.$user->comment.'" data-comment-title="'.$userfullname.'" title="Show comment"></i>' : '';
                        $data .= html_writer::div($checkbox . $profilelink . $commentlink, 'mb-1');

                        $optionusers .= $data;
                    }
                    $cell->text = $optionusers;
                }
            }
            $columns[] = $cell;
            $count++;
        }
        $row = new html_table_row($columns);
        $table->data[] = $row;

        $html .= html_writer::tag('div', html_writer::table($table), array('class'=>'response'));

        $actiondata = '';
        if ($roleplays->viewresponsecapability && $roleplays->deleterepsonsecapability) {
            $selecturl = new moodle_url('#');

            $actiondata .= html_writer::start_div('selectallnone');
            $actiondata .= html_writer::link($selecturl, get_string('selectall'), ['data-select-info' => true]) . ' / ';

            $actiondata .= html_writer::link($selecturl, get_string('deselectall'), ['data-select-info' => false]);

            $actiondata .= html_writer::end_div();

            $actionurl = new moodle_url($PAGE->url, array('sesskey'=>sesskey(), 'action'=>'delete_confirmation()'));
            $actionoptions = array('delete' => get_string('delete'));
            foreach ($roleplays->options as $optionid => $option) {
                if ($optionid > 0) {
                    $actionoptions['choose_'.$optionid] = get_string('chooseoption', 'roleplay', $option->text);
                }
            }
            $select = new single_select($actionurl, 'action', $actionoptions, null,
                array('' => get_string('chooseaction', 'roleplay')), 'attemptsform');
            $select->set_label(get_string('withselected', 'roleplay'));

            $PAGE->requires->js_call_amd('mod_roleplay/select_all_choices', 'init');

            $actiondata .= $this->output->render($select);
        }
        $html .= html_writer::tag('div', $actiondata, array('class'=>'responseaction'));

        if ($roleplays->viewresponsecapability) {
            $html .= html_writer::end_tag('form');
        }

        return $html;
    }


    /**
     * Returns HTML to display roleplays result
     * @deprecated since 3.2
     * @param object $roleplays
     * @return string
     */
    public function display_publish_anonymous_horizontal($roleplays) {
        global $ROLEPLAY_COLUMN_HEIGHT;
        debugging(__FUNCTION__.'() is deprecated. Please use mod_roleplay_renderer::display_publish_anonymous() instead.',
            DEBUG_DEVELOPER);
        return $this->display_publish_anonymous($roleplays, ROLEPLAY_DISPLAY_VERTICAL);
    }

    /**
     * Returns HTML to display roleplays result
     * @deprecated since 3.2
     * @param object $roleplays
     * @return string
     */
    public function display_publish_anonymous_vertical($roleplays) {
        global $ROLEPLAY_COLUMN_WIDTH;
        debugging(__FUNCTION__.'() is deprecated. Please use mod_roleplay_renderer::display_publish_anonymous() instead.',
            DEBUG_DEVELOPER);
        return $this->display_publish_anonymous($roleplays, ROLEPLAY_DISPLAY_HORIZONTAL);
    }

    /**
     * Generate the roleplay result chart.
     *
     * Can be displayed either in the vertical or horizontal position.
     *
     * @param stdClass $roleplays Roleplays responses object.
     * @param int $displaylayout The constants DISPLAY_HORIZONTAL_LAYOUT or DISPLAY_VERTICAL_LAYOUT.
     * @return string the rendered chart.
     */
    public function display_publish_anonymous($roleplays, $displaylayout) {
        global $OUTPUT;
        $count = 0;
        $data = [];
        $numberofuser = 0;
        $percentageamount = 0;
        foreach ($roleplays->options as $optionid => $option) {
            if (!empty($option->user)) {
                $numberofuser = count($option->user);
            }
            if($roleplays->numberofuser > 0) {
                $percentageamount = ((float)$numberofuser / (float)$roleplays->numberofuser) * 100.0;
            }
            $data['labels'][$count] = $option->text;
            $data['series'][$count] = $numberofuser;
            $data['series_labels'][$count] = $numberofuser . ' (' . format_float($percentageamount, 1) . '%)';
            $count++;
            $numberofuser = 0;
        }

        $chart = new \core\chart_bar();
        if ($displaylayout == DISPLAY_HORIZONTAL_LAYOUT) {
            $chart->set_horizontal(true);
        }
        $series = new \core\chart_series(format_string(get_string("responses", "roleplay")), $data['series']);
        $series->set_labels($data['series_labels']);
        $chart->add_series($series);
        $chart->set_labels($data['labels']);
        $yaxis = $chart->get_yaxis(0, true);
        $yaxis->set_stepsize(max(1, round(max($data['series']) / 10)));
        return $OUTPUT->render($chart);
    }
}

