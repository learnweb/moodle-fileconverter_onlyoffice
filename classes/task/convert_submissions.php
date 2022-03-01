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
 * A scheduled task.
 *
 * @package    fileconverter_onlyoffice
 * @copyright  2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 *             based on code by Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_onlyoffice\task;

use core\task\scheduled_task;

/**
 * Simple task to convert submissions to pdf in the background.
 * @copyright  2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 *             based on code by Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convert_submissions extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('preparesubmissionsforannotation', 'fileconverter_onlyoffice');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $DB;
        mtrace('onlyoffice: Processing pending document conversions');

        $params = array(
            'converter' => '\fileconverter_onlyoffice\converter',
            'status' => '1'
        );
        $pendingconversions = $DB->get_recordset('file_conversion', $params, 'sourcefileid DESC', 'sourcefileid, targetformat');

        $fs = get_file_storage();
        foreach ($pendingconversions as $pendingconversion) {

            $file = $fs->get_file_by_id($pendingconversion->sourcefileid);
            if ($file) {
                mtrace('onlyoffice: Processing conversions for file id: ' . $pendingconversion->sourcefileid);
                $conversions = \core_files\conversion::get_conversions_for_file($file, $pendingconversion->targetformat);

                mtrace('onlyoffice: Found: ' . count($conversions)
                    . ' conversions for file id: ' . $pendingconversion->sourcefileid);

                foreach ($conversions as $conversion) {
                    $converter = new \fileconverter_onlyoffice\converter();
                    $converter->poll_conversion_status($conversion);
                }
            }
        }

        $pendingconversions->close();
    }

}
