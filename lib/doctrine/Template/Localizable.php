<?php

class Doctrine_Template_Localizable extends Doctrine_Template
{    
  /**
   * Array of locatable options
   */  
  protected $_options = array('fields' => array(), 'columns' => array(), 'conversions' => array());
  
  /**
   * Constructor for Locatable Template
   *
   * @param array $options 
   * @return void
   * @author Brent Shaffer
   */
  public function __construct(array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
  }


  public function setup()
  {
  }


  /**
   * Set table definition for contactable behavior
   * (borrowed from Sluggable in Doctrine core)
   *
   * @return void
   * @author Brent Shaffer
   */
  public function setTableDefinition()
  {
    foreach ($this->_options['fields'] as $field => $unit) 
    {
      $name = Doctrine_Inflector::tableize($field.'_'.strtolower($unit));
      $this->_options['columns'][$field] = $name;
      $this->hasColumn($name, 'float');
    }
    $this->_table->unshiftFilter(new Doctrine_Record_Filter_Localizable($this->_options));
  }
}

/**
* Localizable Unit
*/
class LocalizableUnit implements ArrayAccess
{
  protected $_unit, $_value, 
            $_converter;
  
  function __construct($value, $unit, $conversions = array())
  {
    $this->setValue($value, $unit);
    $this->_converter = new LocalizableConverter($conversions);
  }
  
  public function setValue($value, $unit = null)
  {
    $this->_value = $value;
    if ($unit) 
    {
      $this->_unit = strtolower($unit);
    }
  }
  
  function __toString()
  {
    return (string)$this->_value;
  }
  
  /**
   * Set key and value to data
   *
   * @see     set, offsetSet
   * @param   $name
   * @param   $value
   * @return  void
   */
  public function __set($name, $value)
  {
      $this->set($name, $value);
  }

  /**
   * Get key from data
   *
   * @see     get, offsetGet
   * @param   mixed $name
   * @return  mixed
   */
  public function __get($name)
  {
      return $this->get($name);
  }

  /**
   * Check if key exists in data
   *
   * @param   string $name
   * @return  boolean whether or not this object contains $name
   */
  public function __isset($name)
  {
      return $this->contains($name);
  }

  /**
   * Remove key from data
   *
   * @param   string $name
   * @return  void
   */
  public function __unset($name)
  {
      return $this->remove($name);
  }

  /**
   * Check if an offset axists
   *
   * @param   mixed $offset
   * @return  boolean Whether or not this object contains $offset
   */
  public function offsetExists($offset)
  {
      return $this->contains($offset);
  }

  /**
   * An alias of get()
   *
   * @see     get, __get
   * @param   mixed $offset
   * @return  mixed
   */
  public function offsetGet($offset)
  {
      return $this->get($offset);
  }

  /**
   * Sets $offset to $value
   *
   * @see     set, __set
   * @param   mixed $offset
   * @param   mixed $value
   * @return  void
   */
  public function offsetSet($offset, $value)
  {
      if ( ! isset($offset)) {
          $this->add($value);
      } else {
          $this->set($offset, $value);
      }
  }

  /**
   * Unset a given offset
   *
   * @see   set, offsetSet, __set
   * @param mixed $offset
   */
  public function offsetUnset($offset)
  {
      return $this->remove($offset);
  }

  /**
   * Remove the element with the specified offset
   *
   * @param mixed $offset The offset to remove
   * @return boolean True if removed otherwise false
   */
  public function remove($offset)
  {
      throw new Doctrine_Exception('Remove is not supported for ' . get_class($this));
  }

  /**
   * Return the element with the specified offset
   *
   * @param mixed $offset     The offset to return
   * @return mixed
   */
  public function get($offset)
  {
    return $this->_converter->convert($this->_value, $this->_unit, strtolower($offset));
  }

  /**
   * Set the offset to the value
   *
   * @param mixed $offset The offset to set
   * @param mixed $value The value to set the offset to
   *
   */
  public function set($offset, $value)
  {
    $this->_value = $this->_converter->convert($value, strtolower($offset), $this->_unit);
  }

  /**
   * Check if the specified offset exists 
   * 
   * @param mixed $offset The offset to check
   * @return boolean True if exists otherwise false
   */
  public function contains($offset)
  {
    try {
      $conversion = $this->_converter->getConversion(strtolower($offset), $this->_unit);
      return true; 
    } catch (Exception $e) {}
    return false; 
  }

  /**
   * Add the value  
   * 
   * @param mixed $value The value to add 
   * @return void
   */
  public function add($value)
  {
      throw new Doctrine_Exception('Add is not supported for ' . get_class($this));
  }
}

/**
* Converter Class
*/
class LocalizableConverter
{
  protected $_conversions = array(
      //  DISTANCE    
      'm' =>  array('m'  => 1,
                    'km' => .001,
                    'cm' => 100,
                    'mm' => 1000,
                    'mi' => .000621371,
                    'yrd' => 1.093613,
                    'ft' => 3.280839,
                    'in' => 39.37),
      'km' => array('m'  => 1000,
                    'km' => 1,
                    'cm' => 100000,
                    'mm' => 1000000,
                    'mi' => .621371,
                    'yrd' => 1093.613,
                    'ft' => 3280.839,
                    'in' => 39370),
      'cm' => array('m'  => .01,
                    'km' => .00001,
                    'cm' => 1,
                    'mm' => 10,
                    'mi' => .00000621371,
                    'yrd' => .01093613,
                    'ft' => .03280839,
                    'in' => .39370),
      'mm' => array('m'  => .001,
                    'km' => .000001,
                    'cm' => .1,
                    'mm' => 1,
                    'mi' => .000000621371,
                    'yrd' => .001093613,
                    'ft' => .003280839,
                    'in' => .039370),
      'mi' => array('m'  => 1609.344,
                    'km' =>  1.609344,
                    'cm' => 160934.4,
                    'mm' => 1609344,
                    'mi' => 1,
                    'yrd' => 1760,
                    'ft' => 5280,
                    'in' => 63360),
      'yrd' => array('m'  => .9144,
                    'km' =>  .0009144,
                    'cm' => 91.44,
                    'mm' => 914.4,
                    'mi' => .00056818,
                    'yrd' => 1,
                    'ft' => 3,
                    'in' => 36),
      'ft' => array('m'  => .3048,
                    'km' =>  .0003048,
                    'cm' => 30.48,
                    'mm' => 304.8,
                    'mi' => .00018934,
                    'yrd' => .333333,
                    'ft' => 1,
                    'in' => 12),
      'in' => array('m'  => .0254,
                    'km' =>  .0000254,
                    'cm' => 2.54,
                    'mm' => 25.4,
                    'mi' => .0000157828,
                    'yrd' => .027778,
                    'ft' => .08333,
                    'in' => 1),
      //  WEIGHT

      'kg' => array('kg'  => 1,
                    'g' => 1000,
                    'mg' => 1000000,
                    'lb' => 2.20462262,
                    'oz' => 35.2739619),
      'g'  => array('kg'  => .001,
                    'g'  => 1,
                    'mg' => 1000,
                    'lb' => .00220462262,
                    'oz' => .0352739619),
      'mg' => array('kg' => .000001,
                    'g'  => .001,
                    'mg' => 1,
                    'lb' => .00000220462262,
                    'oz' => .0000352739619),
      'lb' => array('kg' => .45359237,
                    'g'  => 453.59237,
                    'mg' => 453592.37,
                    'lb' => 1,
                    'oz' => 16),
      'oz' => array('kg' => .0283495231,
                    'g'  => 28.3495231,
                    'mg' => 28349.5231,
                    'lb' => .0625,
                    'oz' => 1),
      //  TEMPURATURE                    

      'c' => array('c'  => 1,
                   'k' => array('factor' => 1, 'deltha' => 273.15),
                   'f' => array('factor' => 1.8, 'deltha' => 32)),
      'k' => array('c'  => array('factor' => 1, 'deltha' => -273.15),
                   'k' => 1,
                   'f' => array('factor' => 1.8, 'deltha' => -459.4)),
      'f' => array('c'  => array('factor' => .55555555, 'deltha' => -17.777778),
                   'k' => array('factor' => .55555555, 'deltha' => 255.372222),
                   'f' => 1),
      // VOLUME

      'ml' =>  array('ml'  => 1,
                    'l'    => .001,
                    'ft3'  => .000035314667,
                    'gal'  => .000264172052,
                    'qt'  => .00105668821,
                    'pint'  => .00211336642,
                    'cup' => .00422675284,
                    'floz'   => .0338140227),
      'l' =>  array('ml'  => 1000,
                    'l'    => 1,
                    'ft3'  => .035314667,
                    'gal' => .264172052,
                    'qt'  => 1.05668821,
                    'pint'  => 2.11336642,
                    'cup' => 4.22675284,
                    'floz'   => 33.8140227),
      'ft3' =>  array('ml'  => 28316.8466,
                    'l'    => 28.3168466,
                    'ft3'  => 1,
                    'gal' => 7.48051948,
                    'qt'  => 29.9220779,
                    'pint'  => 59.8441558,
                    'cup' => 119.688312,
                    'floz'   => 957.506494),
      'gal' =>  array('ml'  => 3785.41178,
                    'l'    => 3.78541178,
                    'ft3'  => .133680556,
                    'gal' => 1,
                    'qt'  => 4,
                    'pint'  => 8,
                    'cup' => 16,
                    'floz'   => 128),
      'qt' =>  array('ml'  => 946.352946,
                    'l'    => .946352946,
                    'ft3'  => .0334201389,
                    'gal' => .25,
                    'qt'  => 1,
                    'pint'  => 2,
                    'cup' => 4,
                    'floz'   => 32),
      'pint' =>  array('ml'  => 473.176473,
                    'l'    => .473176473,
                    'ft3'  => .0167100694,
                    'gal' => .125,
                    'qt'  => .5,
                    'pint'  => 1,
                    'cup' => 2,
                    'floz'   => 16),
      'cup' =>  array('ml'  => 236.588237,
                    'l'    => .236588237,
                    'ft3'  => .00835503472,
                    'gal' => .0625,
                    'qt'  => .25,
                    'pint'  => .5,
                    'cup' => 1,
                    'floz'   => 8),
      'floz' =>  array('ml'  => 29.5735296,
                    'l'    => .0295735296,
                    'ft3'  => .00104437934,
                    'gal' => .0078125,
                    'qt'  => .03125,
                    'pint'  => .0625,
                    'cup' => .125,
                    'floz'   => 1),
      );
  function __construct($conversions = array())
  {
    $this->_conversions = Doctrine_Lib::arrayDeepMerge($this->_conversions, $conversions);
  }
  
  public function convert($value, $from, $to)
  {
    $conversion = $this->getConversion($from, $to);
    if (is_array($conversion)) 
    {  
      /* ORDER OF OPERATIONS IS KEY HERE */
      
      // multiply by the factor
      if (isset($conversion['factor'])) 
      {
        $value *= $conversion['factor'];
      }
      
      // Add the deltha
      if (isset($conversion['deltha'])) 
      {
        $value += $conversion['deltha'];
      }
      
      // Call a specific function by extending this model
      if (isset($conversion['function'])) 
      {
        $function = $conversion['function'];
        $value = $this->$function($value);
      }
      
      return $value;
    }
    return $value * $conversion;
  }
  
  public function getConversion($from, $to)
  {
    if (isset($this->_conversions[$from][$to]))
    {
      return $this->_conversions[$from][$to];
    }
    throw new Exception("Conversion not found for '$from' to '$to'");
  } 
}