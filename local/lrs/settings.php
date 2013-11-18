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
 * Web service local plugin tcapi settings code.
 *
 * @package    local_lrs
 * @copyright  2012 Jamie Smith
 */

defined('MOODLE_INTERNAL') || die;

/*
 * For future use.
if ($hassiteconfig) {
	$settings = new admin_settingpage('local_tcapi', get_string('tcapi:settings','local_tcapi'));
	$ADMIN->add('localplugins', $settings);
	$settings->add(new admin_setting_configcheckbox('local_lrs/onoff', get_string('lrs:onoffoption','local_lrs'), get_string('lrs:onoffoptiondescr','local_lrs'), '0'));
}
*/