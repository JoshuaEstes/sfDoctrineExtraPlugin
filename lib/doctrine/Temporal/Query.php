<?php
class Doctrine_Temporal_Query extends Doctrine_Query {
    /*
     * Static interface for getting Query objects
     */
    public static function create($date = null, $conn = null, $class = null) {
        if (!$class) {
            $class = get_class();
        }
        $query = parent::create($conn, $class);
        $query->setQueryDate($date);
        return $query;
    }



    /*
     * Object interface
     */
    private $query_date = false;         // disabled by default



    /**
     * set query date
     * @return the date that has been set (in case 'null' was passed in)
     */
    public function setQueryDate($query_date) {
        $this->query_date = $query_date;
    }



    /**
     * get currently set query date
     * @return string
     */
    public function getQueryDate($date_format) {
        if ($this->query_date === false) {
            return false;
        }
        if (is_null($this->query_date)) {
            return date($date_format);
        }
        return date($date_format, strtotime($this->query_date));
    }



    /*
     * the wrapped functions
     * intercepts the Doctrine_Query API and provides mechanism for remembering the current state of the query, and then rolling it back when done.
     */

    public function execute($params = array(), $hydrationMode = null) {
        $breadcrumb = $this->getBreadcrumb();
        $result = parent::execute($params, $hydrationMode);
        $this->restoreBreadcrumb($breadcrumb);
        return $result;
    }
    public function fetchArray($params = array()) {
        $breadcrumb = $this->getBreadcrumb();
        $result = parent::fetchArray($params);
        $this->restoreBreadcrumb($breadcrumb);
        return $result;
    }
    public function fetchOne($params = array(), $hydrationMode = null) {
        $breadcrumb = $this->getBreadcrumb();
        $result = parent::fetchOne($params, $hydrationMode);
        $this->restoreBreadcrumb($breadcrumb);
        return $result;
    }
    public function count($params = array()) {
        $breadcrumb = $this->getBreadcrumb();
        $result = parent::count($params);
        $this->restoreBreadcrumb($breadcrumb);
        return $result;
    }



    /**
     * allow modifying & restoring query state by remembering the important bits
     * This function must return everything that can be modified during Temporal query execution.
     * @return array
     */
    private function getBreadcrumb() {
        return array(
            'dqlParts' => $this->_dqlParts,
        );
    }


    /**
     * restore the state of the query by copying important data
     * This function must restore everything returned by getBreadcrumb()
     * @param array $breadcrumb
     */
    private function restoreBreadcrumb(array $breadcrumb) {
        $this->_dqlParts = $breadcrumb['dqlParts'];
    }
}

