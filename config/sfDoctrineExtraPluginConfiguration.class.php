<?php

/**
 * Description
 *
 * @author
 * @copyright
 * @package
 * @subpackage
 * @version
 *
 */
class sfDoctrineExtraPluginConfiguration extends sfPluginConfiguration
{

  /**
   * @see sfPluginConfiguration
   */
  public function initialize()
  {
    $this->dispatcher->connect('context.load_factories', array($this,'listenToContextLoadFactories'));
  }

  public function listenToContextLoadFactories(sfEvent $event)
  {
    /* @var $subject sfContext */
    $subject = $event->getSubject();
    /* @var $user myUser */
    $user = $subject->getUser();

    if (method_exists($user, 'getGuardUser') && $user->isAuthenticated())
    {
      $user_id = $user->getGuardUser()->getId();
//      die(var_dump($user->getGuardUser()));
      Doctrine_Template_Listener_Blameable::setUserId($user_id);
    }
  }
  
}