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