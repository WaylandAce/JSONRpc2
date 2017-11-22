<?php

namespace pavelk\JsonRPC;

use pavelk\JsonRPC\Server\Exception\InternalErrorException;
use pavelk\JsonRPC\Server\Exception\InvalidParamsException;
use pavelk\JsonRPC\Server\Exception\MethodNotFoundException;
use pavelk\JsonRPC\Server\Exception\ServerException;
use pavelk\JsonRPC\Server\Request;
use pavelk\JsonRPC\Server\Response;


class Server
{
    /** @var array */
    protected $map = [];

    /**
     * @param string $className
     * @param string $namespace
     */
    public function addInstance(string $className, string $namespace = '')
    {
        $this->map[$namespace] = $className;
    }

    /**
     * Check for method existence
     *
     * @param string $methodName
     * @return bool
     */
    public function methodExists(string $methodName)
    {
        list($namespace, $method) = $this->parseMethod($methodName);

        if (!isset($this->map[$namespace]))
            return false;

        return method_exists($this->map[$namespace], $method);
    }

    /**
     * @param string $methodName
     * @return array
     */
    public function parseMethod(string $methodName)
    {
        $parts = explode('.', $methodName);
        if (count($parts) == 1) {
            $method    = $methodName;
            $namespace = '';
        } else {
            $method    = array_pop($parts);
            $namespace = implode('.', $parts);
        }

        return [$namespace, $method];
    }

    /**
     * Attempt to invoke the method with params
     *
     * @param string $method
     * @param null $params
     * @return mixed
     * @throws InvalidParamsException
     * @throws MethodNotFoundException
     */
    public function invokeMethod(string $method, $params = null)
    {
        // for named parameters, convert from object to assoc array
        if (is_object($params)) {
            $array = [];
            foreach ($params as $key => $val) {
                $array[$key] = $val;
            }
            $params = [$array];
        }
        // for no params, pass in empty array
        if ($params === null) {
            $params = [];
        }

        list($namespace, $methodName) = $this->parseMethod($method);
        if (!isset($this->map[$namespace]))
            throw new MethodNotFoundException();

        $instance   = new $this->map[$namespace];
        $reflection = new \ReflectionMethod($instance, $methodName);

        // only allow calls to public functions
        if (!$reflection->isPublic()) {
            throw new MethodNotFoundException("Called method is not public accessible.");
        }

        // enforce correct number of arguments
        $numRequiredParams = $reflection->getNumberOfRequiredParameters();
        if ($numRequiredParams > count($params)) {
            throw new InvalidParamsException("Too few parameters passed");
        }

        $params = $this->prepareArgs($reflection, $params);

        return $reflection->invokeArgs($instance, $params);
    }

    /**
     * @param \ReflectionMethod $method
     * @param array $args
     * @return array
     */
    public function prepareArgs(\ReflectionMethod $method, array $args): array
    {
        return $args;
    }

    /**
     * @param string $json
     * @return string
     * @throws ServerException
     */
    public function getResponse(string $json)
    {
        // create request object
        $request  = new Request($json);
        $response = $this->handleRequest($request);

        if ($response instanceof Response) {
            return $response->toJson();
        }

        $batch = [];
        /** @var Response $resp */
        foreach ($response as $resp) {
            $batch[] = $resp->toJson();
        }
        $responses = implode(',', array_filter($batch, function ($a) {
            return $a !== null;
        }));

        return "[" . $responses . "]";
    }

    /**
     * @throws ServerException
     */
    public function process()
    {
        // try to read input
        $json = file_get_contents('php://input');

        echo $this->getResponse($json);
    }

    /**
     * Handle request object / return response json
     *
     * @param Request $request
     * @return array|null|Response
     * @throws ServerException
     */
    public function handleRequest(Request $request)
    {
        // recursion for batch
        if ($request->isBatch()) {
            $batch = [];
            foreach ($request->requests as $req) {
                $batch[] = $this->handleRequest($req);
            }

            return $batch;
        }

        $response     = new Response();
        $response->id = $request->id;

        try {
            try {
                // check validity of request
                $request->checkValid();

                $rawResponse = $this->invokeMethod($request->method, $request->params);
                if ($request->isNotify())
                    return null;

                $response->result = $rawResponse;
            } catch (\ReflectionException $e) {
                throw new MethodNotFoundException();
            } catch (ServerException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->handleThrowable($e);
            }
        } catch (ServerException $e) {
            $response->errorCode    = $e->getCode();
            $response->errorMessage = $e->getMessage();
        }

        return $response;
    }

    /**
     * @param \Throwable $e
     * @throws InternalErrorException
     */
    public function handleThrowable(\Throwable $e)
    {
        throw new InternalErrorException();
    }

}
