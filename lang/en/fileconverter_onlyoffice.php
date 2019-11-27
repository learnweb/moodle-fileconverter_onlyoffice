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
 * Plugin strings are defined here.
 *
 * @package     fileconverter_onlyoffice
 * @category    string
 * @copyright   2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'OnlyOffice document converter';
$string['settings:internaloodsurl'] = 'OnlyOffice Document Server URL';
$string['settings:internaloodsurl_help'] = 'Specify the URL at which the OnlyOffice document server can be reached *by Moodle*. The URL is never resolved in the browser, only in CURL requests by Moodle, so it will be resolved only in  the local network.';
$string['settings:internalmoodleurl'] = 'Internal Moodle URL';
$string['settings:internalmoodleurl_help'] = 'Optionally, specify the URL at which the OnlyOffice document server will be able to find Moodle (modified wwwroot). If left blank, it will use the normal wwwroot as usual. Typically, this setting does not need to be set, unless the wwwroot in the browser differs from that in the local network (which is usually the case in containerised setups).';
