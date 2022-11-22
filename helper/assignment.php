<?php

class helper_plugin_recommend_assignment
{
    public static $confFile = DOKU_CONF . 'recommend_snippets.json';

    public static function getAssignments()
    {
        return @jsonToArray(self::$confFile);
    }

    public function addAssignment($assignment)
    {
        $assignments = self::getAssignments();
        $assignments[] = $assignment;
        return (bool)file_put_contents(self::$confFile, json_encode($assignments, JSON_PRETTY_PRINT));
    }

    public function removeAssignment($assignment)
    {
        if (empty($assignment['pattern'])) {
            return false;
        }

        $assignments = self::getAssignments();
        $remaining = array_filter($assignments, function($data) use ($assignment) {
            return !(
                $assignment['pattern'] === $data['pattern']
                && $assignment['user'] === $data['user']
                && $assignment['message'] === $data['message']
            );
        });

        if (count($remaining) < count($assignments)) {
            return (bool)file_put_contents(self::$confFile, json_encode($remaining, JSON_PRETTY_PRINT));
        }
        return false;
    }

    /**
     * Returns the last matching template.
     *
     * @return array
     */
    public function loadMatchingTemplate()
    {
        $assignments = self::getAssignments();
        $hlp = $this;
        $matches = array_filter($assignments, function ($data) use ($hlp) {
            return $hlp::matchPagePattern($data['pattern']);
        });

        $template = array_pop($matches);
        return $template;
    }

    /**
     * Check if the given pattern matches the given page
     *
     * @param string $pattern the pattern to check against
     * @param string|null $page the cleaned pageid to check
     * @param string|null $pns optimization, the colon wrapped namespace of the page, set null for automatic
     * @return bool
     * @author Andreas Gohr
     *
     */
    public static function matchPagePattern($pattern, $page = null, $pns = null)
    {
        if (is_null($page)) $page = getID();

        if (trim($pattern, ':') == '**') {
            return true;
        } // match all

        // regex patterns
        if ($pattern[0] == '/') {
            return (bool) preg_match($pattern, ":$page");
        }

        if (is_null($pns)) {
            $pns = ':' . getNS($page) . ':';
        }

        $ans = ':' . cleanID($pattern) . ':';
        if (substr($pattern, -2) == '**') {
            // upper namespaces match
            if (strpos($pns, $ans) === 0) {
                return true;
            }
        } elseif (substr($pattern, -1) == '*') {
            // namespaces match exact
            if ($ans == $pns) {
                return true;
            }
        } else {
            // exact match
            if (cleanID($pattern) == $page) {
                return true;
            }
        }

        return false;
    }
}
