<?php

abstract class Doctrine_EventLoggable_Logger
{
    protected $_eventLoggable;

    public function __construct(Doctrine_Template_EventLoggable $eventLoggable)
    {
        $this->_eventLoggable = $eventLoggable;
    }

    public function log($method, $event)
    {
        if ($event instanceof Doctrine_Event) {
            $invoker = $event->getInvoker();
            if ($invoker instanceof Doctrine_Record) {
                $invoker = $invoker->toArray();
            } else if ($invoker instanceof Doctrine_Connection) {
                $invoker = $invoker->getName();
            } else if ($invoker instanceof Doctrine_Transaction) {
                $invoker = get_class($invoker);
            } else if ($invoker instanceof Doctrine_Connection_Statement) {
                $invoker = get_class($invoker);
            }

            $log = array(
                'method' => $method,
                'name' => $event->getName(),
                'code' => $event->getCode(),
                'skipped' => $event->skipOperation,
                'time' => $event->getElapsedSecs(),
                'query' => $event->getQuery(),
                'params' => $event->getParams(),
                'invoker' => $invoker
            );
            $this->_writeLog($log);
        }
    }

    abstract protected function _writeLog(array $log);
}