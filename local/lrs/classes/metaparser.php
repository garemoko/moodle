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
 * This class allows parsing of lrs xml node object.
 * The returnObject holds the object ready for json and inclusion in a statement.
 * dbObject returns an object ready for insertion updating of a DB entry.
 *
 * @package    local lrs
 * @copyright  2012 Jamie Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

class local_lrs_metaparser {

    protected $xmlurl;
    public $xml;
    public $activities = array();
    public $mainactivity;
    public $storeactivities = true;
    public $errors = array();

    public function __construct ($xmlurl) {
        $this->xmlurl = $xmlurl;
    }

    public function parse() {
        if ($this->validate_xml() == false) {
            return false;
        }
        if (isset($this->xml->activities) && isset($this->xml->activities->activity)) {
            for ($i = 0; $i < $this->xml->activities->activity->count(); $i++) {
                $aparser = new local_lrs_activityparser('activity');
                $aparser->parse_object($this->xml->activities->activity[$i]);
                if (!isset($this->mainactivity) && isset($aparser->activity->id)
                    && isset($aparser->activity->definition->type) && $aparser->activity->definition->type == 'course') {
                    $aparser->metaurl = $this->xmlurl;
                    $aparser->parse_object($this->xml->activities->activity[$i]);
                    $this->mainactivity = local_lrs_get_activity($aparser->dbobject, false, true);
                } else {
                    array_push($this->activities, $aparser->dbobject);
                }
            }
            if (isset($this->mainactivity)) {
                foreach ($this->activities as $dbobject) {
                    $dbobject->grouping_id = $this->mainactivity->id;
                    $activity = local_lrs_get_activity($dbobject, false, $this->storeactivities);
                }
                $return = $this->mainactivity;
            }
            return $return;
        }

    }

    private function validate_xml () {
        global $CFG;
        $dom = new DOMDocument;
        $xml = $this->getxml();
        if (empty($xml)) {
            array_push($this->errors, 'XML file not found or unavailable.');
        } else if ($dom->loadXML($xml) === false) {
            array_push($this->errors, 'Could not load XML.');
        } else if (!$dom->schemaValidate($CFG->dirroot.'/local/lrs/tincan.xsd') ||
                  ($this->xml = simplexml_import_dom($dom)) === false) {
            array_push($this->errors, 'XML file invalid for schema.');
            return false;
        }
        return true;
    }

    /*
     * Attempt to determine if this is a local file accessed with pluginfile.php.
     * If so, get file directly using native file class.
     */
    private function getxml() {
        global $CFG;
        $search = "$CFG->wwwroot/pluginfile.php/";
        if (substr($this->xmlurl, 0, strlen($search)) == $search) {
            $url = str_replace('pluginfile.php', 'webservice/pluginfile.php', clean_param($this->xmlurl, PARAM_LOCALURL));
            // Determine connector for launch params.
            $connector = (stripos($url, '?') !== false) ? '&' : '?';
            if ($token = local_lrs_get_user_token()) {
                return file_get_contents($url.$connector.'token='.$token->token);
            }
        } else {
            return file_get_contents($this->xmlurl);
        }
        array_push($this->errors, 'User token required but not valid/found.');
        return '';
    }
}
