<?php

class Doctrine_EventLoggable_FileLogger extends Doctrine_EventLoggable_Logger
{
    protected function _writeLog(array $log)
    {
        $logger = $this->_eventLoggable->getOption('logger');

        if (!isset($logger['path'])) {
            throw new Doctrine_Exception('You must specify a path to log the events to.');
        }

        touch($logger['path']);
        $handle = fopen($logger['path'], 'a');
        fwrite($handle, print_r($log, true));
        fclose($handle);
    }
}