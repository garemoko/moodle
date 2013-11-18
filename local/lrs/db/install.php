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
 * Web service local plugin lrs install code.
 *
 * @package    local_lrs
 * @copyright  2012 Jamie Smith
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_local_lrs_install() {
    global $CFG, $DB, $OUTPUT;

    // Do this every time just to make sure the correct permissions are in place.
    require_once("$CFG->dirroot/local/lrs/locallib.php");
    local_lrs_set_role_permission_overrides();

    return true;
}
