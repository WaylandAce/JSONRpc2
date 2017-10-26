<?php

namespace pavelk\JsonRPC\Server\Exception;


class MethodNotFoundException extends ServerException
{
    protected $code = -32600;

    /**
     * MethodNotFoundException constructor.
     * @param string $message
     */
    public function __construct($message = 'Method not found')
    {
        parent::__construct($message, $this->code);
    }

}