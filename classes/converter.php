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
 * Class for converting files between different file formats using OnlyOffice.
 *
 * @package     fileconverter_onlyoffice
 * @copyright   2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 *              based on code by Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_onlyoffice;

defined('MOODLE_INTERNAL') || die();

use \core_files\conversion;

/**
 * Class for converting files between different file formats using OnlyOffice.
 *
 * @package     fileconverter_onlyoffice
 * @copyright   2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 *              based on code by Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter implements \core_files\converter_interface {
    /** @var array $imports List of supported import file formats */
    private static $imports = [
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.ms-powerpoint',
        'html' => 'text/html',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'txt' => 'text/plain',
        'gif' => 'image/gif',
    ];

    /** @var array $export List of supported export file formats */
    private static $exports = [
        'pdf' => 'application/pdf'
    ];

    /**
     *
     * @var object Plugin configuration.
     */
    private $config;

    /**
     *
     * @var integer status of current conversion.
     * @see conversion::STATUS_PENDING
     */
    public $status;

    /**
     * @see https://github.com/ONLYOFFICE/server/blob/master/Common/sources/commondefines.js
     */
    const CODEPAGE_UTF8 = 65001;

    /**
     * @var documentserver_client
     */
    private $client = null;

    /**
     * Class constructor
     */
    public function __construct() {
        $this->config = get_config('fileconverter_onlyoffice');
    }

    /**
     * Establish access to OnlyOffice Document Server
     *
     * @return documentserver_client
     */
    public function create_client(): documentserver_client {

        // Only create client if it hasn't already been done.
        if ($this->client == null) {
            $this->client = new documentserver_client($this->config->privateurl);
        }

        return $this->client;
    }

    /**
     * Check if the plugin has the required configuration set.
     *
     * @param \fileconverter_onlyoffice\converter $converter
     * @return boolean $isset Is all configuration options set.
     */
    private static function is_config_set(\fileconverter_onlyoffice\converter $converter) {
        $iscorrect = true;

        if (empty($converter->config->privateurl)) {
            $iscorrect = false;
        }
        // The internalmoodleurl setting does not need to be checked, as it is optional.
        return $iscorrect;
    }

    private function format_request_parameters(\stored_file $file, string $targetformat): array {
        global $CFG;
        $downloadfrom = \moodle_url::make_pluginfile_url(\context_system::instance()->id, 'fileconverter_onlyoffice', 'original',
            $file->get_id(), '/'.$file->get_contenthash().'/', $file->get_filename());

        // Modify URL, as the app server may need to request things from Moodle via a different host -- consider Docker!
        if (empty($this->config->internalmoodleurl)) {
            // Setting empty -- use default wwwroot.
            $modifiedurl = $downloadfrom->out(false);
        } else {
            $internalmoodleurl = rtrim($this->config->internalmoodleurl, '/');
            $modifiedurl = str_replace($CFG->wwwroot, $internalmoodleurl, $downloadfrom->out(false));
        }

        $params = [
            'async' => true,
            'codePage' => self::CODEPAGE_UTF8,
            'key' => 'moodle_'.$file->get_contenthash(),
            'outputtype' => $targetformat,
            'url' => $modifiedurl,
        ];
        return $params;
    }

    /**
     * Whether the plugin is configured and requirements are met.
     *
     * @return  bool
     */
    public static function are_requirements_met() {
        $converter = new \fileconverter_onlyoffice\converter();

        // First check that we have the basic configuration settings set.
        if (!self::is_config_set($converter)) {
            debugging('fileconverter_onlyoffice configuration not set');
            return false;
        }

        $converter->create_client();

        return true;
    }

    /**
     * Convert a document to a new format and return a conversion object relating to the conversion in progress.
     *
     * @param   \core_files\conversion $conversion The file to be converted
     * @return  $this
     */
    public function start_document_conversion(\core_files\conversion $conversion) {
        $file = $conversion->get_sourcefile();

        $requestparams = $this->format_request_parameters($file, $conversion->get('targetformat'));

        // Send request for conversion to OnlyOffice document server.
        $ooclient = $this->create_client();
        try {
            $result = $ooclient->request_conversion($requestparams);
            $conversion->set('status', conversion::STATUS_IN_PROGRESS);
            $this->status = conversion::STATUS_IN_PROGRESS;
        } catch (OnlyOfficeException $e) {
            $conversion->set('status', conversion::STATUS_FAILED);
            $this->status = conversion::STATUS_FAILED;
        }
        $conversion->update();

        // Trigger event.
        list($context, $course, $cm) = get_context_info_array($file->get_contextid());
        $eventinfo = array(
            'context' => $context,
            'courseid' => $course->id,
            'other' => array(
                'sourcefileid' => $conversion->get('sourcefileid'),
                'targetformat' => $conversion->get('targetformat'),
                'id' => $conversion->get('id'),
                'status' => $this->status
            ));
        $event = \fileconverter_onlyoffice\event\poll_conversion_status::create($eventinfo);
        $event->trigger();

        return $this;
    }

    /**
     * Workhorse method: Poll an existing conversion for status update. If conversion has succeeded, download the result.
     *
     * @param   conversion $conversion The file to be converted
     * @return  $this;
     */
    public function poll_conversion_status(conversion $conversion) {

        // If conversion is complete or failed return early.
        if ($conversion->get('status') == conversion::STATUS_COMPLETE
            || $conversion->get('status') == conversion::STATUS_FAILED) {
            return $this;
        }

        $file = $conversion->get_sourcefile();

        $requestparams = $this->format_request_parameters($file, $conversion->get('targetformat'));

        $file = $conversion->get_sourcefile();
        $tmpdir = make_request_directory();
        $saveas = $tmpdir . '/' . $file->get_pathnamehash();

        // Re-send original request for conversion to OnlyOffice document server, thus inquiring for status changes.
        $ooclient = $this->create_client();
        try {
            $result = $ooclient->request_conversion($requestparams);
            if ($result->endConvert === false) {
                $conversion->set('status', conversion::STATUS_IN_PROGRESS);
                $this->status = conversion::STATUS_IN_PROGRESS;
            } else {
                // File is ready! Retrieve it from DS and store it in filedir.
                download_file_content($result->fileUrl, null, null, false, 300, 20, false, $saveas);
                $conversion->store_destfile_from_path($saveas);

                // Update persistent.
                $this->status = conversion::STATUS_COMPLETE;
                $conversion->set('status', conversion::STATUS_COMPLETE);
            }
        } catch (OnlyOfficeException $e) {
            $conversion->set('status', conversion::STATUS_FAILED);
            $this->status = conversion::STATUS_FAILED;
        }
        $conversion->update();

        // Trigger event.
        list($context, $course, $cm) = get_context_info_array($file->get_contextid());
        $eventinfo = array(
            'context' => $context,
            'courseid' => $course->id,
            'other' => array(
                'sourcefileid' => $conversion->get('sourcefileid'),
                'targetformat' => $conversion->get('targetformat'),
                'id' => $conversion->get('id'),
                'status' => $this->status
            ));
        $event = \fileconverter_onlyoffice\event\poll_conversion_status::create($eventinfo);
        $event->trigger();

        return $this;

    }

    /**
     * Whether a file conversion can be completed using this converter.
     *
     * @param   string $from The source type
     * @param   string $to The destination type
     * @return  bool
     */
    public static function supports($from, $to) {
        // This is not a one-liner because of php 5.6.
        $imports = self::$imports;
        $exports = self::$exports;
        return isset($imports[$from]) && isset($exports[$to]);
    }

    /**
     * A list of the supported conversions.
     *
     * @return  string
     */
    public function get_supported_conversions() {
        $conversions = array(
            'doc', 'docx', 'rtf', 'xls', 'xlsx', 'ppt', 'pptx', 'html', 'odt', 'ods', 'txt', 'png', 'jpg', 'gif', 'pdf'
            );
        return implode(', ', $conversions);
    }
}
