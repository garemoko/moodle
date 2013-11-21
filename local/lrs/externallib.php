<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tin Can API protocal as Local Plugin
 *
 * @package    local_lrs
 * @copyright  2012 Jamie Smith
 */
require_once($CFG->libdir . "/externallib.php");

class local_lrs_external extends external_api {

    public static function fetch_statement_parameters () {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, null),
                    'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, null),
                    'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with this state', VALUE_DEFAULT, null),
                    'statementId' => new external_value(PARAM_TEXT, 'Statement ID associated with this state', VALUE_DEFAULT, null),
                )
        );
    }

    public static function fetch_statement ($plugin, $pluginid, $registration, $statementid) {

        $params = array('registration' => $registration, 'statementId' => $statementid);

        $statementobject = local_lrs_fetch_statement($params);
        $params['moodle_mod_id'] = $pluginid;
        $pluginfunctionsuffix = '_tcapi_fetch_statement';
        $pluginfunction = self::get_plugin_function($plugin, $pluginfunctionsuffix);
        if (!empty($pluginfunction)) {
            return call_user_func($pluginfunction, $params, $statementobject);
        }

        return $statementobject->statement;
    }

    public static function fetch_statement_returns () {
        return new external_value(PARAM_TEXT, 'Statement requested if exists');
    }

    public static function store_statement_parameters () {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, null),
                    'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, null),
                    'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with this state', VALUE_DEFAULT, null),
                    'statementId' => new external_value(PARAM_TEXT, 'Statement ID associated with this state', VALUE_DEFAULT, null),
                    'content' => new external_value(PARAM_TEXT, 'Statement to store', VALUE_DEFAULT, ''),
                )
        );
    }

    public static function store_statement ($plugin, $pluginid, $registration, $statementid, $content) {

        $params = array('registration' => $registration, 'statementId' => $statementid, 'content' => $content);

        $statementobject = local_lrs_store_statement($params);
        $params['moodle_mod_id'] = $pluginid;
        $pluginfunctionsuffix = '_tcapi_store_statement';
        $pluginfunction = self::get_plugin_function($plugin, $pluginfunctionsuffix);
        if (!empty($pluginfunction)) {
            return call_user_func($pluginfunction, $params, $statementobject);
        }

        return $statementobject->statementId;
    }

    public static function store_statement_returns () {
        return new external_value(PARAM_TEXT, 'Statement ID of stored statement');
    }

    public static function store_activity_state_parameters () {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, null),
                    'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, null),
                    'content' => new external_value(PARAM_TEXT, 'State document to store', VALUE_DEFAULT, ''),
                    'activityId' => new external_value(PARAM_TEXT, 'Activity ID associated with this state'),
                    'actor' => new external_value(PARAM_RAW, 'Actor associated with this state'),
                    'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with this state', VALUE_DEFAULT, null),
                    'stateId' => new external_value(PARAM_TEXT, 'id for the state, within the given context'),
                )
        );
    }

    public static function store_activity_state($plugin, $pluginid, $content, $activityid, $actor, $registration, $stateid) {

        $params = array('content' => $content, 'activityId' => $activityid, 'actor' => $actor, 'registration' => $registration, 'stateId' => $stateid);
        $params['actor'] = json_decode($actor);

        $response = local_lrs_store_activity_state($params);
        $params['moodle_mod_id'] = $pluginid;
        $pluginfunctionsuffix = '_tcapi_store_activity_state';
        $pluginfunction = self::get_plugin_function($plugin, $pluginfunctionsuffix);
        if (!empty($pluginfunction)) {
            return call_user_func($pluginfunction, $params, $statementobject);
        }

        return $response;
    }

    public static function store_activity_state_returns() {
        return new external_value(PARAM_TEXT, 'Success or Failure');
    }

    public static function fetch_activity_state_parameters() {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, null),
                    'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, null),
                    'activityId' => new external_value(PARAM_TEXT, 'Activity ID associated with state(s)'),
                    'actor' => new external_value(PARAM_RAW, 'Actor associated with state(s)'),
                    'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with state(s)', VALUE_DEFAULT, null),
                    'stateId' => new external_value(PARAM_TEXT, 'id for the state, within the given context', VALUE_DEFAULT, null),
                    'since' => new external_value(PARAM_TEXT, 'time benchmark, if any', VALUE_DEFAULT, null),
                )
        );
    }

    public static function fetch_activity_state($plugin, $pluginid, $activityid, $actor, $registration, $stateid, $since) {

        $params = array('activityId' => $activityid, 'actor' => $actor, 'registration' => $registration, 'stateId' => $stateid, 'since' => $since);
        $params['actor'] = json_decode($actor);

        $response = local_lrs_fetch_activity_state($params);
        $params['moodle_mod_id'] = $pluginid;
        $pluginfunctionsuffix = '_tcapi_fetch_activity_state';
        $pluginfunction = self::get_plugin_function($plugin, $pluginfunctionsuffix);
        if (!empty($pluginfunction)) {
            return call_user_func($pluginfunction, $params, $statementobject);
        }

        return $response;
    }

    public static function fetch_activity_state_returns() {
        return new external_value(PARAM_TEXT, 'Activity state value');
    }

    public static function delete_activity_state_parameters() {
        return new external_function_parameters(
                array('moodle_mod' => new external_value(PARAM_TEXT, 'Moodle module name, if any', VALUE_DEFAULT, null),
                    'moodle_mod_id' => new external_value(PARAM_TEXT, 'Moodle module id, if any', VALUE_DEFAULT, null),
                    'activityId' => new external_value(PARAM_TEXT, 'Activity ID associated with state(s)'),
                    'actor' => new external_value(PARAM_RAW, 'Actor associated with state(s)'),
                    'registration' => new external_value(PARAM_TEXT, 'Registration ID associated with state(s)', VALUE_DEFAULT, null),
                )
        );
    }

    public static function delete_activity_state($plugin, $pluginid, $activityid, $actor, $registration) {

        $params = array('activityId' => $activityid, 'actor' => $actor, 'registration' => $registration);
        $params['actor'] = json_decode($actor);
        $pluginfunctionsuffix = '_tcapi_fetch_activity_state';
        $pluginfunction = self::get_plugin_function($plugin, $pluginfunctionsuffix);

        if (!empty($pluginfunction)) {
            $params['moodle_mod_id'] = $pluginid;
            $params = $pluginfunction($params);
            unset($params['moodle_mod_id']);
        }

        if (isset($params['response'])) {
            return $params['response'];
        } else {
            return local_lrs_delete_activity_state($params);
        }
    }

    public static function delete_activity_state_returns() {
        return new external_value(PARAM_TEXT, 'Empty string');
    }

    // Helper function to check function exists and include lib file if required.
    private function get_plugin_function($plugin, $pluginfunctionsuffix) {
        global $CFG;
        if (!is_null($plugin) && file_exists($CFG->dirroot.'/mod/'.$plugin.'/tcapilib.php')) {
            require_once($CFG->dirroot.'/mod/'.$plugin.'/tcapilib.php');
            if (function_exists($plugin.$pluginfunctionsuffix)) {
                return $plugin.$pluginfunctionsuffix;
            } else {
                return '';
            }
        }
    }
}