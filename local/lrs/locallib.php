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

define('LRS_SERVICE', 'local_lrs');
define('LRS_MUST_EXIST', 1);
define('LRS_MUST_NOT_EXIST', 2);
define('LRS_OPTIONAL', 8);
define('LRS_REQUIRED', 9);
define('LRS_RETURN_OK', '200');
define('LRS_RETURN_NOCONTENT', '204');
define('LRS_RETURN_CONFLICT', '409');
define('LRS_RETURN_PRECONDITIONFAILED', '412');

if (isset($CFG->lrs_endpoint)) {
    define('LRS_ENDPOINT', $CFG->lrs_endpoint);
} else {
    define('LRS_ENDPOINT', $CFG->wwwroot.'/local/lrs/endpoint.php/');
}

if (isset($CFG->lrs_content_endpoint)) {
    define('LRS_CONTENT_ENDPOINT', $CFG->lrs_content_endpoint);
} else {
    define('LRS_CONTENT_ENDPOINT', $CFG->wwwroot.'/local/lrs/content_endpoint.php');
}

/**
 * Gets a stored user token for use in making requests to external lrs webservice.
 * If a token does not exist, one is created.
 */
function local_lrs_get_user_token() {
    global $USER, $DB;
    // If service doesn't exist, dml will throw exception.
    $servicerecord = $DB->get_record('external_services', array('shortname' => LRS_SERVICE, 'enabled' => 1), '*', MUST_EXIST);
    if ($token = local_lrs_user_token_exists($servicerecord->id)) {
        return $token;
    } else if (has_capability('moodle/webservice:createtoken', context_system::instance())) {
        // Make sure the token doesn't exist (borrowed from /lib/externallib.php).
        $numtries = 0;
        do {
            $numtries ++;
            $generatedtoken = md5(uniqid(rand(), 1));
            if ($numtries > 5) {
                throw new moodle_exception('tokengenerationfailed');
            }
        } while ($DB->record_exists('external_tokens', array('token' => $generatedtoken)) && $numtries <= 5);
        // Create a new token.
        $token = new stdClass;
        $token->token = $generatedtoken;
        $token->userid = $USER->id;
        $token->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $token->contextid = context_system::instance()->id;
        $token->creatorid = $USER->id;
        $token->timecreated = time();
        $token->lastaccess = time();
        $token->externalserviceid = $servicerecord->id;
        $tokenid = $DB->insert_record('external_tokens', $token);
        add_to_log(SITEID, 'webservice', 'automatically create user token', '' , 'User ID: ' . $USER->id);
        $token->id = $tokenid;
    } else {
        throw new moodle_exception('cannotcreatetoken', 'webservice', '', LRS_SERVICE);
    }
    return $token;
}

/**
 * Simple check to see if a token for lrs web service already exists for a user
 * and returns it.
 * @param Integer $serviceid
 */
function local_lrs_user_token_exists($serviceid = null) {
    global $USER, $DB;
    if ($serviceid == null) {
        // If service doesn't exist, dml will throw exception.
        $servicerecord = $DB->get_record('external_services', array('shortname' => LRS_SERVICE, 'enabled' => 1), '*', MUST_EXIST);
        $serviceid = $servicerecord->id;
    }
    // Check if a token has already been created for this user and this service
    // Note: this could be an admin created or an user created token.
    // It does not really matter we take the first one that is valid.
    $conditions = array('userid' => $USER->id, 'externalserviceid' => $serviceid, 'tokentype' => EXTERNAL_TOKEN_PERMANENT);
    $tokens = $DB->get_records('external_tokens', $conditions, 'timecreated', ' id, sid, token, validuntil, iprestriction');
    // I some valid tokens exist then use the most recent.
    if (!empty($tokens)) {
        $token = array_pop($tokens);
        // Log token access.
        $DB->set_field('external_tokens', 'lastaccess', time(), array('id' => $token->id));
        add_to_log(SITEID, 'webservice', 'user request webservice token', '' , 'User ID: ' . $USER->id);
        return $token;
    }
    return false;
}

/**
 * Store posted statement and return statement id.
 * This function is called by the external service in externallib.php.
 * @param array $params array of parameter passed from external service 
 * @throws invalid_response_exception
 * @throws invalid_parameter_exception
 * @return mixed $return object to be used by external service or false if failure
 */
function local_lrs_store_statement($params) {
    global $DB;
    if (!is_null($params['content']) && ($statement = json_decode($params['content']))) {
        if (is_null($params['statementId']) && !isset($statement->id)) {
            // Make sure the statementId doesn't exist (borrowed from /lib/externallib.php).
            $numtries = 0;
            do {
                $numtries ++;
                $statementid = md5(uniqid(rand(), 1));
                if ($numtries > 5) {
                    throw new invalid_response_exception('Could not create statement_id.');
                }
            } while ($DB->record_exists('lrs_statement', array('statement_id' => $statementid)) && $numtries <= 5);
        } else {
            $statementid = (isset($statement->id)) ? $statement->id : $params['statementId'];
        }
        $sdata = new stdClass();
        $sdata->statement_id = $statementid;
        $sdata->statement = $params['content'];
        $sdata->stored = time();
        $sdata->verb = (isset($statement->verb)) ? $statement->verb : 'experienced';
        if (isset($statement->inProgress)) {
            $sdata->inProgress = '1';
        }
        if (!($actor = local_lrs_get_actor($statement->actor))) {
            throw new invalid_response_exception('Agent object could not be found or created.');
            return false;
        } else {
            $sdata->actorid = $actor->id;
        }
        if (isset($statement->context)) {
            if (isset($statement->context->registration)) {
                $sdata->registration = $statement->context->registration;
            }
            if (isset($statement->context->instructor)) {
                if ($actor = local_lrs_get_actor($statement->context->instructor, true)) {
                    $sdata->instructorid = $actor->id;
                }
            }
            if (isset($statement->context->team)) {
                if ($actor = local_lrs_get_actor($statement->context->team, true)) {
                    $sdata->teamid = $actor->id;
                }
            }
            if (isset($statement->context->contextActivities)) {
                $cas = array('grouping', 'parent', 'other');
                foreach ($cas as $ca) {
                    $fieldid = 'context_'.$ca.'id';
                    if (isset($statement->context->contextActivities->$ca) &&
                        isset($statement->context->contextActivities->$ca->id)) {

                        $activity = new stdClass();
                        $activity->activity_id = $statement->context->contextActivities->$ca->id;
                        if (isset($sdata->context_groupingid)) {
                            $activity->grouping_id = $sdata->context_groupingid;
                        }
                        if ($activity = local_lrs_get_activity($activity)) {
                            $sdata->$fieldid = $activity->id;
                        }
                    }
                }
            }
        }
        if (isset($statement->object)) {
            if (!isset($statement->object->objecttype)) {
                $statement->object->objecttype = 'activity';
            }
            $objecttype = strtolower($statement->object->objecttype);
            $sdata->object_type = $objecttype;
            switch ($objecttype) {
                case 'activity':
                    if (isset($statement->object->id)) {
                        $activity = new stdClass();
                        $activity->activity_id = $statement->object->id;
                        if (isset($statement->object->definition)) {
                            $activity->definition = $statement->object->definition;
                        }
                        if (isset($sdata->context_groupingid)) {
                            $activity->grouping_id = $sdata->context_groupingid;
                        }
                        if (!($activity = local_lrs_get_activity($activity))) {
                            throw new invalid_response_exception('Activity could not be found or created.');
                            return false;
                        }
                        if (isset($activity->activityid)) {
                            // Be sure to capture this in case it's been captured from metadata.
                            $statement->object->id = $activity->activityid;
                        }
                        $statement->activity = $activity;
                    } else {
                        throw new invalid_parameter_exception('Object->id required from statement.');
                        return false;
                    }
                    $sdata->objectid = $activity->id;
                break;
                case 'statement':
                    if ($sdata->verb == 'voided') {
                        if (isset($statement->object->id) && ($r = $DB->get_record('lrs_statement', array('statement_id' => $statement->object->id)))) {
                            $r->voided = '1';
                            if ($DB->update_record('lrs_statement', $r) !== true) {
                                throw new invalid_parameter_exception('Statement could not be voided.');
                                return false;
                            }
                        } else {
                            throw new invalid_parameter_exception('statementId parameter required.');
                            return false;
                        }
                        $sdata->objectid = $r->id;
                    }
                break;
                case 'agent':
                case 'person':
                case 'group':
                    if (isset($statement->object->id)) {
                        if (!($actor = local_lrs_get_actor($params['actor'], true))) {
                            throw new invalid_response_exception('Agent object could not be found or created.');
                            return false;
                        }
                    } else {
                        throw new invalid_parameter_exception('Object->id required from statement.');
                        return false;
                    }
                    $sdata->objectid = $actor->id;
                break;
            }
        } else if ($sdata->verb == 'voided') {
            throw new invalid_parameter_exception('Statement object parameter required.');
            return false;
        }
        if (isset($statement->timestamp) && ($timestamp = strtotime($statement->timestamp))) {
            $sdata->timestamp = $timestamp;
        }
        if (isset($statement->result)) {
            $rdata = new stdClass();
            if (isset($statement->result->score)) {
                $rdata->score = json_encode($statement->result->score);
            }
            if (isset($statement->result->success)) {
                $rdata->success = ($statement->result->success == 'true') ? '1' : '0';
            }
            if (isset($statement->result->completion)) {
                $rdata->completion = (strtolower($statement->result->completion) == 'completed' || $statement->result->completion == true) ? '1' : '0';
            }
            if (isset($statement->result->duration)) {
                if ($tarr = local_lrs_parse_duration($statement->result->duration)) {
                    $rdata->duration = implode(":", $tarr);
                }
            }
            // Check special verbs for assumed completion and success results.
            // Return error if conflicting results are reported.
            $srvverbs = array('completed', 'passed', 'mastered', 'failed');
            if (in_array($sdata->verb, $srvverbs)) {
                $completion = '1';
                $success = ($sdata->verb == 'failed') ? '0' : '1';
                if ((isset($rdata->completion) && $rdata->completion != $completion)
                    || (isset($rdata->success) && $rdata->success != $success)) {

                    throw new invalid_parameter_exception('Statement result conflict.');
                    return false;
                }
                $rdata->completion = $completion;
                $rdata->success = $success;
            }
            if (isset($statement->result->response)) {
                $rdata->response = $statement->result->response;
            }
            if (($rid = $DB->insert_record('lrs_result', $rdata, true)) !== false) {
                $sdata->resultid = $rid;
            }
        }
        $DB->insert_record('lrs_statement', $sdata);

        $return = new stdClass();
        $return->statement = $statement;
        $return->statementId = $sdata->statement_id;
        $return->statementRow = $sdata;
        if (isset($rdata)) {
            $return->resultRow = $rdata;
        }
        return $return;
    }

    return false;
}

/**
 * Stores a posted activity state.
 * Called by the external service in externallib.php
 * @param array $params array of parameter passed from external service
 * @throws invalid_response_exception
 * @throws invalid_parameter_exception
 * @return mixed empty string or throws exception
 */
function local_lrs_store_activity_state ($params) {
    global $DB;
    if (!is_null($params['activityId']) && !is_null($params['stateId']) && !is_null($params['content'])) {
        if ($actor = local_lrs_get_actor($params['actor'])) {
            $data = new stdClass();
            $data->actorid = $actor->id;
            $data->state_id = $params['stateId'];
            if (isset($params['registration'])) {
                $data->registration = $params['registration'];
            }
            $data->contents = $params['content'];
            $data->updated = time();
            $activity = new stdClass();
            $activity->activity_id = $params['activityId'];
            if (!($activity = local_lrs_get_activity($activity))) {
                throw new invalid_response_exception('Activity could not be found or created.');
            } else {
                $data->activityid = $activity->id;
            }
            // Get state content for specific stateId.
            $conditions = array('actorid' => $actor->id, 'activityid' => $activity->id, 'state_id' => $params['stateId']);
            $existingstate = $DB->get_record('lrs_state', $conditions, 'id');
            if (!empty($existingstate)) {
                $data->id = $existingstate->id;
                $DB->update_record('lrs_state', $data);
                return '';
            } else {
                $DB->insert_record('lrs_state', $data);
                return '';
            }
        }
    }
    throw new invalid_parameter_exception('Parameters invalid or state could not be stored.');
}

/**
 * Retrieves an activity state.
 * Called by the external service in externallib.php.
 * If the stateId is provided, will return the value stored under that stateId. Otherwise,
 * will return all stored stateIds as json encoded array.
 * @param array $params array of parameter passed from external service
 * @throws invalid_parameter_exception
 * @return mixed string containing state, string containing json encoded array of stored stateIds, or throws exception
 */
function local_lrs_fetch_activity_state ($params) {
    global $DB;
    if (!is_null($params['activityId'])) {
        if ($actor = local_lrs_get_actor($params['actor'])) {
            $return = '';
            $activity = new stdClass();
            $activity->activity_id = $params['activityId'];
            if (!($activity = local_lrs_get_activity($activity, true))) {
                return $return;
            }
            if (isset($params['stateId'])) {
                // Get state content for specific stateId.
                $params = array($actor->id, $activity->id, $params['stateId']);
                if ($r = $DB->get_record_select('lrs_state', 'actorid = ? AND activityid = ? AND state_id = ? ORDER BY updated DESC', $params, 'id, contents')) {
                    $return = $r->contents;
                }
            } else {
                $states = array();
                $since = (isset($params['since']) && ($sincetime = strtotime($params['since']))) ? 'AND updated >= '.$sincetime : '';
                // Get all stateIds stored.
                if ($rs = $DB->get_records_select('lrs_state', 'actorid = ? AND activityid = ?'.$since, array($actor->id, $activity->id), '', 'id, state_id')) {
                    foreach ($rs as $r) {
                        array_push($states, $r->stateid);
                    }
                }
                $return = json_encode($states);
            }
            return $return;
        }
    }

    throw new invalid_parameter_exception('Parameters invalid or state could not be retrieved.');
}

/**
 * Permanently deletes all states associated with a specific author and activity.
 * @param array $params array of parameter passed from external service
 * @return mixed an empty string if success or throws an exception
 */
function local_lrs_delete_activity_state ($params) {
    global $DB;
    if (!is_null($params['activityId'])) {
        if ($actor = local_lrs_get_actor($params['actor'])) {
            $activity = new stdClass();
            $activity->activity_id = $params['activityId'];
            if (!($activity = local_lrs_get_activity($activity, true))) {
                throw new invalid_response_exception('Activity could not be found.');
            }
            // Delete all states stored.
            $DB->delete_records('lrs_state', array('actorid' => $actor->id, 'activityid' => $activity->id));
            return '';
        }
    }
    throw new invalid_parameter_exception('Parameters invalid or actor could not be found.');
}

function local_lrs_get_actor ($actor, $objecttype = false) {
    global $DB;
    if ((is_null($actor) || !is_object($actor)) && $objecttype === false) {
        global $USER;
        $object = new stdClass();
        $object->name = array($USER->firstname.' '.$USER->lastname);
        $object->mbox = array($USER->email);
        $object->localid = $USER->id;
    } else {
        $object = $actor;
    }
    if (isset($object->mbox) && !empty($object->mbox)) {
        foreach ($object->mbox as $key => $val) {
            $object->mbox[$key] = (strpos($val, 'mailto:') !== false) ? substr($val, strpos($val, 'mailto:') + 7) : $val;
        }
    }
    $sqlwhere = 'object_type=\'person\'';
    $xtrasql = array();
    if (isset($object->localid)) {
        array_push($xtrasql, 'localid = '.$USER->id);
    }
    if (isset($object->mbox_sha1sum) && !empty($object->mbox_sha1sum)) {
        array_push($xtrasql, '(mbox_sha1sum LIKE \'%"'. implode("\"%' OR mbox_sha1sum LIKE '%\"", $object->mbox_sha1sum) .'"%\')');
    }
    if (isset($object->mbox) && !empty($object->mbox)) {
        array_push($xtrasql, '(mbox LIKE \'%"'. implode("\"%' OR mbox LIKE '%\"", $object->mbox) .'"%\')');
    }
    if (!empty($xtrasql)) {
        $sqlwhere .= ' AND ('.implode(" OR ", $xtrasql).')';
    }
    if (($actor = $DB->get_record_select('lrs_agent', $sqlwhere))) {
        $actor = local_lrs_push_actor_properties($actor, $object);
        if (isset($object->localid)) {
            $actor->localid = $object->localid;
        }
        $DB->update_record('lrs_agent', local_lrs_db_conform($actor));
        return $actor;
    } else {
        $actor = new stdClass();
        $actor->object_type = 'person';
        if (isset($object->localid)) {
            $actor->localid = $object->localid;
        }
        $actor = local_lrs_push_actor_properties($actor, $object);
        if ($actor->id = $DB->insert_record('lrs_agent', local_lrs_db_conform($actor), true)) {
            return $actor;
        }
    }
    return false;
}

function local_lrs_get_activity ($object, $mustexist=false, $forceupdate=false) {
    global $DB, $CFG;
    $ismetalink = (filter_var($object->activity_id, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)
        && basename($object->activity_id) == 'tincan.xml');

    if ($ismetalink) {
        $activity = $DB->get_record('lrs_activity', array('metaurl' => $object->activity_id));
    }
    if (empty($activity) && isset($object->grouping_id)) {
        $activity = $DB->get_record('lrs_activity', array('activity_id' => $object->activity_id, 'grouping_id' => $object->grouping_id));
    }
    if (empty($activity) && !isset($object->grouping_id) && !isset($object->metaurl)) {
        $activity = $DB->get_record('lrs_activity', array('activity_id' => $object->activity_id));
    }
    if (empty($activity) && isset($object->metaurl)) {
        $activity = $DB->get_record('lrs_activity', array('activity_id' => $object->activity_id, 'metaurl' => $object->metaurl));
    }

    if (!empty($activity)) {
        if (empty($activity->known) || $forceupdate) {
            $activity = local_lrs_push_activity_properties($activity, $object);
            $DB->update_record('lrs_activity', local_lrs_db_conform($activity));
        }
        return $activity;
    }

    if ($ismetalink) {
        $object->metaurl = $object->activity_id;
        // Activity is defined in tincan.xml file.
        $mparser = new local_lrs_metaparser($object->metaurl);
        if (($activity = $mparser->parse()) && empty($mparser->errors)) {
            return $activity;
        } else if (!empty($mparser->errors)) {
            throw new invalid_response_exception(implode(" ", $mparser->errors));
        }
    } else if ($mustexist === false) {
        $activity = new stdClass();
        $activity = local_lrs_push_activity_properties($activity, $object);
        if ($activity->id = $DB->insert_record('lrs_activity', local_lrs_db_conform($activity), true)) {
            return $activity;
        }
    }
    return false;
}

/**
 * 
 * Converts property names within object to Moodle DB conforming field names.
 * This is necessary prior to insertion into database.
 * @param object $object
 * @throws moodle_exception
 * @throws invalid_response_exception
 * @throws invalid_parameter_exception
 * @return object $newobject
 */
function local_lrs_db_conform ($object) {
    $newobject = new stdClass();
    foreach ($object as $key => $val) {
        $key = strtolower(preg_replace('/([A-Z])/', '_$1', $key));
        $newobject->$key = $val;
    }
    return $newobject;
}

function local_lrs_get_activity_id ($activityid) {
    return $DB->get_record('lrs_activity', array('activity_id' =>$activityid));
}

function local_lrs_push_actor_properties ($actor, $object) {
    $actor->givenName = isset($actor->given_name) ? $actor->given_name : null;
    $actor->familyName = isset($actor->family_name) ? $actor->family_name : null;
    $actor->firstName = isset($actor->first_name) ? $actor->first_name : null;
    $actor->lastName = isset($actor->last_name) ? $actor->last_name : null;
    return local_lrs_push_object_properties ($actor, $object, array('name', 'mbox', 'mbox_sha1sum', 'openid',
                                                                    'account', 'givenName', 'familyName',
                                                                    'firstName', 'lastName'),
                                                              array('name', 'mbox', 'mbox_sha1sum', 'openid',
                                                                    'account', 'givenName', 'familyName',
                                                                    'firstName', 'lastName'));
}

function local_lrs_push_activity_properties ($activity, $object) {
    $activity->interactionType = isset($activity->interaction_type) ? $activity->interaction_type : null;
    if (isset($object->definition)) {
        $object->name = (isset($object->definition->name)) ? $object->definition->name : null;
        $object->description = (isset($object->definition->description)) ? $object->definition->description : null;
    }
    return local_lrs_push_object_properties ($activity, $object, array('activity_id', 'metaurl', 'known', 'name',
                                                                       'description', 'type', 'interactionType',
                                                                       'extensions', 'grouping_id'),
                                                                 false, array('name', 'description'));
}

function local_lrs_push_object_properties($currobject, $pushobject, $propertykeys, $multiplevals=false, $isobject=false) {
    foreach ($propertykeys as $key) {
        if (isset($pushobject->$key) && !empty($pushobject->$key)) {
            if ($multiplevals !== false && in_array($key, $multiplevals)) {
                // Decode current object value and ensure it's an array, or unset.
                if (isset($currobject->$key) && !is_array($currobject->$key)) {
                    $currobject->$key = json_decode($currobject->$key);
                    if (is_null($currobject->$key) || !is_array($currobject->$key)) {
                        unset($currobject->$key);
                    }
                }
                // Ensure the push object is an array.
                if (!is_array($pushobject->$key)) {
                    $pushobject->$key = array($pushobject->$key);
                }
                $currvalues = (isset($currobject->$key)) ? $currobject->$key : array();
                $primaryvalue = array_shift($pushobject->$key);
                if (($pkey = array_search($primaryvalue, $currvalues)) !== false) {
                    unset($currvalues[$pkey]);
                }
                $newvalues = array_merge(array($primaryvalue), $currvalues);
                foreach ($pushobject->$key as $pushval) {
                    if (!in_array($pushval, $newvalues)) {
                        array_push($newvalues, $pushval);
                    }
                }
                $currobject->$key = (!empty($newvalues)) ? json_encode($newvalues) : null;
            } else if ($isobject !== false && in_array($key, $isobject)) {
                if (isset($currobject->$key) && !is_object($currobject->$key)) {
                    $currvalues = json_decode($currobject->$key);
                } else {
                    $currvalues = new stdClass();
                }
                $pushvals = (array)$pushobject->$key;
                foreach ($pushvals as $k => $v) {
                    $currvalues->$k = $v;
                }
                $currobject->$key = json_encode($currvalues);
            } else {
                $currobject->$key = $pushobject->$key;
            }
        }
    }
    return $currobject;
}

/**
 * Parse an ISO 8601 duration string
 * @return array
 * @param string $str
 **/
function local_lrs_parse_duration($str) {
    $result = array();
    preg_match('/^(?:P)([^T]*)(?:T)?(.*)?$/', trim($str), $sections);
    if (!empty($sections[1])) {
        preg_match_all('/(\d+)([YMWD])/', $sections[1], $parts, PREG_SET_ORDER);
        $units = array('Y' => 'years', 'M' => 'months', 'W' => 'weeks', 'D' => 'days');
        foreach ($parts as $part) {
            $part[1] = '00'.$part[1];
            $value = (strpos($part[1], '.')) ? substr($part[1], (strpos($part[1], '.') - 2), strlen($part[1])) : substr($part[1], -2, 2);
            $result[$units[$part[2]]] = $value;
        }
    }
    if (!empty($sections[2])) {
        preg_match_all('/(\d*\.?\d+|\d+)([HMS])/', $sections[2], $parts, PREG_SET_ORDER);
        $units = array('H' => 'hours', 'M' => 'minutes', 'S' => 'seconds');
        foreach ($parts as $part) {
            $part[1] = '00'.$part[1];
            $value = (strpos($part[1], '.')) ? substr($part[1], (strpos($part[1], '.') - 2), strlen($part[1])) : substr($part[1], -2, 2);
            $result[$units[$part[2]]] = $value;
        }
    }
    return $result;
}