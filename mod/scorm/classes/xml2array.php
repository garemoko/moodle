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
 * mod_scorm_xml2array class
 *
 * @package    mod_scorm
 * @author     Dan Marsden <dan@danmarsden.com>
 * @copyright  2013 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Grab some XML data, either from a file, URL, etc. however you want. Assume storage in $strYourXML;
 *     $objXML = new mod_scorm_xml2array();
 *     $arr = $objXML->parse($strYourXML);
 *     print_r($arr); //print it out, or do whatever!
 *
 * @package    mod_scorm
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_scorm_xml2array {

    protected $arr = array();
    protected $resparser;
    protected $strdata;

    /**
     * Parse an XML text string and create an array tree that represent the XML structure.
     *
     * @param string $strinput The XML string
     * @return array
     */
    public function parse($strinput) {
        $this->resparser = xml_parser_create ('UTF-8');
        xml_set_object($this->resparser, $this);
        xml_set_element_handler($this->resparser, "tagopen", "tagclosed");

        xml_set_character_data_handler($this->resparser, "tagdata");

        $this->strdata = xml_parse($this->resparser, $strinput);
        if (!$this->strdata) {
            die(sprintf("XML error: %s at line %d",
                xml_error_string(xml_get_error_code($this->resparser)),
                xml_get_current_line_number($this->resparser)));
        }

        xml_parser_free($this->resparser);

        return $this->arr;
    }

    private function tagopen($parser, $name, $attrs) {
        $tag = array("name" => $name, "attrs" => $attrs);
        array_push($this->arr, $tag);
    }

    private function tagdata($parser, $tagdata) {
        if (trim($tagdata)) {
            if (isset($this->arr[count($this->arr) - 1]['tagData'])) {
                $this->arr[count($this->arr) - 1]['tagData'] .= $tagdata;
            } else {
                $this->arr[count($this->arr) - 1]['tagData'] = $tagdata;
            }
        }
    }

    private function tagclosed($parser, $name) {
        $this->arr[count($this->arr) - 2]['children'][] = $this->arr[count($this->arr) - 1];
        array_pop($this->arr);
    }
}
