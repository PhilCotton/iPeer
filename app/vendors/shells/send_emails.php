<?php
App::import('Component', 'Email');
require_once (CORE_PATH.'cake/libs/controller/controller.php');

/**
 * CreateAclShell
 *
 * @uses Shell
 * @package   CTLT.iPeer
 * @author    Pan Luo <pan.luo@ubc.ca>
 * @copyright 2012 All rights reserved.
 * @license   MIT {@link http://www.opensource.org/licenses/MIT}
 */
class SendEmailsShell extends Shell
{
    public $uses = array('User', 'EmailSchedule', 'SysParameter', 'EmailMerge','Event');
    const EMAIL_TASK_LOCK = 'tmp/email_task_lock';
    /**
     * main
     *
     *
     * @access public
     * @return void
     */
    function main()
    {
        // check if the LOCK is already there
        if (file_exists(APP.SendEmailsShell::EMAIL_TASK_LOCK)) {
            echo "Send email task is still running. exiting...\n";
            return;
        }

        if (!touch(APP.SendEmailsShell::EMAIL_TASK_LOCK)) {
            echo "Failed to create the lock file: ".APP.SendEmailsShell::EMAIL_TASK_LOCK."! Check permissions.\n";
            return;
        }

        $controller = new Controller();
        $this->Email = new EmailComponent(null);
        $this->Email->initialize($controller);

        /**
         * Goes through scheduled emails that have not yet been sent,
         * send them if they're due and mark them them as sent.
         */
        $emails = $this->EmailSchedule->getEmailsToSend();
        $defaultFrom = $this->SysParameter->get('display.contact_info');

        foreach ($emails as $e) {
            $successCount = 0;
            $failedCount = 0;
            $e = $e['EmailSchedule'];
            echo "Processing email schedule id ".$e['id']."\n";
            $from_id = $e['from'];
			$event_id = $e['event_id'];
			
            $from = $this->User->getEmails($from_id);
            $from = (isset($from[$from_id]) && empty($from[$from_id])) ? $defaultFrom : $from[$from_id];

			$filter_email_list = reminderFilter($event_id,$e['to'],$e['id'],$e['date']); //Returns the modifies emaillist if the list contains the 'save_reminder'
			                                                                 //param, else returns $e['to']
			
            $emailList = $this->User->getEmails(explode(';', $filter_email_list));
            foreach ($emailList as $to_id => $to) {
                if (empty($to)) {
                    // skip the empty ones
                    continue;
                }
			
			
                $subject = $e['subject'];
                $content = $this->doMerge($e['content'], EmailMerge::MERGE_START, EmailMerge::MERGE_END, $to_id);
                if ($this->sendEmail($content, $subject, $from, $to)) {
                    $successCount++;
                } else {
                    echo "Failed to send email to ".$to."\n";
                    $failedCount++;
                }

            }
            $tmp = array('id' => $e['id'], 'sent' => '1');
            $this->EmailSchedule->save($tmp);
            echo "Message Sent: Success = ".$successCount.", Failed = ".$failedCount."\n";
        }

        if (!unlink(APP.SendEmailsShell::EMAIL_TASK_LOCK)) {
            echo "Failed to delete the email task lock file. Check the permission!\n";
        }
    }

   /*
    *Given an array of email addresses, delete the ones that have already submitted the evaluation with event_id
    * 
    * @param $event_id : The event id for the concerning event
    * @param $to : $to array
    * @param $email_id : id for the current email schedule id
    * @param $date : The date of the reminder with id = $email_id
    * 
    */

    private function reminderFilter($event_id,$to,$email_id,$date){
    if(isset($event_id) && $to[0]=='save_reminder') {
        //If the date on the reminder is past the due date, delete the corresponding reminder from the table
        $event =$this->Event->getEventById($event_id);
        if( strtotime($event['Event']['due_date']) < strtotime($date)){
            //Delete the corresponding row and return an empty to[] list
            $this->EmailSchedule->delete($email_id,false);
            $to_list_empty = array();
            return $to_list_empty;
        }
        else{ //Modify the to list and save the updated to[] list data in the database
    	$to_list = array();
		$submissions = $this->EvaluationSubmission->getEvalSubmissionsByEventId($event_id);
			for($i=1;$i < count($to);$i++){
				foreach($submissions as $s){
					if($to[$i] == $s['EvaluationSubmission']['submitter_id']){
						array_push($to_list,$to[$i]);
				}
			}
		}
			$to_list = array_values(array_diff($to, $to_list));
			array_shift($to_list);
			$to_list = implode(';', $to_list);
			
			//Save the new array in the Database table email_schedules
			$data = array('id' => $email_id,'to'=>$to_list);
			$this->EmailSchedule->save($data);
			$to_list = explode(';', $to_list);
			
		return $to_list;
	}
    }
	else //Database entry does not correspond to reminders so, return list as is. 
		return $to;
    }

    /**
     * Given a user id, get the email address associated with that id, if any.
     *
     * @param int $id - the user id
     *
     * @return The user's email address, if it exists, empty string if not
     */
    private function getEmailAddress($id) {
        return $this->User->field('email', array('User.id' => $id));
    }

    /**
     * Do merge
     *
     * @param string $string  string with merge fields
     * @param int    $start   start of merge field
     * @param int    $end     end of merge field
     * @param int    $user_id user id
     *
     * @return merged string
     */
    function doMerge($string, $start, $end, $user_id = null)
    {
        //Return array $matches that contains all tags
        preg_match_all('/'.$start.'(.*?)'.$end.'/', $string, $matches, PREG_OFFSET_CAPTURE);
        $patterns = array();
        $replacements = array();
        $patterns = $matches[0];
        foreach ($matches[0] as $key => $match) {
            $patterns[$key] = '/'.$match[0].'/';

            $table = $this->EmailMerge->getFieldAndTableNameByValue($match[0]);
            $table_name = $table['table_name'];
            $field_name = $table['field_name'];
            $this->$table_name->recursive = -1;
            $value = $this->$table_name->find('first', array(
                'conditions' => array($table_name.'.id' => $user_id),
                'fields' => $field_name
            ));

            $replacements[$key] = $value[$table_name][$field_name];
        }
        return preg_replace($patterns, $replacements, $string);
    }

    /**
     * _sendEmail send email wrapper
     *
     * @param mixed $content      email body
     * @param mixed $subject      email subject
     * @param mixed $from         sender address
     * @param mixed $toAddress    receiver address
     * @param bool  $templateName email template name
     * @param bool  $ccAddress    cc field
     * @param bool  $bcc          bcc field
     *
     * @access protected
     * @return void
     */
    protected function sendEmail($content, $subject, $from, $toAddress, $templateName = 'default', $ccAddress = array(), $bcc= array())
    {
        $this->SysParameter->reload();
        $smtp['port'] = $this->SysParameter->get('email.port');
        $smtp['host'] = $this->SysParameter->get('email.host');
        $smtp['username'] = $this->SysParameter->get('email.username');
        $smtp['password'] = $this->SysParameter->get('email.password');
        $smtp['timeout'] = 30;
        $this->Email->reset();

        $this->Email->smtpOptions = $smtp;
        $this->Email->delivery = 'smtp';
        $this->Email->to = $toAddress;
        $this->Email->cc = $ccAddress;
        $this->Email->bcc = $bcc;
        $this->Email->subject = $subject;
        $this->Email->from = $from;
        $this->Email->template = $templateName;
        $this->Email->sendAs = 'both';

        return $this->Email->send($content);
    }
}
