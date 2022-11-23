<?php

use dokuwiki\Extension\AuthPlugin;

/**
 * Mail helper
 */
class helper_plugin_recommend_mail extends DokuWiki_Plugin
{
    /**
     * @param string $recipient
     * @param string $mailtext
     * @param string $sender
     * @return void
     */
    public function sendMail($recipient, $mailtext, $sender)
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
     * Processes recipients from input and returns an array of emails
     * with user groups resolved to individual users
     *
     * @param string $recipients
     * @return array
     * @throws Exception
     */
    public function resolveRecipients($recipients)
    {
        $resolved = [];

        $recipients = explode(',', $recipients);

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);

            if ($recipient[0] === '@') {
                $this->resolveGroup($resolved, $recipient);
            } elseif (strpos($recipient, '@') === false) {
                $this->resolveUser($resolved, $recipient);
            } else {
                if (!mail_isvalid($recipient)) {
                    throw new \Exception($this->getLang('err_recipient'));
                }
                $resolved[] = $recipient;
            }
        }
        return $resolved;
    }

    /**
     * @param array $resolved
     * @param string $recipient
     * @return void
     * @throws Exception
     */
    protected function resolveGroup(&$resolved, $recipient)
    {
        /** @var AuthPlugin $auth */
        global $auth;
        if (!$auth->canDo('getUsers')) {
            throw new \Exception('');
        }

        $users = $auth->retrieveUsers(0, 0, ['grps' => substr($recipient, 1)]);
        foreach ($users as $user) {
            $resolved[] = $user['mail'];
        }
    }

    /**
     * @param array $resolved
     * @param string $recipient
     * @return void
     */
    protected function resolveUser(&$resolved, $recipient)
    {
        /** @var AuthPlugin $auth */
        global $auth;
        $user = $auth->getUserData($recipient);
        if ($user) $resolved[] = $user['mail'];
    }
}
