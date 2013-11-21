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

class local_lrs_activityparser {

    public $objecttype;
    public $xml;
    public $metaurl;
    public $activity;
    public $extensions;
    public $jsonobject;
    public $dbobject;

    public function __construct ($type) {
        $this->objecttype = $type;
        $this->activity = new stdClass();
    }

    public function parse_object($xml) {
        $this->xml = $xml;
        $this->activity = new stdClass();
        $this->activity->definition = new stdClass();
        $this->parse_by_name('id');
        $this->parse_by_name('type', false, 'definition');
        $this->parse_by_name('name', true, 'definition');
        $this->parse_by_name('description', true, 'definition');
        $this->parse_by_name('interactionType', false, 'definition');
        if ($this->activity->definition->type == 'cmi.interaction' && isset($this->activity->definition->interactionType)) {
            $this->parse_interaction_extensions();
        }
        $this->jsonobject = json_encode($this->activity);
        $this->create_db_object();
    }

    private function parse_by_name($attr, $lang=false, $ca=null) {
        $a = null;
        if (isset($this->xml[$attr])) {
            $a = strval($this->xml[$attr]);
        } else if (isset($this->xml->$attr)) {
            if ($lang) {
                $a = $this->parse_as_langstr($this->xml->$attr);
            } else if ($this->xml->$attr->count() > 1) {
                $a = array();
                foreach ($this->xml->$attr as $node) {
                    array_push($a, strval($node));
                }
            } else {
                $a = strval($this->xml->$attr);
            }
        }
        if (is_null($a)) {
            return;
        }
        if (!is_null($ca)) {
            $this->activity->$ca->$attr = $a;
        } else {
            $this->activity->$attr = $a;
        }
    }

    private function parse_as_langstr($attr) {
        $arr = array();
        foreach ($attr as $node) {
            $arr[strval($node['lang'])] = strval($node);
        }
        return (object)$arr;
    }

    private function parse_interaction_extensions() {
        $this->extensions = new stdClass();
        $crp = (isset($this->xml->correctResponsePatterns->correctResponsePattern)) ? $this->xml->correctResponsePatterns->correctResponsePattern : null;
        if (!is_null($crp)) {
            $cra = array();
            foreach ($crp as $cr) {
                array_push($cra, strval($cr));
            }
            $this->activity->definition->correctResponsesPattern = array(implode("[,]", $cra));
            $this->extensions->correctResponsesPattern = array(implode("[,]", $cra));
        }
        $componentnames = array();
        switch ($this->activity->definition->interactionType) {
            case 'choice':
            case 'multiple-choice':
            case 'sequencing':
            case 'true-false':
                array_push($componentnames, 'choices');
                break;
            case 'likert':
                array_push($componentnames, 'scale');
                break;
            case 'matching':
                array_push($componentnames, 'source');
                array_push($componentnames, 'target');
                break;
        }
        foreach ($componentnames as $components) {
            if (isset($this->xml->$components->component)) {
                $comparray = array();
                foreach ($this->xml->$components->component as $compnode) {
                    $compobject = new stdClass();
                    if (isset($compnode->id)) {
                        $compobject->id = strval($compnode->id);
                    } else {
                        continue;
                    }
                    if (isset($compnode->description)) {
                        $compobject->description = $this->parse_as_langstr($compnode->description);
                    }
                    array_push($comparray, $compobject);
                }
                $this->activity->definition->$components = $comparray;
                $this->extensions->$components = $comparray;
            }
        }
        return;
    }

    private function create_db_object() {
        $this->dbobject = new stdClass();
        $this->dbobject->activity_id = $this->activity->id;
        if (isset($this->metaurl)) {
            $this->dbobject->metaurl = $this->metaurl;
        }
        $this->dbobject->known = 1;
        $this->dbobject->name = (isset($this->activity->definition->name)) ? json_encode($this->activity->definition->name) : null;
        $this->dbobject->description = (isset($this->activity->definition->description)) ? json_encode($this->activity->definition->description) : null;
        $this->dbobject->type = (isset($this->activity->definition->type)) ? $this->activity->definition->type : null;
        $this->dbobject->interactionType = (isset($this->activity->definition->interactionType)) ? $this->activity->definition->interactionType : null;
        $this->dbobject->extensions = (isset($this->extensions)) ? json_encode($this->extensions) : null;
    }
}