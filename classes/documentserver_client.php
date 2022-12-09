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
 * Mapper for document server api methods.
 *
 * @package    fileconverter_onlyoffice
 * @copyright  2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_onlyoffice;

use coding_exception;
use curl;
use Firebase\JWT\JWT;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Mapper for document server api methods.
 *
 * @package    fileconverter_onlyoffice
 * @copyright  2019 Jan Dageförde, University of Münster <jan.dagefoerde@ercis.uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class documentserver_client {

    /** @var curl $curl */
    protected $curl;
    /**
     * Private OnlyOfice document server URL
     * @var string
     */
    private $documentserverhost;

    /**
     * @var string|false optional secret for communication.
     */
    private $secret;

    /**
     * Initialise the client.
     * @param string $documentserverhost Private OnlyOfice document server URL
     */
    public function __construct(string $documentserverhost) {
        $this->documentserverhost = rtrim($documentserverhost, '/');
        $this->curl = new \curl();
        $this->secret = get_config('fileconverter_onlyoffice', 'secret');
    }

    public function request_conversion($params) {
        $endpoint = $this->documentserverhost . '/ConvertService.ashx';
        if ($this->secret) {
            $body = json_encode(['token' => JWT::encode($params, $this->secret, 'HS256')]);
        } else {
            $body = json_encode($params);
        }
        $this->curl->setHeader('Content-type: application/json');
        $this->curl->setHeader('Accept: application/json');
        $response = $this->curl->post($endpoint, $body);

        if ($this->curl->errno != 0) {
            throw new coding_exception($this->curl->error, $this->curl->errno);
        }

        $json = json_decode($response);

        if (!empty($json->error)) {
            if (is_int($json->error)) {
                if ($json->error == -8) {
                    if ($this->secret) {
                        throw new coding_exception("Invalid secret configured!");
                    } else {
                        throw new coding_exception("The server requires a secret. Please set it in the plugin settings.");
                    }
                }
                throw new coding_exception("Error $json->error, see https://api.onlyoffice.com/editors/conversionapi#error-codes . Response was " . $response);
            } else {
                throw new coding_exception($json->error->code . ': ' . $json->error->message . '. Response was: '.$response);
            }
        }
        return $json;
    }
}
