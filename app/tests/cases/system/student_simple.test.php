<?php
require_once('system_base.php');

class studentSimple extends SystemBaseTestCase
{
    protected $eventId = 0;
    
    public function startCase()
    {
        $this->getUrl();
        $wd_host = 'http://localhost:4444/wd/hub';
        $this->web_driver = new PHPWebDriver_WebDriver($wd_host);
        $this->session = $this->web_driver->session('firefox');
        $this->session->open($this->url);
        
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $this->session->deleteAllCookies();
        $login = PageFactory::initElements($this->session, 'Login');
        $home = $login->login('root', 'ipeeripeer');
    }
    
    public function endCase()
    {
        $this->session->deleteAllCookies();
        $this->session->close();
    }
    
    public function testCreateEvent()
    {
        $this->session->open($this->url.'events/add/1');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventTitle')->sendKeys('Simple Evaluation');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventDescription')->sendKeys('Description for the Eval.');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventComReq1')->click(); // comments required
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventAutoRelease1')->click();

        //set due date and release date end to next month so that the event is opened.
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventDueDate')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventReleaseDateBegin')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventReleaseDateEnd')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'a[title="Next"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, '4')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventResultReleaseDateBegin')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'a[title="Next"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, '5')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventResultReleaseDateEnd')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'a[title="Next"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, '28')->click();
        
        // add penalty
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Add Penalty')->click();;
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'select[id="Penalty0PercentPenalty"] option[value="10"]')->click();
        
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'select[id="GroupGroup"] option[value="1"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'select[id="GroupGroup"] option[value="2"]')->click();

        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[type="submit"]')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
        $msg = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']")->text();
        $this->assertEqual($msg, 'Add event successful!');
    }
    
    public function testStudent()
    {
        $this->waitForLogoutLogin('redshirt0001');
        $title = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "h1.title")->text();
        $this->assertEqual($title, 'Home');
        
        $pending = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'div[class="eventSummary pending"]')->text();
        // check that there is at least one pending event
        $this->assertEqual(substr($pending, -20), 'Pending Events Total');
        
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        
        // check the submit button is disabled
        $submit = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Submit Evaluation"]');
        $this->assertTrue($submit->attribute('disabled'));
        // check the instructions for comments
        $comment = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'font[color="red"]');
        $this->assertEqual($comment->text(), '(Must)');
        // check penalty note
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, '( Show/Hide late penalty policy )')->click();
        $penalty = $this->session->element(PHPWebDriver_WebDriverBy::ID, 'penalty')->text();
        $this->assertEqual($penalty, "1 day late: 10% deduction.\n10% is deducted afterwards.");
        
        // move the sliders
        $this->handleOffset('handle6', 24);
        $this->handleOffset('handle7', -25);
        
        // wait for distribution to finish
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'distr_button')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;
        $w->until(
            function($session) {
                $six = $session->element(PHPWebDriver_WebDriverBy::ID, 'point6')->attribute('value');
                $seven = $session->element(PHPWebDriver_WebDriverBy::ID, 'point7')->attribute('value');
                return ($six != '' && $seven != '');
            }
        );
        $alex = $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point6')->attribute('value');
        $matt = $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point7')->attribute('value');
        $this->assertEqual($alex, 140);
        $this->assertEqual($matt, 60);
        
        $warning = $this->session->element(PHPWebDriver_WebDriverBy::ID, 'statusMsg')->text();
        $this->assertEqual($warning, "All points are allocated.\nThere are still 2 comments to be filled.");
        
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment6')->sendKeys('A very great group member');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment7')->sendKeys('Very responsible.');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment6')->click();
        
        $warning = $this->session->element(PHPWebDriver_WebDriverBy::ID, 'statusMsg')->text();
        $this->assertEqual($warning, "All points are allocated.\nAll comments are filled.");
        
        $submit->click();       
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
        $msg = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']")->text();
        $this->assertEqual($msg, 'Your Evaluation was submitted successfully.');
    }
    
    public function testReSubmit()
    {
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $delete = new PHPWebDriver_WebDriverKeys('BackspaceKey');
        // edit user's mark - could not use clear(), the javascript will give it a 'NaN'
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point6')->sendKeys($delete->key.$delete->key.$delete->key.'120');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment6')->click();
        // wait for new status message
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;
        $w->until(
            function($session) {
                $status = $session->element(PHPWebDriver_WebDriverBy::ID, 'statusMsg')->text();
                return ($status == "Please allocate 20 more points.\nAll comments are filled.");
            }
        );
        // distribute the leftover marks
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point7')->sendKeys($delete->key.$delete->key.'80');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment7')->click();

        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'submit0')->click();       
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
        $msg = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']")->text();
        $this->assertEqual($msg, 'Your Evaluation was submitted successfully.');
        
        // test negative points
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point6')->sendKeys($delete->key.$delete->key.$delete->key.'-100');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point7')->sendKeys($delete->key.$delete->key.'300');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment7')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'submit0')->click();       
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::ID, "flashMessage"));
            }
        );
        $msg = $this->session->element(PHPWebDriver_WebDriverBy::ID, 'flashMessage')->text();
        $this->assertEqual($msg, 'One or more of your group members have negative points. Please use positive numbers.');
        
        $this->secondStudent();
        $this->tutor();
    }
    
    public function testCheckResult()
    {
        $this->waitForLogoutLogin('root');
        $this->removeFromGroup();
        
        $this->session->open($this->url.'events/index/1');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $this->eventId = end(explode('/', $this->session->url()));
        
        // edit event's release date end to test final penalty
        // edit event's result release date begin to test student view of the results
        $this->session->open($this->url.'events/edit/'.$this->eventId);
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventReleaseDateEnd')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="ui-datepicker-div"]/div[3]/button[1]')->click(); // today
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventResultReleaseDateBegin')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="ui-datepicker-div"]/div[3]/button[1]')->click(); // today
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Submit"]')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;     
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
        
        $this->session->open($this->url.'evaluations/view/'.$this->eventId);
        $title = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "h1.title")->text();
        $this->assertEqual($title, 'MECH 328 - Mechanical Engineering Design Project > Simple Evaluation > Results');
        
        //auto-release results message
        $msg = $this->session->elements(PHPWebDriver_WebDriverBy::ID, 'autoRelease_msg');
        $this->assertTrue(!empty($msg));
        
        // check lates
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, '3 Late')->click();
        $ed = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[2]/tbody/tr[2]/td[4]');
        $this->assertEqual($ed->text(), '1 day(s)');
        $alex = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[2]/tbody/tr[3]/td[4]');
        $this->assertEqual($alex->text(), '1 day(s)');
        $matt = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[2]/tbody/tr[4]/td[3]');
        $this->assertEqual($matt->text(), '(not submitted)');
        $matt = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[2]/tbody/tr[4]/td[4]');
        $this->assertEqual($matt->text(), '---');
        
        // view evaluation results
        $this->session->open($this->url.'evaluations/viewEvaluationResults/'.$this->eventId.'/1');
        // check members that are not enrolled and have not completed
        $notSubmit = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[1]/th')->text();
        $this->assertEqual($notSubmit, 'Have not submitted their evaluations');
        $notComp = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td')->text();
        $this->assertEqual($notComp, 'Matt Student (student)');
        $left = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[1]/th')->text();
        $this->assertEqual($left, 'Left the group, but had submitted or were evaluated');
        $left = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[2]/td')->text();
        $this->assertEqual($left, 'Tutor 1 (TA)');
        
        // auto-release results messages
        $msg = $this->session->elements(PHPWebDriver_WebDriverBy::ID, 'autoRelease_msg');
        $this->assertTrue(!empty($msg));
        $msg = $this->session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'li[class="green"]');
        $this->assertTrue(!empty($msg));
        
        // check summary table
        // colour coding names
        $matt = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[2]/th[3]');
        $this->assertEqual($matt->attribute('class'), 'red');
        $matt = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[5]/th');
        $this->assertEqual($matt->attribute('class'), 'red');
        $tutor1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[6]/th');
        $this->assertEqual($tutor1->attribute('class'), 'blue');
        //comments section
        $matt = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/table[1]/tbody/tr[3]/td[1]');
        $this->assertEqual($matt->attribute('class'), 'red');
        $tutor1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/h3[3]');
        $this->assertEqual($tutor1->attribute('class'), 'blue');
        
        // check that the individual marks given are correct for Ed
        $mark1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[3]/td[1]')->text();
        $mark2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[3]/td[2]')->text();        
        $mark3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[3]/td[3]')->text();
        $this->assertEqual($mark1, '-');
        $this->assertEqual($mark2, '120.00');
        $this->assertEqual($mark3, '80.00');
        
        // check that the individual marks given are correct for Alex
        $mark1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[4]/td[1]')->text();
        $mark2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[4]/td[2]')->text();        
        $mark3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[4]/td[3]')->text();
        $this->assertEqual($mark1, '95.00');
        $this->assertEqual($mark2, '-');
        $this->assertEqual($mark3, '105.00');
        
        // check that the individual marks given are correct for Matt
        $mark1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[5]/td[1]')->text();
        $mark2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[5]/td[2]')->text();        
        $mark3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[5]/td[3]')->text();
        $this->assertEqual($mark1, '-');
        $this->assertEqual($mark2, '-');
        $this->assertEqual($mark3, '-');
        
        // check that the individual marks given are correct for Tutor 1
        $mark1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[6]/td[1]')->text();
        $mark2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[6]/td[2]')->text();        
        $mark3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[6]/td[3]')->text();
        $this->assertEqual($mark1, '100.00');
        $this->assertEqual($mark2, '100.00');
        $this->assertEqual($mark3, '100.00');
        
        // total
        $total1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[8]/td[1]')->text();
        $total2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[8]/td[2]')->text();
        $total3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[8]/td[3]')->text();
        $this->assertEqual($total1, '195.00');
        $this->assertEqual($total2, '220.00');
        $this->assertEqual($total3, '285.00');
        
        // penalty
        $penalty1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[9]/td[1]')->text();
        $penalty2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[9]/td[2]')->text();
        $penalty3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[9]/td[3]')->text();
        $this->assertEqual($penalty1, '19.50 (10%)');
        $this->assertEqual($penalty2, '22.00 (10%)');
        $this->assertEqual($penalty3, '28.50 (10%)');
        
        // final mark
        $final1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[10]/td[1]')->text();
        $final2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[10]/td[2]')->text();
        $final3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[10]/td[3]')->text();
        $this->assertEqual($final1, '175.50');
        $this->assertEqual($final2, '198.00');
        $this->assertEqual($final3, '256.50');
        
        // num
        $num1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[11]/td[1]')->text();
        $num2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[11]/td[2]')->text();
        $num3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[11]/td[3]')->text();
        $this->assertEqual($num1, '2');
        $this->assertEqual($num2, '2');
        $this->assertEqual($num3, '3');
        
        // average
        $avg1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[12]/td[1]')->text();
        $avg2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[12]/td[2]')->text();
        $avg3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[12]/td[3]')->text();
        $this->assertEqual($avg1, '87.75');
        $this->assertEqual($avg2, '99.00');
        $this->assertEqual($avg3, '85.5');
        
        // check the comments are correct
        $com1 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/table[1]/tbody/tr[2]/td[2]')->text();
        $com2 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/table[1]/tbody/tr[3]/td[2]')->text();
        $com3 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/table[2]/tbody/tr[2]/td[2]')->text();
        $com4 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/table[2]/tbody/tr[3]/td[2]')->text();
        $com5 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/table[3]/tbody/tr[2]/td[2]')->text();
        $com6 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/table[3]/tbody/tr[3]/td[2]')->text();
        $com7 = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/table[3]/tbody/tr[4]/td[2]')->text();
        $this->assertEqual($com1, 'A very great group member');
        $this->assertEqual($com2, 'Very responsible.');
        $this->assertEqual($com3, 'Very hardworking');
        $this->assertEqual($com4, 'A great leader');
        $this->assertEqual($com5, 'Does his fair share of work');
        $this->assertEqual($com6, 'Knows what he is doing');
        $this->assertEqual($com7, 'Does his work efficiently');
        
        // colour coding for Tutor in comments section
        $tutor = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/h3[3]');
        $this->assertEqual($tutor->attribute('class'), 'blue');
    }
    
    public function testStudentViewResults()
    {
        $this->waitForLogoutLogin('redshirt0003');
        $this->session->open($this->url.'evaluations/studentViewEvaluationResult/'.$this->eventId.'/1');
        $rating = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[1]')->text();
        $this->assertEqual($rating, '95.00 - (9.5)* = 85.50          ( )* : 10% late penalty.');
        $groupAve = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[2]')->text();
        $this->assertEqual($groupAve, '90.75');
        $comments[] = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[2]/td')->text();
        $comments[] = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[3]/td')->text();
        $comments[] = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[4]/td')->text();
        sort($comments);
        $this->assertEqual($comments[0], 'A great leader');
        $this->assertEqual($comments[1], 'Does his work efficiently');
        $this->assertEqual($comments[2], 'Very responsible.');    
    }
    
    public function testNoDetails()
    {
        $this->waitForLogoutLogin('root');
        $this->session->open($this->url.'events/edit/'.$this->eventId);
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventEnableDetails0')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Submit"]')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;     
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
        $this->waitForLogoutLogin('redshirt0002');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $rating = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td')->text();
        $this->assertEqual($rating, '110.00 - (11)* = 99.00          ( )* : 10% late penalty.');
        $groupAve = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[2]');
        $this->assertTrue(empty($groupAve));
        $comments = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]');
        $this->assertTrue(empty($comments));
    }
    
    public function testTutorInInstructorView()
    {
        $this->waitForLogoutLogin('root');
        $this->assignToGroup();
        // tutor's panel - does not exist
        $this->session->open($this->url.'evaluations/viewEvaluationResults/'.$this->eventId.'/1/Detail');
        // summary table
        $tutor = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[2]/th[4]');
        $this->assertTrue(empty($tutor));
        // no class for Tutor - since they are reassigned to the group
        $tutor = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[6]/th');
        $this->assertFalse($tutor->attribute('class'));
        $tutor = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm2"]/h3[3]');
        $this->assertFalse($tutor->attribute('class'));
    }
    
    public function testRelease()
    {
        $this->session->open($this->url.'events/edit/'.$this->eventId);
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventAutoRelease0')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'EventEnableDetails1')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Submit"]')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;     
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
        
        // release Ed's grades
        $this->session->open($this->url.'evaluations/viewEvaluationResults/'.$this->eventId.'/1');
        $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[13]/td[2]/button')->click();
        $w->until(
            function($session) {
                $button = $session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[5]/tbody/tr[13]/td[2]/button');
                return ($button->text() == 'Unrelease');
            }
        );
        // mark peer evaluations as reviewed
        $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm"]/input[5]')->click();
        $w->until(
            function($session) {
                $button = $session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="evalForm"]/input[5]');
                return ($button->attribute('value') == ' Mark Peer Evaluations as Not Reviewed');
            }
        );
        // release one of Ed's comment to Alex
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'release5[]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Save Changes"]')->click();
        $check = $this->session->element(PHPWebDriver_WebDriverBy::ID, 'release5[]');
        $this->assertTrue($check->attribute('checked'));
        // check that the evaluation is marked reviewed
        $this->session->open($this->url.'evaluations/view/'.$this->eventId);
        $review = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '//*[@id="ajaxListDiv"]/div/table/tbody/tr[2]/td[6]/div');
        $this->assertEqual($review->text(), 'Reviewed');
        
        // check Ed's results
        $this->waitForLogoutLogin('redshirt0001');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $rating = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[1]');
        $this->assertEqual($rating->text(), '97.50 - (9.75)* = 87.75          ( )* : 10% late penalty.');
        $avg = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[2]');
        $this->assertEqual($avg->text(), '90.75');
        $comment = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[1]/th');
        $this->assertTrue(empty($comment));
        $comment = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[2]/td');
        $this->assertTrue(empty($comment));
        
        // check Alex's results
        $this->waitForLogoutLogin('redshirt0002');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $rating = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[1]');
        $this->assertEqual($rating->text(), 'Not Released');
        $avg = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[2]');
        $this->assertEqual($avg->text(), 'Not Released');
        $comment = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[2]/td');
        $this->assertEqual($comment->text(), 'A very great group member');
        $comment = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[3]/td');
        $this->assertTrue(empty($comment));
                
        // check Matt's results
        $this->waitForLogoutLogin('redshirt0003');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $rating = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[1]');
        $this->assertEqual($rating->text(), 'Not Released');
        $avg = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[2]');
        $this->assertEqual($avg->text(), 'Not Released');
        $comment = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[1]/th');
        $this->assertTrue(empty($comment));
        $comment = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[2]/td');
        $this->assertTrue(empty($comment));
    }
    
    public function testReleaseAllComments()
    {
        $this->waitForLogoutLogin('root');
        $this->session->open($this->url.'evaluations/viewEvaluationResults/'.$this->eventId.'/1');
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Release All"]')->click();
        
        $this->waitForLogoutLogin('redshirt0003');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $rating = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[1]');
        $this->assertEqual($rating->text(), 'Not Released');
        $avg = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[2]');
        $this->assertEqual($avg->text(), 'Not Released');
        $comments[] = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[2]/td')->text();
        $comments[] = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[3]/td')->text();
        $comments[] = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[4]/td')->text();
        sort($comments);
        $this->assertEqual($comments[0], 'A great leader');
        $this->assertEqual($comments[1], 'Does his work efficiently');
        $this->assertEqual($comments[2], 'Very responsible.');   
    }
    
    public function testUnreleaseAllComments()
    {
        $this->waitForLogoutLogin('root');
        $this->session->open($this->url.'evaluations/viewEvaluationResults/'.$this->eventId.'/1');
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Unrelease All"]')->click();
        
        $this->waitForLogoutLogin('redshirt0003');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $rating = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[1]');
        $this->assertEqual($rating->text(), 'Not Released');
        $avg = $this->session->element(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[3]/tbody/tr[2]/td[2]');
        $this->assertEqual($avg->text(), 'Not Released');
        $comment = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[1]/th');
        $this->assertTrue(empty($comment));
        $comment = $this->session->elements(PHPWebDriver_WebDriverBy::XPATH, '/html/body/div[1]/table[4]/tbody/tr[2]/td');
        $this->assertTrue(empty($comment));
    }
    
    public function testDeleteEvent()
    {
        $this->waitForLogoutLogin('root');
        $this->session->open($this->url.'events/delete/'.$this->eventId);
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
        $msg = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']")->text();
        $this->assertEqual($msg, 'The event has been deleted successfully.');
    }
    
    public function handleOffset($id, $offset)
    {
        $handle = $this->session->element(PHPWebDriver_WebDriverBy::ID, $id);
        $this->session->moveto(array('element' => $handle->getID()));
        $this->session->buttondown();
        $this->session->moveto(array('xoffset' => $offset, 'yoffset' => 0));
        $this->session->buttonup();
    }
    
    public function secondStudent()
    {
        $this->waitForLogoutLogin('redshirt0002');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point5')->sendKeys('95');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point7')->sendKeys('105');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment5')->sendKeys('Very hardworking');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment7')->sendKeys('A great leader');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment5')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'submit0')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
    }
    
    public function tutor()
    {
        $this->waitForLogoutLogin('tutor1');
        $this->session->element(PHPWebDriver_WebDriverBy::LINK_TEXT, 'Simple Evaluation')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point5')->sendKeys('100');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point6')->sendKeys('100');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'point7')->sendKeys('100');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment5')->sendKeys('Does his fair share of work');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment6')->sendKeys('Knows what he is doing');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment7')->sendKeys('Does his work efficiently');
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'comment5')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::ID, 'submit0')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
    }
    
    public function removeFromGroup()
    {
        $this->session->open($this->url.'groups/edit/1');
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'select[id="selected_groups"] option[value="5"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'select[id="selected_groups"] option[value="6"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'select[id="selected_groups"] option[value="7"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="<< Remove "]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Edit Group"]')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
    }
    
    public function assignToGroup()
    {
        $this->session->open($this->url.'groups/edit/1');
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'select[id="all_groups"] option[value="35"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Assign >>"]')->click();
        $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, 'input[value="Edit Group"]')->click();
        $w = new PHPWebDriver_WebDriverWait($this->session);
        $session = $this->session;
        $w->until(
            function($session) {
                return count($session->elements(PHPWebDriver_WebDriverBy::CSS_SELECTOR, "div[class='message good-message green']"));
            }
        );
    }
}