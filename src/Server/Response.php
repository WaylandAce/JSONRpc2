<?php
/**
 * Created by PhpStorm.
 * User: pkirpitsov
 * Date: 10/3/17
 * Time: 1:36 PM
 */

namespace pavelk\JsonRPC\Server;


class Response
{
    public $id;
    public $result;
    public $errorCode;
    public $errorMessage;

    public function __construct()
    {

    }

    /**
     * return raw JSON response
     *
     * @return string
     */
    public function toJson()
    {
        // successful response
        $arr = array('jsonrpc' => Request::JSON_RPC_VERSION);
        if ($this->result !== null) {
            $arr['result'] = $this->result;
            $arr['id']     = $this->id;

            return json_encode($arr);
            // error response
        } else {
            $arr['error'] = array('code' => $this->errorCode, 'message' => $this->errorMessage);
            $arr['id']    = $this->id;

            return json_encode($arr);
        }
    }

}