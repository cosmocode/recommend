<?php
require_once DOKU_PLUGIN . 'action.php';
require_once DOKU_INC . 'inc/form.php';
require_once dirname(__FILE__) . '/log.php';

class action_plugin_recommend extends DokuWiki_Action_Plugin {
    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function register(Doku_Event_Handler $controller) {
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
            echo 'Thanks for recommending our site.';
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
        $form->startFieldset('Recommend page “' . hsc($id). '”');
        if (isset($_SERVER['REMOTE_USER'])) {
            global $USERINFO;
            $form->addHidden('s_name', $USERINFO['name']);
            $form->addHidden('s_email', $USERINFO['mail']);
        } else {
            $form->addElement(form_makeTextField('s_name', $s_name, 'Your name'));
            $form->addElement(form_makeTextField('s_email', $s_email,
                                                 'Your email address'));
        }
        $form->addElement(form_makeTextField('r_name', $r_name, 'Recipient name'));
        $form->addElement(form_makeTextField('r_email', $r_email,
                                             'Recipient email address'));
        $form->addElement('<label><span>'.hsc('Additional comment').'</span>'.
                          '<textarea name="comment" rows="3" cols="10" ' .
                          'class="edit">' . $comment . '</textarea></label>');
        $helper = null;
        if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if(!is_null($helper) && $helper->isEnabled()){
            $form->addElement($helper->getHTML());
        }

        $form->addElement(form_makeButton('submit', '', 'Send recommendation'));
        $form->addElement(form_makeButton('submit', 'cancel', 'Cancel'));
        $form->printForm();
    }

    function _handle_post() {
        $helper = null;
        if(@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if(!is_null($helper) && $helper->isEnabled() && !$helper->check()) {
            return 'Wrong captcha';
        }

        /* Validate input. */
        if (!isset($_POST['r_email']) || !mail_isvalid($_POST['r_email'])) {
            return 'Invalid recipient email address submitted';
        }
        if (!isset($_POST['r_name']) || trim($_POST['r_name']) === '') {
            return 'Invalid recipient name submitted';
        }
        $r_name    = $_POST['r_name'];
        $recipient = $r_name . ' <' . $_POST['r_email'] . '>';

        if (!isset($_POST['s_email']) || !mail_isvalid($_POST['s_email'])) {
            return 'Invalid sender email address submitted';
        }
        if (!isset($_POST['s_name']) || trim($_POST['s_name']) === '') {
            return 'Invalid sender name submitted';
        }
        $s_name = $_POST['s_name'];
        $sender = $s_name . ' <' . $_POST['s_email'] . '>';

        if (!isset($_POST['id']) || !page_exists($_POST['id'])) {
            return 'Invalid page submitted';
        }
        $page = $_POST['id'];

        $comment = isset($_POST['comment']) ? $_POST['comment'] : null;

        /* Prepare mail text. */
        $mailtext = file_get_contents(dirname(__FILE__).'/template.txt');

        global $conf;
        global $USERINFO;
        foreach (array('NAME' => $r_name,
                       'PAGE' => $page,
                       'SITE' => $conf['title'],
                       'URL'  => wl($page, '', true),
                       'COMMENT' => $comment,
                       'AUTHOR' => $s_name) as $var => $val) {
            $mailtext = str_replace('@' . $var . '@', $val, $mailtext);
        }
        /* Limit to two empty lines. */
        $mailtext = preg_replace('/\n{4,}/', "\n\n\n", $mailtext);

        /* Perform stuff. */
        mail_send($recipient, 'Page recommendation', $mailtext, $sender);
        $log = new Plugin_Recommend_Log(date('Y-m'));
        $log->writeEntry($page, $sender, $recipient, $comment);
        return false;
    }
}
