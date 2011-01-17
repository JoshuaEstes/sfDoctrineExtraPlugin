<?php

class Doctrine_Template_EventLoggable extends Doctrine_Template
{
    public function setTableDefinition()
    {
        $this->addListener(new Doctrine_Template_Listener_EventLoggable($this));
        $this->getTable()->getConnection()->addListener(new Doctrine_Template_Listener_EventLoggable($this));
    }
}