<?php

declare(strict_types=1);

/**
 * Class xoctCurl
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class xoctCurl
{
    private static bool $verify_host = false;
    private static bool $verify_peer = false;

    public static function init(xoctCurlSettings $xoctCurlSettings): void
    {
        self::$ip_v4 = $xoctCurlSettings->isIpV4();
        self::$username = $xoctCurlSettings->getUsername();
        self::$password = $xoctCurlSettings->getPassword();
    }

    /**
     * @var int
     */
    protected static $r_no = 1;

    public function get(): void
    {
        $this->setRequestType(self::REQ_TYPE_GET);
        $this->execute();
    }

    public function put(): void
    {
        $this->setRequestType(self::REQ_TYPE_PUT);
        $this->execute();
    }

    public function post(): void
    {
        $this->setRequestType(self::REQ_TYPE_POST);
        $this->execute();
    }

    public function delete(): void
    {
        $this->setRequestType(self::REQ_TYPE_DELETE);
        $this->execute();
    }

    protected function execute()
    {
        static $ch;
        if (!isset($ch)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if (self::$ip_v4) {
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            }
            if ($this->getUsername() && $this->getPassword()) {
                curl_setopt($ch, CURLOPT_USERPWD, $this->getUsername() . ':' . $this->getPassword());
            }
        }

        curl_setopt($ch, CURLOPT_URL, $this->getUrl());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->getRequestType());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $this->prepare($ch);

        if ($this->getRequestContentType() !== '' && $this->getRequestContentType() !== '0') {
            $this->addHeader('Content-Type: ' . $this->getRequestContentType());
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        $this->debug($ch);
        $resp_orig = curl_exec($ch);
        if ($resp_orig === false) {
            $this->setResponseError(new xoctCurlError($ch));
        }
        $this->setResponseBody($resp_orig);
        $this->setResponseMimeType(curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $this->setResponseContentSize(curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD));
        $this->setResponseStatus(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        $i = 1000;

        xoctLog::getInstance()->write(
            'CURLINFO_CONNECT_TIME: ' . round(curl_getinfo($ch, CURLINFO_CONNECT_TIME) * $i, 2) . ' ms',
            xoctLog::DEBUG_LEVEL_1
        );
        xoctLog::getInstance()->write(
            'CURLINFO_NAMELOOKUP_TIME: ' . round(curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME) * $i, 2) . ' ms',
            xoctLog::DEBUG_LEVEL_1
        );
        xoctLog::getInstance()->write(
            'CURLINFO_REDIRECT_TIME: ' . round(curl_getinfo($ch, CURLINFO_REDIRECT_TIME) * $i, 2) . ' ms',
            xoctLog::DEBUG_LEVEL_1
        );
        xoctLog::getInstance()->write(
            'CURLINFO_STARTTRANSFER_TIME: ' . round(curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * $i, 2) . ' ms',
            xoctLog::DEBUG_LEVEL_1
        );
        xoctLog::getInstance()->write(
            'CURLINFO_PRETRANSFER_TIME: ' . round(curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME) * $i, 2) . ' ms',
            xoctLog::DEBUG_LEVEL_1
        );
        xoctLog::getInstance()->write(
            'CURLINFO_TOTAL_TIME: ' . round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * $i, 2) . ' ms',
            xoctLog::DEBUG_LEVEL_1
        );

        if ($this->getResponseStatus() > 299) {
            xoctLog::getInstance()->write('ERROR ' . $this->getResponseStatus(), xoctLog::DEBUG_LEVEL_1);
            xoctLog::getInstance()->write('Response:' . $resp_orig, xoctLog::DEBUG_LEVEL_3);

            switch ($this->getResponseStatus()) {
                case 403:
                    throw new xoctException(xoctException::API_CALL_STATUS_403, $resp_orig);
                case 401:
                    throw new xoctException(xoctException::API_CALL_BAD_CREDENTIALS);
                case 404:
                    throw new xoctException(xoctException::API_CALL_STATUS_404, $resp_orig);
                case 409:
                    throw new xoctException(xoctException::API_CALL_STATUS_409, $resp_orig);
                default:
                    throw new xoctException(xoctException::API_CALL_STATUS_500, $resp_orig);
            }
        }
        //		curl_close($ch);
    }

    public const REQ_TYPE_GET = 'GET';
    public const REQ_TYPE_POST = 'POST';
    public const REQ_TYPE_DELETE = 'DELETE';
    public const REQ_TYPE_PUT = 'PUT';
    /**
     * @var array
     */
    protected $post_fields = [];
    /**
     * @var bool
     */
    protected static $ip_v4 = false;
    /**
     * @var string
     */
    protected $url = '';
    /**
     * @var string
     */
    protected $request_type = self::REQ_TYPE_GET;
    /**
     * @var array
     */
    protected $headers = [];
    /**
     * @var string
     */
    protected $response_body = '';
    /**
     * @var string
     */
    protected $response_mime_type = '';
    /**
     * @var string
     */
    protected $response_content_size = '';
    /**
     * @var int
     */
    protected $response_status = 200;
    /**
     * @var xoctCurlError
     */
    protected $response_error;
    /**
     * @var string
     */
    protected $put_file_path = '';
    /**
     * @var string
     */
    protected $post_body = '';
    /**
     * @var string
     */
    protected static $username = '';
    /**
     * @var string
     */
    protected static $password = '';
    /**
     * @var string
     */
    protected $request_content_type = '';
    /**
     * @var xoctUploadFile[]
     */
    protected $files = [];

    public static function getErrorText($ch): string
    {
        return (new xoctCurlError($ch))->getMessage();
    }

    /**
     * @return boolean
     */
    public static function isIpV4()
    {
        return self::$ip_v4;
    }

    /**
     * @param boolean $ip_v4
     */
    public static function setIpV4($ip_v4): void
    {
        self::$ip_v4 = $ip_v4;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl($url): void
    {
        $this->url = $url;
    }

    /**
     * @return boolean
     */
    public function isVerifyHost()
    {
        return self::$verify_host;
    }

    /**
     * @param boolean $verify_host
     */
    public function setVerifyHost($verify_host): void
    {
        self::$verify_host = $verify_host;
    }

    /**
     * @return boolean
     */
    public function isVerifyPeer()
    {
        return self::$verify_peer;
    }

    /**
     * @param boolean $verify_peer
     */
    public function setVerifyPeer($verify_peer): void
    {
        self::$verify_peer = $verify_peer;
    }

    /**
     * @return string
     */
    public function getRequestType()
    {
        return $this->request_type;
    }

    /**
     * @param string $request_type
     */
    public function setRequestType($request_type): void
    {
        $this->request_type = $request_type;
    }

    /**
     * @param $string
     */
    public function addHeader($string): void
    {
        $this->headers[] = $string;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     */
    public function setHeaders($headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @return string
     */
    public function getResponseBody()
    {
        return $this->response_body;
    }

    /**
     * @param string $response_body
     */
    public function setResponseBody($response_body): void
    {
        $this->response_body = $response_body;
    }

    /**
     * @return string
     */
    public function getResponseMimeType()
    {
        return $this->response_mime_type;
    }

    /**
     * @param string $response_mime_type
     */
    public function setResponseMimeType($response_mime_type): void
    {
        $this->response_mime_type = $response_mime_type;
    }

    /**
     * @return string
     */
    public function getResponseContentSize()
    {
        return $this->response_content_size;
    }

    /**
     * @param string $response_content_size
     */
    public function setResponseContentSize($response_content_size): void
    {
        $this->response_content_size = $response_content_size;
    }

    /**
     * @return int
     */
    public function getResponseStatus()
    {
        return $this->response_status;
    }

    /**
     * @param int $response_status
     */
    public function setResponseStatus($response_status): void
    {
        $this->response_status = $response_status;
    }

    /**
     * @return xoctCurlError
     */
    public function getResponseError()
    {
        return $this->response_error;
    }

    /**
     * @param xoctCurlError $response_error
     */
    public function setResponseError($response_error): void
    {
        $this->response_error = $response_error;
    }

    /**
     * @return string
     */
    public function getPutFilePath()
    {
        return $this->put_file_path;
    }

    /**
     * @param string $put_file_path
     */
    public function setPutFilePath($put_file_path): void
    {
        $this->put_file_path = $put_file_path;
    }

    /**
     * @return string
     */
    public function getPostBody()
    {
        return $this->post_body;
    }

    /**
     * @param string $post_body
     */
    protected function setPostBody($post_body)
    {
        $this->post_body = $post_body;
    }

    /**
     * @return array
     */
    protected function getPostFields()
    {
        return $this->post_fields;
    }

    /**
     * @param array $post_fields
     */
    public function setPostFields($post_fields): void
    {
        $this->post_fields = $post_fields;
    }

    /**
     * @param $key
     * @param $value
     */
    public function addPostField($key, $value): void
    {
        $this->post_fields[$key] = $value;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return self::$username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username): void
    {
        self::$username = $username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return self::$password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password): void
    {
        self::$password = $password;
    }

    /**
     * @return string
     */
    public function getRequestContentType()
    {
        return $this->request_content_type;
    }

    /**
     * @param string $request_content_type
     */
    public function setRequestContentType($request_content_type): void
    {
        $this->request_content_type = $request_content_type;
    }

    /**
     * @return xoctUploadFile[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param xoctUploadFile[] $files
     */
    public function setFiles($files): void
    {
        $this->files = $files;
    }

    public function addFile(xoctUploadFile $xoctUploadFile): void
    {
        $this->files[] = $xoctUploadFile;
    }

    /**
     * @param $ch
     *
     * @throws xoctException
     */
    protected function preparePut($ch)
    {
        if ($this->getPostFields()) {
            $this->preparePost($ch);
        }
    }

    /**
     * @param $ch
     */
    protected function preparePost($ch)
    {
        curl_getinfo($ch, CURLINFO_HEADER_OUT);
        if ($this->getFiles() !== []) {
            curl_getinfo($ch, CURLOPT_SAFE_UPLOAD);
            foreach ($this->getFiles() as $file) {
                $this->addPostField($file->getPostVar(), $file->getCURLFile());
            }
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getPostFields());

        xoctLog::getInstance()->write('POST-Body', xoctLog::DEBUG_LEVEL_2);
        xoctLog::getInstance()->write(print_r($this->getPostFields(), true), xoctLog::DEBUG_LEVEL_2);
    }

    /**
     * @param $ch
     */
    protected function debug($ch)
    {
        $xoctLog = xoctLog::getInstance();
        $xoctLog->write('execute *************************************************', xoctLog::DEBUG_LEVEL_1);
        $xoctLog->write($this->getUrl(), xoctLog::DEBUG_LEVEL_1);
        $xoctLog->write($this->getRequestType(), xoctLog::DEBUG_LEVEL_1);
        $backtrace = "Backtrace: \n";
        foreach (debug_backtrace() as $b) {
            $backtrace .= $b['file'] . ': ' . $b["function"] . "\n";
        }
        $xoctLog->write($backtrace, xoctLog::DEBUG_LEVEL_4);
        if (xoctLog::getLogLevel() >= xoctLog::DEBUG_LEVEL_3) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, fopen(xoctLog::getFullPath(), 'ab'));
        }
    }

    /**
     * @param $ch
     */
    protected function prepare($ch)
    {
        switch ($this->getRequestType()) {
            case self::REQ_TYPE_PUT:
                $this->preparePut($ch);
                break;
            case self::REQ_TYPE_POST:
                $this->preparePost($ch);
                break;
        }
    }

    /**
     * @param $ch
     */
    protected function buildBoundary($ch)
    {
        //		$disallow = array( "\0", "\"", "\r", "\n" );
        $disallow = [];
        $body = [];
        // build normal parameters
        foreach ($this->getPostFields() as $k => $v) {
            $k = str_replace($disallow, "", $k);
            $body[] = implode("\r\n", [
                "Content-Disposition: form-data; name=\"$k\"",
                "",
                filter_var($v),
            ]);
        }

        // build file parameters
        foreach ($this->getFiles() as $k => $v) {
            $k = $v->getTitle();
            $v = $v->getPath();

            switch (true) {
                case false === $v = realpath(filter_var($v)):
                case !is_file($v):
                case !is_readable($v):
                    continue 2; // or return false, throw new InvalidArgumentException
            }
            $data = file_get_contents($v);
            $v = call_user_func("end", explode(DIRECTORY_SEPARATOR, $v));

            $k = str_replace($disallow, "_", $k);
            $v = str_replace($disallow, "_", $v);
            $body[] = implode("\r\n", [
                "Content-Disposition: form-data; name=\"$k\"; filename=\"$v\"",
                "Content-Type: application/octet-stream",
                "",
                $data,
            ]);
        }

        // generate safe boundary
        do {
            $boundary = "---------------------" . md5(random_int(0, mt_getrandmax()) . microtime());
        } while (preg_grep("/$boundary/", $body));

        // add boundary for each parameters
        array_walk($body, function (&$part) use ($boundary): void {
            $part = "--$boundary\r\n$part";
        });

        // add final boundary
        $body[] = "--$boundary--";
        $body[] = "";

        // set options
        $this->setPostBody(implode("\r\n", $body));
        //		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getPostBody());
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $this->addHeader('Expect: 100-continue');
        $this->addHeader("Content-Type: multipart/form-data; boundary=$boundary");
    }
}
