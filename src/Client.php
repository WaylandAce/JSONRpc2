<?php

namespace pavelk\JsonRPC;

use pavelk\JsonRPC\Client\Request,
    pavelk\JsonRPC\Client\Response,
    pavelk\JsonRPC\Client\Exception;


class Client
{
    public $uri, $authHeader;

    /**
     * create new client connection
     *
     * @param $uri
     */
    public function __construct(string $uri)
    {
        $this->uri = $uri;
    }

    /**
     * shortcut to call a single, non-notification request
     *
     * @param $method
     * @param $params
     * @return string
     * @throws Exception
     */
    public function __call($method, $params)
    {
        $request = new Request($method, $params);

        return $this->sendRequest($request);
    }

    /**
     * set basic http authentication
     *
     * @param $username
     * @param $password
     */
    public function setBasicAuth($username, $password)
    {
        $this->authHeader = "Authorization: Basic " . base64_encode("$username:$password") . "\r\n";
    }

    /**
     * clear any existing http authentication
     */
    public function clearAuth()
    {
        $this->authHeader = null;
    }

    /**
     * send a single request object
     *
     * @param Request $request
     * @return array|string
     * @throws Exception
     */
    public function sendRequest($request)
    {
        return $this->send($request->getJSON());
    }

    /**
     * send a single notify request object
     *
     * @param Request $req
     * @return bool
     * @throws Exception
     */
    public function sendNotify($req)
    {
        if (property_exists($req, 'id') && $req->id != null) {
            throw new Client\Exception("Notify requests must not have ID set");
        }

        $this->send($req->getJSON(), true);

        return true;
    }

    /**
     * send an array of request objects as a batch
     *
     * @param Request[] $requests
     * @return array|bool
     * @throws Exception
     */
    public function sendBatch(array $requests)
    {
        $arr        = array();
        $ids        = array();
        $all_notify = true;
        foreach ($requests as $req) {
            if ($req->id) {
                $all_notify = false;
                $ids[]      = $req->id;
            }
            $arr[] = $req->getArray();
        }
        $response = $this->send(json_encode($arr), $all_notify);

        // no response if batch is all notifications
        if ($all_notify) {
            return true;
        }

        // check for missing ids and return responses in order of requests
        $orderedResponse = array();
        foreach ($ids as $id) {
            if (array_key_exists($id, $response)) {
                $orderedResponse[] = $response[$id];
                unset($response[$id]);
            } else {
                throw new Client\Exception("Missing id in response");
            }
        }

        // check for extra ids in response
        if (count($response) > 0) {
            throw new Client\Exception("Extra id(s) in response");
        }

        return $orderedResponse;
    }

    /**
     * send raw json to the server
     *
     * @param $json
     * @param bool $notify
     * @return array|bool|Response
     * @throws Exception
     */
    public function send($json, $notify = false)
    {
        // use http authentication header if set
        $header = "Content-Type: application/json\r\n";
        if ($this->authHeader) {
            $header .= $this->authHeader;
        }

        // prepare data to be sent
        $opts    = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => $header,
                'content' => $json));
        $context = stream_context_create($opts);

        // try to physically send data to destination
        try {
            $response = file_get_contents($this->uri, false, $context);
        } catch (\Exception $e) {
            $message = "Unable to connect to {$this->uri}";
            $message .= PHP_EOL . $e->getMessage();
            throw new Client\Exception($message);
        }

        // handle communication errors
        if ($response === false) {
            throw new Client\Exception("Unable to connect to {$this->uri}");
        }

        // notify has no response
        if ($notify) {
            return true;
        }

        // try to decode json
        $jsonResponse = $this->decodeJSON($response);

        // handle response, create response object and return it
        return $this->handleResponse($jsonResponse);
    }

    /**
     * decode json throwing exception if unable
     *
     * @param $json
     * @return mixed
     * @throws Exception
     */
    function decodeJSON($json)
    {
        $jsonResponse = json_decode($json);
        if ($jsonResponse === null) {
            throw new Client\Exception("Unable to decode JSON response from: {$json}");
        }

        return $jsonResponse;
    }

    /**
     * handle the response and return a result or an error
     *
     * @param $response
     * @return array|Response
     */
    public function handleResponse($response)
    {
        // recursion for batch
        if (is_array($response)) {
            $responseArr = array();
            foreach ($response as $res) {
                $responseArr[$res->id] = $this->handleResponse($res);
            }

            return $responseArr;
        }

        return $response;
    }

}
