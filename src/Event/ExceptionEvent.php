<?php

namespace pavelk\JsonRPC\Event;

use Symfony\Component\EventDispatcher\Event;


class ExceptionEvent extends Event
{
    /** @var \Exception */
    protected $exception;

    const NAME = 'JsonRpcException';

    /**
     * ExceptionEvent constructor.
     * @param \Exception $e
     */
    public function __construct(\Exception $e)
    {
        $this->exception = $e;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

}
