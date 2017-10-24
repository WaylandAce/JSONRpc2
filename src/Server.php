<?php

namespace pavelk\JsonRPC;

use pavelk\JsonRPC\Server\Exception;
use pavelk\JsonRPC\Server\Request;
use pavelk\JsonRPC\Server\Response;

const ERROR_INVALID_REQUEST  = -32600;
const ERROR_METHOD_NOT_FOUND = -32601;
const ERROR_INVALID_PARAMS   = -32602;
const ERROR_EXCEPTION        = -32099;


class Server
{
    public $exposedInstance, $input, $map;

    /**
     * Server constructor.
     * @param null $className
     * @param string $namespace
     */
    public function __construct($className = null, $namespace = '')
    {
//        if (!is_object($className)) {
//            throw new Serverside\Exception("Server requires an object");
//        }

        $this->input = 'php://input';

        if ($className)
            $this->addInstance($className, $namespace);
    }

    /**
     * @param $className
     * @param string $namespace
     */
    public function addInstance($className, $namespace = '')
    {
        $this->map[$namespace] = $className;
    }

    /**
     * check for method existence
     *
     * @param $methodName
     * @return bool
     */
    public function methodExists($methodName)
    {
        list($namespace, $method) = $this->parseMethod($methodName);

        if (!isset($this->map[$namespace]))
            return false;

        return method_exists($this->map[$namespace], $method);
    }

    /**
     * @param $methodName
     * @return array
     */
    public function parseMethod($methodName)
    {
        $parts = explode('.', $methodName);
        if (count($parts) == 1) {
            $method    = $methodName;
            $namespace = '';
        } else {
            $method    = array_pop($parts);
            $namespace = implode('.', $parts);
        }

        return array($namespace, $method);
    }

    /**
     * attempt to invoke the method with params
     *
     * @param $method
     * @param null $params
     * @return mixed
     * @throws Exception
     * @throws \ReflectionException
     */
    public function invokeMethod($method, $params = null)
    {
        // for named parameters, convert from object to assoc array
        if (is_object($params)) {
            $array = array();
            foreach ($params as $key => $val) {
                $array[$key] = $val;
            }
            $params = array($array);
        }
        // for no params, pass in empty array
        if ($params === null) {
            $params = array();
        }

        list($namespace, $methodName) = $this->parseMethod($method);
        if (!isset($this->map[$namespace]))
            throw new \ReflectionException('Method not exists');

        $instance = new $this->map[$namespace];

        $reflection = new \ReflectionMethod($instance, $methodName);

        // only allow calls to public functions
        if (!$reflection->isPublic()) {
            throw new Server\Exception("Called method is not public accessible.");
        }

        // enforce correct number of arguments
        $numRequiredParams = $reflection->getNumberOfRequiredParameters();
        if ($numRequiredParams > count($params)) {
            throw new Server\Exception("Too few parameters passed.");
        }

        return $reflection->invokeArgs($instance, $params);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getResponse()
    {
        // try to read input
        $json = file_get_contents($this->input);

        try {
            // create request object
            $request  = $this->makeRequest($json);
            $response = $this->handleRequest($request);
        } catch (Server\Exception $e) {
            $response = new Response();

            $response->errorCode    = $e->getCode();
            $response->errorMessage = $e->getMessage();
        }

        if ($response instanceof Response) {
            return $response->toJson();
        } else {
            $batch = [];
            foreach ($response as $resp) {
                $batch[] = $resp->toJson();
            }
            $responses = implode(',', array_filter($batch, function ($a) {
                return $a !== null;
            }));

            return "[" . $responses . "]";
        }
    }

    /**
     * @throws \Exception
     */
    public function process()
    {
        echo $this->getResponse();
    }

    /**
     * create new request (used for test mocking purposes)
     *
     * @param $json
     * @return Request
     */
    public function makeRequest($json)
    {
        return new Request($json);
    }

    /**
     * handle request object / return response json
     *
     * @param Request $request
     * @return Response|null|string
     * @throws Exception
     * @throws \Exception
     */
    public function handleRequest(Request $request)
    {
        // recursion for batch
        if ($request->isBatch()) {
            $batch = array();
            foreach ($request->requests as $req) {
                $batch[] = $this->handleRequest($req);
            }

            return $batch;
        }

        $response = new Response();

        try {
            // check validity of request
            $request->checkValid();

            $rawResponse = $this->invokeMethod($request->method, $request->params);
            if ($request->isNotify())
                return null;

            $response->id     = $request->id;
            $response->result = $rawResponse;
            // return whatever we got
        } catch (\ReflectionException $e) {
            $response->id           = $request->id;
            $response->errorCode    = ERROR_METHOD_NOT_FOUND;
            $response->errorMessage = 'Method not exists.';
        } catch (Exception $e) {
            $response->id           = $request->id;
            $response->errorCode    = $e->getCode();
            $response->errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $response->id           = $request->id;
            $response->errorCode    = ERROR_EXCEPTION;
            $response->errorMessage = $e->getMessage();
        }

        return $response;
    }

}
