<?php // $Id: assignment.class.php,v 1.32.2.15 2008/10/09 11:22:14 poltawski Exp $
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/assignment/type/upload/assignment.class.php');

/**
 * Extend the base assignment class for assignments where you upload a single file
 *
 */
class assignment_team extends assignment_upload {

    function assignment_team($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_upload($cmid, $assignment, $cm, $course);
        $this->type = 'typeteam';
    }

    function view() {
        session_start();
        global $USER;
        
        $joinaction = optional_param('act_jointeam', NULL, PARAM_RAW);
        $removeaction = optional_param('act_removemember', NULL, PARAM_RAW);
        $deleteteamaction = optional_param('act_deleteteam',NULL,  PARAM_RAW);
        $opencloseaction = optional_param('act_opencloseteam',NULL, PARAM_RAW);
        $createaction = optional_param('act_createteam', NULL,  PARAM_RAW);
        $teacherdeleteteamact = optional_param('act_teacherdeleteteam', NULL, PARAM_RAW);
        $teacherdeletemember =  optional_param('act_teacherdeletemember', NULL, PARAM_RAW);

        //common parameters
        $teamid = optional_param('teamid', NULL, PARAM_INT);

        require_capability('mod/assignment:view', $this->context);


        add_to_log($this->course->id, 'assignment', 'view', "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        $this->view_header();

        if ($this->assignment->timeavailable > time()
        and !has_capability('mod/assignment:grade', $this->context)      // grading user can see it anytime
        and $this->assignment->var3) {                                   // force hiding before available date
            print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
            print_string('notavailableyet', 'assignment');
            print_simple_box_end();
        } else {
            $this->view_intro();
        }

        $this->view_dates();
        $submitcap  = has_capability('mod/assignment:submit', $this->context);
        $gradecap = has_capability('mod/assignment:grade', $this->context);
        
        if ($submitcap || $gradecap) {
            //2.check if user can remove a member from a team
            if (isset($removeaction) || isset($teacherdeletemember)) {
                //possible values
                $members = optional_param('members', NULL, PARAM_INT);
                $removetime = optional_param('removetime', NULL, PARAM_INT);
                $confirm = optional_param('confirm', NULL, PARAM_INT);
                if (isset($members)
                && isset($removetime)
                && ($this->is_member($teamid) ||$gradecap)
                && (!isset($_SESSION['removetime']) || $_SESSION['removetime']!= $removetime)
                //use session control to avoid users processing this action by refresh browser.
                ) {
                    error_log('start remove members');
                    $_SESSION['removetime'] = $removetime;
                    //$members maynot be array if the POST back from delete members UI
                    //refer remove_user_from_team().

                    if (!is_array($members)) {
                        if( is_numeric($members)
                        && $members >= 0
                        && isset($confirm)
                        && $confirm == 1) {
                            $memberids = array();
                            for($i = 0 ; $i<=$members ; $i++){
                                $memberkey = 'member'.$i;
                                $memberids[] = optional_param ($memberkey, NULL, PARAM_INT);
                            }
                            $this ->remove_users_from_team($memberids, $teamid, $gradecap);
                        }
                    } else if(count($members)>0) {
                        $this->remove_users_from_team($members, $teamid, $gradecap);
                    }
                }
            }
            
        }
        
        if ($submitcap) {
             
            $this->view_feedback();
            //1.check if user can join team
            //possible values from join team action
            $groups = optional_param('groups', NULL, PARAM_INT);
            $jointime = optional_param('jointeamtime', NULL, PARAM_INT);
            if (isset($joinaction)
            && isset($jointime)
            && isset($groups)
            && count($groups)==1
            && (!isset($_SESSION['jointeamtime']) || $_SESSION['jointeamtime']!= $jointime) ) {
                error_log('start join team');
                $this->join_team($USER->id, $groups[0]);
                $_SESSION['jointeamtime'] = $jointime;
            }

            //3.check if user delete a team
            $deleteteamtime = optional_param('deleteteamtime', NULL, PARAM_INT);
            if (isset($deleteteamaction)
            && isset($deleteteamtime)
            && isset($teamid)
            && (!isset($_SESSION['deleteteamtime']) || $_SESSION['deleteteamtime']!= $deleteteamtime )) {
                //use session control to avoid users processing this action by refresh browser.
                error_log('start to delete team ');
                $this->delete_team($teamid);
                $_SESSION['deleteteamtime'] = $deleteteamtime;
            }

            //4. check if user can open or close team
            $openclosetime = optional_param('openclosetime', NULL, PARAM_INT);
            if (isset($opencloseaction)
            && isset($openclosetime )
            && isset($teamid)
            && (!isset($_SESSION['openclosetime']) || $_SESSION['openclosetime']!= $openclosetime)
            ) {
                error_log('start to open or close a team');
                //use session control to avoid users processing this action by refresh browser.
                $this->open_close_team($teamid);
                $_SESSION['openclosetime'] = $openclosetime;

            }

            //5. check if user can create a team
            $createteamtime = optional_param('createteamtime', NULL, PARAM_INT);
            $teamname = optional_param('teamname', NULL, PARAM_RAW);
            if (isset($createaction)
            && isset($createteamtime)
            && (!isset($_SESSION['createteamtime']) || $_SESSION['createteamtime']!= $createteamtime)) {
                error_log('create team start');
                error_log('creation parameter: '.$createaction);
                $this->create_team($teamname);
                $_SESSION['createteamtime'] = $createteamtime;
            }

            //6. check if user belongs to a team for this assignment.
            //We already done capability check. 
            $team = $this->get_user_team($USER->id);
            if ($team) {
                $filecount = $this->count_user_files($USER->id);
                $submission = $this->get_submission($USER->id);
                $this->print_team_admin($team, $filecount, $submission);
                $this->view_final_submission($team->id);
            } else {
                // Allow the user to join an existing team or create and join a new team
                $this->print_team_list();
            }
        } elseif($gradecap) {
            
            //7. teachers, edit teacher, or admin can delete a team
            $deletetime = optional_param('teacherdeleteteamtime', NULL, PARAM_INT);
            $groups = optional_param('groups', NULL, PARAM_INT);
            error_log("delete time: ".$deletetime);
            error_log('teacher delete team action : '.$teacherdeleteteamact);
            if (isset($teacherdeleteteamact)
            && isset($deletetime)
            && (!isset($_SESSION['teacherdeleteteamtime']) || $_SESSION['teacherdeleteteamtime']!= $deletetime )) {
                
                $confirm  = optional_param('confirm', 0, PARAM_BOOL);
                if ($confirm == 0 && is_array($groups) && count($groups)==1 ) {
                    error_log('groups[0] team id'.$groups[0]);
                    $optionsyes = array('confirm'=>1,'teamid'=>$groups[0], 'teacherdeleteteamtime'=> time(), 'act_teacherdeleteteam'=>get_string('deleteteam','assignment_team'));
                    $optionno = array();
                    $team = get_record('team', 'id', $groups[0]);
                    error_log('team name: '.$team->name);
                    if ($team) {
                        $message = get_string('teacherdelteteam','assignment_team', $team->name);
                        print_heading(get_string('delete'));
                        notice_yesno($message, $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_URI'], $optionsyes, $optionno, 'post', 'post');
                        print_footer('none');
                        die;
                    }
                    
                } elseif ($confirm == 1 && isset($teamid)) {
                    //use session control to avoid users processing this action by refresh browser.
                    error_log('teacher start to delete team  id:'.$teamid);
                    $this->delete_team($teamid, true);
                    $_SESSION['teacherdeleteteamtime'] = $deletetime;
                }
                
            }
            
            //8 teachers , editor teachers, or admin remove a team members
            if (isset($teacherdeletemember)) {
                //possible values
                error_log('teacher remove team members');
                $members = optional_param('members', NULL, PARAM_INT);
                $removetime = optional_param('removetime', NULL, PARAM_INT);
                $confirm = optional_param('confirm', NULL, PARAM_INT);
                if (isset($members)
                && isset($removetime)
                && $this->is_member($teamid)
                && (!isset($_SESSION['removetime']) || $_SESSION['removetime']!= $removetime)
                //use session control to avoid users processing this action by refresh browser.
                ) {
                    error_log('start remove members');
                    $_SESSION['removetime'] = $removetime;
                    //$members maynot be array if the POST back from delete members UI
                    //refer remove_user_from_team().

                    if (!is_array($members)) {
                        if( is_numeric($members)
                        && $members >= 0
                        && isset($confirm)
                        && $confirm == 1) {
                            $memberids = array();
                            for($i = 0 ; $i<=$members ; $i++){
                                $memberkey = 'member'.$i;
                                $memberids[] = optional_param ($memberkey, NULL, PARAM_INT);
                            }
                            $this ->remove_users_from_team($memberids, $teamid);
                        }
                    } else if(count($members)>0) {
                        $this->remove_users_from_team($members, $teamid);
                    }
                }
            }
            
            $this->print_team_list(true);
            
        } 
        
        $this->view_footer();
    }
    
    /**
     * override super class method
     */
    function view_feedback($submission=NULL) {
        global $USER, $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        error_log('view_feedback() method');
        if (!$submission) { /// Get submission for this assignment
            error_log('submission is null');
            $submission = $this->get_submission($USER->id);
        }

        if (empty($submission->timemarked)) {   /// Nothing to show, so print nothing
            error_log('check total response file');
            if ($this->count_total_responsefiles($USER->id)) {
                print_heading(get_string('responsefiles', 'assignment', $this->course->teacher), '', 3);
                $responsefiles = $this->print_responsefiles($USER->id, true);
                print_simple_box($responsefiles, 'center');
            }
            return;
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, $USER->id);
        $item = $grading_info->items[0];
        $grade = $item->grades[$USER->id];

        if ($grade->hidden or $grade->grade === false) { // hidden or error
            return;
        }

        if ($grade->grade === null and empty($grade->str_feedback)) {   /// Nothing to show yet
            return;
        }

        $graded_date = $grade->dategraded;
        $graded_by   = $grade->usermodified;

        /// We need the teacher info
        if (!$teacher = get_record('user', 'id', $graded_by)) {
            error('Could not find the teacher');
        }

        /// Print the feedback
        print_heading(get_string('submissionfeedback', 'assignment'), '', 3);

        echo '<table cellspacing="0" class="feedback">';

        echo '<tr>';
        echo '<td class="left picture">';
        print_user_picture($teacher, $this->course->id, $teacher->picture);
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        echo '<div class="fullname">'.fullname($teacher).'</div>';
        echo '<div class="time">'.userdate($graded_date).'</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        if ($this->assignment->grade) {
            echo '<div class="grade">';
            echo get_string("grade").': '.$grade->str_long_grade;
            echo '</div>';
            echo '<div class="clearer"></div>';
        }

        echo '<div class="comment">';
        echo $grade->str_feedback;
        echo '</div>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        echo $this->print_responsefiles($USER->id, true);
        echo '</tr>';

        echo '</table>';
    }

    function count_total_responsefiles($userid) {
        global $CFG;
        error_log('count total response files');
        $team = $this->get_user_team($userid);
        $filearea = $this->file_area_name($userid).'/responses';
        $filecount = 0;
        if ( is_dir($CFG->dataroot.'/'.$filearea) && $basedir = $this->file_area($userid)) {
            $basedir .= '/responses';
            if ($files = get_directory_list($basedir)) {
                $filecount = $filecount +  count($files);
            }
        }
        if (isset($team)) {
            $filecount = $filecount + $this->count_team_responsefiles($team->id);
        }
        
        return $filecount;
    }

    private function count_team_responsefiles($teamid) {
        global $CFG;
        error_log('count team response files');
        $teamfilearea = $this->team_file_area_name($teamid).'/responses';
        $basedir = $CFG->dataroot.'/'.$teamfilearea;
        error_log('base dir:'. $basedir);
        if (is_dir($basedir)) {
            error_log('base dir existing');
            if ($files = get_directory_list($basedir)) {
                return count($files);
            }
        }
        return 0;
    }
    /**
     * override super class method.
     * @param $userid
     * @param $return
     */
    function print_responsefiles($userid, $return=false) {
        error_log('print_responsefiles() method');
        global $CFG, $USER;

        error_log('print response files');
        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        $filearea = $this->file_area_name($userid).'/responses';

        $output = '';

        $candelete = $this->can_manage_responsefiles();
        $strdelete   = get_string('delete');

        //print team response files.

        $team = $this->get_user_team($userid);
        if (isset($team)) {
            error_log('print team response files start');
            $teamresponse = $this  -> print_team_responsefiles(NULL, $team->id, NULL, NULL, false);
            $output .= $teamresponse;
            $output .= '&nbsp;';
        }

        if ($basedir = $this->file_area($userid)) {
            $basedir .= '/responses';

            if ($files = get_directory_list($basedir)) {
                require_once($CFG->libdir.'/filelib.php');
                foreach ($files as $key => $file) {

                    $icon = mimeinfo('icon', $file);

                    $ffurl = get_file_url("$filearea/$file");

                    $output .= '<a href="'.$ffurl.'" ><img src="'.$CFG->pixpath.'/f/'.$icon.'" alt="'.$icon.'" />'.$file.'</a>';

                    if ($candelete) {
                        $delurl  = "$CFG->wwwroot/mod/assignment/delete.php?id={$this->cm->id}&amp;file=$file&amp;userid=$userid&amp;mode=$mode&amp;offset=$offset&amp;action=response";

                        $output .= '<a href="'.$delurl.'">&nbsp;'
                        .'<img title="'.$strdelete.'" src="'.$CFG->pixpath.'/t/delete.gif" class="iconsmall" alt=""/></a> ';
                    }

                    $output .= '&nbsp;';
                }

            }
            $output = '<div class="responsefiles">'.$output.'</div>';

        }
         
        if ($return) {
            return $output;
        }
        echo $output;
    }

    function print_team_admin($team, $filecount, $submission) {
        global $CFG, $USER;
        // display the team and the file submission box
        echo '<table cellpadding="6" class="generaltable generalbox groupmanagementtable boxaligncenter" summary="">'."\n";

        //submission file and managment button row
        echo '<tr>';
        //print team name and files
        echo '<td>';
        $teamheading = $team ->name." ".$this -> get_team_status_name($team->membershipopen);
        print_heading($teamheading, '', 3);
        if (!$this->drafts_tracked() or !$this->isopen() or $this->is_finalized($submission)) {
            print_heading(get_string('submission', 'assignment'), '', 9);
        } else {
            print_heading(get_string('submissiondraft', 'assignment'), '', 9);
        }

        if ($filecount and $submission) {
            print_simple_box($this->print_user_files($USER->id, true, $team->id), 'center');
        } else {
            if (!$this->isopen() or $this->is_finalized($submission)) {
                print_simple_box(get_string('nofiles', 'assignment'), 'center');
            } else {
                print_simple_box(get_string('nofilesyet', 'assignment'), 'center');
            }
        }
        echo '</td>';
         
        echo '<td>';
        echo '<form id="controlform" action="'.$_SERVER['REQUEST_URI'].'" method="post">';
        echo '<div align ="right" >';
        echo '<input type="hidden" name="teamid" value="'.$team->id.'" />';
        echo '<input type="hidden" name="openclosetime" value="'.time().'" />';
        echo '<input type="hidden" name="deleteteamtime" value="'.time().'" />';
        //may use for next release
        //echo '<input type ="submit" name ="act_editteam" style = "height:25px; width:100px" value="'.get_string('editteam','assignment_team').'"/><br/>';
        echo '<input type ="submit" name ="act_deleteteam" style = "height:25px; width:100px" value="'.get_string('deleteteam','assignment_team').'"/><br/>';
        echo '<input type ="submit" name ="act_opencloseteam" style = "height:25px; width:100px" value="'.get_string('openclosemembership','assignment_team').'"/><br/>';
        echo '</div>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
        //submission file and managment button row end

        //team member row and submission file start
        echo '<tr>';
        //print team members
        echo '<td>';
        echo '<p><label for="teammember"><span id="teammemberlabel">'.
        get_string('teammember', 'assignment_team').' </span></label></p>'."\n";
        echo '<form id="removememberform" action="'.$_SERVER['REQUEST_URI'].'" method="post">';
        echo '<select name ="members[]" multiple="multiple" id="teammember" size="15">';
        $members =$this -> get_members_from_team ($team ->id);
        if (is_array($members) && count($members)>0) {
            foreach ($members as $member) {
                $userid = $member->student;
                $user = get_record ('user', 'id', $userid);
                echo "<option value=\"{$user->id}\" >".fullname($user)."</option>";
            }
        } else {
            //print empty list
            echo '<option>&nbsp;</option>';
        }
        echo '</select><br/>';
        echo '<input type="hidden" name="teamid" value="'.$team->id.'" />';
        echo '<input type="hidden" name="removetime" value="'.time().'" />';
        echo '<input type ="submit" name ="act_removemember" value ="'.get_string('removeteammember','assignment_team').'"  >';
        echo '</form>';
        echo '</td>';
        echo '<td>';
        $this->view_upload_form($team->id);
        echo '</td>';
        echo '</tr>';
        //team row end
        echo '</table>';



    }

    /**
     * to support team feedback
     * Creating  a uploading response file input box.
     * @param $submission
     * @param $return
     */
    function custom_team_feedbackform($id, $teamid, $userrep, $mode) {
        global $CFG;
        $output = get_string('responsefiles', 'assignment').': ';

        $output .= '<form enctype="multipart/form-data" method="post" '.
             "action=\"$CFG->wwwroot/mod/assignment/upload.php\">";
        $output .= '<div>';
        $output .= '<input type="hidden" name="id" value="'.$id.'" />';
        $output .= '<input type="hidden" name="action" value="uploadteamresponse" />';
        $output .= '<input type="hidden" name="mode" value="'.$mode.'" />';
        $output .= '<input type="hidden" name="teamid" value="'.$teamid.'" />';
        $output .= '<input type="hidden" name="userrep" value="'.$userrep.'" />';
        $output .= '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        require_once($CFG->libdir.'/uploadlib.php');
        $output .= upload_print_form_fragment(1,array('newfile'),null,false,null,0,0,true);
        $output .= '<input type="submit" name="save" value="'.get_string('uploadthisfile').'" />';
        $output .= '</div>';
        $output .= '</form>';

        $responsefiles = $this->print_team_responsefiles($id, $teamid, $userrep, $mode);
        if (!empty($responsefiles)) {
            $output .= $responsefiles;
        }
        return $output;
    }

    function view_upload_form($teamid) {
        global $CFG, $USER;

        $submission = $this->get_submission($USER->id);

        $struploadafile = get_string('teamsubmission', 'assignment_team');
        $maxbytes = $this->assignment->maxbytes == 0 ? $this->course->maxbytes : $this->assignment->maxbytes;
        $strmaxsize = get_string('maxsize', '', display_size($maxbytes));

        if ($this->is_finalized($submission)) {
            // no uploading
            return;
        }

        if ($this->can_upload_file($submission, $teamid)) {
            echo '<div style="text-align:center">';
            echo '<form enctype="multipart/form-data" method="post" action="upload.php">';
            echo '<fieldset class="invisiblefieldset">';
            echo "<p>$struploadafile ($strmaxsize)</p>";
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="hidden" name="action" value="uploadfile" />';
            echo '<input type="hidden" name="teamid" value="'.$teamid.'" />';
            require_once($CFG->libdir.'/uploadlib.php');
            upload_print_form_fragment(1,array('newfile'),null,false,null,0,$this->assignment->maxbytes,false);
            echo '<input type="submit" name="save" value="'.get_string('uploadthisfile').'" />';
            echo '</fieldset>';
            echo '</form>';
            echo '</div>';
            echo '<br />';
        }

    }


    function view_final_submission($teamid) {
        global $CFG, $USER;

        $submission = $this->get_submission($USER->id);

        if ($this->isopen() and $this->can_finalize($submission)) {
            //print final submit button
            print_heading(get_string('submitformarking','assignment'), '', 3);
            echo '<div style="text-align:center">';
            echo '<form method="post" action="upload.php">';
            echo '<fieldset class="invisiblefieldset">';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="hidden" name="action" value="finalize" />';
            echo '<input type="hidden" name="teamid" value="'.$teamid.'" />';
            echo '<input type="submit" name="formarking" value="'.get_string('sendformarking', 'assignment').'" />';
            echo '</fieldset>';
            echo '</form>';
            echo '</div>';
        } else if (!$this->isopen()) {
            print_heading(get_string('nomoresubmissions','assignment'), '', 3);

        } else if ($this->drafts_tracked() and $state = $this->is_finalized($submission)) {
            if ($state == ASSIGNMENT_STATUS_SUBMITTED) {
                print_heading(get_string('submitedformarking','assignment'), '', 3);
            } else {
                print_heading(get_string('nomoresubmissions','assignment'), '', 3);
            }
        } else {
            //no submission yet
        }
    }

    /**
     * @param $teamid
     */
    function print_team_responsefiles($id, $teamid, $userrep, $mode, $delete = true) {
        global $CFG, $USER;
        $output = '';
        $filearea = $this->team_file_area_name($teamid).'/responses';
        $basedir = $CFG->dataroot.'/'.$filearea;
        if (!is_dir($basedir)) {
            return $output;
        }

        $candelete = $this->can_manage_responsefiles();
        $strdelete   = get_string('delete');

        if ($files = get_directory_list($basedir)) {
            require_once($CFG->libdir.'/filelib.php');
            foreach ($files as $key => $file) {

                $icon = mimeinfo('icon', $file);

                $ffurl = get_file_url("$filearea/$file");

                $output .= '<a href="'.$ffurl.'" ><img src="'.$CFG->pixpath.'/f/'.$icon.'" alt="'.$icon.'" />'.$file.'</a>';

                if ($candelete && $delete) {
                    $delurl  = "$CFG->wwwroot/mod/assignment/delete.php?id=$id&amp;file=$file&amp;teamid=$teamid&amp;userrep=$userrep&amp;mode=$mode&amp;action=teamresponse";

                    $output .= '<a href="'.$delurl.'">&nbsp;'
                    .'<img title="'.$strdelete.'" src="'.$CFG->pixpath.'/t/delete.gif" class="iconsmall" alt=""/></a> ';
                }

                $output .= '&nbsp;';
            }
        }
        $output = '<div class="responsefiles">'.$output.'</div>';

        return $output;

    }

    /**
     * Although all team members have their own assignment submission record, the work that is
     * submitted belongs to the team and is kept in the team_file_area
     * @param teamid
     */
    private function print_team_answer($teamid){
        global $CFG, $COURSE;

        $filearea = $this->team_file_area_name($teamid);
        $teammember = $this->get_first_teammember($teamid);
        $submission = $this->get_submission($teammember->id);

        $output = '';

        if ($basedir = $this->team_file_area($teamid)) {
            if ($this->drafts_tracked() and $this->isopen() and !$this->is_finalized($submission)) {
                $output .= '<strong>'.get_string('draft', 'assignment').':</strong> ';
            }

            if ($this->notes_allowed() and !empty($submission->data1)) {
                $output .= link_to_popup_window ('/mod/assignment/type/upload/notes.php?id='.$this->cm->id.'&amp;userid='.$teammember->id,
                                                'notes'.$teammember->id, get_string('notes', 'assignment'), 500, 780, get_string('notes', 'assignment'), 'none', true, 'notesbutton'.$teammember->id);
                $output .= '&nbsp;';
            }
            $i = 0;
            if ($files = get_directory_list($basedir)) {
                require_once($CFG->libdir.'/filelib.php');
                foreach ($files as $key => $file) {
                    if ($i<>0) {
                        $output .='<br/>';
                    }
                    $i++;
                    $icon = mimeinfo('icon', $file);
                    $ffurl = get_file_url("$filearea/$file");

                    $output .= '<a href="'.$ffurl.'" ><img class="icon" src="'.$CFG->pixpath.'/f/'.$icon.'" alt="'.$icon.'" />'.$file.'</a>&nbsp;';
                    // Start Optional Turnitin code
//                    $assignment = get_record('assignment', 'id', $submission->assignment);
//                    if (isset($assignment->use_tii_submission) && $assignment->use_tii_submission) {
//                        if (has_capability('moodle/local:viewsimilarityscore', $this->context)) {
//                            include_once($CFG->libdir.'/turnitinlib.php');
//                            if ($tiisettings = tii_get_settings()) {
//                                $tiifile = get_record_select('tii_files', "course='".$COURSE->id.
//                                                     "' AND module='".get_field('modules', 'id','name','assignment').
//                                                     "' AND instance='".$submission->assignment.
//                                                     "' AND userid='".$userid.
//                                                     "' AND filename='".$file.
//                                                     "' AND tiicode<>'pending' AND tiicode<>'51'");
//                                if (isset($tiifile->tiiscore) && $tiifile->tiicode=='success') {
//                                    if (has_capability('moodle/local:viewfullreport', $this->context)) {
//                                        $output .= '&nbsp;<a class="turnitinreport" href="'.tii_get_report_link($tiifile).'" target="_blank">'.get_string('similarity', 'turnitin').':</a>'.$tiifile->tiiscore.'%';
//                                    } else {
//                                         $output .= '&nbsp;'.get_string('similarity', 'turnitin').':'.$tiifile->tiiscore.'%';
//                                    }
//                                } elseif(isset($tiifile->tiicode)) {
//                                    $output .= get_tii_error($tiifile->tiicode);
//                                }
//                            }
//                        }                
//                    }
                    // End Optional Turnitin code
                }
            }
            $output = '<div class="files">'.$output.'</div>';
            $output .= '<br />';

            return $output;
        }
    }

    /**
     * Produces a list of links to the files uploaded by a user
     *
     * @param $userid int optional id of the user. If 0 then $USER->id is used.
     * @param $return boolean optional defaults to false. If true the list is returned rather than printed
     * @return string optional
     */
    function print_user_files($userid=0, $return=false, $teamid) {
        global $CFG, $USER;
        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $filearea = $this->team_file_area_name($teamid);
        $output = '';

        $submission = $this->get_submission($userid);

        $candelete = $this->can_delete_files($submission, $teamid);
        $strdelete   = get_string('delete');

        if ($this->drafts_tracked() and $this->isopen() and !$this->is_finalized($submission) and !empty($mode)) {                 // only during grading
            $output .= '<strong>'.get_string('draft', 'assignment').':</strong><br />';
        }

        if ($this->notes_allowed() and !empty($submission->data1) and !empty($mode)) { // only during grading

            $npurl = $CFG->wwwroot."/mod/assignment/type/upload/notes.php?id={$this->cm->id}&amp;userid=$userid&amp;offset=$offset&amp;mode=single";
            $output .= '<a href="'.$npurl.'">'.get_string('notes', 'assignment').'</a><br />';

        }

        if ($basedir = $this->team_file_area($teamid)) {
            if ($files = get_directory_list($basedir, 'responses')) {
                require_once($CFG->libdir.'/filelib.php');
                foreach ($files as $key => $file) {
                    $icon = mimeinfo('icon', $file);
                    $ffurl = get_file_url("$filearea/$file");
                    $output .= '<a href="'.$ffurl.'" ><img src="'.$CFG->pixpath.'/f/'.$icon.'" class="icon" alt="'.$icon.'" />'.$file.'</a>';

                    if ($candelete) {
                        $delurl  = "$CFG->wwwroot/mod/assignment/delete.php?id={$this->cm->id}&amp;file=$file&amp;userid={$submission->userid}&amp;mode=$mode&amp;offset=$offset&amp;teamid=$teamid";

                        $output .= '<a href="'.$delurl.'">&nbsp;'
                        .'<img title="'.$strdelete.'" src="'.$CFG->pixpath.'/t/delete.gif" class="iconsmall" alt="" /></a> ';
                    }
                    // Start Optional Turnitin code
//                    if (isset($this->assignment->use_tii_submission) && $this->assignment->use_tii_submission) { //if this assignment uses tii
//                        include_once($CFG->libdir.'/turnitinlib.php');
//                        if ($tiisettings = tii_get_settings()) {
//                            $tiifile = get_record_select('tii_files', "course='".$this->assignment->course.
//                                                    "' AND module='".get_field('modules', 'id','name','assignment').
//                                                    "' AND instance='".$this->assignment->id.
//                                                    "' AND userid='".$userid.
//                                                    "' AND filename='".$file."'");
//                            if (isset($tiifile->tiiscore) && $tiifile->tiicode=='success') { //if TII has returned a succesful score.
//                                $assignclosed = ! $this->isopen();
//                                if (isset($this->assignment->tii_show_student_report) && isset($this->assignment->tii_show_student_score) and //if report and score fields are set.
//                                   ($this->assignment->tii_show_student_report== 1 or $this->assignment->tii_show_student_score ==1 or //if show always is set
//                                   ($this->assignment->tii_show_student_score==2 && $assignclosed) or //if student score to be show when assignment closed
//                                   ($this->assignment->tii_show_student_report==2 && $assignclosed))) { //if student report to be shown when assignment closed
//                                    if (($this->assignment->tii_show_student_report==2 && $assignclosed) or $this->assignment->tii_show_student_report==1) {
//                                        $output .= '&nbsp;<a href="'.tii_get_report_link($tiifile).'" target="_blank">'.get_string('similarity', 'turnitin').'</a>';
//                                        if ($this->assignment->tii_show_student_score==1 or ($this->assignment->tii_show_student_score==2 && $assignclosed)) {
//                                             $output .= ':'.$tiifile->tiiscore.'%';
//                                        }
//                                    } else {
//                                        $output .= '&nbsp;'.get_string('similarity', 'turnitin').':'.$tiifile->tiiscore.'%';
//                                    }
//                                }
//                            } elseif(isset($tiifile->tiicode)) { //always display errors - even if the student isn't able to see report/score.
//                                   $output .= tii_error_text($tiifile->tiicode);
//                            }
//                        }
//                    }
                    // End Optional Turnitin code
                    $output .= '<br />';
                }
            }
        }

        if ($this->drafts_tracked() and $this->isopen() and has_capability('mod/assignment:grade', $this->context) and $mode != '') { // we do not want it on view.php page
            if ($this->can_unfinalize($submission)) {
                $options = array ('id'=>$this->cm->id, 'userid'=>$userid, 'action'=>'unfinalize', 'mode'=>$mode, 'offset'=>$offset);
                $output .= print_single_button('upload.php', $options, get_string('unfinalize', 'assignment'), 'post', '_self', true);
            } else if ($this->can_finalize($submission)) {
                $options = array ('id'=>$this->cm->id, 'userid'=>$userid, 'action'=>'finalizeclose', 'mode'=>$mode, 'offset'=>$offset, 'teamid' =>$teamid);
                $output .= print_single_button('upload.php', $options, get_string('finalize', 'assignment'), 'post', '_self', true);
            }
        }

        $output = '<div class="files">'.$output.'</div>';

        if ($return) {
            return $output;
        }
        echo $output;
    }

    function upload() {
        $action = required_param('action', PARAM_ALPHA);

        switch ($action) {
            case 'finalize':
                $this->finalize();
                break;
            case 'finalizeclose':
                $this->finalizeclose();
                break;
            case 'unfinalize':
                $this->unfinalize();
                break;
            case 'uploadresponse':
                $this->upload_responsefile();
                break;
            case 'uploadteamresponse':
                $this->upload_team_responsefile();
                break;
            case 'uploadfile':
                $this->upload_file();
            case 'savenotes':
            case 'editnotes':
                $this->upload_notes();
            default:
                error('Error: Unknow upload action ('.$action.').');
        }
    }

    function delete() {
        $action   = optional_param('action', '', PARAM_ALPHA);

        switch ($action) {
            case 'response':
                $this->delete_responsefile();
                break;

            case 'teamresponse':
                $this->delete_team_responsefile();
                break;

            default:
                $this->delete_file();
        }
        die;
    }

    /**
     * Team members should have same files in their  folder.
     * This method will handle the team submission files.
     */
    function upload_file() {
        global $CFG, $USER;
        $mode   = optional_param('mode', '', PARAM_ALPHA);
        $offset = optional_param('offset', 0, PARAM_INT);
        $teamid = optional_param('teamid','',PARAM_INT);

        $returnurl = 'view.php?id='.$this->cm->id;

        $submission = $this->get_submission($USER->id);
        if (!$this->can_upload_file($submission, $teamid)) {
            $this->view_header(get_string('upload'));
            notify(get_string('uploaderror', 'assignment'));
            print_continue($returnurl);
            $this->view_footer();
            die;
        }

        //team can not be empty
        $members = $this->get_members_from_team($teamid);
        if ($members && is_array($members)) {
            require_once($CFG->dirroot.'/lib/uploadlib.php');
            $currenttime = time();
            $um = new upload_manager('newfile',false,true,$this->course,false,$this->assignment->maxbytes,true);
            $dir = $this->team_file_area_name($teamid);
            check_dir_exists($CFG->dataroot.'/'.$dir, true, true);
            if ($um -> process_file_uploads($dir)) {
                // if file was uploaded successfully, update members' assignment_submission records.
                foreach ($members as $member) {
                    //update all team members's assignment_submission records.
                    $submission = $this->get_submission($member->student, true); //create new submission if needed
                    $updated = new object();
                    $updated->id           = $submission->id;
                    $updated->timemodified = $currenttime;

                    if (update_record('assignment_submissions', $updated)) {
                        add_to_log($this->course->id, 'assignment', 'upload',
                            'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                        $submission = $this->get_submission($member->student);
                        $this->update_grade($submission);
                        if (!$this->drafts_tracked()) {
                            $this->email_teachers($submission);
                        }
                        // Start Optional Turnitin code
//                        if (isset($this->assignment->use_tii_submission) && $this->assignment->use_tii_submission && (empty($this->assignment->tii_draft_submit) or !$this->drafts_tracked())) {
//                            include_once($CFG->libdir.'/turnitinlib.php');
//                            update_tii_files($um->get_new_filename(), $this->course->id, $this->cm->module, $this->assignment->id);
//                        }
                        // End Optional Turnitin code
                    } else {
                        $new_filename = $um->get_new_filename();
                        $this->view_header(get_string('upload'));
                        notify(get_string('uploadnotregistered', 'assignment', $new_filename));
                        print_continue($returnurl);
                        $this->view_footer();
                        die;
                    }
                }
            } else {
                $this->view_header(get_string('upload'));
                notify('upload process fail');
                print_continue($returnurl);
                $this->view_footer();
            }
            redirect('view.php?id='.$this->cm->id);
        }

        $this->view_header(get_string('upload'));
        notify(get_string('uploaderror', 'assignment'));
        echo $um->get_errors();
        print_continue($returnurl);
        $this->view_footer();
        die;
    }

    function finalize() {
        global $USER, $COURSE;
        $submission = $this->get_submission($USER->id);
        $confirm    = optional_param('confirm', 0, PARAM_BOOL);
        $returnurl  = 'view.php?id='.$this->cm->id;
        $teamid     = optional_param('teamid', '', PARAM_INT );
        $sesskey    = required_param('sesskey', PARAM_RAW);

        if (!$this->can_finalize($submission) || !$this->is_member($teamid)) {
            redirect($returnurl); // probably already graded, redirect to assignment page, the reason should be obvious
        }

        if (!data_submitted() or !$confirm or !confirm_sesskey()) {
            $optionsno = array('id'=>$this->cm->id);
            $optionsyes = array ('id'=>$this->cm->id, 'confirm'=>1, 'action'=>'finalize', 'teamid'=>$teamid, 'sesskey' =>$sesskey);
            $this->view_header(get_string('submitformarking', 'assignment'));
            print_heading(get_string('submitformarking', 'assignment'));
            notice_yesno(get_string('onceassignmentsent', 'assignment'), 'upload.php', 'view.php', $optionsyes, $optionsno, 'post', 'get');
            $this->view_footer();
            die;

        }
        $members = $this->get_members_from_team($teamid);
        $currenttime = time();
        if ($members && is_array($members)) {
            foreach ($members as $member) {
                $submission = $this->get_submission($member->student);
                $updated = new object();
                $updated->id           = $submission->id;
                $updated->data2        = ASSIGNMENT_STATUS_SUBMITTED;
                $updated->timemodified = $currenttime;

                if (update_record('assignment_submissions', $updated)) {
                    add_to_log($this->course->id, 'assignment', 'upload', //TODO: add finalize action to log
                    'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                    $submission = $this->get_submission($USER->id);
                    $this->update_grade($submission);
                    $this->email_teachers($submission);
                } else {
                    $this->view_header(get_string('submitformarking', 'assignment'));
                    notify(get_string('finalizeerror', 'assignment'));
                    print_continue($returnurl);
                    $this->view_footer();
                    die;
                }
            }
            //close team membership
            $team = get_record('team', 'id', $teamid, 'assignment', $this->assignment->id);
            if ($team && $this ->is_member($teamid)) {
                $team -> membershipopen = 0;
                $team ->timemodified = time();
                update_record('team', $team);
            }
        }
        // Start Optional Turnitin code
//        if (isset($this->assignment->use_tii_submission) && $this->assignment->use_tii_submission && // is TII enabled for this assignment?
//            ($this->drafts_tracked() && isset($this->assignment->tii_draft_submit) && $this->assignment->tii_draft_submit == 1)) { // is TII to be sent on final submission?
//            // we need to get a list of files attached to this assignment and put them in an array, so that
//            // we can submit each of them to TII for processing.
//            if ($basedir = $this->team_file_area($teamid)) {
//                $files = get_directory_list($basedir);
//                debugging(var_dump($files),DEBUG_DEVELOPER);
//            }
//            if ($files) {
//                foreach ($files as $file) {
//                    update_tii_files($file, $COURSE->id, $this->cm->module, $this->assignment->id);
//                }
//            }
//        }  
        // End Optional Turnitin code
        redirect($returnurl);
    }

    function delete_team_responsefile() {
        global $CFG;
        $file     = required_param('file', PARAM_FILE);
        $teamid   = required_param('teamid', PARAM_INT);
        $mode     = required_param('mode', PARAM_ALPHA);
        $userrep   = required_param('userrep', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);

        $returnurl = "submissions.php?id={$this->cm->id}&amp;teamid=$teamid&amp;userrep=$userrep&amp;mode=$mode";

        if (!$this->can_manage_responsefiles()) {
            redirect($returnurl);
        }

        $urlreturn = 'submissions.php';
        $optionsreturn = array('id'=>$this->cm->id, 'teamid'=>$teamid, 'userrep'=>$userrep, 'mode'=>$mode);

        if (!data_submitted('nomatch') or !$confirm) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'teamid'=>$teamid, 'userrep'=>$userrep, 'confirm'=>1, 'action'=>'teamresponse', 'mode'=>$mode);
            print_header(get_string('delete'));
            print_heading(get_string('delete'));
            notice_yesno(get_string('confirmdeletefile', 'assignment', $file), 'delete.php', $urlreturn, $optionsyes, $optionsreturn, 'post', 'get');
            print_footer('none');
            die;
        }

        $dir = $this->team_file_area_name($teamid).'/responses';
        $filepath = $CFG->dataroot.'/'.$dir.'/'.$file;
        if (file_exists($filepath)) {
            if (@unlink($filepath)) {//delete team response file
                //delete other team members' response files.
                //We need individual response directories in case one team member is marked differently
                $members = $this->get_members_from_team($teamid);
                foreach($members as $member) {
                    $memberdir = $this->file_area_name($member->student).'/responses';
                    $memberfilepath = $CFG->dataroot.'/'.$memberdir.'/'.$file;
                    if (file_exists($memberfilepath)) {
                        unlink($memberfilepath);
                    }
                }
                redirect($returnurl);
            }
        }

        // print delete error
        print_error('deletefilefailed', 'assignment', $returnurl);
    }

    function delete_file() {
        global $CFG;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);
        $mode     = optional_param('mode', '', PARAM_ALPHA);
        $offset   = optional_param('offset', 0, PARAM_INT);
        $teamid   = optional_param('teamid', PARAM_INT);

        require_login($this->course->id, false, $this->cm);

        if (empty($mode)) {
            $urlreturn = 'view.php';
            $optionsreturn = array('id'=>$this->cm->id);
            $returnurl = 'view.php?id='.$this->cm->id;
        } else {
            $urlreturn = 'submissions.php';
            $optionsreturn = array('id'=>$this->cm->id, 'offset'=>$offset, 'mode'=>$mode, 'userid'=>$userid);
            $returnurl = "submissions.php?id={$this->cm->id}&amp;offset=$offset&amp;mode=$mode&amp;userid=$userid";
        }
        if (!$submission = $this->get_submission($userid) // incorrect submission
        or !$this->can_delete_files($submission, $teamid)) {     // can not delete
            $this->view_header(get_string('delete'));
            notify(get_string('cannotdeletefiles', 'assignment'));
            print_continue($returnurl);
            $this->view_footer();
            die;
        }
        $dir = $this->team_file_area_name($teamid);

        if (!data_submitted('nomatch') or !$confirm or !confirm_sesskey()) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'sesskey'=>sesskey(), 'mode'=>$mode, 'offset'=>$offset, 'teamid'=>$teamid);
            if (empty($mode)) {
                $this->view_header(get_string('delete'));
            } else {
                print_header(get_string('delete'));
            }
            print_heading(get_string('delete'));
            notice_yesno(get_string('confirmdeletefile', 'assignment', $file), 'delete.php', $urlreturn, $optionsyes, $optionsreturn, 'post', 'get');
            if (empty($mode)) {
                $this->view_footer();
            } else {
                print_footer('none');
            }
            die;
        }

        $filepath = $CFG->dataroot.'/'.$dir.'/'.$file;
        if (file_exists($filepath)) {
            if (@unlink($filepath)) {
                // Update the submissions for all team members
                $currenttime = time();
                $members = $this->get_members_from_team($teamid);
                if ($members && is_array($members)) {
                    foreach ($members as $member) {
                        $membersubmission = $this->get_submission($member->student, true);
                        $updated = new object();
                        $updated->id           = $membersubmission->id;
                        $updated->timemodified = $currenttime;
                        if (update_record('assignment_submissions', $updated)) {
                            add_to_log($this->course->id, 'assignment', 'upload', //TODO: add delete action to log
                                'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                            $this->update_grade($membersubmission);
                        } else {
                            print_error('deletefilefailed', 'assignment', $returnurl);
                        }
                    }
                }
                 
                redirect($returnurl);
            }
        }

        // print delete error
        if (empty($mode)) {
            $this->view_header(get_string('delete'));
        } else {
            print_header(get_string('delete'));
        }
        notify(get_string('deletefilefailed', 'assignment'));
        print_continue($returnurl);
        if (empty($mode)) {
            $this->view_footer();
        } else {
            print_footer('none');
        }
        die;
    }


    function can_upload_file($submission, $teamid) {
        global $USER;

        if (has_capability('mod/assignment:submit', $this->context)           // can submit
        and $this->isopen()                                                 // assignment not closed yet
        and (empty($submission) or $submission->userid == $USER->id)        // his/her own submission
        and $this->count_user_files($USER->id) < $this->assignment->var1    // file limit not reached
        and !$this->is_finalized($submission)                              // no uploading after final submission
        and $this->is_member($teamid)) {
            return true;
        } else {
            return false;
        }
    }

    function can_delete_files($submission, $teamid) {
        global $USER;

        if (has_capability('mod/assignment:grade', $this->context)) {
            return true;
        }
        if (has_capability('mod/assignment:submit', $this->context)
        and $this->isopen()                                      // assignment not closed yet
        and $this->assignment->resubmit                          // deleting allowed
        and $USER->id == $submission->userid                     // his/her own submission
        and !$this->is_finalized($submission)                    // no deleting after final submission
        and $this->is_member($teamid)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the team that the user belongs to. A student can only belong to one
     * team per assignment
     * @param $userid
     * @return team object
     */
    function get_user_team($userid ){
        global $CFG;
        $teams = get_records_sql("SELECT id, assignment, name, membershipopen".
                                 " FROM {$CFG->prefix}team ".
                                 " WHERE assignment = ".$this->assignment->id);
        if ($teams) {
            foreach($teams as $team) {
                $teamid = $team->id;
                if (get_record('team_student','student',$userid, 'team', $teamid)) {
                    return $team;
                }
            }
        }
        
        return null;
    }

    /**
     *return the first member whose id is not same as login user id
     */
    function get_another_user_copy($userid, $teamid) {
        global $CFG;
        $members = $this -> get_members_from_team($teamid);
        if (is_array($members)) {
            foreach($members as $member) {
                if($member->student != $userid) {
                    return $member;
                }
            }
        }
        return null;
    }
    
    /**
     * returns all valid members from this team
     * @param $teamid
     * @return array of users or boolean if not found
     */
    function get_members_from_team ($teamid) {
        global $CFG;
        $validmembers = array();
        $allmembers = get_records_sql("SELECT id, student, timemodified".
                                 " FROM {$CFG->prefix}team_student ".
                                 " WHERE team = ".$teamid);
        if ($allmembers) {
            foreach ($allmembers as $member) {
                if ($this->is_user_course_participant($member->student)) {
                    $validmembers[]=$member;
                }
            }
            if (!empty($validmembers)) {
                return $validmembers;
            }
        }
        return false;
    }

    function remove_users_from_team($userids, $teamid, $isteacher = false) {
        
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);
        if ($confirm == 0) {
            $i = 0;
            $optionsyes = array('confirm'=>1,'teamid'=>$teamid,'members'=>$i, 'removetime'=> time(), 'act_removemember'=>get_string('removeteammember','assignment_team'));

            $team = get_record('team', 'id', $teamid,'assignment', $this->assignment->id);
            if (!$team) {
                return ;
            }
            $deletemembers = '';
            foreach ($userids as $userid) {
                $memberkey = 'member'.$i;
                $optionsyes[$memberkey] = $userid ;
                $optionsyes['members'] =$i;
                $user = get_record('user','id', $userid);
                $deletemembers = $deletemembers. ' \''.fullname($user).'\' ';
                $i++;
            }
            $message = '';
            if ($team->membershipopen) {
                $message = get_string('removememberwhenmembershipopen', 'assignment_team',$deletemembers);
            } else {
                $message = get_string('removememberwhenmembershipclosed', 'assignment_team',$deletemembers);;
            }
            $optionsreturn =array();
             
            print_heading(get_string('delete'));
            notice_yesno($message, $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_URI'], $optionsyes, $optionsreturn, 'post', 'post');
            print_footer('none');
            die;
        }else{
            foreach($userids as $userid) {
                $this->remove_user_from_team($userid, $teamid, $isteacher);
            }
        }
    }



    function is_member($teamid) {
        global $USER;
        $team = $this->get_user_team($USER->id);
        return isset($team) && ($team->id == $teamid);
         
    }

    /**
     *
     * @param $name
     *
     */
    function create_team ($name) {
        global $USER;
        if(!isset($name) || trim($name)== '' ) {
            notify(get_string('teamnameerror', 'assignment_team'));
        } else {
            if (get_record('team', 'assignment',$this->assignment->id, 'name',$name)) {
                notify(get_string('teamnameexist','assignment_team'));
            } else {
                $userteam = $this->get_user_team($USER ->id);
                if (!isset($userteam)) {
                    $team = new Object();
                    $team ->assignment = $this->assignment->id;
                    $team ->name = $name;
                    $team ->membershipopen = 1; //1 for team membership open, 0 is for team membership close
                    $team ->timemodified = time();
                    //start create a team and join this team
                    $createTeam = insert_record('team', $team, true) ;
                    //Insert a record into a table and return the "id" field or boolean value
                    if (!$createTeam) {
                        notify(get_string('createteamerror', 'assignment_team'));
                    } else {
                        $this ->join_team($USER->id, $createTeam);
                    }
                }
            }
        }
    }

    
    /**
     *
     * @param $teamid
     *
     */
    function delete_team ($teamid, $isteacher = false) {
        global $USER;
        $members = $this->get_members_from_team($teamid);
        //teacher can remove the whole team members and team.
        //student only can remove this team if only if there is only this log in student in this team
        if ($isteacher 
            || (isset($members)&& is_array($members)&& count($members)== 1) ) {
            foreach ($members as $member) {
                if (($member->student == $USER->id)
                     || $isteacher) {
                    $this -> remove_user_from_team($member->student, $teamid, $isteacher);
                }
            }
        }
    }

    /**
     * Print a select box with the list of teams
     * @return unknown_type
     */
    function print_team_list($isteacher=false){
        global $CFG;
        $viewmemberact = optional_param('act_viewmember', NULL, PARAM_RAW);
        $groups = optional_param('groups', NULL, PARAM_INT);
        $teams = get_records_sql("SELECT id, assignment, name, membershipopen".
                                 " FROM {$CFG->prefix}team ".
                                 " WHERE assignment = ".$this->assignment->id);
        $strteams = get_string('teams');
        $onchange = '';
        echo '<form id="jointeamform" action="'.$_SERVER['REQUEST_URI'].'" method="post">'."\n";
        echo '<div>'."\n";
        echo '<table cellpadding="6" class="generaltable generalbox groupmanagementtable boxaligncenter" summary="">'."\n";
        echo '<tr>'."\n";
        echo "<td>\n";
        echo '<p><label for="groups"><span id="groupslabel">'.get_string('existingteams', 'assignment_team').'</span></label></p>'."\n";
        echo '<select name="groups[]"  id="groups" size="15" class="select" onchange="'.$onchange.'"'."\n";
        echo ' onclick="window.status=this.selectedIndex==-1 ? \'\' : this.options[this.selectedIndex].title;" onmouseout="window.status=\'\';">'."\n";

        if ($teams) {
            // Print out the HTML
            foreach ($teams as $team) {
                if (!$this->has_members($team->id)) {
                    continue;
                }
                $select = '';
                //after any post action from act_viewmember button still can selected previous select
                if (isset($viewmemberact)
                && isset($groups)
                && count($groups)==1
                && $groups[0] == $team->id ) {
                    $select = ' selected = "true" ';
                }
                $usercount = (int)count_records('team_student', 'team', $team->id);
                $teamname = format_string($team->name).' ('.$usercount.')'.' '.$this ->get_team_status_name($team->membershipopen);
                echo "<option value=\"{$team->id}\"$select\" title=\"$teamname\">$teamname</option>\n";
            }
        } else {
            // Print an empty option to avoid the XHTML error of having an empty select element
            echo '<option>&nbsp;</option>';
        }
        echo '</select>'."\n";
        //student join team or teacher delete team.
        if (!$isteacher) {
             echo '<input type="hidden" name="jointeamtime" value="'.time().'" />';
             echo '<p><input type="submit" name="act_jointeam" id="jointeam" value="'
                  . get_string('jointeam', 'assignment_team') . '" />
                  <input type ="submit" name="act_viewmember"  id ="viewteam" value = "'
                  . get_string('viewmember', 'assignment_team') . '"  />
                  </p>'."\n";
        } else {
             echo '<input type="hidden" name="teacherdeleteteamtime" value="'.time().'" />';
             echo '<p><input type="submit" name="act_teacherdeleteteam" id="teacherdeleteteam" value="'
                  . get_string('deleteteam', 'assignment_team') . '" />
                  <input type ="submit" name="act_viewmember"  id ="viewteam" value = "'
                  . get_string('viewmember', 'assignment_team') . '"  />
                  </p>'."\n";
        }
       
        echo '</td>'."\n";
        echo '<td>'."\n";
        echo '<p><label for="teammember"><span id="teammemberlabel">'.
        get_string('teammember', 'assignment_team').' </span></label></p>'."\n";
        echo '<select name ="members[]" multiple="multiple" id="teammember" size="15">';
        $teamid =0;
        if (isset($viewmemberact) && isset($groups)
        && count($groups)==1) {
            $teamid = $groups[0];
            $members = $this->get_members_from_team($teamid);
            if (is_array($members) && count($members)>0) {
                foreach ($members as $member) {
                    $userid = $member->student;
                    $user = get_record ('user', 'id', $userid);
                    if ($user) {
                        echo "<option value=\"{$user->id}\" >".fullname($user)."</option>";
                    }
                }
            }
              
        } else {
            //print empty list
            echo '<option>&nbsp;</option>';
        }
         //teache can delete team members
        if ($isteacher) {
            echo '<input type="hidden" name="removetime" value="'.time().'" />';
            echo '<input type="hidden" name="teamid" value="'.$teamid.'" />';
            echo '<p><input type="submit" name="act_teacherdeletemember" id="teacherdeletemember" value="'
                  . get_string('removeteammember', 'assignment_team') . '" />
                  </p>'."\n";
        }   
        echo '</select>';
        echo '</td>'."\n";
        echo '</tr>'."\n";
        //student can create a team.
        if (!$isteacher) {
             echo '<tr>';
             echo '<td colspan="2" >';
             echo '<p>'.get_string('createteamlabel','assignment_team').'</p>';
             echo '<p>'.get_string('teamname','assignment_team').' '.'<input type ="text" name ="teamname" id="teamname" />
              <input type="hidden" name="createteamtime" value="'.time().'" />
              <input type ="submit" name="act_createteam" id ="createteam" 
               value = "'.get_string('createteam','assignment_team').'" /> </p>';
             echo '</td>';
             echo '</tr>';
        }
       
        echo '</table>'."\n";
        echo '</div>'."\n";
        echo '</form>'."\n";
    }


    function join_team ($studentid, $teamid) {
        global $CFG;
        //if user already in team update otherwise insert
        $team = $this->get_user_team($studentid);
        //insert team_student table
        if (!isset($team)) {
            if ($teamid == null || $teamid == 0) {
                return;
            }
            $insertteam = get_record('team', 'id', $teamid, 'assignment', $this->assignment->id);
            if (!$insertteam) {
                return;
            }
            if ($insertteam->membershipopen) {

                //if the team already has members and they have submitted, create a mdl_assignment_submissions
                //record for the new member
                $existmember = $this->get_another_user_copy($studentid, $teamid);
                if (isset($existmember)) {
                    //if this existing member already has been graded, other student cannot join this team.
                    $copy = get_record('assignment_submissions', 'assignment', $this->assignment->id, 'userid', $existmember->student);
                    if ($copy) {
                        //check the existing member's grade.
                        //If this team already have a grade, a new student cannot join the team.
                        if ($copy->grade >= 0) {
                            notify(get_string('teammarkedwarning', 'assignment_team'));
                            return;
                        }
                        $this -> add_new_team_member($studentid, $teamid);
                        $submission = $this -> prepare_update_submission($studentid, $copy);
                        update_record('assignment_submissions', $submission);
                    }
                } else {
                    $this -> add_new_team_member($studentid, $teamid);
                    //create a dummy record and update assignment_submission record.
                    $submission = $this->get_submission($studentid, true);
                    $dummy = $this -> prepare_new_submission($studentid,false);
                    $dummyrec = $this->copy_submission($submission, $dummy);
                    update_record('assignment_submissions', $dummyrec);                  
                }
            } else {
                notify(get_string('teamclosedwarning', 'assignment_team'));
            }
        }

    }

    private function add_new_team_member($studentid, $teamid) {
        $teamstudent = new Object();
        $teamstudent ->student = $studentid;
        $teamstudent ->team = $teamid;
        $teamstudent ->timemodified = time();
        insert_record('team_student',$teamstudent, false);

        //update team timemodified
        $team = get_record('team', 'id', $teamid, 'assignment', $this->assignment->id);
        if ($team) {
            $team -> timemodified = time();
            update_record('team', $team);
        } else {
            error_log('team not exist teamId: '.$teamid);
        }
        
    }
    
    function prepare_update_submission($studentid, $copy) {
        $submission = $this->get_submission($studentid, true);
        $submission->assignment   = $copy->assignment;
        $submission->userid       = $studentid;
        $submission->timecreated  = $copy->timecreated;
        $submission->timemodified = time();
        $submission->numfiles     = $copy->numfiles;
        $submission->data1        = $copy->data1;
        $submission->data2        = $copy->data2;
        $submission->grade        = $copy->grade;
        $submission->submissioncomment      = $copy->submissioncomment;
        $submission->format       = $copy->format;
        $submission->teacher      = $copy->teacher;
        $submission->timemarked   = $copy->timemarked;
        $submission->mailed       = $copy->mailed;
        return $submission;
    }
    
    private function copy_submission($submission, $copy) {
        $submission->assignment   = $copy->assignment;
        $submission->timecreated  = $copy->timecreated;
        $submission->timemodified = $copy->timemodified;
        $submission->numfiles     = $copy->numfiles;
        $submission->data1        = $copy->data1;
        $submission->data2        = $copy->data2;
        $submission->grade        = $copy->grade;
        $submission->submissioncomment      = $copy->submissioncomment;
        $submission->format       = $copy->format;
        $submission->teacher      = $copy->teacher;
        $submission->timemarked   = $copy->timemarked;
        $submission->mailed       = $copy->mailed;
        return $submission;
    }

    /**
     * delete this user all files and dir.
     */
    function delete_all_files($dir) {
        global $CFG;
        require_once($CFG->libdir.'/filelib.php');
        $filepath = $CFG->dataroot.'/'.$dir;
        fulldelete($filepath);
    }

    function delete_submission_file($dir, $file) {
        global $CFG;
        $filepath = $CFG->dataroot.'/'.$dir.'/'.$file;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    function print_error() {
        $returnurl = 'view.php?id='.$this->cm->id;
        print_error('unkownerror', 'assignment_team', $returnurl);       
    }

    /**
     *
     * @param $teamid
     */
    function open_close_team($teamid) {
        $team = get_record('team', 'id', $teamid,'assignment', $this->assignment->id);
        if ($team && $this ->is_member($teamid)) {
            $status = $team -> membershipopen;
            if ($status) {
                $team -> membershipopen = 0;
            } else {
                $team -> membershipopen = 1;
            }
            $team ->timemodified = time();
            update_record('team', $team);
        }
    }

    /**
     * display two links one for individual team members submissions
     * another for team submissions
     * Override the method form base class
     * @param $allgroups
     */
    function submittedlink($allgroups=false) {
        global $USER, $CFG;
        $linkmessage = '';
        $context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
        if (has_capability('mod/assignment:grade', $context)) {
            $teams = $this->get_teams();
            $teamsubmitted ='';
            $membersubmitted ='';

            if ($teams) {
                if ($teamcount = $this->get_all_team_submissions_number($teams)) {
                    $teamsubmitted = '<a href="submissions.php?id='.$this->cm->id.'&amp;mode=team">'.
                    get_string('viewteamsubmissions', 'assignment_team', $teamcount).'</a>';
                    $membersubmitted = '<a href="submissions.php?id='.$this->cm->id.'">'.
                    get_string('viewmembersubmissions', 'assignment_team', $teamcount).'</a>';
                    $linkmessage = $teamsubmitted.'<br/>'.$membersubmitted.'<br/>';
                } else {
                    $linkmessage = '<a href="submissions.php?id='.$this->cm->id.'">'.
                    get_string('noattempts', 'assignment').'</a>';
                }
            }

        } else {
            if (!empty($USER->id)) {
                if ($submission = $this->get_submission($USER->id)) {
                    if ($submission->timemodified) {
                        if ($submission->timemodified <= $this->assignment->timedue || empty($this->assignment->timedue)) {
                            $linkmessage = '<span class="early">'.userdate($submission->timemodified).'</span>';
                        } else {
                            $linkmessage = '<span class="late">'.userdate($submission->timemodified).'</span>';
                        }
                    }
                }
            }
        }

        return $linkmessage;
    }

    /**
     * overrid base class method.
     * @param $mode
     */
    function submissions($mode) {
        ///The main switch is changed to facilitate
        ///1) Batch fast grading
        ///2) Skip to the next one on the popup
        ///3) Save and Skip to the next one on the popup

        //make user global so we can use the id
        global $USER;

        $mailinfo = optional_param('mailinfo', null, PARAM_BOOL);
        if (is_null($mailinfo)) {
            $mailinfo = get_user_preferences('assignment_mailinfo', 0);
        } else {
            set_user_preference('assignment_mailinfo', $mailinfo);
        }

        switch ($mode) {
            case 'grade':                         // We are in a popup window grading
                if ($submission = $this->process_feedback()) {
                    //IE needs proper header with encoding
                    print_header(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
                    print_heading(get_string('changessaved'));
                    print $this->update_main_listing($submission);
                }
                close_window();
                break;

            case 'teamgrade' :
                if ($this->process_team_grades()) {
                    //IE needs proper header with encoding
                    print_header(get_string('feedback', 'assignment').':'.format_string($this->assignment->name));
                    print_heading(get_string('changessaved'));
                    //refresh parent page.
                    $userid = (int)$_POST['userrep'];
                    if ($submission = $this->get_submission($userid)) {
                        print $this->update_team_main_listing($submission);
                    } else {
                        // add logic for when submission is not found
                    }
                }
                close_window();
                break;

            case 'single':                        // We are in a popup window displaying submission
                $this->display_submission();
                break;

            case 'all':                          // Main window, display everything
                $this->display_submissions();
                break;

            case 'team':
                $this->display_team_submissions();
                break;

            case 'showteam':
                $this->show_team_members();
                break;

            case 'fastgrade':
                ///do the fast grading stuff  - this process should work for all 3 subclasses

                $grading    = false;
                $commenting = false;
                $col        = false;
                if (isset($_POST['submissioncomment'])) {
                    $col = 'submissioncomment';
                    $commenting = true;
                }
                if (isset($_POST['menu'])) {
                    $col = 'menu';
                    $grading = true;
                }
                if (!$col) {
                    //both submissioncomment and grade columns collapsed..
                    $this->display_submissions();
                    break;
                }

                foreach ($_POST[$col] as $id => $unusedvalue){

                    $id = (int)$id; //clean parameter name

                    $this->process_outcomes($id);

                    if (!$submission = $this->get_submission($id)) {
                        $submission = $this->prepare_new_submission($id);
                        $newsubmission = true;
                    } else {
                        $newsubmission = false;
                    }
                    unset($submission->data1);  // Don't need to update this.
                    unset($submission->data2);  // Don't need to update this.

                    //for fast grade, we need to check if any changes take place
                    $updatedb = false;

                    if ($grading) {
                        $grade = $_POST['menu'][$id];
                        $updatedb = $updatedb || ($submission->grade != $grade);
                        $submission->grade = $grade;
                    } else {
                        if (!$newsubmission) {
                            unset($submission->grade);  // Don't need to update this.
                        }
                    }
                    if ($commenting) {
                        $commentvalue = trim($_POST['submissioncomment'][$id]);
                        $updatedb = $updatedb || ($submission->submissioncomment != stripslashes($commentvalue));
                        $submission->submissioncomment = $commentvalue;
                    } else {
                        unset($submission->submissioncomment);  // Don't need to update this.
                    }

                    $submission->teacher    = $USER->id;
                    if ($updatedb) {
                        $submission->mailed = (int)(!$mailinfo);
                    }

                    $submission->timemarked = time();

                    //if it is not an update, we don't change the last modified time etc.
                    //this will also not write into database if no submissioncomment and grade is entered.

                    if ($updatedb){
                        if ($newsubmission) {
                            if (!isset($submission->submissioncomment)) {
                                $submission->submissioncomment = '';
                            }
                            if (!$sid = insert_record('assignment_submissions', $submission)) {
                                return false;
                            }
                            $submission->id = $sid;
                        } else {
                            if (!update_record('assignment_submissions', $submission)) {
                                return false;
                            }
                        }

                        // triger grade event
                        $this->update_grade($submission);

                        //add to log only if updating
                        add_to_log($this->course->id, 'assignment', 'update grades',
                                   'submissions.php?id='.$this->assignment->id.'&user='.$submission->userid,
                        $submission->userid, $this->cm->id);
                    }

                }

                $message = notify(get_string('changessaved'), 'notifysuccess', 'center', true);

                $this->display_submissions($message);
                break;


            case 'next':
                /// We are currently in pop up, but we want to skip to next one without saving.
                ///    This turns out to be similar to a single case
                /// The URL used is for the next submission.

                $this->display_submission();
                break;

            case 'saveandnext':
                ///We are in pop up. save the current one and go to the next one.
                //first we save the current changes
                if ($submission = $this->process_feedback()) {
                    //print_heading(get_string('changessaved'));
                    $extra_javascript = $this->update_main_listing($submission);
                }

                //then we display the next submission
                $this->display_submission($extra_javascript);
                break;

            default:
                echo "something seriously is wrong!!";
                break;
        }
    }

    function display_team_submissions($message = '') {
        global $CFG, $db, $USER;
        require_once($CFG->libdir.'/gradelib.php');
			// miki before was $perpage = 10; and now 100 temp to show many teams in one page
        $perpage    = 100;
        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id);

        $page    = optional_param('page', 0, PARAM_INT);
        $strsaveallfeedback = get_string('saveallfeedback', 'assignment');

        /// Some shortcuts to make the code read better

        $course     = $this->course;
        $assignment = $this->assignment;
        $cm         = $this->cm;

        $tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet
        add_to_log($course->id, 'assignment', 'view submission', 'submissions.php?id='.$this->cm->id, $this->assignment->id, $this->cm->id);
        $navigation = build_navigation($this->strsubmissions, $this->cm);
        print_header_simple(format_string($this->assignment->name,true), "", $navigation,
                '', '', true, update_module_button($cm->id, $course->id, $this->strassignment), navmenu($course, $cm));

        $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
            . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }

        if (!empty($message)) {
            echo $message;   // display messages here if any
        }

        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        $teamusers = $this->get_team_users();
        if (count($teamusers)==0) {
            print_heading(get_string('nosubmitusers','assignment'));
            return true;
        } else {
            $users = array();
            foreach ($teamusers as $key =>$value) {
                $users[] = $value;
            }
        }

        $tablecolumns = array('teamname',  'grade', 'submissioncomment', 'timemodified', 'timemarked', 'status');
         

        $tableheaders = array( get_string('team', 'assignment_team'), //add team heading
        get_string('grade'),
        get_string('comment', 'assignment'),
        get_string('lastmodified').' ('.get_string('team', 'assignment_team').')',
        get_string('lastmodified').' ('.$course->teacher.')',
        get_string('status'),
        );

        $currentgroup = groups_get_activity_group($cm, true);
        require_once($CFG->libdir.'/tablelib.php');
        $table = new flexible_table('mod-assignment-submissions');

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot.'/mod/assignment/submissions.php?id='.$this->cm->id.'&amp;currentgroup='.$currentgroup);

        $table->sortable(true, 'lastname');//sorted by lastname by default
        $table->collapsible(true);
        $table->initialbars(true);


        $table->column_suppress('fullname');
        $table->column_class('grade', 'grade');
        $table->column_class('submissioncomment', 'comment');
        $table->column_class('timemodified', 'timemodified');
        $table->column_class('timemarked', 'timemarked');
        $table->column_class('status', 'status');

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');
        $table->no_sorting('outcome');
        $table->no_sorting('teamname');
        $table->no_sorting('grade');
        $table->no_sorting('submissioncomment');
        $table->no_sorting('timemodified');
        $table->no_sorting('timemarked');
        $table->no_sorting('status');
        $table->collapsible(false);

        // Start working -- this is necessary as soon as the niceties are over
        $table->setup();

        /// Construct the SQL

        if ($where = $table->get_sql_where()) {
            $where .= ' AND ';
        }

        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY '.$sort;
        }

        $select = 'SELECT u.id, u.firstname, u.lastname, u.picture, u.imagealt,
                          s.id AS submissionid, s.grade, s.submissioncomment,
                          s.timemodified, s.timemarked,
						  COALESCE(SIGN(SIGN(s.timemarked) + 
                              SIGN(
								CASE WHEN s.timemarked = 0 THEN null 
                                     WHEN s.timemarked > s.timemodified THEN s.timemarked - s.timemodified 
                                     ELSE 0 END
                              )), 0) AS status ';
						  $sql = 'FROM '.$CFG->prefix.'user u '.
               'LEFT JOIN '.$CFG->prefix.'assignment_submissions s ON u.id = s.userid
                                                                  AND s.assignment = '.$this->assignment->id.' '.
               'WHERE '.$where.'u.id IN ('.implode(',',$users).') ';

        $table->pagesize($perpage, count($users));

        $strupdate = get_string('update');
        $strgrade  = get_string('grade');
        $grademenu = make_grades_menu($this->assignment->grade);

        if (($ausers = get_records_sql($select.$sql.$sort, $table->get_page_start(), $table->get_page_size())) !== false) {
            $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array_keys($ausers));
            foreach ($ausers as $auser) {
                $final_grade = $grading_info->items[0]->grades[$auser->id];
                $grademax = $grading_info->items[0]->grademax;
                $final_grade->formatted_grade = round($final_grade->grade,2) .' / ' . round($grademax,2);
                $locked_overridden = 'locked';
                $team = $this -> get_user_team($auser->id);
                if ($final_grade->overridden) {
                    $locked_overridden = 'overridden';
                }

                /// Calculate user status
                $auser->status = ($auser->timemarked > 0) && ($auser->timemarked >= $auser->timemodified);
                $picture = print_user_picture($auser, $course->id, $auser->picture, false, true);

                if (empty($auser->submissionid)) {
                    $auser->grade = -1; //no submission yet
                }

                if (!empty($auser->submissionid)) {
                    ///Prints team answer and student modified date
                    if ($auser->timemodified > 0) {
                        $studentmodified = '<div id="ts'.$auser->id.'">'.$this->print_team_answer($team->id)
                        . userdate($auser->timemodified).'</div>';
                    } else {
                        $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                    }
                    ///Print grade, dropdown or text
                    if ($auser->timemarked > 0) {
                        $teachermodified = '<div id="tt'.$auser->id.'">'.userdate($auser->timemarked).'</div>';

                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                        } else {
                            $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                        }

                    } else {
                        $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                        } else {
                            $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                        }
                    }
                    ///Print Comment
                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';
					// miki let the quickgrage work on team mode
					} else if ($quickgrade) {
                        $comment = '<div id="com'.$auser->id.'">'
                        . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                        //miki it was rows=2 and cols = 20
						. $auser->id.'" rows="8" cols="50">'.($auser->submissioncomment).'</textarea></div>';
                    // miki let the quickgrage work on team mode end
					} else {
					//miki expand the comment column to 150 instead of 15
                        $comment = '<div class="scrollmiki" id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),999).'</div>';
                    }
                } else {
                    $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                    $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                    $status          = '<div id="st'.$auser->id.'">&nbsp;</div>';

                    if ($final_grade->locked or $final_grade->overridden) {
                        $grade = '<div id="g'.$auser->id.'">'.$final_grade->formatted_grade . '</div>';
                    } else  {
                        $grade = '<div id="g'.$auser->id.'">-</div>';
                    }

                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.$final_grade->str_feedback.'</div>';
                    } else {
                        $comment = '<div id="com'.$auser->id.'">&nbsp;</div>';
                    }
                }

                if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
                    $auser->status = 0;
                } else {
                    $auser->status = 1;
                }

                $buttontext = ($auser->status == 1) ? $strupdate : $strgrade;
                $popup_url = '/mod/assignment/submissions.php?id='.$this->cm->id
                . '&amp;teamid='.$team->id.'&amp;userrep='.$auser->id.'&amp;mode=single';
                $button = link_to_popup_window ($popup_url, 'grade'.$auser->id, $buttontext, 600, 780,
                $buttontext, 'none', true, 'button'.$auser->id);

                $status  = '<div id="up'.$auser->id.'" class="s'.$auser->status.'">'.$button.'</div>';
                //add team columns into the table.
                //final check if this team has different grade for team members.
                if ($this->is_grades_diff($team->id)) {
                    $grade ='<div id="g'.$auser->id.'">'. get_string('teamgradesdiff', 'assignment_team').'</div>';
                }
                //team link
                $teamlink = $this ->get_team_link($team);
                $row = array($teamlink, $grade,  $comment, $studentmodified, $teachermodified, $status);

                $table->add_data($row);
            }
        }
        $table->print_html();  /// Print the whole table
        $this->view_footer();
    }

    function show_team_members() {
        $teamid = required_param('teamid', PARAM_INT);
        $team = get_record('team', 'id', $teamid,'assignment', $this->assignment->id);
        $members = $this->get_members_from_team($teamid);
        print_header ($team->name);
        if ($team && $members) {
            echo '<table cellspacing="0"  >';
            ///Start of teacher info row
            echo '<tr>';
            echo '<th>&nbsp;</th>';
            echo '<th>'.$team->name.'</th>';
            echo '</tr>';
            foreach ($members as $member) {
                if ($user = get_record('user', 'id', $member->student)) {
                    echo '<tr>';
                    echo '<td class="topic">';
                    print_user_picture($user, $this->course->id, $user->picture);
                    echo '</td>';
                    echo '<td class="topic">';
                    echo fullname($user);
                    echo '</td></tr>';
                }
            }
            echo '</table>';
        } else {
            echo get_string('teamchangedwarning', 'assignment_team');
        }
        print_footer('none');
    }

    /**
     * override base class method
     * this method is to display all team members submsission info.
     * add a column filed called team indicating which team that a user belongs to.
     * @param $message
     */
    function display_submissions($message='') {
        global $CFG, $db, $USER;
        require_once($CFG->libdir.'/gradelib.php');

        /* first we check to see if the form has just been submitted
         * to request user_preference updates
         */

        if (isset($_POST['updatepref'])){
            $perpage = optional_param('perpage', 10, PARAM_INT);
            $perpage = ($perpage <= 0) ? 10 : $perpage ;
            set_user_preference('assignment_perpage', $perpage);
            set_user_preference('assignment_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
        }

        /* next we get perpage and quickgrade (allow quick grade) params
         * from database
         */
        $perpage    = get_user_preferences('assignment_perpage', 10);

        $quickgrade = get_user_preferences('assignment_quickgrade', 0);

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id);

        if (!empty($CFG->enableoutcomes) and !empty($grading_info->outcomes)) {
            $uses_outcomes = true;
        } else {
            $uses_outcomes = false;
        }

        $page    = optional_param('page', 0, PARAM_INT);
        $strsaveallfeedback = get_string('saveallfeedback', 'assignment');

        /// Some shortcuts to make the code read better

        $course     = $this->course;
        $assignment = $this->assignment;
        $cm         = $this->cm;

        $tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet
        add_to_log($course->id, 'assignment', 'view submission', 'submissions.php?id='.$this->cm->id, $this->assignment->id, $this->cm->id);
        $navigation = build_navigation($this->strsubmissions, $this->cm);
        print_header_simple(format_string($this->assignment->name,true), "", $navigation,
                '', '', true, update_module_button($cm->id, $course->id, $this->strassignment), navmenu($course, $cm));

        $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
            . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }

        if (!empty($message)) {
            echo $message;   // display messages here if any
        }

        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        /// Check to see if groups are being used in this assignment

        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm,  $CFG->wwwroot .  '/mod/assignment/view.php?' . $this->cm->id);

        /// Get all ppl that are allowed to submit assignments
        if ($users = get_users_by_capability($context, 'mod/assignment:submit', 'u.id', '', '', '', $currentgroup, '', false)) {
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        $tablecolumns = array('picture', 'fullname', 'grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade');
        if ($uses_outcomes) {
            $tablecolumns[] = 'outcome'; // no sorting based on outcomes column
        }

        $tableheaders = array('',
        get_string('fullname'),
        get_string('grade'),
        get_string('team', 'assignment_team'), //add team heading
        get_string('comment', 'assignment'),
        get_string('lastmodified').' ('.$course->student.')',
        get_string('lastmodified').' ('.$course->teacher.')',
        get_string('status'),
        get_string('finalgrade', 'grades'));
        if ($uses_outcomes) {
            $tableheaders[] = get_string('outcome', 'grades');
        }

        require_once($CFG->libdir.'/tablelib.php');
        $table = new flexible_table('mod-assignment-submissions');

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot.'/mod/assignment/submissions.php?id='.$this->cm->id.'&amp;currentgroup='.$currentgroup);

        $table->sortable(true, 'lastname');//sorted by lastname by default
        $table->collapsible(true);
        $table->initialbars(true);

        $table->column_suppress('picture');
        $table->column_suppress('fullname');

        $table->column_class('picture', 'picture');
        $table->column_class('fullname', 'fullname');
        $table->column_class('grade', 'grade');
        $table->column_class('submissioncomment', 'comment');
        $table->column_class('timemodified', 'timemodified');
        $table->column_class('timemarked', 'timemarked');
        $table->column_class('status', 'status');
        $table->column_class('finalgrade', 'finalgrade');
        if ($uses_outcomes) {
            $table->column_class('outcome', 'outcome');
        }

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');
        //$table->set_attribute('align', 'center');

        $table->no_sorting('finalgrade');
        $table->no_sorting('outcome');

        // Start working -- this is necessary as soon as the niceties are over
        $table->setup();

        if (empty($users)) {
            print_heading(get_string('nosubmitusers','assignment'));
            return true;
        }

        /// Construct the SQL

        if ($where = $table->get_sql_where()) {
            $where .= ' AND ';
        }

        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY '.$sort;
        }

        $select = 'SELECT u.id, u.firstname, u.lastname, u.picture, u.imagealt,
                          s.id AS submissionid, s.grade, s.submissioncomment,
                          s.timemodified, s.timemarked,
							COALESCE(SIGN(SIGN(s.timemarked) + 
                              SIGN(
								CASE WHEN s.timemarked = 0 THEN null 
                                     WHEN s.timemarked > s.timemodified THEN s.timemarked - s.timemodified 
                                     ELSE 0 END
                              )), 0) AS status ';        $sql = 'FROM '.$CFG->prefix.'user u '.
               'LEFT JOIN '.$CFG->prefix.'assignment_submissions s ON u.id = s.userid
                                                                  AND s.assignment = '.$this->assignment->id.' '.
               'WHERE '.$where.'u.id IN ('.implode(',',$users).') ';

        $table->pagesize($perpage, count($users));

        ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
        $offset = $page * $perpage;

        $strupdate = get_string('update');
        $strgrade  = get_string('grade');
        $grademenu = make_grades_menu($this->assignment->grade);

        if (($ausers = get_records_sql($select.$sql.$sort, $table->get_page_start(), $table->get_page_size())) !== false) {
            $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array_keys($ausers));
            foreach ($ausers as $auser) {
                $final_grade = $grading_info->items[0]->grades[$auser->id];
                $grademax = $grading_info->items[0]->grademax;
                $final_grade->formatted_grade = round($final_grade->grade,2) .' / ' . round($grademax,2);
                $locked_overridden = 'locked';
                if ($final_grade->overridden) {
                    $locked_overridden = 'overridden';
                }

                /// Calculate user status
                $auser->status = ($auser->timemarked > 0) && ($auser->timemarked >= $auser->timemodified);
                $picture = print_user_picture($auser, $course->id, $auser->picture, false, true);

                if (empty($auser->submissionid)) {
                    $auser->grade = -1; //no submission yet
                }

                if (!empty($auser->submissionid)) {
                    ///Prints student answer and student modified date
                    ///attach file or print link to student answer, depending on the type of the assignment.
                    ///Refer to print_student_answer in inherited classes.
                    if ($auser->timemodified > 0) {
                        $team = $this->get_user_team($auser->id);
                        if (isset($team)) {
                            $studentmodified = '<div id="ts'.$auser->id.'">'.$this->print_team_answer($team->id)
                            . userdate($auser->timemodified).'</div>';
                        } else {
                            error_log('team is not set');
                            $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                        }
                    } else {
                        $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                    }
                    ///Print grade, dropdown or text
                    if ($auser->timemarked > 0) {
                        $teachermodified = '<div id="tt'.$auser->id.'">'.userdate($auser->timemarked).'</div>';

                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                        } else if ($quickgrade) {
                            $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                                     'menu['.$auser->id.']', $auser->grade,
                            get_string('nograde'),'',-1,true,false,$tabindex++);
                            $grade = '<div id="g'.$auser->id.'">'. $menu .'</div>';
                        } else {
                            $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                        }

                    } else {
                        $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                        if ($final_grade->locked or $final_grade->overridden) {
                            $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                        } else if ($quickgrade) {
                            $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                                     'menu['.$auser->id.']', $auser->grade,
                            get_string('nograde'),'',-1,true,false,$tabindex++);
                            $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                        } else {
                            $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                        }
                    }
                    ///Print Comment
                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';

                    } else if ($quickgrade) {
                        $comment = '<div id="com'.$auser->id.'">'
                        . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                        //miki it was rows=2 and cols = 20
						. $auser->id.'" rows="8" cols="50">'.($auser->submissioncomment).'</textarea></div>';
                    } else {
					//miki expand the comment column to 150 instead of 15
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),150).'</div>';
                    }
                } else {
                    $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                    $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                    $status          = '<div id="st'.$auser->id.'">&nbsp;</div>';

                    if ($final_grade->locked or $final_grade->overridden) {
                        $grade = '<div id="g'.$auser->id.'">'.$final_grade->formatted_grade . '</div>';
                    } else if ($quickgrade) {   // allow editing
                        $menu = choose_from_menu(make_grades_menu($this->assignment->grade),
                                                 'menu['.$auser->id.']', $auser->grade,
                        get_string('nograde'),'',-1,true,false,$tabindex++);
                        $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                    } else {
                        $grade = '<div id="g'.$auser->id.'">-</div>';
                    }

                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.$final_grade->str_feedback.'</div>';
                    } else if ($quickgrade) {
                        $comment = '<div id="com'.$auser->id.'">'
                        . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                        . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                    } else {
                        $comment = '<div id="com'.$auser->id.'">&nbsp;</div>';
                    }
                }

                if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
                    $auser->status = 0;
                } else {
                    $auser->status = 1;
                }

                $buttontext = ($auser->status == 1) ? $strupdate : $strgrade;

                ///No more buttons, we use popups ;-).
                $popup_url = '/mod/assignment/submissions.php?id='.$this->cm->id
                . '&amp;userid='.$auser->id.'&amp;mode=single'.'&amp;offset='.$offset++;
                $button = link_to_popup_window ($popup_url, 'grade'.$auser->id, $buttontext, 600, 780,
                $buttontext, 'none', true, 'button'.$auser->id);

                $status  = '<div id="up'.$auser->id.'" class="s'.$auser->status.'">'.$button.'</div>';

                $finalgrade = '<span id="finalgrade_'.$auser->id.'">'.$final_grade->str_grade.'</span>';

                $outcomes = '';

                if ($uses_outcomes) {

                    foreach($grading_info->outcomes as $n=>$outcome) {
                        $outcomes .= '<div class="outcome"><label>'.$outcome->name.'</label>';
                        $options = make_grades_menu(-$outcome->scaleid);

                        if ($outcome->grades[$auser->id]->locked or !$quickgrade) {
                            $options[0] = get_string('nooutcome', 'grades');
                            $outcomes .= ': <span id="outcome_'.$n.'_'.$auser->id.'">'.$options[$outcome->grades[$auser->id]->grade].'</span>';
                        } else {
                            $outcomes .= ' ';
                            $outcomes .= choose_from_menu($options, 'outcome_'.$n.'['.$auser->id.']',
                            $outcome->grades[$auser->id]->grade, get_string('nooutcome', 'grades'), '', 0, true, false, 0, 'outcome_'.$n.'_'.$auser->id);
                        }
                        $outcomes .= '</div>';
                    }
                }

                $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course=' . $course->id . '">' . fullname($auser) . '</a>';
                //add team columns into the table.
                $team = $this -> get_user_team($auser->id);
                $teamlink ='&nbsp';
                if (!empty($team)) {
                    $teamlink = $this ->get_team_link($team);
                }
                $row = array($picture, $userlink, $grade, $teamlink, $comment, $studentmodified, $teachermodified, $status, $finalgrade);
                if ($uses_outcomes) {
                    $row[] = $outcomes;
                }

                $table->add_data($row);
            }
        }

        /// Print quickgrade form around the table
        if ($quickgrade){
            echo '<form action="submissions.php" id="fastg" method="post">';
            echo '<div>';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="mode" value="fastgrade" />';
            echo '<input type="hidden" name="page" value="'.$page.'" />';
			//miki temp
			echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '</div>';
        }

        $table->print_html();  /// Print the whole table

        if ($quickgrade){
            $lastmailinfo = get_user_preferences('assignment_mailinfo', 1) ? 'checked="checked"' : '';
            echo '<div class="fgcontrols">';
            echo '<div class="emailnotification">';
            echo '<label for="mailinfo">'.get_string('enableemailnotification','assignment').'</label>';
            echo '<input type="hidden" name="mailinfo" value="0" />';
            echo '<input type="checkbox" id="mailinfo" name="mailinfo" value="1" '.$lastmailinfo.' />';
            helpbutton('emailnotification', get_string('enableemailnotification', 'assignment'), 'assignment').'</p></div>';
            echo '</div>';
            echo '<div class="fastgbutton"><input type="submit" name="fastg" value="'.get_string('saveallfeedback', 'assignment').'" /></div>';
            echo '</div>';
            echo '</form>';
        }
        /// End of fast grading form

        /// Mini form for setting user preference
        echo '<div class="qgprefs">';
        echo '<form id="options" action="submissions.php?id='.$this->cm->id.'" method="post"><div>';
        echo '<input type="hidden" name="updatepref" value="1" />';
        echo '<table id="optiontable">';
        echo '<tr><td>';
        echo '<label for="perpage">'.get_string('pagesize','assignment').'</label>';
        echo '</td>';
        echo '<td>';
        echo '<input type="text" id="perpage" name="perpage" size="1" value="'.$perpage.'" />';
        helpbutton('pagesize', get_string('pagesize','assignment'), 'assignment');
        echo '</td></tr>';
        echo '<tr><td>';
        echo '<label for="quickgrade">'.get_string('quickgrade','assignment').'</label>';
        echo '</td>';
        echo '<td>';
        $checked = $quickgrade ? 'checked="checked"' : '';
        echo '<input type="checkbox" id="quickgrade" name="quickgrade" value="1" '.$checked.' />';
        helpbutton('quickgrade', get_string('quickgrade', 'assignment'), 'assignment').'</p></div>';
        echo '</td></tr>';
        echo '<tr><td colspan="2">';
        echo '<input type="submit" value="'.get_string('savepreferences').'" />';
        echo '</td></tr></table>';
        echo '</div></form></div>';
        ///End of mini form
        $this->view_footer();
    }

    /**
     * this method is to decide which type of marking popup window
     * are displayed.
     * @param $extra_javascript
     */
    function display_submission($extra_javascript = '') {
        $teamid = optional_param('teamid', NULL, PARAM_INT);
        $userid = optional_param('userid', NULL, PARAM_INT);
        if (isset($teamid)) {
            error_log('display team marking window');
            $this-> display_team_marking_window($extra_javascript );//display pop up marking window for team marking
        } elseif (isset($userid)) {
            error_log('display user marking window');
            $this->display_member_marking_window($extra_javascript); //display popup marking window for individual marking
        }

    }

    function team_file_area_name($teamid) {
        global $CFG;
        return $this->course->id.'/'.$CFG->moddata.'/assignment/'.$this->assignment->id.'/'.'team/'.$teamid;
    }

    /**
     * Makes a team upload directory
     * @param $teamid int The user id
     * @return string path to file area.
     */
    function team_file_area($teamid) {
        return make_upload_directory( $this->team_file_area_name($teamid) );
    }

    function display_team_marking_window($extra_javascript) {

        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/tablelib.php');

        //short cut var
        $teamid = required_param('teamid', PARAM_INT);
        $userrep = required_param('userrep', PARAM_INT);
        $id = $this->cm->id;
        $mode = "teamgrade";
        $submission = $this->get_submission($userrep);
        $team = get_record('team', 'id', $teamid,'assignment', $this->assignment->id);

        //check if team id or userrep exist.
        $error = false;
        if (!$this->is_user_course_participant($userrep)
            ||!$team 
            ||!get_record('team_student', 'team', $teamid, 'student', $userrep)) {
            $error = true;
        }

        if($error) {
            print_header(get_string('warning', 'assignment_team'));
            print_heading(get_string('teamchangedwarning', 'assignment_team'));
            print_footer('none');
            die;
        }
        $course     = $this->course;
        $assignment = $this->assignment;
        $cm         = $this->cm;
        $context    = get_context_instance(CONTEXT_MODULE, $cm->id);
        //get team name
        print_header(get_string('feedback', 'assignment').':'.$team->name.':'.format_string($this->assignment->name));

        ///SOme javascript to help with setting up >.>
        echo '<table cellspacing="0"  >';

        ///Start of teacher info row

        echo '<tr>';
        echo '<td class="picture teacher">';

        global $USER;
        $teacher = $USER;

        print_user_picture($teacher, $this->course->id, $teacher->picture);
        echo '</td>';
        echo '<td class="content">';
        echo '<form id="submitform" action="submissions.php" method="post">';
        echo '<div>'; // xhtml compatibility - invisiblefieldset was breaking layout here
        echo '<input type="hidden" name="teamid" value="'.$teamid.'" />';
        echo '<input type="hidden" name="userrep" value="'.$userrep.'" />';
        echo '<input type="hidden" name="id" value="'.$id.'" />';
        echo '<input type="hidden" name="mode" value="'.$mode.'" />';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        echo '<div class="from">';
        echo '<div class="fullname">'.$team->name.'</div>';
        echo '</div>';
        echo '<div class="grade"><label for="menugrade">'.get_string('grade').'</label> ';
        $grade = '';
        if (!$this->is_grades_diff($teamid)) {
            $grade = $submission ->grade;
        }
        choose_from_menu(make_grades_menu($this->assignment->grade), 'grade', $grade, get_string('nograde'), '', -1, false, false);
        echo '</div>';
         
        $comment = '';
        if ($submission) {
            $comment = $submission->submissioncomment;
        }
        print_textarea($this->usehtmleditor, 14, 58, 0, 0, 'submissioncomment', $comment, $this->course->id);
        if ($this->usehtmleditor) {
            echo '<input type="hidden" name="format" value="'.FORMAT_HTML.'" />';
        } else {
            echo '<div class="format">';
            choose_from_menu(format_text_menu(), "format", '', "");
            helpbutton("textformat", get_string("helpformatting"));
            echo '</div>';
        }
        echo '<div class="buttons">';
        echo '<input type="submit" name="submit" value="'.get_string('savechanges').'"  />';
        echo '<input type="submit" name="cancel" value="'.get_string('cancel').'" />';
        echo '</div>';
        echo '</div></form>';
        echo '</td></tr>';
        echo '</table>';

        $customfeedback = $this->custom_team_feedbackform($id, $teamid, $userrep, 'single');
        if (!empty($customfeedback)) {
            echo $customfeedback;
        }
        if ($this->usehtmleditor) {
            use_html_editor();
        }
        print_footer('none');
         
    }

    /**
     * display a marking popup window for teachers marking a team member's submission
     * teacher can not reverse student submission to draft.
     * @param $extra_javascript
     */
    function display_member_marking_window($extra_javascript) {
        global $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/tablelib.php');

        $userid = required_param('userid', PARAM_INT);
        $offset = required_param('offset', PARAM_INT);//offset for where to start looking for student.

        if (!$user = get_record('user', 'id', $userid)) {
            error('No such user!');
        }

        if (!$submission = $this->get_submission($user->id)) {
            $submission = $this->prepare_new_submission($userid);
        }
        if ($submission->timemodified > $submission->timemarked) {
            $subtype = 'assignmentnew';
        } else {
            $subtype = 'assignmentold';
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array($user->id));
        $disabled = $grading_info->items[0]->grades[$userid]->locked || $grading_info->items[0]->grades[$userid]->overridden;

        /// construct SQL, using current offset to find the data of the next student
        $course     = $this->course;
        $assignment = $this->assignment;
        $cm         = $this->cm;
        $context    = get_context_instance(CONTEXT_MODULE, $cm->id);

        /// Get all ppl that can submit assignments

        $currentgroup = groups_get_activity_group($cm);
        if ($users = get_users_by_capability($context, 'mod/assignment:submit', 'u.id', '', '', '', $currentgroup, '', false)) {
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        $nextid = 0;

        if ($users) { // miki change 	 COALESCE
            $select = 'SELECT u.id, u.firstname, u.lastname, u.picture, u.imagealt,
                              s.id AS submissionid, s.grade, s.submissioncomment,
                              s.timemodified, s.timemarked,
							  COALESCE(SIGN(SIGN(s.timemarked) + 
                              SIGN(
								CASE WHEN s.timemarked = 0 THEN null 
                                     WHEN s.timemarked > s.timemodified THEN s.timemarked - s.timemodified 
                                     ELSE 0 END
                              )), 0) AS status ';            $sql = 'FROM '.$CFG->prefix.'user u '.
                   'LEFT JOIN '.$CFG->prefix.'assignment_submissions s ON u.id = s.userid
                                                                      AND s.assignment = '.$this->assignment->id.' '.
                   'WHERE u.id IN ('.implode(',', $users).') ';

            if ($sort = flexible_table::get_sql_sort('mod-assignment-submissions')) {
                $sort = 'ORDER BY '.$sort.' ';
            }

            if (($auser = get_records_sql($select.$sql.$sort, $offset+1, 1)) !== false) {
                $nextuser = array_shift($auser);
                /// Calculate user status
                $nextuser->status = ($nextuser->timemarked > 0) && ($nextuser->timemarked >= $nextuser->timemodified);
                $nextid = $nextuser->id;
            }
        }

        print_header(get_string('feedback', 'assignment').':'.fullname($user, true).':'.format_string($this->assignment->name));

        /// Print any extra javascript needed for saveandnext
        echo $extra_javascript;

        ///SOme javascript to help with setting up >.>

        echo '<script type="text/javascript">'."\n";
        echo 'function setNext(){'."\n";
        echo 'document.getElementById(\'submitform\').mode.value=\'next\';'."\n";
        echo 'document.getElementById(\'submitform\').userid.value="'.$nextid.'";'."\n";
        echo '}'."\n";

        echo 'function saveNext(){'."\n";
        echo 'document.getElementById(\'submitform\').mode.value=\'saveandnext\';'."\n";
        echo 'document.getElementById(\'submitform\').userid.value="'.$nextid.'";'."\n";
        echo 'document.getElementById(\'submitform\').saveuserid.value="'.$userid.'";'."\n";
        echo 'document.getElementById(\'submitform\').menuindex.value = document.getElementById(\'submitform\').grade.selectedIndex;'."\n";
        echo '}'."\n";

        echo '</script>'."\n";
        echo '<table cellspacing="0" class="feedback '.$subtype.'" >';

        ///Start of teacher info row

        echo '<tr>';
        echo '<td class="picture teacher">';
        if ($submission->teacher) {
            $teacher = get_record('user', 'id', $submission->teacher);
        } else {
            global $USER;
            $teacher = $USER;
        }
        print_user_picture($teacher, $this->course->id, $teacher->picture);
        echo '</td>';
        echo '<td class="content">';
        echo '<form id="submitform" action="submissions.php" method="post">';
        echo '<div>'; // xhtml compatibility - invisiblefieldset was breaking layout here
        echo '<input type="hidden" name="offset" value="'.($offset+1).'" />';
        echo '<input type="hidden" name="userid" value="'.$userid.'" />';
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        echo '<input type="hidden" name="mode" value="grade" />';
        echo '<input type="hidden" name="menuindex" value="0" />';//selected menu index

        //new hidden field, initialized to -1.
        echo '<input type="hidden" name="saveuserid" value="-1" />';

        if ($submission->timemarked) {
            echo '<div class="from">';
            echo '<div class="fullname">'.fullname($teacher, true).'</div>';
            echo '<div class="time">'.userdate($submission->timemarked).'</div>';
            echo '</div>';
        }
        echo '<div class="grade"><label for="menugrade">'.get_string('grade').'</label> ';
        choose_from_menu(make_grades_menu($this->assignment->grade), 'grade', $submission->grade, get_string('nograde'), '', -1, false, $disabled);
        echo '</div>';

        echo '<div class="clearer"></div>';
        echo '<div class="finalgrade">'.get_string('finalgrade', 'grades').': '.$grading_info->items[0]->grades[$userid]->str_grade.'</div>';
        echo '<div class="clearer"></div>';

        if (!empty($CFG->enableoutcomes)) {
            foreach($grading_info->outcomes as $n=>$outcome) {
                echo '<div class="outcome"><label for="menuoutcome_'.$n.'">'.$outcome->name.'</label> ';
                $options = make_grades_menu(-$outcome->scaleid);
                if ($outcome->grades[$submission->userid]->locked) {
                    $options[0] = get_string('nooutcome', 'grades');
                    echo $options[$outcome->grades[$submission->userid]->grade];
                } else {
                    choose_from_menu($options, 'outcome_'.$n.'['.$userid.']', $outcome->grades[$submission->userid]->grade, get_string('nooutcome', 'grades'), '', 0, false, false, 0, 'menuoutcome_'.$n);
                }
                echo '</div>';
                echo '<div class="clearer"></div>';
            }
        }


        $this->preprocess_submission($submission);

        if ($disabled) {
            echo '<div class="disabledfeedback">'.$grading_info->items[0]->grades[$userid]->str_feedback.'</div>';

        } else {
            print_textarea($this->usehtmleditor, 14, 58, 0, 0, 'submissioncomment', $submission->submissioncomment, $this->course->id);
            if ($this->usehtmleditor) {
                echo '<input type="hidden" name="format" value="'.FORMAT_HTML.'" />';
            } else {
                echo '<div class="format">';
                choose_from_menu(format_text_menu(), "format", $submission->format, "");
                helpbutton("textformat", get_string("helpformatting"));
                echo '</div>';
            }
        }

        $lastmailinfo = get_user_preferences('assignment_mailinfo', 1) ? 'checked="checked"' : '';

        ///Print Buttons in Single View
        echo '<input type="hidden" name="mailinfo" value="0" />';
        echo '<input type="checkbox" id="mailinfo" name="mailinfo" value="1" '.$lastmailinfo.' /><label for="mailinfo">'.get_string('enableemailnotification','assignment').'</label>';
        echo '<div class="buttons">';
        echo '<input type="submit" name="submit" value="'.get_string('savechanges').'" onclick = "document.getElementById(\'submitform\').menuindex.value = document.getElementById(\'submitform\').grade.selectedIndex" />';
        echo '<input type="submit" name="cancel" value="'.get_string('cancel').'" />';
        //if there are more to be graded.
        if ($nextid) {
            echo '<input type="submit" name="saveandnext" value="'.get_string('saveandnext').'" onclick="saveNext()" />';
            echo '<input type="submit" name="next" value="'.get_string('next').'" onclick="setNext();" />';
        }
        echo '</div>';
        echo '</div></form>';

        $customfeedback = $this->custom_feedbackform($submission, true);
        if (!empty($customfeedback)) {
            echo $customfeedback;
        }

        echo '</td></tr>';

        ///End of teacher info row, Start of student info row
        echo '<tr>';
        echo '<td class="picture user">';
        print_user_picture($user, $this->course->id, $user->picture);
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        echo '<div class="fullname">'.fullname($user, true).'</div>';
        if ($submission->timemodified) {
            echo '<div class="time">'.userdate($submission->timemodified).
            $this->display_lateness($submission->timemodified).'</div>';
        }
        echo '<div>'.$this->print_student_answer($user->id).'</div>';
        echo '</div>';

        echo '</td>';
        echo '</tr>';

        ///End of student info row

        echo '</table>';

        if (!$disabled and $this->usehtmleditor) {
            use_html_editor();
        }

        print_footer('none');
    }
     
    function upload_team_responsefile() {
        global $CFG;
        $teamid = required_param('teamid', PARAM_INT);
        $userrep = required_param('userrep', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $id = required_param('id', PARAM_INT);

        $teammembers = $this->get_members_from_team($teamid);

        if($teammembers) {
            $returnurl = "submissions.php?id=$id&amp;teamid=$teamid&amp;userrep=$userrep&amp;mode=$mode";

            if (data_submitted('nomatch') and $this->can_manage_responsefiles()) {

                //team responses folder dirs
                $teamdir = $this->team_file_area_name($teamid).'/responses';
                check_dir_exists($CFG->dataroot.'/'.$teamdir, true, true);
                require_once($CFG->dirroot.'/lib/uploadlib.php');
                $um = new upload_manager('newfile',false,true,$this->course,false,0,true);
                if (!$um->process_file_uploads($teamdir)) {
                    print_error('uploaderror', 'assignment', $returnurl);
                }
            }
            redirect($returnurl);
        }
    }

    /**
     * Count the team files uploaded by a given user
     * This method overrides the method in assignment_upload
     * @param $userid int The user id
     * @return int
     */
    function count_user_files($userid) {
        global $CFG;

        $team = $this->get_user_team($userid);

        $filearea = $this->team_file_area_name($team->id);

        if ( is_dir($CFG->dataroot.'/'.$filearea) && $basedir = $this->team_file_area($team->id)) {
            if ($files = get_directory_list($basedir, 'responses')) {
                return count($files);
            }
        }
        return 0;
    }

    /**
     *
     * @param $userid
     * @param $teamid
     * @return removed object
     */
    private function remove_user_from_team($userid , $teamid, $isteacher =false) {
        global $USER;

        $submission = $this->get_submission($userid, false);
        //capability check only if team member can remove a user from a team
        //or teacher can remove any team member.
        if ($this->is_member($teamid) || $isteacher) {
            if (!$isteacher && $submission->grade >= 0) {
                notify(get_string('teammarkedwarning', 'assignment_team'));
                return ;
            }
            $select = ' student = '.$userid.' and '.' team = '.$teamid;
            if (!delete_records_select('team_student', $select)){
                $this->print_error();

            }
            //if team members in this team  are empty, delete this team
            $members = $this->get_members_from_team($teamid);
            $team = get_record('team' , 'id', $teamid, 'assignment', $this->assignment->id);
            if ($team && !$members) {
                $team->assignment = 0;
                $team->timemodified = time();
                if (!update_record('team', $team)) {
                    $this -> print_error();
                } else {
                    $dir = $this->team_file_area_name($teamid);
                    $this->delete_all_files($dir);
                }              
            }
            if ($submission) {
                $dummysubmission = $this->prepare_dummy_submission($submission);
                if (!update_record('assignment_submissions',$dummysubmission)) {
                    $this -> print_error();
                }
            }
            //remove this student's assignment files
            $dir = $this->file_area_name($userid);
            $this -> delete_all_files($dir);

            //double check whether or not this team existing, update team record if this team exist
            $team = get_record('team' , 'id', $teamid,'assignment', $this->assignment->id);
            if ($team) {
                $team ->timemodified = time();
                update_record('team', $team);
            }
        } else {
            $this->print_error();
        }
    }
    
    private function prepare_dummy_submission($submission) {
        //Students leave the team, we update the this time. 
        $submission->timemodified = time();
        $submission->numfiles     = 0;
        $submission->data1        = '';
        $submission->data2        = '';
        $submission->grade        = -1;
        $submission->submissioncomment      = '';
        $submission->format       = 0;
        $submission->teacher      = 0;
        $submission->timemarked   = 0;
        $submission->mailed       = 0;
        return $submission;
    } 
    

    private function get_teams() {
        global $CFG;
        $validteams = array();    
        $allteams = get_records_sql("SELECT id, assignment, name, membershipopen".
                                 " FROM {$CFG->prefix}team ".
                                 " WHERE assignment = ".$this->assignment->id);
        if ($allteams && is_array($allteams)) {
            foreach ($allteams as $team) {
                if ($this->has_members($team->id)) {
                    $validteams[] = $team;
                }
            }
            if (!empty($validteams)) {
                return $validteams;
            }
        }
        return false;
    }

    private function get_team_status_name($status) {
        if ($status) {
            return  get_string('teamopen', 'assignment_team');
        } else {
            return  get_string('teamclosed', 'assignment_team');
        }
    }

    private function get_all_team_submissions_number($teams) {
        global $CFG;
        $count = 0;
        foreach ($teams as $team) {
            //$member = get_record('team_student', 'team', $team->id);
            $members = $this ->get_members_from_team($team->id);
            if($members && count($members)>0) {
                if ($this->is_team_submitted($members)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function is_team_submitted($members) {
        foreach ($members as $member) {
            $membersubmission = $this ->get_submission($member->student);
            if ($membersubmission) {
                if ($membersubmission->data2 == 'submitted') {
                    return true;
                }
            }
        }
        return false;
    }

    private function get_team_users() {
        //create teamusers to represent a team.
        //When a maker mark this a teamuser ,all the members in this team are updated.
        $teamuser = array();
        $teams = $this-> get_teams();
        if($teams) {
            foreach($teams as $team) {
                $teamstudent = $this->get_first_teammember($team->id);
                if (!isset($teamuser[$team->id])) {
                    $teamuser[$team->id] = $teamstudent->student;
                }
            }
        }     
        return $teamuser;
    }

    /**
     * get first match team member
     * @param unknown_type $teamid
     * return first team member or false
     */
    private function get_first_teammember($teamid) {
        global $CFG;
        $members = $this -> get_members_from_team ($teamid);
        if ($members && count($members)) {
            foreach ($members as $member) {
                return $member;
            }
        }
        return false;
    }

    private function is_grades_diff($teamid) {
        $teammembers = $this->get_members_from_team($teamid);
        $i = 0;
        $flag = true;
        $prev = null;
        foreach ($teammembers as $member) {
            if ($submission = $this->get_submission($member->student)) {
                if ($i == 0) {
                    $prev = $submission;
                }
                if ($prev->grade != $submission->grade) {
                    return true;
                }
                $i++;
                $prev = $submission;
            }else {
                // TODO add logic
            }
        }
        return false;
    }

    private function process_team_grades() {
        global $CFG, $USER;
        require_once($CFG->libdir.'/gradelib.php');

        if (!$feedback = data_submitted()) {      // No incoming data?
            return false;
        }

        if (!empty($feedback->cancel)) {          // User hit cancel button
            return false;
        }

        $teamid = $feedback->teamid;
        $members = $this->get_members_from_team($teamid);
        foreach ($members as $member) {
            $userid = $member->student;
            if(get_record('user', 'id', $userid)) {
                $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, $userid);

                // store outcomes if needed
                $this->process_outcomes($userid);

                $submission = $this->get_submission($userid, true);  // Get or make one

                if (!$grading_info->items[0]->grades[$userid]->locked and
                !$grading_info->items[0]->grades[$userid]->overridden) {

                    $submission->grade      = $feedback->grade;
                    $submission->submissioncomment    = $feedback->submissioncomment;
                    $submission->format     = $feedback->format;
                    $submission->teacher    = $USER->id;
                    $mailinfo = get_user_preferences('assignment_mailinfo', 0);
                    if (!$mailinfo) {
                        $submission->mailed = 1;       // treat as already mailed
                    } else {
                        $submission->mailed = 0;       // Make sure mail goes out (again, even)
                    }
                    $submission->timemarked = time();

                    unset($submission->data1);  // Don't need to update this.
                    unset($submission->data2);  // Don't need to update this.

                    if (empty($submission->timemodified)) {   // eg for offline assignments
                        // $submission->timemodified = time();
                    }

                    if (! update_record('assignment_submissions', $submission)) {
                        return false;
                    }

                    // triger grade event
                    $this->update_grade($submission);

                    add_to_log($this->course->id, 'assignment', 'update grades',
                       'submissions.php?id='.$this->assignment->id.'&user='.$userid, $userid, $this->cm->id);
                }
            }
        }
        return true;
    }

    /**
     * helper class to update parent page view after updating the team marking
     *
     * @param $submission
     */
    private function update_team_main_listing($submission) {
        global $SESSION, $CFG;

        $output = '';
	// miki before was $perpage = 10; and now 100 temp to show many teams in one page
        $perpage = 100;

        /// Run some Javascript to try and update the parent page
        $output .= '<script type="text/javascript">'."\n<!--\n";
        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['submissioncomment'])) {
            $output.= 'opener.document.getElementById("com'.$submission->userid.
                '").innerHTML="'.shorten_text(trim(strip_tags($submission->submissioncomment)), 15)."\";\n";

        }

        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['grade'])) {
            $output.= 'opener.document.getElementById("g'.$submission->userid.'").innerHTML="'.
            $this->display_grade($submission->grade)."\";\n";

        }
        //need to add student's assignments in there too.
        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['timemodified']) &&
        $submission->timemodified) {
            $output.= 'opener.document.getElementById("ts'.$submission->userid.
                 '").innerHTML="'.addslashes_js($this->print_student_answer($submission->userid)).userdate($submission->timemodified)."\";\n";
        }

        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['timemarked']) &&
        $submission->timemarked) {
            $output.= 'opener.document.getElementById("tt'.$submission->userid.
                 '").innerHTML="'.userdate($submission->timemarked)."\";\n";
        }

        //modified the popup_url link parameters.
        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['status'])) {
            $output.= 'opener.document.getElementById("up'.$submission->userid.'").className="s1";';
            $buttontext = get_string('update');
            $team = $this -> get_user_team($submission->userid);
            $popup_url = '/mod/assignment/submissions.php?id='.$this->cm->id
            . '&amp;teamid='.$team->id.'&amp;userrep='.$submission->userid.'&amp;mode=single';
            $button = link_to_popup_window ($popup_url, '', $buttontext, 600, 780,
            $buttontext, 'none', true, 'button'.$submission->userid);
            $output.= 'opener.document.getElementById("up'.$submission->userid.'").innerHTML="'.addslashes_js($button).'";';
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, $submission->userid);

        if (!empty($CFG->enableoutcomes) and empty($SESSION->flextable['mod-assignment-submissions']->collapse['outcome'])) {

            if (!empty($grading_info->outcomes)) {
                foreach($grading_info->outcomes as $n=>$outcome) {
                    if ($outcome->grades[$submission->userid]->locked) {
                        continue;
                    }
                    $options = make_grades_menu(-$outcome->scaleid);
                    $options[0] = get_string('nooutcome', 'grades');
                    $output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.'").innerHTML="'.$options[$outcome->grades[$submission->userid]->grade]."\";\n";
                     
                }
            }
        }

        $output .= "\n-->\n</script>";
        return $output;
    }

    private function get_team_link($team) {
        $teambuttontext = $team->name;
        $teampopup_url = '/mod/assignment/submissions.php?id='.$this->cm->id
        . '&amp;teamid='.$team->id.'&amp;mode=showteam';
        return link_to_popup_window ($teampopup_url, $teambuttontext, $teambuttontext, 600, 780,
        $teambuttontext, 'none', true);
    }
    
    private function is_user_course_participant($userid) {
        global $CFG;
        $studentrole = 5;
        $context = get_context_instance(CONTEXT_COURSE, $this->assignment->course);
        $contextid = $context->id;
        $sql =  "SELECT u.id FROM {$CFG->prefix}user u INNER JOIN ".
               "{$CFG->prefix}role_assignments ra on u.id=ra.userid ".
               "WHERE u.id = {$userid} ".
               "AND ra.contextid = {$contextid} ".
               "AND ra.roleid = {$studentrole}";
        if (get_records_sql($sql)) {
            return true;
        }
        return false;
    
   }
   
   private function has_members($teamid) {
       if (get_record('team', 'id', $teamid, 'assignment', $this->assignment->id)) {
           $members = $this->get_members_from_team ($teamid);
           if($members && is_array($members)) {
               foreach ($members as $member) {
                   if ($this->is_user_course_participant($member->student)) {
                       return true;
                   }
               }
           }        
       }
       return false;
   }
}

?>
