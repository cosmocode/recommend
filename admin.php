<?php

class admin_plugin_recommend extends DokuWiki_Admin_Plugin {

    protected $entries;
    protected $logs;
    protected $month;
    protected $assignments;

    public function handle() {
        if (isset($_REQUEST['rec_month']) &&
            preg_match('/^\d{4}-\d{2}$/', $_REQUEST['rec_month'])) {
            $this->month = $_REQUEST['rec_month'];
        } else {
            $this->month = date('Y-m');
        }
        $log = new helper_plugin_recommend_log($this->month);
        $this->entries = $log->getEntries();
        $this->logs = $log->getLogs();

        global $INPUT;
        global $ID;

        /** @var helper_plugin_recommend_assignment $assignmentsHelper */
        $assignmentsHelper = plugin_load('helper', 'recommend_assignment');

        if ($INPUT->str('action') && $INPUT->arr('assignment') && checkSecurityToken()) {
            $assignment = $INPUT->arr('assignment');
                if ($INPUT->str('action') === 'delete') {
                    $ok = $assignmentsHelper->removeAssignment($assignment);
                    if (!$ok) {
                        msg('failed to remove pattern', -1);
                    }
                } elseif ($INPUT->str('action') === 'add') {
                    if ($assignment['pattern'][0] == '/') {
                        if (@preg_match($assignment['pattern'], null) === false) {
                            msg('Invalid regular expression. Pattern not saved', -1);
                        } else {
                            $ok = $assignmentsHelper->addAssignment($assignment);
                            if (!$ok) {
                                msg('failed to add pattern', -1);
                            }
                        }
                    } else {
                        $ok = $assignmentsHelper->addAssignment($assignment);
                        if (!$ok) {
                            msg('failed to add pattern', -1);
                        }
                    }

            }

            send_redirect(wl($ID, array('do' => 'admin', 'page' => 'recommend'), true, '&'));
        }
    }

    public function getTOC() {
        return array_map([$this, 'recommendMakeTOC'], $this->logs);
    }

    public function html() {
        echo $this->locale_xhtml('intro');

        if (!$this->logs) {
            echo 'No recommendations.';
            return;
        }
        if (!$this->entries) {
            echo 'No recommendations were made in ' . $this->month . '.';
            return;
        }

        echo '<h2>' . $this->getLang('headline_snippets') . '</h2>';

        echo $this->getForm();

        echo '<h2>' . $this->getLang('headline_logs') . '</h2>';
        echo '<p>In ' . $this->month . ', your users made the following ' . count($this->entries) . ' recommendations:</p>';
        echo '<ul>';
        foreach (array_reverse($this->entries) as $entry) {
            echo "<li>" . hsc($entry) . "</li>";
        }
        echo '</ul>';
    }

    protected function getForm()
    {
        global $ID;

        $assignments = helper_plugin_recommend_assignment::getAssignments();

        $form = '<form action="' . wl($ID) . '" action="post">';
        $form .= '<input type="hidden" name="do" value="admin" />';
        $form .= '<input type="hidden" name="page" value="recommend" />';
        $form .= '<input type="hidden" name="sectok" value="' . getSecurityToken() . '" />';
        $form .= '<table class="inline">';

        // header
        $form .= '<tr>';
        $form .= '<th>' . $this->getLang('assign_pattern') . '</th>';
        $form .= '<th>' . $this->getLang('assign_user') . '</th>';
        $form .= '<th>' . $this->getLang('assign_message') . '</th>';
        $form .= '<th></th>';
        $form .= '</tr>';

        // existing assignments
        foreach ($assignments as $assignment) {
            $pattern = $assignment['pattern'];
            $user = $assignment['user'];
            $message = $assignment['message'];

            $link = wl(
                $ID,
                [
                    'do' => 'admin',
                    'page' => 'recommend',
                    'action' => 'delete',
                    'sectok' => getSecurityToken(),
                    'assignment[pattern]' => $pattern,
                    'assignment[user]' => $user,
                    'assignment[message]' => $message,
                ]
            );

            $form .= '<tr>';
            $form .= '<td>' . hsc($pattern) . '</td>';
            $form .= '<td>' . hsc($user) . '</td>';
            $form .= '<td>' . nl2br($message) . '</td>';
            $form .= '<td><a class="deletePattern" href="' . $link . '">' . $this->getLang('assign_del') . '</a></td>';
            $form .= '</tr>';
        }

        // new assignment form
        $form .= '<tr>';
        $form .= '<td><input type="text" name="assignment[pattern]" /></td>';
        $form .= '<td><input type="text" name="assignment[user]" /></td>';
        $form .= '<td><textarea cols="30" rows="4" name="assignment[message]"></textarea></td>';
        $form .= '<td><button type="submit" name="action" value="add">' . $this->getLang('assign_add') . '</button></td>';
        $form .= '</tr>';

        $form .= '</table>';
        $form .= '</form>';

        return $form;
    }

    protected function recommendMakeTOC($month) {
        global $ID;
        return html_mktocitem('?do=admin&page=recommend&id=' . $ID . '&rec_month=' . $month, $month, 2, '');
    }
}
