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
 * Plugin functions
 *
 * @package     fileconverter_onlyoffice
 * @copyright   2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core_files\conversion;

/**
 * Serve the submitted files to the OnlyOffice document server.
 *
 * Requests to this endpoint are not authenticated as they will be made by the OnlyOffice document server. We have no way of
 * entering any Moodle credentials there. Instead, we allow access if ALL of the following conditions are met and hope that will
 * be sufficient:
 * - Correct contenthash (via first element of path),
 * - Correct sourcefileid (via itemid),
 * - Correct timing (file must not yet be processed).
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function fileconverter_onlyoffice_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB;

    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'original') {
        return false;
    }

    // Requests to this endpoint are not authenticated (see docblock for details).

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.
    $contenthash = array_shift($args); // Second item in the array.

    // Look for a fitting conversion.
    $params = array(
        'converter' => '\fileconverter_onlyoffice\converter',
        'status0' => conversion::STATUS_PENDING,
        'status1' => conversion::STATUS_IN_PROGRESS,
        'sourcefileid' => $itemid,
    );

    $pendingconversion = $DB->get_record_sql('SELECT sourcefileid, targetformat
                                                    FROM {file_conversion}
                                                    WHERE converter = :converter
                                                    AND status IN (:status0, :status1)
                                                    AND sourcefileid = :sourcefileid', $params);

    if (!$pendingconversion) {
        // Conversion not found or already completed.
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file_by_id($itemid);
    if (!$file) {
        return false;
    }

    if (!$file->get_contenthash() === $contenthash) {
        // Someone might be trying to guess file names -- $contenthash is checked to prevent this.
        return false;
    }

    // We can now send the file back to the document server that has made the request - cache lifetime of 1 hour.
    send_stored_file($file, 3600, 0, $forcedownload, $options);
}
