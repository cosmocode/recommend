<?php
require_once DOKU_PLUGIN . 'admin.php';

class admin_plugin_recommend extends DokuWiki_Admin_Plugin {

    protected $entries;
    protected $logs;
    protected $month;

    public function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

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
    }

    public function getTOC() {
        return array_map([$this, 'recommend_make_toc'], $this->logs);
    }

    public function html() {
        if (!$this->logs) {
            echo 'No recommendations.';
            return;
        }
        if (!$this->entries) {
            echo 'No recommendations were made in ' . $this->month . '.';
            return;
        }
        echo '<p>In ' . $this->month . ', your users made the following ' . count($this->entries) . ' recommendations:</p>';
        echo '<ul>';
        foreach(array_reverse($this->entries) as $entry) {
            echo "<li>" . hsc($entry) . "</li>";
        }
        echo '</ul>';
    }

    public function recommend_make_toc($month) {
        global $ID;
        return html_mktocitem('?do=admin&page=recommend&id=' . $ID . '&rec_month=' . $month, $month, 1, '');
    }
}
