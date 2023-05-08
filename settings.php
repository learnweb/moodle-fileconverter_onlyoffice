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

global $CFG;

if ($ADMIN->fulltree) {
    // Specify the URL to the OnlyOffice document server host.
    // Must be specified in a way s. t. Moodle(!) can connect to it directly -- it is never used from the browser.
    $settings->add(new admin_setting_configtext('fileconverter_onlyoffice/internaloodsurl',
        get_string('settings:internaloodsurl', 'fileconverter_onlyoffice'),
        get_string('settings:internaloodsurl_help', 'fileconverter_onlyoffice'),
        ''));

    // Specify the URL at which the OO document server can reach the Moodle wwwroot.
    // Usually it is identical to the wwwroot, but it may vary in certain configurations (e. g., containerised setup with Docker).
    // Leave empty if it is identical to the wwwroot.
    $settings->add(new admin_setting_configtext('fileconverter_onlyoffice/internalmoodleurl',
        get_string('settings:internalmoodleurl', 'fileconverter_onlyoffice'),
        get_string('settings:internalmoodleurl_help', 'fileconverter_onlyoffice'),
        ''));

    $settings->add(new admin_setting_configpasswordunmask('fileconverter_onlyoffice/secret',
        get_string('settings:secret', 'fileconverter_onlyoffice'),
        get_string('settings:secret_help', 'fileconverter_onlyoffice'),
        ''
    ));
}
