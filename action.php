<?php
require_once DOKU_PLUGIN . 'action.php';
require_once DOKU_INC . 'inc/form.php';
require_once dirname(__FILE__) . '/log.php';

class action_plugin_recommend extends DokuWiki_Action_Plugin {
    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function register(&$controller) {
        foreach (array('ACTION_ACT_PREPROCESS', 'AJAX_CALL_UNKNOWN',
                       'TPL_ACT_UNKNOWN') as $event) {
            $controller->register_hook($event, 'BEFORE', $this, '_handle');
        }
    }

    function _handle(&$event, $param) {
        if (!in_array($event->data, array('recommend', 'plugin_recommend'))) {
            return;
        }

        $event->preventDefault();

        if ($event->name === 'ACTION_ACT_PREPROCESS') {
            return;
        }

        $event->stopPropagation();

        if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            isset($_POST['sectok']) &&
            !($err = $this->_handle_post())) {
            if ($event->name === 'AJAX_CALL_UNKNOWN') {
                /* To signal success to AJAX. */
                header('HTTP/1.1 204 No Content');
                return;
            }
	    $thanks_msg=$this->getLang('thanks_msg');
            echo $thanks_msg;
            return;
        }
        /* To display msgs even via AJAX. */
        echo ' ';
        if (isset($err)) {
            msg($err, -1);
        }
        $this->_show_form();
    }

    function _show_form() {
	$site_label=$this->getLang('site');
	$r_name_label=$this->getLang('recipient_name');
	$r_email_label=$this->getLang('recipient_mail');
	$s_name_label=$this->getLang('sender_name');
	$s_email_label=$this->getLang('sender_mail');
	$comment_label=$this->getLang('comment');
	$send_btn_label=$this->getLang('send_btn');
	$cancel_btn_label=$this->getLang('cancel_btn'); 

        $r_name  = isset($_REQUEST['r_name']) ? $_REQUEST['r_name'] : '';
        $r_email = isset($_REQUEST['r_email']) ? $_REQUEST['r_email'] : '';
        $s_name  = isset($_REQUEST['s_name']) ? $_REQUEST['s_name'] : '';
        $s_email = isset($_REQUEST['s_email']) ? $_REQUEST['s_email'] : '';
        $comment = isset($_REQUEST['comment']) ? $_REQUEST['r_comment'] : '';
        if (isset($_REQUEST['id'])) {
            $id  = $_REQUEST['id'];
        } else {
            global $ID;
            if (!isset($ID)) {
                msg('Unknown page', -1);
                return;
            }
            $id  = $ID;
        }
        $form = new Doku_Form('recommend_plugin', '?do=recommend');
        $form->addHidden('id', $id);
        $form->startFieldset($site_label.' “' . hsc($id). '”');
        if (isset($_SERVER['REMOTE_USER'])) {
            global $USERINFO;
            $form->addHidden('s_name', $USERINFO['name']);
            $form->addHidden('s_email', $USERINFO['mail']);
        } else {
            $form->addElement(form_makeTextField('s_name', $s_name, $s_name_label));
            $form->addElement(form_makeTextField('s_email', $s_email, $s_email_label));
        }
        $form->addElement(form_makeTextField('r_name', $r_name, $r_name_label));
        $form->addElement(form_makeTextField('r_email', $r_email, $r_email_label));
        $form->addElement('<label><span>'.hsc($comment_label).'</span>'.
                          '<textarea name="comment" rows="3" cols="10" ' .
                          'class="edit">' . $comment . '</textarea></label>');
        $helper = null;
        if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if(!is_null($helper) && $helper->isEnabled()){
            $form->addElement($helper->getHTML());
        }

        $form->addElement(form_makeButton('submit', '', $send_btn_label));
        $form->addElement(form_makeButton('submit', 'cancel', $cancel_btn_label));
        $form->printForm();
    }

    function _handle_post() {
        $helper = null;
	$captcha_errmsg=$this->getLang('captcha_errmsg');
        if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if(!is_null($helper) && $helper->isEnabled() && !$helper->check()) {
            return $captcha_errmsg;
        }

        /* Validate input. */
	$captcha_errmsg=$this->getLang('captcha_errmsg');
	$r_mail_errmsg=$this->getLang('r_mail_errmsg');
	$r_name_errmsg=$this->getLang('r_name_errmsg');
	$s_mail_errmsg=$this->getLang('s_mail_errmsg');
	$s_name_errmsg=$this->getLang('s_name_errmsg');	
	$page_errmsg=$this->getLang('page_errmsg');	
        if (!isset($_POST['r_email']) || !mail_isvalid($_POST['r_email'])) {
            return $r_mail_errmsg;
        }
        if (!isset($_POST['r_name']) || trim($_POST['r_name']) === '') {
            return $r_name_errmsg;
        }
        $r_name    = $_POST['r_name'];
        $recipient = $r_name . ' <' . $_POST['r_email'] . '>';

        if (!isset($_POST['s_email']) || !mail_isvalid($_POST['s_email'])) {
            return $s_mail_errmsg;
        }
        if (!isset($_POST['s_name']) || trim($_POST['s_name']) === '') {
            return $s_name_errmsg;
        }
        $s_name = $_POST['s_name'];
        $sender = $s_name . ' <' . $_POST['s_email'] . '>';

        if (!isset($_POST['id']) || !page_exists($_POST['id'])) {
            return $page_errmsg;
        }
        $page = $_POST['id'];

        $comment = isset($_POST['comment']) ? $_POST['comment'] : null;

        /* Prepare mail text. */
        $mailtext = $this->locale_xhtml('template'); 

        global $conf;
        global $USERINFO;
        foreach (array('NAME' => $r_name,
                       'PAGE' => $page,
                       'SITE' => $conf['title'],
                       'ADDRESS'  => wl($page, '', true),
                       'COMMENT' => $comment,
                       'AUTHOR' => $s_name) as $var => $val) {
            $mailtext = str_replace('@' . $var . '@', $val, $mailtext);
        }
        /* Limit to two empty lines. */
	$mailtext = str_replace('<p>', '', $mailtext);        
	$mailtext = str_replace('</p>', '', $mailtext);
	$mailtext = preg_replace('/\n{4,}/', "\n\n\n", $mailtext);

        /* Perform stuff. */
	$subject_title=$this->getLang('subject_title');	
        mail_send($recipient, $subject_title, $mailtext, $sender);
        $log = new Plugin_Recommend_Log(date('Y-m'));
        $log->writeEntry($page, $sender, $recipient, $comment);
        return false;
    }
}
