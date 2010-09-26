<?php
class ExportHelperComponent extends Object
{
    var $components = array('rdAuth');
    var $globUsersArr = array();
    var $globEventId;

    function createCSV($params) {
        $this->Course = new Course;
        $this->UserCourse = new UserCourse;
        $this->Event = new Event;
        $this->GroupEvent = new GroupEvent;
        $csvContent = '';

        //*******header
        $header = array();
        //get coursename
        $courseId = $this->rdAuth->courseId;
        $course = $this->Course->find('id='.$courseId,'course');
        $header['course_name'] = empty($params['form']['include_course']) ? '':$course['Course']['course'];
        //get date of export
        $header['export_date'] = empty($params['form']['include_date']) ? '':date('F t Y g:ia');
        //get instructor name
        $header['instructors'] = empty($params['form']['include_instructor']) ? '':$this->UserCourse->getInstructors($courseId);
        //print_r($header['instructors']); die;

        $csvContent .= $this->createHeader($header);

        //*******subheader
        //parse through each event... ugly...
        $events = $this->Event->getCourseEvalEvent($courseId);

        foreach ($events as $event) {
            $subHeader = array();
            //get evaluation event names
            $subHeader['event_name'] = empty($params['form']['include_eval_event_names']) ? '':$event['Event']['title'];
            //get evaluation types
            $subHeader['event_type_id'] = empty($params['form']['include_eval_event_type']) ? '':$event['Event']['event_template_type_id'];

            $csvContent .= $this->createSubHeader($subHeader);

            $eventId = $event['Event']['id'];
            $eventTemplateId = $event['Event']['template_id'];
            $eventTypeId = $event['Event']['event_template_type_id'];
            $groupEvents = $this->GroupEvent->findAll('event_id='.$eventId);

            //too much garbage... outsourced to createBody
            if (!empty($groupEvents)) {
                $csvContent .= $this->createBody($groupEvents,$params,$eventTemplateId,$eventTypeId);
            }

        }
        return $csvContent;
    }



	function createHeader($params) {
        if (!empty($params['course_name']) || !empty($params['export_date']) || !empty($params['instructors'])) {
            $header = '***************'."\n";
            $header .= !empty($params['course_name']) ? $params['course_name']."\n":'';
            $header .= !empty($params['export_date']) ? $params['export_date']."\n":'';
            foreach ($params['instructors'] as $instructor) {
                $instructor = $instructor['User'];
                $header .= $instructor['first_name'].' '.$instructor['last_name']."\n";
            }   //
            $header .= '**************'."\n";
            return  $header;
        } else {
            return '';
        }
	}

    function createSubHeader($params) {
        $evalTypes = array(1=>'Simple Evaluation',2=>'Rubric',4=>'Mixed Evaluation');
        if (!empty($params['event_name']) || !empty($params['event_type_id'])) {
            $subHeader = "\n";
            $subHeader .= !empty($params['event_name']) ? 'Event Name: '.$params['event_name']."\n":'';
            $subHeader .= !empty($params['event_type_id']) ? 'Event Type: '.$evalTypes[$params['event_type_id']]."\n":'';
            $subHeader .= "\n";
            return $subHeader;

        } else {
            return '';
        }
    }


    function createBody ($groupEvents,$params,$eventTemplateId,$eventTypeId) {
        global $globEventId;
        $this->Group = new Group;
        $this->GroupsMembers = new GroupsMembers;
        $this->User = new User;
        $this->SimpleEvaluation = new SimpleEvaluation;
        $this->Rubric = new Rubric;
        $this->Mixeval = new Mixeval;
        $this->RubricsCriteria = new RubricsCriteria;
        $this->MixEvalsQuestionDesc = new MixevalsQuestionDesc;
        $this->EvaluationSimple = new EvaluationSimple;
        $this->EvaluationRubric = new EvaluationRubric;
        $this->EvaluationMixeval = new EvaluationMixeval;

        $globEventId = $groupEvents[0]['GroupEvent']['event_id'];

        //bigass IF
        $data = array();
        $legends = array();
        $i=0;
        foreach ($groupEvents as $groupEvent) {
            //*******beef
            $groupId = $groupEvent['GroupEvent']['group_id'];
            $groupEventId = $groupEvent['GroupEvent']['id'];
            $group = $this->Group->find('id='.$groupId);
            //get group names
            $data[$i]['group_name'] = $group['Group']['group_name'];
            //get group stati
            $data[$i]['group_status'] = $groupEvent['GroupEvent']['marked'];

            $groupMembers = $this->GroupsMembers->getMembers($groupId);
            unset($groupMembers['member_count']);
            $j=0;
            $data[$i]['students'] = array();

            global $globUsersArr;
            foreach($groupMembers as $groupMember) {
                $userId = $groupMember['GroupsMembers']['user_id'];
                $student = $this->User->findUserByid($userId);
                $globUsersArr[$student['User']['student_no']] = $userId;
            }
            if (!empty($globUsersArr)) {
                $submittedArr = $this->buildSubmittedArr();
            } else {
                $submittedArr = array();
            }
            $count = 0;
            foreach($groupMembers as $groupMember) {
                if(in_array($groupMember['GroupsMembers']['user_id'], $submittedArr)) {
                    $count++;
                }
            }

            foreach ($groupMembers as $groupMember) {
                //get student info: first_name, last_name, id, email
                $userId = $groupMember['GroupsMembers']['user_id'];
                $student = $this->User->findUserByid($userId);
                $data[$i]['students'][$j]['student_id'] = $student['User']['student_no'];
                $data[$i]['students'][$j]['first_name'] = $student['User']['first_name'];
                $data[$i]['students'][$j]['last_name'] = $student['User']['last_name'];
                $data[$i]['students'][$j]['email'] = $student['User']['email'];

                switch ($eventTypeId) {
                    case 1://simple
                        $comments = $this->EvaluationSimple->getAllComments($groupEventId,$userId);
                        $data[$i]['students'][$j]['comments'] = '';
                        foreach ($comments as $comment) {
                            $data[$i]['students'][$j]['comments'] .= $comment['EvaluationSimple']['eval_comment'].'; ';
                        }
                        $data[$i]['students'][$j]['score'] = '';
                        $score_tmp = $this->EvaluationSimple->getReceivedTotalScore($groupEventId,$userId);
                        if (in_array($userId, $submittedArr)) {
                            $data[$i]['students'][$j]['score'] = !isset($score_tmp[0]['received_total_score']) ? '':($score_tmp[0]['received_total_score']/((count($groupMembers)-1)-$count+1));
                        } else {
                            $divisor = (((count($groupMembers)-1)-$count) > 0) ? ((count($groupMembers)-1)-$count) : 1;
                            $data[$i]['students'][$j]['score'] = !isset($score_tmp[0]['received_total_score']) ? '':($score_tmp[0]['received_total_score']/($divisor));
                        }
                        break;
                    case 2://rubric
                        //get the legend
                        if (empty($legend)) {
                            $legend_tmp = $this->RubricsCriteria->findAll('rubric_id='.$eventTemplateId, 'criteria');
                            foreach ($legend_tmp as $legend) {
                                array_push($legends, $legend['RubricsCriteria']['criteria']);
                            }
                        }
                        $subScore = $this->EvaluationRubric->getCriteriaResults($groupEventId, $userId);
                        $data[$i]['students'][$j]['sub_score'] = $subScore;
                        // Get total score:
                        $score_tmp = 0;
                        foreach ($subScore as $key => $value) {
                            $score_tmp += $value;
                        }
                        $data[$i]['students'][$j]['score'] = !isset($score_tmp) ? '' : $score_tmp;
                        //get comments
                        $data[$i]['students'][$j]['comments'] = '';
                        $comments = $this->EvaluationRubric->getAllComments($groupEventId, $userId);
                        foreach ($comments as $comment) {
                            $data[$i]['students'][$j]['comments'] .= $comment['EvaluationRubric']['general_comment'].'; ';
                        }
                        break;
                    case 4://mixeval
                        // $comments = $this->EvaluationMixvalDetail->getAllByEvalMixevalId($eventTemplateId);
                        //print_r($legend_tmp); die;
                        //  foreach ($comments as $comment) {
                        //    array_push($data[$i]['comments'],$comment['EvaluationMixevalDetail']['question_comment']);
                        // }
                        $score_tmp = $this->EvaluationMixeval->getReceivedTotalScore($groupEventId,$userId);
                        $userResults = $this->EvaluationMixeval->getResultsDetailByEvaluatee($groupEventId,$userId);
                        $data[$i]['students'][$j]['score'] = !isset($score_tmp[0]['received_total_score'])?'':$score_tmp[0]['received_total_score'];

                        $data[$i]['students'][$j]['comments'] = '';

                        foreach ($userResults as $comment) {
                            foreach($comment['EvaluationMixevalDetail'] as $sComment => $value) {
                                if($sComment == 'evaluation_mixeval_id' && !is_array($value)) {
                                    $userArray = $this->EvaluationMixeval->getMixEvalById($value);
                                    $evaluatorId = $userArray['EvaluationMixeval']['evaluator'];
                                    $evaluateeId = $userArray['EvaluationMixeval']['evaluatee'];
                                    $evaluatorArray = $this->User->findUserByid($evaluatorId);
                                    $evaluateeArray = $this->User->findUserByid($evaluateeId);
                                    $evaluatorName = $evaluatorArray['User']['first_name'];
                                    $evaluateeName = $evaluateeArray['User']['first_name'];
                                }

                                if(is_array($value)) {
                                    foreach($value as $comm => $commValue) {
                                        $data[$i]['students'][$j]['comments'] .= isset($commValue)&&!empty($commValue) && $comm == 'question_comment' ? $evaluateeName. "->". $evaluatorName. ": ". $commValue.'; ':'';
                                    }
                                }
                            }
                        }
                        break;
                    default:
                        break;
                }
                $j++;
            }
            //calculate final mark
            $i++;
        }
        return $this->formatBody($data, $params, $legends);
    }

    function formatBody($data, $params, $legends=null) {
        $content = '';
        //sloppy code... sorry...
        $fields = array('group_status','group_names','student_first','student_last','student_id','student_id','criteria_marks','general_comments');
        $hasContent=false;
        for ($i=0;$i<count($fields);$i++) {
            if (!empty($params['form']['include_'.$fields[$i]])) {
                $hasContent=true;
                break;
            }
        }
        //legend
        if (isset($legends)&&!empty($params['form']['include_criteria_legend'])) {
            $k=1;
            foreach ($legends as $item) {
                $content .= "Creteria " . $k++.",".$item."\n";
            }
            $content .= "\n";
        }

        //group header
        $content .= empty($params['form']['include_group_status']) ? '':'Status(X/OK),';
        $content .= empty($params['form']['include_group_names']) ? '':'Group Name,';
        $content .= empty($params['form']['include_student_first']) ? '':'First Name,';
        $content .= empty($params['form']['include_student_last']) ? '':'Last Name,';
        $content .= empty($params['form']['include_student_id']) ? '':'Student Number,';
        $content .= empty($params['form']['include_student_email']) ? '':'Email,';

        $content .= 'Final Mark,';

        if (!empty($params['form']['include_criteria_marks'])) {
            $k = 1;
            foreach ($legends as $key=>$value) {
                $content .= "Criteria $k, ";
                $k++;
            }
        }

        $content .= !isset($params['form']['include_general_comments']) ? '':'Comments';
        if ($hasContent) {
            $content .= "\n\n";
        }
        foreach ($data as $group) {

            foreach ($group['students'] as $student) {
                if (!empty($params['form']['include_group_status'])) {
                    $submittedArr = $this->buildSubmittedArr();
                    set_time_limit(1200);
                    if(array_key_exists($student['student_id'], $submittedArr) ) {
                        $content .= 'X,';
                    } else {
                        $content .= 'OK,';
                    }
                }

                $content .= empty($params['form']['include_group_names']) ? '':$group['group_name'].",";
                $content .= empty($params['form']['include_student_first']) ? '':"\"".$student['first_name']."\", ";
                $content .= empty($params['form']['include_student_last']) ? '':"\"".$student['last_name']."\", ";
                $content .= empty($params['form']['include_student_id']) ? '':$student['student_id'].", ";
                $content .= empty($params['form']['include_student_email']) ? '':"\"".$student['email']."\", ";
                $content .= empty($params['form']['include_criteria_marks']) ? '':$student['score'].", ";

                if (!empty($legends) &&
                    !empty($params['form']['include_criteria_marks'])) {
                    foreach ($student['sub_score'] as $key => $value) {
                        $content .= $value . ", ";
                    }

                }

                $content .= empty($params['form']['include_general_comments']) ? '': "\"".$student['comments']."\",";
                if ($hasContent) {
                    $content .= "\n";
                }
            }
            if ($hasContent) {
                $content .= "\n";
            }
        }
        return $content;
    }

  function buildSubmittedArr() {
    global $globEventId, $globUsersArr;
    $this->EvalSubmission = new EvaluationSubmission();
    foreach ($globUsersArr as $globUserStuNum=>$globUserId) {
      if($this->EvalSubmission->getEvalSubmissionByEventIdSubmitter($globEventId, $globUserId) != null)
        unset($globUsersArr[$globUserStuNum]);
    }
    return $globUsersArr;
  }
}
?>