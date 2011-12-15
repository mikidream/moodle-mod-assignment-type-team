 ///Print Comment
                    if ($final_grade->locked or $final_grade->overridden) {
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';

                    } else {
					//miki expand the comment column to 150 instead of 15
                        $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),150).'</div>';
                    }
                } else {