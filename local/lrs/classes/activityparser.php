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

    var $objectType;
    var $xml;
    var $metaurl;
    var $activity;
    var $extensions;
    var $jsonObject;
    var $dbObject;

    function __construct ($type) {
        $this->objectType = $type;
        $this->activity = new stdClass();
    }

    function parseObject($xml) {
        $this->xml = $xml;
        $this->activity = new stdClass();
        $this->activity->definition = new stdClass();
        $this->parseAttrByName('id');
        $this->parseAttrByName('type', false, 'definition');
        $this->parseAttrByName('name', true, 'definition');
        $this->parseAttrByName('description', true, 'definition');
        $this->parseAttrByName('interactionType', false, 'definition');
        if ($this->activity->definition->type == 'cmi.interaction' && isset($this->activity->definition->interactionType))
            $this->parseInteractionExtensions();
        $this->jsonObject = json_encode($this->activity);
        $this->createDbObject();
    }

    function parseAttrByName ($attr,$lang=false,$ca=null) {
        $a = null;
        if (isset($this->xml[$attr]))
            $a = strval($this->xml[$attr]);
        elseif (isset($this->xml->$attr)) {
            if ($lang)
                $a = $this->parseAsLangStr($this->xml->$attr);
            elseif ($this->xml->$attr->count() > 1) {
                $a = array();
                foreach ($this->xml->$attr as $node)
                    array_push($a,strval($node));
            } else
                $a = strval($this->xml->$attr);
        }
        if (is_null($a))
            return;
        if (!is_null($ca))
            $this->activity->$ca->$attr = $a;
        else
            $this->activity->$attr = $a;
    }

    function parseAsLangStr ($attr) {
        $arr = array();
        foreach ($attr as $node)
            $arr[strval($node['lang'])] = strval($node);
        return (object)$arr;
    }

    function parseInteractionExtensions () {
        $this->extensions = new stdClass();
        $crp = (isset($this->xml->correctResponsePatterns->correctResponsePattern)) ? $this->xml->correctResponsePatterns->correctResponsePattern : null;
        if (!is_null($crp)) {
            $cra = array();
            foreach ($crp as $cr)
                array_push($cra,strval($cr));
            $this->activity->definition->correctResponsesPattern = array(implode("[,]",$cra));
            $this->extensions->correctResponsesPattern = array(implode("[,]",$cra));
        }
        $componentNames = array();
        switch ($this->activity->definition->interactionType) {
            case 'choice':
            case 'multiple-choice':
            case 'sequencing':
            case 'true-false':
                array_push($componentNames,'choices');
                break;
            case 'likert':
                array_push($componentNames,'scale');
                break;
            case 'matching':
                array_push($componentNames,'source');
                array_push($componentNames,'target');
                break;
        }
        foreach ($componentNames as $components) {
            if (isset($this->xml->$components->component)) {
                $compArray = array();
                foreach($this->xml->$components->component as $compNode) {
                    $compObject = new stdClass();
                    if (isset($compNode->id))
                        $compObject->id = strval($compNode->id);
                    else
                        continue;
                    if (isset($compNode->description))
                        $compObject->description = $this->parseAsLangStr($compNode->description);
                    array_push($compArray,$compObject);
                }
                $this->activity->definition->$components = $compArray;
                $this->extensions->$components = $compArray;
            }
        }
        return;
    }

    function createDbObject() {
        $this->dbObject = new stdClass();
        $this->dbObject->activity_id = $this->activity->id;
        if (isset($this->metaurl))
            $this->dbObject->metaurl = $this->metaurl;
        $this->dbObject->known = 1;
        $this->dbObject->name = (isset($this->activity->definition->name)) ? json_encode($this->activity->definition->name) : null;
        $this->dbObject->description = (isset($this->activity->definition->description)) ? json_encode($this->activity->definition->description) : null;
        $this->dbObject->type = (isset($this->activity->definition->type)) ? $this->activity->definition->type : null;
        $this->dbObject->interactionType = (isset($this->activity->definition->interactionType)) ? $this->activity->definition->interactionType : null;
        $this->dbObject->extensions = (isset($this->extensions)) ? json_encode($this->extensions) : null;
    }

}