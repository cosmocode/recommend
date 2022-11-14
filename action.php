<?php

class action_plugin_recommend extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler $controller) {
        foreach (array('ACTION_ACT_PREPROCESS', 'AJAX_CALL_UNKNOWN',
                       'TPL_ACT_UNKNOWN') as $event) {
            $controller->register_hook($event, 'BEFORE', $this, 'handle');
        }
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'handleMenu');

    }

    public function handle(Doku_Event $event) {
        if ($event->data !=='recommend') {
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
        echo $this->getForm();
    }

    public function handleMenu(Doku_Event $event)
    {
        if ($event->data['view'] !== 'page') return;

        array_splice($event->data['items'], -1, 0, [new \dokuwiki\plugin\recommend\MenuItem()]);
    }
    /**
     * Returns rendered form
     *
     * @return string
     */
    protected function getForm()
    {
        global $INPUT;

        $id = getID(); // we may run in AJAX context
        if ($id === '') throw new \RuntimeException('No ID given');

        $form = new \dokuwiki\Form\Form([
            'action' => wl($id, ['do' => 'recommend'], false, '&'),
            'id' => 'recommend__plugin',
        ]);
        $form->setHiddenField('id', $id); // we need it for the ajax call

        if ($INPUT->server->has('REMOTE_USER')) {
            global $USERINFO;
            $form->setHiddenField('s_name', $USERINFO['name']);
            $form->setHiddenField('s_email', $USERINFO['mail']);
        } else {
            $form->addTextInput('s_name', $this->getLang('yourname'))->addClass('edit');
            $form->addTextInput('s_email', $this->getLang('youremailaddress'))->addClass('edit');
        }

        $form->addTextInput('r_email', $this->getLang('recipients'))->addClass('edit');
        $form->addTextInput('subject', $this->getLang('subject'))->addClass('edit');
        $form->addTextarea('comment', $this->getLang('message'))->attr('rows', '8')->attr('cols', '10')->addClass('edit');

        /** @var helper_plugin_captcha $captcha */
        $captcha = plugin_load('helper', 'captcha');
        if ($captcha) $form->addHTML($captcha->getHTML());

        $form->addTagOpen('div')->addClass('buttons');
        $form->addButton('submit', $this->getLang('send'))->attr('type', 'submit');
        $form->addButton('reset', $this->getLang('cancel'))->attr('type', 'reset');
        $form->addTagClose('div');

        return $form->toHTML();
    }

    /**
     * Handles form submission and returns error state: error message or else false.
     *
     * @return string|false
     * @throws Exception
     */
    protected function _handle_post()
    {
        if (!checkSecurityToken()) {
            throw new \Exception('Security token did not match');
        }

        global $INPUT;

        $helper = null;
        if (@is_dir(DOKU_PLUGIN.'captcha')) $helper = plugin_load('helper','captcha');
        if (!is_null($helper) && $helper->isEnabled() && !$helper->check()) {
            return 'Wrong captcha';
        }

        /* Validate input. */
        $recipient = $INPUT->str('r_email');
        if (!$recipient || !mail_isvalid($recipient)) {
            return 'Invalid recipient email address submitted';
        }

        if (!isset($_POST['s_email']) || !mail_isvalid($_POST['s_email'])) {
            return 'Invalid sender email address submitted';
        }
        if (!isset($_POST['s_name']) || trim($_POST['s_name']) === '') {
            return 'Invalid sender name submitted';
        }
        $s_name = $_POST['s_name'];
        $sender = $s_name . ' <' . $_POST['s_email'] . '>';

        $id = $INPUT->filter('cleanID')->str('id');
        if ($id === '' || !page_exists($id)) throw new \Exception($this->getLang('err_page'));

        $comment = $INPUT->str('comment');

        /* Prepare mail text. */
        $mailtext = file_get_contents(dirname(__FILE__).'/template.txt');

        global $conf;
        foreach (array('NAME' => $recipient,
                       'PAGE' => $id,
                       'SITE' => $conf['title'],
                       'URL'  => wl($id, '', true),
                       'COMMENT' => $comment,
                       'AUTHOR' => $s_name) as $var => $val) {
            $mailtext = str_replace('@' . $var . '@', $val, $mailtext);
        }
        /* Limit to two empty lines. */
        $mailtext = preg_replace('/\n{4,}/', "\n\n\n", $mailtext);

        /* Perform stuff. */
        $this->sendMail($recipient, $mailtext, $sender);
        /** @var helper_plugin_recommend_log $log */
        $log = new helper_plugin_recommend_log(date('Y-m'));
        $log->writeEntry($id, $sender, $recipient, $comment);

        return false;
    }

    /**
     * @param string $recipient
     * @param string $mailtext
     * @param string $sender
     * @return void
     */
    protected function sendMail($recipient, $mailtext, $sender)
    {
        global $INPUT;

        $mailer = new Mailer();
        $mailer->bcc($recipient);
        $mailer->from($sender);

        $subject = $INPUT->str('subject');
        $mailer->subject($subject);
        $mailer->setBody($mailtext);
        $mailer->send();
    }
}
