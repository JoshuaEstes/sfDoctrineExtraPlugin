<?php
/**
 * parameter class used for masquerading as a Temporal-behavior Doctrine_Record object.
 * @author luke
 *
 */
class Doctrine_Temporal_TimePeriod {
    /*
     * getters & setters
     */
    private $eff_date = null;
    private $exp_date = null;
    public function __construct($eff_date, $exp_date) {
        $this->eff_date = $eff_date;
        $this->exp_date = $exp_date;
    }
    public function getTimePeriod() {
        return $this;
    }
    public function getEffectiveDate($date_only = false) {
        if ($date_only) {
            return $this->formatToDate($this->eff_date);
        }
        return $this->eff_date;
    }
    public function getExpirationDate($date_only = false) {
        if ($date_only) {
            return $this->formatToDate($this->exp_date);
        }
        return $this->exp_date;
    }
    public function set($eff_date, $exp_date) {
        $this->eff_date = $eff_date;
        $this->exp_date = $exp_date;
    }



    public function getLengthDays() {
        // convert the begin & end to date-only values, to prevent a morning-to-afternoon scenario falsely adding 1 day
        $eff_date = $this->getEffectiveDate(true);
        $exp_date = $this->getExpirationDate(true);
        if (is_null($exp_date) || is_null($eff_date)) {
            return null;
        }
        $length_seconds = strtotime($exp_date) - strtotime($eff_date);
        $length_days = $length_seconds / 60 / 60 / 24;
        return intval(floor($length_days));
    }



    public function setLengthDays($days, $format = 'Y-m-d') {
        if (is_null($this->eff_date)) {
            return null;
        }
        
        $length_seconds = $days * 24 * 60 * 60;
        $eff_date_seconds = strtotime($this->eff_date);
        $exp_date_seconds = $eff_date_seconds + $length_seconds;
        $this->exp_date = date($format, $exp_date_seconds);
    }



    /**
     * combine any periods that meet or overlap - returns the most concise representation of the set of time periods
     * @param array $time_periods array of Doctrine_Temporal_TimePeriod objects to coalesce
     * @return array of Doctrine_Temporal_TimePeriod objects
     */
    public static function coalesce(array $time_periods) {
        if (count($time_periods) == 0) {
            return $time_periods;
        }

        usort($time_periods, array("Doctrine_Temporal_TimePeriod", "usortByStartDate"));
        $working = null;
        foreach ($time_periods as $k => &$time_period) {
            if (is_null($working)) {
                $working =& $time_period;
            }
            elseif (is_null($working->exp_date)) {
                unset($time_periods[$k]);
            }
            elseif($working->exp_date >= $time_period->eff_date) {
                $working->exp_date = max($working->exp_date, $time_period->exp_date);
                unset($time_periods[$k]);
            }
            else {
                $working =& $time_period;
            }
        }
        return $time_periods;
    }



    /**
     * implements sorting algorithm for usort() in above function
     * @param Doctrine_Temporal_TimePeriod $t1
     * @param Doctrine_Temporal_TimePeriod $t2
     * @return integer
     */
    private static function usortByStartDate(Doctrine_Temporal_TimePeriod $t1, Doctrine_Temporal_TimePeriod $t2) {
        if ($t1->eff_date == $t2->eff_date) {
            return 0;
        }
        if ($t1->eff_date > $t2->eff_date) {
            return 1;
        }
        return -1;
    }



    /**
     * checks whether the eff_date and exp_date of this record contain a given single date (eff <= date < exp)
     * @return boolean
     */
    public function containsDate($date) {
        // if my time period starts AFTER the date, then it doesn't contain it.
        if ($date < $this->eff_date) {
            return false;
        }

        // if my time period never expires then we know we're ok at this point
        if (is_null($this->exp_date)) {
            return true;
        }

        // if my time period ends before or on date, then I don't contain it.
        if (is_null($date)) {
            return false;
        }
        if ($this->exp_date <= $date) {
            return false;
        }

        // no more failure scenarios.
        return true;
    }



    /**
     * checks whether this record's time period fully contains/engulfs the given record's time period
     * @param Doctrine_Temporal_TimePeriod $theirs a Doctrine_Temporal_TimePeriod object to compare to
     * @param $exp_date_inclusive boolean set to false to require that this record's exp_date be GREATER than that passed in
     * @return boolean
     */
    public function containsPeriod(Doctrine_Temporal_TimePeriod $theirs, $exp_date_inclusive = true) {
        // if my time period starts AFTER theirs starts, then it doesn't contain it.
        if ($theirs->eff_date < $this->eff_date) {
            return false;
        }

        // if my time period never expires then we know we're ok at this point
        if (is_null($this->exp_date)) {
            return true;
        }

        // if their time period ends AFTER mine ends, then mine doesn't contain it.
        if (is_null($theirs->exp_date)) {
            return false;
        }
        if ($this->exp_date < $theirs->exp_date) {
            return false;
        }
        if (!$exp_date_inclusive &&  $this->exp_date == $theirs->exp_date) {
            return false;
        }

        // no more failure scenarios.
        return true;
    }



    /**
     * returns true if the given time period's start or end date equals this end or start date, respectively.
     * @param Doctrine_Temporal_TimePeriod $theirs a Doctrine_Temporal_TimePeriod object to compare to
     * @return boolean
     */
    public function borders(Doctrine_Temporal_TimePeriod $theirs) {
        if (!is_null($theirs->exp_date) && $theirs->exp_date == $this->eff_date) {
            return true;
        }
        if (!is_null($this->exp_date) && $this->exp_date == $theirs->eff_date) {
            return true;
        }
        return false;
    }



    /**
     * checks whether the eff_date and exp_date of this time period overlap a given date range
     * @param $theirs - either temporal record, or a Doctrine_Temporal_TimePeriod
     * @return boolean
     */
    public function overlaps(Doctrine_Temporal_TimePeriod $theirs) {
        // if my record starts AFTER theirs, then it doesn't overlap it.
        if (!is_null($theirs->exp_date) && $theirs->exp_date <= $this->eff_date) {
            return false;
        }

        // if their record starts AFTER mine, then it doesn't overlap it.
        if (!is_null($this->exp_date) && $this->exp_date <= $theirs->eff_date) {
            return false;
        }

        // no more failure scenarios.
        return true;
    }



    /**
     * returns true if this time period is contained by the given one, and their start dates are the same (given.eff == my.eff < my.exp < given.exp)
     * @param $theirs
     * @return boolean
     */
    public function begins(Doctrine_Temporal_TimePeriod $theirs) {
        // does the given record contain this one? (theirs.eff <= my.eff < my.exp <= theirs.exp)
        if (!$theirs->containsPeriod($this)) {
            return false;
        }

        // do they start on the same date?
        return ($this->eff_date == $theirs->eff_date);
    }



    /**
     * returns true if this time period is contained by the given one, and their start dates are the same (given.eff == my.eff < my.exp < given.exp)
     * @param $theirs
     * @return boolean
     */
    public function ends(Doctrine_Temporal_TimePeriod $theirs) {
        // does the given time period contain this one? (theirs.eff <= my.eff < my.exp <= theirs.exp)
        if (!$theirs->containsPeriod($this)) {
            return false;
        }

        // do they end on the same date?
        if (is_null($this->exp_date) && is_null($theirs->exp_date)) {
            // consider two infinite end dates to be 'equal'
            return true;
        }
        return ($this->exp_date == $theirs->exp_date);
    }



    /**
     * returns true if this time period ends before the given one (i.e. this exp < theirs, or this exp. is not null and theirs is.)
     * @param $theirs
     * @return boolean
     */
    public function endsBeforeDate($theirs) {
        if (!is_object($theirs)) {
            $theirs = new Doctrine_Temporal_TimePeriod($theirs, $theirs);
        }
        return $this->endsBefore($theirs);
    }



    /**
     * returns true if this time period ends before the given one (i.e. this exp < theirs, or this exp. is not null and theirs is.)
     * @param $theirs
     * @return boolean
     */
    public function endsBefore(Doctrine_Temporal_TimePeriod $theirs) {
        if (is_null($this->exp_date)) {
            // if this is infinite, it can't end before anything.
            return false;
        }
        if (is_null($theirs->exp_date)) {
            // this one isn't infinite (see above) and theirs is.
            return true;
        }
        // the basic comparison scenario
        return ($this->exp_date < $theirs->exp_date);
    }



    private function formatToDate($date) {
        if (preg_match('/^(\d{4}).(\d{2}).(\d{2})/', $date, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }
        return $date;
    }
}
