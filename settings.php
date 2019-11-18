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
 * Plugin administration pages are defined here.
 *
 * @package     fileconverter_onlyoffice
 * @category    admin
 * @copyright   2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Basic settings.
    $settings->add(new admin_setting_configtext('fileconverter_onlyoffice/publicurl',
        get_string('settings:publicurl', 'fileconverter_onlyoffice'),
        get_string('settings:publicurl_help', 'fileconverter_onlyoffice'),
        ''));

    $settings->add(new admin_setting_configtext('fileconverter_onlyoffice/privateurl',
        get_string('settings:privateurl', 'fileconverter_onlyoffice'),
        get_string('settings:privateurl_help', 'fileconverter_onlyoffice'),
        ''));
}
