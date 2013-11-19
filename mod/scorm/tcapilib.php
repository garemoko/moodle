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
 * Tincan functions used by SCORM module.
 * @package   scorm
 * @author    Jamie Smith <jamie.g.smith@gmail.com>
 * @copyright 2013 Jamie Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// Verify this site is configured to allow Tin can
function scorm_tincan_enabled() {
    // First check web services are enabled.
    if (empty($CFG->enablewebservices)) {
        return false;
    }
    // Check REST protocol enabled.
    if (!webservice_protocol_is_enabled('rest')) {
        return false;
    }

    // TODO: check permissions of users to make sure they can submit content for tin can packages.
    /*
    $role = $DB->get_record('role', array('archetype' => 'user'), 'id', MUST_EXIST);
    if (isset($role->id)) {
        require_once($CFG->dirroot.'/lib/accesslib.php');
        role_change_permission($role->id, context_system::instance(), 'moodle/webservice:createtoken', CAP_ALLOW);
        role_change_permission($role->id, context_system::instance(), 'webservice/rest:use', CAP_ALLOW);
        role_change_permission($role->id, context_system::instance(), 'local/lrs:use', CAP_ALLOW);
    }
    */
}

/*
 * Objectives of this library is to provide functions that handle incoming
 * TCAPI requests from the local/tcapi webservice.
 * Incoming statements will be stored by sco->id and user->id in the tracks table.
 * Activity/state requests will be stored/retrieved by using the associated tracks table entry
 * by sco->id, user->id, attempt, element('cmi.suspend_data') as value.
 * cmi.core.total_time will be aggregated based on time lapsed between state requests but
 * may be overridden by a statement result that specifies 'duration'.
 * TODO: Decide the most effective and efficient way to determin cmi.core.total.time since not all content will report state data and results the same.
 * Maybe using state requests and compare to duration taking the largest number of the two??
 *
 * All modules that participate in using the TCAPI will be able to capture the
 * incoming requests and override the normal api protocol for storage and retrieval
 * of statements and activity/states.
 *
 */

function scorm_tcapi_fetch_activity_state($params, $response) {
    global $CFG, $DB, $USER;
    if (isset($params['actor']) && isset($params['actor']->moodle_user)) {
        $userid = $params['actor']->moodle_user;
    } else {
        $userid = $USER->id;
    }
    if (isset($params['moodle_mod_id'])) {
        $scoid = $params['moodle_mod_id'];
    } else {
        throw new invalid_parameter_exception('Module id not provided.');
    }
    require_once($CFG->dirroot.'/mod/scorm/locallib.php');
    $response = '';
    if (isset($params['stateId']) && $params['stateId'] == 'resume'
        && ($sco = scorm_get_sco($scoid)) && ($attempt = scorm_get_last_attempt($sco->scorm, $userid))) {
        if ($trackdata = scorm_get_tracks($scoid, $USER->id, $attempt)) {
            // if the activity status is 'failed',
            // 'skip content structure page' is selected to 'always' and additional attempts are allowed,
            // create a new attempt and return empty state data
            // We do this because the content structure page is the only way to generate a new SCORM attempt.
            if (($trackdata->status == 'failed') && ($scorm = $DB->get_record_select('scorm', 'id = ?', array($sco->scorm))) && ($scorm->skipview == 2)) {
                if (($attempt < $scorm->maxattempt) || ($scorm->maxattempt == 0)) {
                    $newattempt = $attempt + 1;
                    if (scorm_insert_track($USER->id, $scorm->id, $scoid, $newattempt, 'x.start.time', time())) {
                        return '';
                    }
                }
            }
        }
        $sql = 'userid=? AND scormid=? AND scoid=? AND attempt=? AND element=\'cmi.suspend_data\'';
        if (($tracktest = $DB->get_record_select('scorm_scoes_track', $sql, array($userid, $sco->scorm, $scoid, $attempt)))) {
            $response = $tracktest->value;
        }
    } else {
        throw new invalid_parameter_exception('Parameters invalid or Scorm/Sco not found.');
    }
    return $response;
}

function scorm_tcapi_store_activity_state($params, $response) {
    global $CFG, $USER, $SESSION;
    if (isset($params['actor']) && isset($params['actor']->moodle_user)) {
        $userid = $params['actor']->moodle_user;
    } else {
        $userid = $USER->id;
    }
    if (isset($params['moodle_mod_id'])) {
        $scoid = $params['moodle_mod_id'];
    } else {
        throw new invalid_parameter_exception('Module id not provided.');
    }
    require_once($CFG->dirroot.'/mod/scorm/locallib.php');
    if (isset($params['stateId']) && $params['stateId'] == 'resume'
        && isset($params['content']) && ($sco = scorm_get_sco($scoid)) && ($attempt = scorm_get_last_attempt($sco->scorm, $userid))) {
        // if the activity is considered complete, do not store updated state data
        if (($trackdata = scorm_get_tracks($scoid, $USER->id, $attempt))
            && (($trackdata->status == 'completed') || ($trackdata->status == 'passed') || ($trackdata->status == 'failed'))) {
            return $response;
        } else {
            scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.suspend_data', $params['content']);
        }
    } else {
        throw new invalid_parameter_exception('Parameters invalid or Scorm/Sco not found.');
    }
    return $response;
}

function scorm_tcapi_store_statement($params, $statementobject) {
    global $CFG, $USER, $DB, $SESSION;
    if (isset($params['actor']) && isset($params['actor']->moodle_user)) {
        $userid = $params['actor']->moodle_user;
    } else {
        $userid = $USER->id;
    }
    if (isset($params['moodle_mod_id'])) {
        $scoid = $params['moodle_mod_id'];
    } else {
        throw new invalid_parameter_exception('Module id not provided.');
    }
    require_once($CFG->dirroot.'/mod/scorm/locallib.php');
    if (($sco = scorm_get_sco($scoid)) && ($attempt = scorm_get_last_attempt($sco->scorm, $userid))) {
        $usertrack = scorm_get_tracks($scoid, $userid, $attempt);

        // if the activity is considered complete, only update the time if it doesn't yet exist
        $attemptcomplete = ($usertrack && (($usertrack->status == 'completed') || ($usertrack->status == 'passed') || ($usertrack->status == 'failed')));

        $statement = $statementobject->statement;
        $statementrow = $statementobject->statementRow;
        // check that the incoming statement refers to the sco identifier
        if (isset($statement->activity)) {
            $scoactivity = $statement->activity;
            // TODO: Add support for interaction tracks for child results reporting.
            // if (!empty($statement->activity->grouping_id) && ($lrs_activity = $DB->get_record_select('tcapi_activity','id = ?',array($statement->activity->grouping_id))))
                // $scoactivity = $lrs_activity;
            if ($sco->identifier == $scoactivity->activity_id) {
                // check for existing cmi.core.lesson_status
                // set default to 'incomplete'
                // check statement->verb and set cmi.core.lesson_status as appropriate
                $cmicorelessonstatus = (empty($usertrack->status) || $usertrack->status == 'notattempted') ? 'incomplete' : $usertrack->status;
                if (in_array(strtolower($statementrow->verb), array('completed', 'passed', 'mastered', 'failed'))) {
                    $cmicorelessonstatus = strtolower($statementrow->verb);
                    // Indicates activity status is complete
                    $complstatus = ($cmicorelessonstatus !== 'failed') ? 'completed' : 'incomplete';
                    if (!$attemptcomplete) {
                        scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.completion_status', $complstatus);
                    }
                    // Create/update track for cmi.core.lesson_status
                    if (!$attemptcomplete && in_array($cmicorelessonstatus, array('passed', 'failed', 'completed', 'incomplete'))) {
                        scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.lesson_status', $cmicorelessonstatus);
                    }
                    if (!$attemptcomplete && in_array($cmicorelessonstatus, array('passed', 'failed'))) {
                        scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.success_status', $cmicorelessonstatus);
                    } else if (!isset($usertrack->{'cmi.success_status'})) {
                        scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.success_status', 'unknown');
                    }
                    // Check if any result was reported.
                    if (isset($statementobject->resultRow)) {
                        $result = $statementobject->resultRow;
                        // If a duration was reported, add to any existing total_time.
                        if (isset($result->duration)) {
                            if ($usertrack->total_time == '00:00:00') {
                                $totaltime = $result->duration;
                            } else if (!$attemptcomplete) {
                                $totaltime = scorm_tcapi_add_time($result->duration, $usertrack->total_time);
                            }
                            if (isset($totaltime)) {
                                scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.total_time', $totaltime);
                            }
                        }

                        if (isset($result->score) && !$attemptcomplete) {
                            $score = json_decode($result->score);
                            if (isset($score->raw)) {
                                scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.score.raw', $score->raw);
                            }
                            if (isset($score->min)) {
                                scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.score.min', $score->min);
                            }
                            if (isset($score->max)) {
                                scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.score.max', $score->max);
                            }
                            // if scaled is provided but no raw, calculate the raw as we need it for SCORM grades
                            // try to use the min/max if available. if not, use 0/100
                            if (isset($score->scaled)) {
                                if (!isset($score->raw)) {
                                    $scoremin = (isset($score->min)) ? $score->min : 0;
                                    $scoremax = (isset($score->max)) ? $score->max : 100;
                                    $score->raw = ($score->scaled * ($scoremax - $scoremin)) + $scoremin;
                                    scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.score.raw', $score->raw);
                                    if (!isset($score->min)) {
                                        scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.score.min', $scoremin);
                                    }
                                    if (!isset($score->max)) {
                                        scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.score.max', $scoremax);
                                    }
                                }
                                scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.score.scaled', $score->scaled);
                            }
                        }

                    }
                }
                if ($attemptcomplete) {
                    return $statementobject->statementId;
                }

                // set cmi.core.exit to suspend if status is incomplete, else remove the track entry
                if ($cmicorelessonstatus == 'incomplete') {
                    scorm_insert_track($userid, $sco->scorm, $scoid, $attempt, 'cmi.core.exit', 'suspend');
                } else if ($track = $DB->get_record('scorm_scoes_track', array('userid' => $userid, 'scormid' => $sco->scorm,
                                                                               'scoid' => $scoid, 'attempt' => $attempt,
                                                                               'element' => 'cmi.core.exit'))) {
                    $DB->delete_records_select('scorm_scoes_track', 'id = ?', array($track->id));
                }
            }
        }
    } else {
        throw new invalid_parameter_exception('Parameters invalid or Scorm/Sco not found.');
    }

    return $statementobject->statementId;
}


function scorm_tcapi_add_time($a, $b) {
    $aes = explode(':', $a);
    $bes = explode(':', $b);
    $aseconds = explode('.', $aes[2]);
    $bseconds = explode('.', $bes[2]);
    $change = 0;

    $acents = 0; // Cents.
    if (count($aseconds) > 1) {
        $acents = $aseconds[1];
    }
    $bcents = 0;
    if (count($bseconds) > 1) {
        $bcents = $bseconds[1];
    }
    $cents = $acents + $bcents;
    $change = floor($cents / 100);
    $cents = $cents - ($change * 100);
    if (floor($cents) < 10) {
        $cents = '0'. $cents;
    }

    $secs = $aseconds[0] + $bseconds[0] + $change; // Seconds.
    $change = floor($secs / 60);
    $secs = $secs - ($change * 60);
    if (floor($secs) < 10) {
        $secs = '0'. $secs;
    }

    $mins = $aes[1] + $bes[1] + $change;  // Minutes.
    $change = floor($mins / 60);
    $mins = $mins - ($change * 60);
    if ($mins < 10) {
        $mins = '0' .  $mins;
    }

    $hours = $aes[0] + $bes[0] + $change; // Hours.
    if ($hours < 10) {
        $hours = '0' . $hours;
    }

    if ($cents != '0') {
        return $hours . ":" . $mins . ":" . $secs . '.' . $cents;
    } else {
        return $hours . ":" . $mins . ":" . $secs;
    }
}