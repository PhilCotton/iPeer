<?php
/**
 * Custom AppController for iPeer
 *
 * @author  Pan Luo <pan.luo@ubc.ca>
 * @license MIT http://www.opensource.org/licenses/MIT
 * @version GIT:
 *
 */
ini_set('auto_detect_line_endings', true);

uses('sanitize');
App::import('Lib', 'toolkit');
App::import('Lib', 'breadcrumb');

/**
 * AppController the base controller
 *
 * @uses Controller
 * @package App
 */
class AppController extends Controller
{
    public $startpage  = 'pages';
    public $uses       = array('User', 'SysParameter', 'OauthToken');
    public $components = array('Session', 'Output',
        'userPersonalize', 'framework', 'Guard.Guard', 'Acl',
        'AccessControl', 'TemplateEmail');
    public $helpers    = array('Session', 'Html', 'Js', 'Vocabulary');
    public $access     = array ();
    public $actionList = array ();
    public $breadcrumb;

    /**
     * if this request has session transfer data
     */
    public $isSessionTransfer = false;

    /**
     * session transfer data
     */
    public $sessionTransferData = array();


    /**
     * __construct constructor function
     *
     * @access protected
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * beforeFilter function called before filter
     *
     * @access public
     * @return void
     */
    public function beforeFilter()
    {
        $this->Auth->autoRedirect = false;
        // backward compatible with original ipeer hash  method
        Security::setHash('md5');
        Configure::write('Security.salt', '');

        // set default language for now
        Configure::write('Config.language', 'eng');

        // store user in the singleton for global access
        User::store($this->Auth->user());

        $this->breadcrumb = Breadcrumb::create();

        if ($this->Auth->isAuthorized()) {
            // check if the user has permission to access the controller/action
            $permission = array_filter(array('controllers', ucwords($this->params['plugin']), ucwords($this->params['controller']), $this->params['action']));
            if (!User::hasPermission(join('/', $permission))) {
                $this->Session->setFlash('Error: You do not have permission to access the page.');
                $this->redirect('/home');
                return;
            }

            $this->_checkDatabaseVersion();
        }

        parent::beforeFilter();
    }

    /**
     * checkDatabaseVersion
     *
     * @access public
     * @return void
     */
    public function _checkDatabaseVersion()
    {
        $dbv = $this->SysParameter->getDatabaseVersion();

        if (User::hasPermission('controllers/upgrade') &&
            Configure::read('DATABASE_VERSION') > $dbv) {
            $flashMessage  = "Your database version is older than the current version. ";
            $flashMessage .= "Please do the <a href=" . $this->webroot ."upgrade" .">upgrade</a>.";
            $this->Session->setFlash($flashMessage);
        }
    }


    /**
     * extractModel extract the model
     *
     * @param mixed $model model name
     * @param mixed $array model of array
     * @param mixed $field field
     *
     * @access public
     * @return void
     */
    public function _extractModel($model,$array,$field)
    {
        $return = array();
        foreach ($array as $row) {
            array_push($return, $row[$model][$field]);
        }

        return $return;
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
    protected function _sendEmail($content, $subject, $from, $toAddress, $templateName = 'default', $ccAddress = array(), $bcc= array())
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

    /**
     * beforeLogin callback, called every time in auth compoment
     *
     * @access public
     * @return void
     */
    public function _beforeLogin()
    {
        // if we have a session transfered to us
        if ($this->_hasSessionTransferData()) {
            if ($this->_authenticateWithSessionTransferData()) {
                if (method_exists($this, '_afterLogin')) {
                    $this->_afterLogin();
                }
                return true;
            } else {
                $this->Session->setFlash($this->Auth->loginError, $this->Auth->flashElement, array(), 'auth');
                return false;
            }
        }
        $this->set('loginHeader', $this->SysParameter->get('display.login.header'));
        $this->set('loginFooter', $this->SysParameter->get('display.login.footer'));
    }

    /**
     * afterLogin callback, called when logging in successfully
     *
     * @access public
     * @return void
     */
    public function _afterLogin()
    {
        if ($this->Auth->isAuthorized()) {
            User::getInstance($this->Auth->user());
            // after login stuff
            $this->User->loadRoles(User::get('id'));
            $this->AccessControl->loadPermissions();
            //TODO logging!
        }

        $redirect = $this->Auth->redirect();
        if (isset($this->params['url']['redirect'])) {
            $redirect = $this->params['url']['redirect'];
        }

        $this->redirect($redirect);
    }

    /**
     * afterLogout callback, clean up after logout
     *
     * @access public
     * @return void
     */
    function _afterLogout()
    {
        $this->Session->destroy();
    }

    /**
     * hasSessionTransferData
     *
     * @access public
     * @return boolean
     */
    function _hasSessionTransferData()
    {
        $params = $this->params['url'];
        if (isset($params['username']) && isset($params['timestamp']) && isset($params['token']) && isset($params['signature'])) {
            $this->isSessionTransfer = true;
            $this->sessionTransferData = $params;

            return true;
        }

        return false;
    }

    /**
     * authenticateWithSessionTransferData
     *
     * @access public
     * @return boolean
     */
    function _authenticateWithSessionTransferData()
    {
        $message = $this->sessionTransferData['username'].$this->sessionTransferData['timestamp'].$this->sessionTransferData['token'];
        $secret = $this->OauthToken->getTokenSecret($this->sessionTransferData['token']);
        $signature = base64_encode(hash_hmac('sha1', $message, $secret, true));
        if ($signature == $this->sessionTransferData['signature']) {
            $user = $this->User->findByUsername($this->sessionTransferData['username']);
            $this->Session->write($this->Auth->sessionKey, $user['User']);
            return true;
        }

        $this->log('Invalid signature! Expect '.$signature.', Got '.$this->sessionTransferData['signature']);
        return false;
    }
}
