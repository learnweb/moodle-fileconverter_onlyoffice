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
use Firebase\JWT\JWT;
use curl;

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
     * Private OnlyOfice document server Secret Token
     * @var string
     */
    private $documentserversecret;

    /**
     * Initialise the client.
     * @param string $documentserverhost Private OnlyOfice document server URL
     */
    public function __construct(string $documentserverhost, string $documentserversecret = null) {
        $this->documentserverhost = rtrim($documentserverhost, '/');
        $this->documentserversecret = $documentserversecret;
        $this->curl = new \curl();
    }

    public function request_conversion($params) {
        $endpoint = $this->documentserverhost . '/ConvertService.ashx';
        if ($this->documentserversecret ) {
             $payload = ["payload" => $params];
             $headertoken = JWT::encode($payload, $this->documentserversecret);
             $token = JWT::encode($params, $this->documentserversecret);
             $params['token'] = $token;
        }
        $callargs = json_encode($params);
        $this->curl->setHeader('Content-type: application/json');
        $this->curl->setHeader('Accept: application/json');
        if ($this->documentserversecret) {
            $this->curl->setHeader('Authorization: ' . $headertoken);
        }
        $response = $this->curl->post($endpoint, $callargs);

        if ($this->curl->errno != 0) {
            throw new coding_exception($this->curl->error, $this->curl->errno);
        }

        $json = json_decode($response);

        if (!empty($json->error)) {
            throw new coding_exception($json->error->code . ': ' . $json->error->message . '. Response was: '.$response);
        }
        return $json;
    }
}
