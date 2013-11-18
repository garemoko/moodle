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

function scorm_get_tincan_launch_params($scorm, $sco, $launchurl) {
    global $CFG, $USER;
    // verify tcapi plugin exists, if not, return nothing.
    if (!file_exists($CFG->dirroot.'/local/lrs/locallib.php')) {
        return '';
    }
    require_once($CFG->dirroot.'/local/lrs/locallib.php');
    // Call the LRS local webservice locallib for token and endpoint.
    $token = local_lrs_get_user_token();
    // Generate activity_id as unique using URI method (also provides LRS with metadata path).
    $activityid = str_ireplace($sco->launch, 'tincan.xml', $launchurl);
    // Determine connector for launch params.
    $connector = (stripos($sco->launch, '?') !== false) ? '&' : '?';
    // build a registration (not used for anything at this time).
    $registration = md5($USER->id.$sco->id);
    // add version (not used for anything at this time).
    $revision = $scorm->revision;
    // gather all the launch params.
    $launchparams = array(
    'endpoint' => LRS_ENDPOINT,
    'auth' => $token->token,
    'moodle_mod' => 'scorm',
    'moodle_mod_id' => $sco->id,
    'revision' => $revision,
    'registration' => $registration,
    'activity_id' => $activityid);
    // Webservice content address for content endpoint (Articulate Mobile Player)
    // Only provide if using the pluginfile.php method for delivery.
    if ($pos = strpos($launchurl, '/pluginfile.php')) {
        $wscontenturl = substr($launchurl, $pos, strlen($sco->launch) * -1);
        $wscontenturl = str_ireplace('/pluginfile.php', '/', $wscontenturl);
        $launchparams['content_endpoint'] = LRS_CONTENT_ENDPOINT.$wscontenturl;
        $launchparams['content_token'] = $token->token;
    }
    $paramsencoded = array();
    foreach ($launchparams as $lk => $lv) {
        array_push($paramsencoded, $lk.'='.rawurlencode($lv));
    }
    // Build and return content launch string.
    return $connector.implode("&", $paramsencoded);
}

function scorm_get_tincan_manifest($blocks, $scoes) {
    global $OUTPUT;
    static $parents = array();
    static $resources;

    static $manifest;
    static $organization;
    static $courseid;

    if (count($blocks) > 0) {
        foreach ($blocks as $block) {
            switch ($block['name']) {
                case 'TINCAN':
                    $identifier = 'organization';
                    $organization = '';
                    $scoes->elements[$manifest][$organization][$identifier] = new stdClass();
                    $scoes->elements[$manifest][$organization][$identifier]->identifier = $identifier;
                    $scoes->elements[$manifest][$organization][$identifier]->parent = '/';
                    $scoes->elements[$manifest][$organization][$identifier]->launch = '';
                    $scoes->elements[$manifest][$organization][$identifier]->scormtype = '';

                    $parents = array();
                    $parent = new stdClass();
                    $parent->identifier = $identifier;
                    $parent->organization = $organization;
                    array_push($parents, $parent);
                    $organization = $identifier;

                    if (!empty($block['children'])) {
                        $scoes = scorm_get_tincan_manifest($block['children'], $scoes);
                    }

                    array_pop($parents);
                break;
                case 'ACTIVITIES':
                    if (!isset($scoes->defaultorg) && isset($block['attrs']['DEFAULT'])) {
                        $scoes->defaultorg = $block['attrs']['DEFAULT'];
                    }
                    if (!empty($block['children'])) {
                        $scoes = scorm_get_tincan_manifest($block['children'], $scoes);
                    }
                break;
                case 'ACTIVITY':
                    if (empty($courseid) && isset($block['attrs']['TYPE']) && $block['attrs']['TYPE'] == 'course') {

                        $org = $parent = array_pop($parents);
                        array_push($parents, $parent);

                        $courseid = $identifier = $block['attrs']['ID'];
                        $scoes->elements[$manifest][$organization][$identifier] = new stdClass();
                        $scoes->elements[$manifest][$organization][$identifier]->identifier = $identifier;
                        $scoes->elements[$manifest][$organization][$identifier]->parent = $parent->identifier;
                        $scoes->elements[$manifest][$organization][$identifier]->launch = '';
                        $scoes->elements[$manifest][$organization][$identifier]->scormtype = 'sco';

                        $parent = new stdClass();
                        $parent->identifier = $identifier;
                        $parent->organization = $organization;
                        array_push($parents, $parent);

                        if (!empty($block['children'])) {
                            $scoes = scorm_get_tincan_manifest($block['children'], $scoes);
                        }
                        // If a title was found for the course block, apply it to the organization block.
                        if (isset($scoes->elements[$manifest][$organization][$identifier]->title)) {
                            $scoes->elements[$manifest][$org->organization][$org->identifier]->title = $scoes->elements[$manifest][$organization][$identifier]->title;
                        }

                        array_pop($parents);
                    }
                break;
                case 'NAME':
                    $parent = array_pop($parents);
                    array_push($parents, $parent);
                    if (!isset($block['tagData'])) {
                        $block['tagData'] = '';
                    }
                    $scoes->elements[$manifest][$parent->organization][$parent->identifier]->title = $block['tagData'];
                break;
                case 'LAUNCH':
                    $parent = array_pop($parents);
                    array_push($parents, $parent);
                    if (!isset($block['tagData'])) {
                        $block['tagData'] = '';
                    }
                    $scoes->elements[$manifest][$parent->organization][$parent->identifier]->launch = $block['tagData'];
                break;
            }
        }
    }
    return $scoes;
}

/**
 * Define optional data for sco item.
 * TODO: Decide if we should define anything here. It will need to be added to the get_manifest as well.
 * 
 * @param object $item
 * @param array $standarddata
 */
function scorm_tincan_optionals_data($item, $standarddata) {
    $result = array();
    $sequencingdata = array();
    foreach ($item as $element => $value) {
        if (! in_array($element, $standarddata)) {
            if (! in_array($element, $sequencingdata)) {
                $result[] = $element;
            }
        }
    }
    return $result;
}

/**
 * 
 * Parse the TCAPI resource.
 * 
 * @param object $scorm
 * @param object $manifest
 */
function scorm_parse_tincan($scorm, $manifest) {
    global $CFG, $DB;

    // load manifest into string
    if ($manifest instanceof stored_file) {
        $xmltext = $manifest->get_content();
    } else {
        require_once("$CFG->libdir/filelib.php");
        $xmltext = download_file_content($manifest);
    }

    $launch = 0;
    $pattern = '/&(?!\w{2,6};)/';
    $replacement = '&amp;';
    $xmltext = preg_replace($pattern, $replacement, $xmltext);

    $objxml = new mod_scorm_xml2array();
    $manifests = $objxml->parse($xmltext);
    $scoes = new stdClass();
    $scoes->version = 'TCAPI';
    $scoes = scorm_get_tincan_manifest($manifests, $scoes);
    if (count($scoes->elements) > 0) {
        $olditems = $DB->get_records('scorm_scoes', array('scorm' => $scorm->id));
        foreach ($scoes->elements as $manifest => $organizations) {
            foreach ($organizations as $organization => $items) {
                foreach ($items as $identifier => $item) {
                    $newitem = new stdClass();
                    $newitem->scorm = $scorm->id;
                    $newitem->manifest = $manifest;
                    $newitem->organization = $organization;
                    $standarddatas = array('parent', 'identifier', 'launch', 'scormtype', 'title');
                    foreach ($standarddatas as $standarddata) {
                        if (isset($item->$standarddata)) {
                            $newitem->$standarddata = $item->$standarddata;
                        }
                    }

                    // Insert the new SCO, and retain the link between the old and new for later adjustment.
                    $id = $DB->insert_record('scorm_scoes', $newitem);
                    if (!empty($olditems) && ($olditemid = scorm_array_search('identifier', $newitem->identifier, $olditems))) {
                        $olditems[$olditemid]->newid = $id;
                    }

                    if ($optionaldatas = scorm_tincan_optionals_data($item, $standarddatas)) {
                        $data = new stdClass();
                        $data->scoid = $id;
                        foreach ($optionaldatas as $optionaldata) {
                            if (isset($item->$optionaldata)) {
                                $data->name = $optionaldata;
                                $data->value = $item->$optionaldata;
                                $dataid = $DB->insert_record('scorm_scoes_data', $data);
                            }
                        }
                    }

                    if (($launch == 0) && ((empty($scoes->defaultorg)) || ($scoes->defaultorg == $identifier))) {
                        $launch = $id;
                    }
                }
            }
        }
        if (!empty($olditems)) {
            foreach ($olditems as $olditem) {
                $DB->delete_records('scorm_scoes', array('id' => $olditem->id));
                $DB->delete_records('scorm_scoes_data', array('scoid' => $olditem->id));
                if (isset($olditem->newid)) {
                    $DB->set_field('scorm_scoes_track', 'scoid', $olditem->newid, array('scoid' => $olditem->id));
                }
                $DB->delete_records('scorm_scoes_track', array('scoid' => $olditem->id));
            }
        }
        $DB->set_field('scorm', 'version', $scoes->version, array('id' => $scorm->id));
        $scorm->version = $scoes->version;
    }

    $scorm->launch = $launch;

    return true;
}

/**
 * generate a simple single activity TinCan object
 *
 * @param object $scorm package record
 */
function scorm_tincan_generate_simple_sco($scorm) {
    global $DB;
    // Find the old one.
    $scos = $DB->get_records('scorm_scoes', array('scorm' => $scorm->id));
    if (!empty($scos)) {
        $sco = array_shift($scos);
    } else {
        $sco = new object();
    }
    // Get rid of old ones.
    foreach ($scos as $oldsco) {
        $DB->delete_records('scorm_scoes', array('id' => $oldsco->id));
        $DB->delete_records('scorm_scoes_track', array('scoid' => $oldsco->id));
    }

    $sco->identifier = 'TCAPI1';
    $sco->scorm = $scorm->id;
    $sco->organization = '';
    $sco->title = $scorm->name;
    $sco->parent = '/';
    $sco->launch = $scorm->reference;
    $sco->scormtype = 'sco';
    if (isset($sco->id)) {
        $DB->update_record('scorm_scoes', $sco);
        $id = $sco->id;
    } else {
        $id = $DB->insert_record('scorm_scoes', $sco);
    }
    return $id;
}