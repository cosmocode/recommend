<?php

class helper_plugin_recommend_log
{
    protected $path;

    /**
     * @param $month
     */
    public function __construct($month)
    {
        $this->path = DOKU_INC . 'data/cache/recommend';
        if (!file_exists($this->path)) {
            mkdir($this->path);
        }
        $this->path .= '/' . $month . '.log';
    }

    public function getLogs()
    {
        return array_map([$this, 'recommendStripExtension'], glob(DOKU_INC . 'data/cache/recommend/*.log'));
    }

    public function getEntries()
    {
        return @file($this->path);
    }

    public function writeEntry($page, $sender, $receiver, $comment)
    {
        $comment = str_replace(["\n", '"'], ['', '\''], $comment);
        $logfile = fopen($this->path, 'a');
        fwrite($logfile, date('r') . ': ' .
                         "“${sender}” recommended “${page}” to " .
                         "“${receiver}” with comment “${comment}”.\n");
        fclose($logfile);
    }


    protected function recommendStripExtension($str)
    {
        return substr(basename($str), 0, -4);
    }
}
