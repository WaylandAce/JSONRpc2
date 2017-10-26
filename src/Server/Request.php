<?php

namespace pavelk\JsonRPC\Server;

use pavelk\JsonRPC\Server\Exception\InvalidRequestException;
use pavelk\JsonRPC\Server\Exception\ParseErrorException;


class Request
{
    const JSON_RPC_VERSION         = "2.0";
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
     * @throws InvalidRequestException
     * @throws ParseErrorException
     */
    public function __construct($json = null)
    {
        $this->batch = false;
        $this->raw   = $json;

        // handle empty request
        if ($this->raw === false || $this->raw === "") {
            throw new InvalidRequestException();
        }

        // parse json into object
        $obj = json_decode($json);

        // handle json parse error
        if ($obj === null) {
            throw new ParseErrorException();
        }

        // array of objects for batch
        if (is_array($obj)) {

            // empty batch
            if (count($obj) == 0) {
                throw new InvalidRequestException();
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
     * @throws InvalidRequestException
     */
    public function checkValid()
    {
        // missing jsonrpc or method
        if (!$this->jsonRpc || !$this->method) {
            throw new InvalidRequestException();
        }

        // reserved method prefix
        if (substr($this->method, 0, 4) == 'rpc.') {
            throw new InvalidRequestException("Illegal method name; Method cannot start with 'rpc.'");
        }

        // illegal method name
        if (!preg_match(self::VALID_FUNCTION_NAME, $this->method)) {
            throw new InvalidRequestException();
        }

        // mismatched json-rpc version
        if ($this->jsonRpc != "2.0") {
            throw new InvalidRequestException("Client/Server JSON-RPC version mismatch; Expected '2.0'");
        }
    }

    /**
     * returns true if request is a batch
     *
     * @return bool
     */
    public function isBatch(): bool
    {
        return $this->batch;
    }

    /**
     * returns true if request is a notification
     *
     * @return bool
     */
    public function isNotify(): bool
    {
        return !isset($this->id);
    }


}
