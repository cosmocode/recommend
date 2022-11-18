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
}
