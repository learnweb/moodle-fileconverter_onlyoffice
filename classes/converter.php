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
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

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
     * Class constructor
     */
    public function __construct() {
        $this->config = get_config('fileconverter_onlyoffice');
    }

    /**
     * Create AWS S3 API client.
     *
     * @param \GuzzleHttp\Handler $handler Optional handler.
     * @return \Aws\S3\S3Client
     */
    public function create_client($handler=null) {
        $connectionoptions = array(
            'version' => 'latest',
            'region' => $this->config->api_region,
            'credentials' => [
                'key' => $this->config->api_key,
                'secret' => $this->config->api_secret
            ]);

        // Allow handler overriding for testing.
        if ($handler != null) {
            $connectionoptions['handler'] = $handler;
        }

        // Only create client if it hasn't already been done.
        if ($this->client == null) {
            $this->client = new S3Client($connectionoptions);
        }

        return $this->client;
    }

    /**
     * When an exception occurs get and return
     * the exception details.
     *
     * @param \Aws\Exception $exception The thrown exception.
     * @return string $details The details of the exception.
     */
    private function get_exception_details($exception) {
        $message = $exception->getMessage();

        if (get_class($exception) !== 'S3Exception') {
            return "Not a S3 exception : $message";
        }

        $errorcode = $exception->getAwsErrorCode();

        $details = ' ';

        if ($message) {
            $details .= "ERROR MSG: " . $message . "\n";
        }

        if ($errorcode) {
            $details .= "ERROR CODE: " . $errorcode . "\n";
        }

        return $details;
    }

    /**
     * Check if the plugin has the required configuration set.
     *
     * @param \fileconverter_onlyoffice\converter $converter
     * @return boolean $isset Is all configuration options set.
     */
    private static function is_config_set(\fileconverter_onlyoffice\converter $converter) {
        $iscorrect = true;

        if (empty($converter->config->api_key) ||
            empty($converter->config->api_secret) ||
            empty($converter->config->api_region)) {
            $iscorrect = false;
        }
        return $iscorrect;
    }

    /**
     * Delete the converted file from the output bucket in S3.
     *
     * @param string $objectkey The key of the object to delete.
     */
    private function delete_converted_file($objectkey) {
        $deleteparams = array(
            'Bucket' => $this->config->s3_output_bucket, // Required.
            'Key' => $objectkey, // Required.
        );

        $s3client = $this->create_client();
        $s3client->deleteObject($deleteparams);

    }

    private function format_request_parameters(\stored_file $file, string $targetformat): array {
        //$file->
        $sourceformat = 'docx'; // TODO get extension from filename.

        $params = [
            'async' => true,
            'codePage' => self::CODEPAGE_UTF8,
            'filetype' => $sourceformat,
            'key' => $file->get_contenthash(), // TODO must be unique, add a moodly prefix.
            'outputtype' => $targetformat,
            'url' => '', // TODO
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

        // TODO Check connection/permissions.
        $bucket = $converter->config->s3_input_bucket;
        $permissions = self::have_bucket_permissions($converter, $bucket);
        if (!$permissions->success) {
            debugging('fileconverter_onlyoffice permissions failure on input bucket');
            return false;
        }

        // Check output bucket permissions.
        $bucket = $converter->config->s3_output_bucket;
        $permissions = self::have_bucket_permissions($converter, $bucket);
        if (!$permissions->success) {
            debugging('fileconverter_onlyoffice permissions failure on output bucket');
            return false;
        }

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
        } catch (OOException $e) {
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

        $requestparams = $this->format_request_parameters($file,
            $conversion->get('targetformat'));

        $file = $conversion->get_sourcefile();
        $tmpdir = make_request_directory();
        $saveas = $tmpdir . '/' . $file->get_pathnamehash();

        // Re-send original request for conversion to OnlyOffice document server, thus inquiring for status changes.
        $ooclient = $this->create_client();
        try {
            $result = $ooclient->request_conversion($requestparams);
            if ($result['endConvert'] === false) {
                $conversion->set('status', conversion::STATUS_IN_PROGRESS);
                $this->status = conversion::STATUS_IN_PROGRESS;
            } else {
                $result = $s3client->getObject($downloadparams);// side-effect: stores into $saveas. TODO adapt
                $conversion->store_destfile_from_path($saveas);
                $this->status = conversion::STATUS_COMPLETE;
                $conversion->set('status', conversion::STATUS_COMPLETE);
                //$this->delete_converted_file($file->get_pathnamehash());

            }
        } catch (OOException $e) {
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
