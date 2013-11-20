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
 * @author     Dan Marsden <dan@danmarsden.com>
 * @copyright  2013 Dan Marsden
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    // Enable mobile web service
    $temp = new admin_settingpage('local_lrs', new lang_string('pluginname', 'local_lrs'));
    $temp->add(new local_lrs_admin_setting_lrsoverview());
    $ADMIN->add('localplugins', $temp);
}
