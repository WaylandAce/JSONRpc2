<?php

namespace pavelk\JsonRPC\Server\Exception;



class InvalidRequestException extends ServerException
{
    protected $code = -32600;

    /**
     * InvalidRequestException constructor.
     * @param string $message
     */
    public function __construct($message = 'Invalid Request')
    {
        parent::__construct($message, $this->code);
    }

}
