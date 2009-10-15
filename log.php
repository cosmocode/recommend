<?php
class Plugin_Recommend_Log {
    var $path;

    function Plugin_Recommend_Log($month) {
        $this->path = DOKU_INC . 'data/cache/recommend';
        if (!file_exists($this->path)) {
            mkdir($this->path);
        }
        $this->path .= '/' . $month . '.log';
    }

    function getLogs() {
        return array_map('recommend_strip_extension', glob(DOKU_INC . 'data/cache/recommend/*.log'));
    }

    function getEntries() {
        return @file($this->path);
    }

    function writeEntry($page, $sender, $receiver) {
        file_put_contents($this->path, date('r') . ': ' .
                          "“${sender}” recommended “${page}” to “${receiver}”.\n",
                          FILE_APPEND);
    }
}

function recommend_strip_extension($str) {
    return substr(basename($str), 0, -4);
}
