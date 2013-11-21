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
 * lrs service server implementation.
 * Borrowed from Moodle webservice_rest_server class and modified to suit lrs requirements.
 *
 * @package    local lrs
 * @copyright  2012 Jamie Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_lrs_webservice_rest_server extends webservice_base_server {

    /** @var string return header response code */
    protected $responsecode;
    /** @var boolean encode response as json string */
    protected $responseencode;
    protected $requestmethod;

    /**
     * Contructor
     *
     * @param string $authmethod authentication method of the web service (WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN, ...)
     */
    public function __construct($authmethod) {
        parent::__construct($authmethod);
        $this->wsname = 'rest';
        $this->responsecode = '200';
        $this->responseencode = true;
    }

    /**
     * This method parses the $_POST and $_GET superglobals then
     * any body params and looks for
     * the following information:
     *  1/ Authorization token
     *  2/ functionname via get_functionname method
     */
    protected function parse_request() {

        // Retrieve and clean the POST/GET parameters from the parameters specific to the server.
        parent::set_web_service_call_settings();

        // Get GET and POST parameters.
        $methodvariables = array_merge($_GET, $_POST, $this->get_headers());
        $this->requestmethod = (isset($methodvariables['method'])) ? $methodvariables['method'] : $_SERVER['REQUEST_METHOD'];
        if ($this->requestmethod == 'OPTIONS') {
            $this->send_options();
        }

        // now how about PUT/POST bodies? These override any existing parameters.
        $body = @file_get_contents('php://input');
        if (!isset($methodvariables['content'])) {
            $methodvariables['content'] = $body;
        }
        if ($bodyparams = json_decode($body)) {
            foreach ($bodyparams as $paramname => $paramvalue) {
                $methodvariables[$paramname] = stripslashes($paramvalue);
            }
        } else {
            $bodyparams = array();
            parse_str($body, $bodyparams);
            foreach ($bodyparams as $paramname => $paramvalue) {
                $methodvariables[$paramname] = stripslashes($paramvalue);
            }
        }

        // Determine Authentication method to use (WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN is default)
        // Simple token (as used in Bookmarklet method) and Basic authentication is supported at this time.
        if (isset($methodvariables['Authorization'])) {
            // TODO: Add support for OAuth authentication. That should really be a web service addition so we can call it here.
            if (substr($methodvariables['Authorization'], 0, 5) == 'Basic') {
                $userauth = explode(":", base64_decode(substr($methodvariables['Authorization'], 6)));
                if (is_array($userauth) && count($userauth) == 2) {
                    $this->username = $userauth[0];
                    $this->password = $userauth[1];
                    $this->authmethod = WEBSERVICE_AUTHMETHOD_USERNAME;
                }
            } else {
                $this->token = isset($methodvariables['Authorization']) ? $methodvariables['Authorization'] : null;
            }
        }
        unset($methodvariables['Authorization']);
        $this->parameters = $methodvariables;
        $this->functionname = $this->get_functionname();
    }

    /**
     * Try to sort out headers for people who aren't running apache.
     */
    public static function get_headers() {
        if (function_exists('apache_request_headers')) {
            // we need this to get the actual Authorization: header
            // because apache tends to tell us it doesn't exist.
            return apache_request_headers();
        }
        // otherwise we don't have apache and are just going to have to hope
        // that $_SERVER actually contains what we need.
        $out = array();
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == "HTTP_") {
                // this is chaos, basically it is just there to capitalize the first
                // letter of every word that is not an initial HTTP and strip HTTP
                // code from przemek.
                $key = str_replace(
                    " ",
                    "-",
                    ucwords(strtolower(str_replace("_", " ", substr($key, 5))))
                );
                $out[$key] = $value;
            }
        }
        return $out;
    }

    protected function send_options() {
        header("HTTP/1.0 200 OK");
        header('Content-type: text/plain');
        header('Content-length: 0');
        header('Connection: Keep Alive');
        header('Keep Alive: timeout=2, max=100'); // TODO: What is most appropriate? Is this necessary?
        header('Access-Control-Allow-Origin: *'); // TODO: Decide how or whether or not to limit Xdomains.
        header('Access-Control-Allow-Methods: PUT, DELETE, POST, GET, OPTIONS'); // TODO: Should we base this on specific request and restrict based on endpoint target/request?
        header('Access-Control-Allow-Headers: authorization,content-type'); // TODO: Should we base this on specific request and restrict based on endpoint target/request?
        header('Access-Control-Max-Age: 1728000'); // TODO: What's appropriate here?
        exit();
    }

    /**
     * Send the result of function call to the WS client.
     * If an exception is caught, ensure a failure response code is sent.
     */
    protected function send_response() {

        // Check that the returned values are valid.
        try {
            if ($this->function->returns_desc != null) {
                $validatedvalues = external_api::clean_returnvalue($this->function->returns_desc, $this->returns);
            } else {
                $validatedvalues = null;
            }
        } catch (Exception $ex) {
            $exception = $ex;
        }

        if (!empty($exception)) {
            $response = $this->generate_error($exception);
            if ($this->responsecode == '200') {
                $this->responsecode = '400';
            }
        } else {
            $response = ($this->function->returns_desc instanceof external_value) ? $validatedvalues : json_encode($validatedvalues);
        }
        $this->send_headers();
        echo $response;
    }

    /**
     * Send the error information to the WS client.
     * Note: the exception is never passed as null,
     *       it only matches the abstract function declaration.
     * @param exception $ex the exception that we are sending
     */
    protected function send_error($ex=null) {
        $this->send_headers(true);
        echo $this->generate_error($ex);
    }

    /**
     * Build the error information matching the JSON format
     * @param exception $ex the exception we are converting in the server rest format
     * @return string the error in JSON format
     */
    protected function generate_error($ex) {
        $errorobject = new stdClass;
        $errorobject->exception = get_class($ex);
        $errorobject->errorcode = $ex->errorcode;
        $errorobject->message = $ex->getMessage();
        if (debugging() and isset($ex->debuginfo)) {
            $errorobject->debuginfo = $ex->debuginfo;
        }
        $error = json_encode($errorobject);
        return $error;
    }

    /**
     * Internal implementation - sending of page headers.
     * @param boolean $iserror send error header with 400 response code if not already defined
     */
    protected function send_headers($iserror=false) {
        if ($iserror && $this->responsecode == '200') {
            $this->responsecode = '400';
        }
        header("HTTP/1.0 ".$this->responsecode, true, $this->responsecode);
        header('Access-Control-Allow-Origin: *'); // TODO: Decide how or whether or not to limit Xdomains.
        header('Content-type: application/json');
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header('Pragma: no-cache');
        header('Accept-Ranges: none');
    }

    /**
     * Internal implementation - get function name to execute
     * as part of webservice request.
     * @return string name of external function
     */
    protected function get_functionname() {
        global $SCRIPT;
        /*
         * Get the arguments passed to endpoint.
         * Borrowed from weblib.php / get_file_arguments().
         * We can't use get_file_arguments as it checks optional_param('file');
         */
        $relativepath = false;

        // then try extract file from the slasharguments
        if (stripos($_SERVER['SERVER_SOFTWARE'], 'iis') !== false) {
            // NOTE: ISS tends to convert all file paths to single byte DOS encoding,
            //       we can not use other methods because they break unicode chars,
            //       the only way is to use URL rewriting
            if (isset($_SERVER['PATH_INFO']) and $_SERVER['PATH_INFO'] !== '') {
                // check that PATH_INFO works == must not contain the script name
                if (strpos($_SERVER['PATH_INFO'], $SCRIPT) === false) {
                    $relativepath = clean_param(urldecode($_SERVER['PATH_INFO']), PARAM_PATH);
                }
            }
        } else {
            // all other apache-like servers depend on PATH_INFO
            if (isset($_SERVER['PATH_INFO'])) {
                if (isset($_SERVER['SCRIPT_NAME']) and strpos($_SERVER['PATH_INFO'], $_SERVER['SCRIPT_NAME']) === 0) {
                    $relativepath = substr($_SERVER['PATH_INFO'], strlen($_SERVER['SCRIPT_NAME']));
                } else {
                    $relativepath = $_SERVER['PATH_INFO'];
                }
                $relativepath = strtolower(clean_param($relativepath, PARAM_PATH));
            }
        }

        $functionname = '';
        unset($this->parameters['method']);

        if (substr($relativepath, 0, 11) == '/statements') {
            switch ($this->requestmethod) {
                case  'PUT':
                    $functionname = 'store_statement';
                    $this->responseencode = false;
                    break;
                case  'POST':
                    $functionname = 'store_statement';
                    break;
                default:
                    $functionname = 'fetch_statement';
                    break;
            }
        } else if (substr($relativepath, 0, 17) == '/activities/state') {
            $this->responseencode = false;
            switch ($this->requestmethod) {
                case  'PUT':
                    $functionname = 'store_activity_state';
                    break;
                case  'DELETE':
                    $functionname = 'delete_activity_state';
                    break;
                default:
                    $functionname = 'fetch_activity_state';
                    break;
            }
        }
        if (!empty($functionname)) {
            $this->get_functionparams($functionname);
            return 'local_lrs_'.$functionname;
        }
        return null;
    }

    /**
     * Internal implementation - sets parameters to extract from all parameters
     * passed as part of webservice request based on external function called.
     * @param string $functionname name of external function
     */
    protected function get_functionparams($functionname) {
        $paramkeys = array('moodle_mod', 'moodle_mod_id');
        switch($functionname) {
            case 'fetch_statement':
                $paramkeys = array_merge($paramkeys, array('statementId', 'registration'));
                break;
            case 'store_statement':
                $paramkeys = array_merge($paramkeys, array('content', 'statementId', 'registration'));
                break;
            case 'store_activity_state':
                $paramkeys = array_merge($paramkeys, array('content', 'activityId', 'actor', 'registration', 'stateId'));
                break;
            case 'delete_activity_state':
                $paramkeys = array_merge($paramkeys, array('activityId', 'actor', 'registration'));
                break;
            case 'fetch_activity_state':
                $paramkeys = array_merge($paramkeys, array('activityId', 'actor', 'registration', 'stateId', 'since'));
                break;
            case 'delete_activity_state':
                $paramkeys = array_merge($paramkeys, array('activityId', 'actor', 'registration'));
                break;

        }
        $parameters = array();
        foreach ($paramkeys as $key) {
            $parameters[$key] = (isset($this->parameters[$key])) ? $this->parameters[$key] : null;
        }
        $this->parameters = $parameters;
    }

}
