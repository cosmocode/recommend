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

        global $INPUT;

        // early output to trigger display msgs even via AJAX.
        echo ' ';
        tpl_flush();
        if ($INPUT->server->str('REQUEST_METHOD') === 'POST') {
            try {
                $this->handlePost();
                if ($event->name === 'AJAX_CALL_UNKNOWN') {
                    $this->ajaxSuccess(); // To signal success to AJAX.
                } else {
                    msg($this->getLang('thanks'), 1);
                }
                return; // we're done here
            } catch (\Exception $e) {
                msg($e->getMessage(), -1);
            }
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
            'id' => 'plugin__recommend',
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
        $form->addTextarea('comment', $this->getLang('message'))->attr('rows', '8')->attr('cols', '40')->addClass('edit');

        /** @var helper_plugin_captcha $captcha */
        $captcha = plugin_load('helper', 'captcha');
        if ($captcha) $form->addHTML($captcha->getHTML());

        $form->addTagOpen('div')->addClass('buttons');
        $form->addButton('submit', $this->getLang('send'))->attr('type', 'submit');
        $form->addTagClose('div');

        return $form->toHTML();
    }

    /**
     * Handles form submission
     *
     * @throws Exception
     */
    protected function handlePost()
    {
        global $INPUT;

        if (!checkSecurityToken()) {
            throw new \Exception('Security token did not match');
        }

        // Captcha plugin
        $helper = null;
        if (@is_dir(DOKU_PLUGIN . 'captcha')) $helper = plugin_load('helper','captcha');
        if (!is_null($helper) && $helper->isEnabled() && !$helper->check()) {
            throw new \Exception($this->getLang('err_captcha'));
        }

        /* Validate input. */
        $recipient = $INPUT->str('r_email');
        if (!$recipient || !mail_isvalid($recipient)) {
            throw new \Exception($this->getLang('err_recipient'));
        }

        if (!isset($_POST['s_email']) || !mail_isvalid($_POST['s_email'])) {
            throw new \Exception($this->getLang('err_sendermail'));
        }
        if (!isset($_POST['s_name']) || trim($_POST['s_name']) === '') {
            throw new \Exception($this->getLang('err_sendername'));
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

        $this->sendMail($recipient, $mailtext, $sender);

        /** @var helper_plugin_recommend_log $log */
        $log = new helper_plugin_recommend_log(date('Y-m'));
        $log->writeEntry($id, $sender, $recipient, $comment);
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

    /**
     * show success message in ajax mode
     */
    protected function ajaxSuccess()
    {
        echo '<form id="plugin__recommend" accept-charset="utf-8" method="post" action="?do=recommend">';
        echo '<div class="no">';
        echo '<span class="ui-icon ui-icon-circle-check" style="float: left; margin: 0 7px 50px 0;"></span>';
        echo '<p>' . $this->getLang('done') . '</p>';
        echo '<button type="reset" class="button">' . $this->getLang('close') . '</button>';
        echo '</div>';
        echo '</form>';
    }
}
