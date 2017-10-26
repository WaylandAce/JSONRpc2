<?php

namespace pavelk\JsonRPC;

use pavelk\JsonRPC\Client\Request,
    pavelk\JsonRPC\Client\Response,
    pavelk\JsonRPC\Client\ClientException;


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
     * @param string $method
     * @param $params
     * @return string
     * @throws ClientException
     */
    public function __call(string $method, $params)
    {
        $request = new Request($method, $params);

        return $this->sendRequest($request);
    }

    /**
     * Set basic http authentication
     *
     * @param string $username
     * @param string $password
     */
    public function setBasicAuth(string $username, string $password)
    {
        $this->authHeader = "Authorization: Basic " . base64_encode("$username:$password") . "\r\n";
    }

    /**
     * Clear any existing http authentication
     */
    public function clearAuth()
    {
        $this->authHeader = null;
    }

    /**
     * Send a single request object
     *
     * @param Request $request
     * @return array|string
     * @throws ClientException
     */
    public function sendRequest(Request $request)
    {
        return $this->send($request->getJSON());
    }

    /**
     * Send a single notify request object
     *
     * @param Request $request
     * @return bool
     * @throws ClientException
     */
    public function sendNotify(Request $request)
    {
        if (property_exists($request, 'id') && $request->id != null) {
            throw new Client\ClientException("Notify requests must not have ID set");
        }

        $this->send($request->getJSON(), true);

        return true;
    }

    /**
     * Send an array of request objects as a batch
     *
     * @param Request[] $requests
     * @return array|bool
     * @throws ClientException
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
                throw new Client\ClientException("Missing id in response");
            }
        }

        // check for extra ids in response
        if (count($response) > 0) {
            throw new Client\ClientException("Extra id(s) in response");
        }

        return $orderedResponse;
    }

    /**
     * Send raw json to the server
     *
     * @param string $json
     * @param bool $notify
     * @return array|bool|Response
     * @throws ClientException
     */
    public function send(string $json, $notify = false)
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
            throw new Client\ClientException($message);
        }

        // handle communication errors
        if ($response === false) {
            throw new Client\ClientException("Unable to connect to {$this->uri}");
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
     * @param string $json
     * @return mixed
     * @throws ClientException
     */
    private function decodeJSON(string $json)
    {
        $jsonResponse = json_decode($json);
        if ($jsonResponse === null) {
            throw new Client\ClientException("Unable to decode JSON response from: {$json}");
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
