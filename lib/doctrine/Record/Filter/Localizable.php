<?php
/*
 *  $Id: Record.php 1298 2007-05-01 19:26:03Z zYne $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.org>.
 */

/**
 * Doctrine_Record_Filter_Standard
 * Filters the record getters and setters
 *
 * @package     Doctrine
 * @subpackage  Record
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.phpdoctrine.org
 * @since       1.0
 * @version     $Revision: 1298 $
 */
class Doctrine_Record_Filter_Localizable extends Doctrine_Record_Filter
{
  /**
   * Array of locatable options
   */  
  protected $_options = array(),
            $_localizables = array();


  /**
   * Constructor for Localizable Template
   *
   * @param array $options 
   * @return void
   * @author Brent Shaffer
   */  
  public function __construct(array $options)
  {
    $this->_options = $options;
  }
  
  public function init()
  {
  }
  
  /**
   * filterSet
   * defines an implementation for filtering the set() method of Doctrine_Record
   *
   * @param mixed $name                       name of the property or related component
   */
  public function filterSet(Doctrine_Record $record, $name, $value)
  {
    if (in_array($name, array_keys($this->_options['fields']))) 
    {
      $template = $record->getTable()->getTemplate('Localizable');
      $unit = $this->_options['fields'][$name];
      if (isset($this->_localizables[$name])) 
      {
        $this->_localizables[$name]->setValue((string)$value, $unit);
        $localizable = $this->_localizables[$name];
      }
      else
      {
        $localizable = new LocalizableUnit($value, $unit, $this->_options['conversions']);
        $this->_localizables[$name] = $localizable;
      }
      
      $field = $this->_options['columns'][$name];
      $record->$field = $value;
      
      return $record;
    }
    
    throw new Doctrine_Record_UnknownPropertyException(sprintf('Unknown record property / related component "%s" on unit filter "%s"', $name, get_class($record)));
  }

  /**
   * filterGet
   * defines an implementation for filtering the get() method of Doctrine_Record
   *
   * @param mixed $name                       name of the property or related component
   */
  public function filterGet(Doctrine_Record $record, $name)
  {
    if (in_array($name, array_keys($this->_options['columns']))) 
    {
      $column = $this->_options['columns'][$name];
      $unit = $this->_options['fields'][$name];      
      $value = $record->$column;
      
      if (isset($this->_localizables[$name])) 
      {
        $localizable = $this->_localizables[$name];
      }
      else
      {
        $localizable = new LocalizableUnit($value, $unit, $this->_options['conversions']);
        $this->_localizables[$name] = $localizable;
      }
      
      return $localizable;
    }
    
    throw new Doctrine_Record_UnknownPropertyException(sprintf('Unknown record property / related component "%s" on unit filter "%s"', $name, get_class($record)));
  }
}