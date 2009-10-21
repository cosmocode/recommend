<?php
require_once DOKU_PLUGIN . 'action.php';
require_once DOKU_INC . 'inc/form.php';
require_once dirname(__FILE__) . '/log.php';

class action_plugin_recommend extends DokuWiki_Action_Plugin {
    function getInfo(){
        return confToHash(dirname(__FILE__).'/INFO.txt');
    }

    function register(&$controller) {
        foreach (array('ACTION_ACT_PREPROCESS', 'AJAX_CALL_UNKNOWN',
                       'TPL_ACT_UNKNOWN') as $event) {
            $controller->register_hook($event, 'BEFORE', $this, '_handle');
        }
    }

    function _handle(&$event, $param) {
        if (!in_array($event->data, array('recommend', 'plugin_recommend')) ||
            !isset($_SERVER['REMOTE_USER'])) {
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
        $name    = isset($_REQUEST['r_name']) ? $_REQUEST['r_name'] : '';
        $mail    = isset($_REQUEST['r_email']) ? $_REQUEST['r_email'] : '';
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
        $form->addElement(form_makeTextField('r_name', $name, 'Recipient name'));
        $form->addElement(form_makeTextField('r_email', $mail,
                                             'Recipient email address'));
        $form->addElement('<label><span>'.hsc('Additional comment').'</span>'.
                          '<textarea name="comment" rows="3" cols="10" ' .
                          'class="edit">' . $comment . '</textarea></label>');
        $form->addElement(form_makeButton('submit', '', 'Send recommendation'));
        $form->addElement(form_makeButton('submit', 'cancel', 'Cancel'));
        $form->printForm();
    }

    function _handle_post() {
        /* Validate input. */
        if (!isset($_POST['r_email']) || !mail_isvalid($_POST['r_email'])) {
            return 'Invalid email address submitted';
        }
        $email = $_POST['r_email'];

        if (!isset($_POST['id']) || !page_exists($_POST['id'])) {
            return 'Invalid page submitted';
        }
        $page = $_POST['id'];

        if (!isset($_POST['r_name']) || trim($_POST['r_name']) === '') {
            return 'Invalid name submitted';
        }
        $name = $_POST['r_name'];

        $comment = isset($_POST['comment']) ? $_POST['comment'] : null;

        /* Prepare mail text. */
        $mailtext = file_get_contents(dirname(__FILE__).'/template.txt');

        global $conf;
        global $USERINFO;
        foreach (array('NAME' => $name,
                       'PAGE' => $page,
                       'SITE' => $conf['title'],
                       'URL'  => wl($page, '', true),
                       'COMMENT' => $comment,
                       'AUTHOR' => $USERINFO['name']) as $var => $val) {
            $mailtext = str_replace('@' . $var . '@', $val, $mailtext);
        }
        /* Limit to two empty lines. */
        $mailtext = preg_replace('/\n{4,}/', "\n\n\n", $mailtext);

        /* Perform stuff. */
        mail_send($email, 'Page recommendation', $mailtext);
        $log = new Plugin_Recommend_Log(date('Y-m'));
        $log->writeEntry($page, $USERINFO['mail'], $email);
        return false;
    }
}
