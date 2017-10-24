<?php

namespace pavelk\JsonRPC\Server;


class Request
{
    const JSON_RPC_VERSION         = "2.0";
    const ERROR_PARSE_ERROR        = -32700;
    const ERROR_INVALID_REQUEST    = -32600;
    const ERROR_MISMATCHED_VERSION = -32000;
    const ERROR_RESERVED_PREFIX    = -32001;
    const VALID_FUNCTION_NAME      = '/^[a-zA-Z_][a-zA-Z0-9_\.]*$/';

    public $batch;
    public $raw;
    public $result;
    public $jsonRpc;
    public $method;
    public $params;
    public $id;

    public $requests;

    /**
     * create new server request object from raw json
     *
     * Request constructor.
     * @param null $json
     * @throws Exception
     */
    public function __construct($json = null)
    {
        $this->batch = false;
        $this->raw   = $json;

        // handle empty request
        if ($this->raw === false || $this->raw === "") {
            throw new Exception("Invalid Request.", self::ERROR_INVALID_REQUEST);
        }

        // parse json into object
        $obj = json_decode($json);

        // handle json parse error
        if ($obj === null) {
            throw new Exception("Parse error.", self::ERROR_PARSE_ERROR);
        }

        // array of objects for batch
        if (is_array($obj)) {

            // empty batch
            if (count($obj) == 0) {
                throw new Exception("Invalid Request.", self::ERROR_INVALID_REQUEST);
            }

            // non-empty batch
            $this->batch    = true;
            $this->requests = array();
            foreach ($obj as $req) {
                // recursion for bad requests
                if (!is_object($req)) {
                    $this->requests[] = new Request('');
                    // recursion for good requests
                } else {
                    $this->requests[] = new Request(json_encode($req));
                }
            }

            // single request
        } else {
            $this->jsonRpc = $obj->jsonrpc;
            $this->method  = $obj->method;
            if (property_exists($obj, 'params')) {
                $this->params = $this->getParams($json);
            };
            if (property_exists($obj, 'id')) {
                $this->id = $obj->id;
            };
        }
    }

    /**
     * @param $json
     * @return mixed
     */
    public function getParams($json)
    {
        $obj = json_decode($json, true);
        return $obj['params'];
    }

    /**
     * returns true if request is valid or returns false assigns error
     *
     * @throws Exception
     * @throws \Exception
     */
    public function checkValid()
    {
        // missing jsonrpc or method
        if (!$this->jsonRpc || !$this->method) {
            throw new Exception("Invalid Request.", self::ERROR_INVALID_REQUEST);
        }

        // reserved method prefix
        if (substr($this->method, 0, 4) == 'rpc.') {
            throw new Exception("Illegal method name; Method cannot start with 'rpc.'", self::ERROR_RESERVED_PREFIX);
        }

        // illegal method name
        if (!preg_match(self::VALID_FUNCTION_NAME, $this->method)) {
            throw new \Exception("Invalid Request.", self::ERROR_INVALID_REQUEST);
        }

        // mismatched json-rpc version
        if ($this->jsonRpc != "2.0") {
            throw new \Exception("Client/Server JSON-RPC version mismatch; Expected '2.0'", self::ERROR_MISMATCHED_VERSION);
        }
    }

    /**
     * returns true if request is a batch
     *
     * @return bool
     */
    public function isBatch()
    {
        return $this->batch;
    }

    /**
     * returns true if request is a notification
     *
     * @return bool
     */
    public function isNotify()
    {
        return !isset($this->id);
    }


}
