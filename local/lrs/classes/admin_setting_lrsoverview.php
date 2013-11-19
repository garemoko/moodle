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
 * Special class for overview of Learning Record Store.
 *
 * @author Dan Marsden
 */
class local_lrs_admin_setting_lrsoverview extends admin_setting {

    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct('webservicesoverviewui',
            get_string('webservicesoverview', 'webservice'), '', '');
    }

    /**
     * Always returns true, does nothing
     *
     * @return true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Always returns true, does nothing
     *
     * @return true
     */
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Always returns '', does not write anything
     *
     * @return string Always returns ''
     */
    public function write_setting($data) {
        // do not write any setting
        return '';
    }

    /**
     * Builds the XHTML to display the control
     *
     * @param string $data Unused
     * @param string $query
     * @return string
     */
    public function output_html($data, $query='') {
        global $CFG, $OUTPUT;

        $return = "";
        $brtag = html_writer::empty_tag('br');

        // One system controlling Moodle with Token.
        $table = new html_table();
        $table->head = array(get_string('step', 'webservice'), get_string('status'),
            get_string('description'));
        $table->colclasses = array('leftalign step', 'leftalign status', 'leftalign description');
        $table->id = 'onesystemcontrol';
        $table->attributes['class'] = 'admintable wsoverview generaltable';
        $table->data = array();

        $return .= $brtag . get_string('lrssteps', 'local_lrs')
            . $brtag . $brtag;

        // 1. Enable Web Services.
        $row = array();
        $url = new moodle_url("/admin/search.php?query=enablewebservices");
        $row[0] = "1. " . html_writer::tag('a', get_string('enablews', 'local_lrs'),
                array('href' => $url));
        $status = html_writer::tag('span', get_string('no'), array('class' => 'statuscritical'));
        if ($CFG->enablewebservices) {
            $status = get_string('yes');
        }
        $row[1] = $status;
        $row[2] = get_string('enablewsdescription', 'local_lrs');
        $table->data[] = $row;

        // 2. Enable protocols.
        $row = array();
        $url = new moodle_url("/admin/settings.php?section=webserviceprotocols");
        $row[0] = "2. " . html_writer::tag('a', get_string('enablerest', 'local_lrs'),
                array('href' => $url));
        $status = html_writer::tag('span', get_string('none'), array('class' => 'statuscritical'));

        // Retrieve activated protocol
        $activeprotocols = empty($CFG->webserviceprotocols) ?
            array() : explode(',', $CFG->webserviceprotocols);
        if (in_array("rest", $activeprotocols)) {
            $status = get_string('yes');
        } else {
            $status = html_writer::tag('span', get_string('no'), array('class' => 'statuscritical'));
        }
        $row[1] = $status;
        $row[2] = get_string('enablerestdescription', 'local_lrs');
        $table->data[] = $row;

        // TODO: Check users have capability to use the web services.

        $return .= html_writer::table($table);

        return highlight($query, $return);
    }

}