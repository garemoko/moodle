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
 * LRS REST web service entry point.
 * For ./statements and ./activity/state endpoint access, the authentication is done via tokens.
 * For direct access, ie.: record retrieval and operations, the authentication is done via
 * copied from webservice/rest/server.php and modified to suit LRS requirements.
 *
 * @package    local_lrs
 * @copyright  2009 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * NO_DEBUG_DISPLAY - disable moodle specific debug messages and any errors in output
 */
define('NO_DEBUG_DISPLAY', true);

/**
 * NO_MOODLE_COOKIES - no cookies with web service
 */
define('NO_MOODLE_COOKIES', true);

require_once('../../config.php');
require_once('locallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

if (!webservice_protocol_is_enabled('rest')) {
    debugging('The lrs service failed because web services or the REST protocol are not enabled',
        DEBUG_DEVELOPER);
    die;
}

$server = new local_lrs_webservice_rest_server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
$server->run();
die;

