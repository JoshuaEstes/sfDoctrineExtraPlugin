<?php
class Doctrine_Template_Listener_Temporal extends Doctrine_Record_Listener {

    protected $table = null;
    protected $_options = null;
    protected $date_format = null;


    /**
     * __construct
     *
     * @param string $options
     * @return void
     */
    public function __construct(Doctrine_Table &$table, Array $options) {
        $this->table = $table;
        $this->_options = $options;
        $this->date_format = ($this->_options['type'] == 'date') ? 'Y-m-d' : 'c';
    }



    /**
     * (non-PHPdoc)
     * @see branch/vendors/doctrine/Doctrine/Record/Doctrine_Record_Listener#preDqlSelect()
     * add a date constraint to every query before it's executed
     * @param Doctrine_Event $event
     * @return void
     */
    public function preDqlSelect(Doctrine_Event $event) {
        $query = $event->getQuery();
        if (get_class($query) == Doctrine_Template_Temporal::QUERY_CLASS_NAME) { // is this a Temporal query?
            $query_date = $query->getQueryDate($this->date_format);
            if ($query_date !== false) { // false means 'ignore query date'
                $params = $event->getParams();
                $eff_date_field = $params['alias'] . '.' . $this->_options['eff_date'];
                $exp_date_field = $params['alias'] . '.' . $this->_options['exp_date'];
                $query->addWhere($eff_date_field.' <= ?', $query_date);
                $query->addWhere("($exp_date_field IS NULL OR $exp_date_field > ?)", $query_date);
            }
        }
    }



    /**
     * note: listener hook fires after record hook
     * (non-PHPdoc)
     * @see branch/vendors/doctrine/Doctrine/Record/Doctrine_Record_Listener#preSave()
     */
    public function preSave(Doctrine_Event $event) {
        // housekeeping
        $record = $event->getInvoker();

            $this->limitParentDates($record);

            // set any runtime defaults here
            if (!$record->getEffectiveDate()) {
                // effective date defaults to the table's currently set query date
                $record->{$this->_options['eff_date']} = date($this->date_format);
            }

            // sanity checks
            if (!$record->isModified()) { // no changes? don't check anything
                return;
            }
            // don't save an instantaneous or nonsense record
            if (!is_null($record->getExpirationDate())) {
                if ($record->getEffectiveDate() == $record->getExpirationDate()) {
                    throw new Doctrine_Record_SavingNonsenseException(
                        "Won't save instantaneous {$record->getTable()->getComponentName()} record with effective & expiration: ".$record->getEffectiveDate()
                    );
                }
                elseif ($record->getEffectiveDate() > $record->getExpirationDate()) {
                    throw new Doctrine_Record_SavingNonsenseException(
                        "Won't save {$record->getTable()->getComponentName()} record with effective > expiration: ".$record->getEffectiveDate()." > ".$record->getExpirationDate()
                    );
                }
            }

            /**
             * validation: can't modify past dates
             * - can't change expired records (exp_date < today)
             * - can't change past eff_date (eff_date < today)
             *  - if changing eff_date to future on current record, then this will force creation of new, future record AND truncate the current one TODAY.
             */
            if (Doctrine_Template_Temporal::isTemporalEnforcementSet() && !$this->_options['allow_past_modifications']) {
                $this->enforceTemporalModConstraints($record);
            }

            // after finalizing & checking all the date values, is there a temporal unique constraint violation?
            $record->enforceTemporalUniqueness();
    }



    /**
     * enforces the "can't modify the past" rules
     *
     * @param $record
     * @return null
     */
    private function enforceTemporalModConstraints(Doctrine_Record &$record) {
        $today = date($this->date_format);

        $modified_table = $record->getTable()->getComponentName();
        $modified_from = $record->getModified(true);
        $modified_to = $record->getModified();
        $problem = false;
        if (@$modified_from[$this->_options['eff_date']] && $modified_from[$this->_options['eff_date']] < $today) {
            $problem = 'from effective';
            $date = $modified_from[$this->_options['eff_date']];
        }
        elseif(@$modified_from[$this->_options['exp_date']] && $modified_from[$this->_options['exp_date']] < $today) {
            $problem = 'from expiration';
            $date = $modified_from[$this->_options['exp_date']];
        }
        elseif(!is_null($record[$this->_options['exp_date']]) && $record[$this->_options['exp_date']] < $today) {
            $problem = 'expiration';
            $date = $record[$this->_options['exp_date']];
        }

        if ($problem) {
            throw new Doctrine_Temporal_PastModificationException("Won't modify past $modified_table $problem date: ".$date);
        }

        // now, check for modification of a current record OTHER THAN SETTING EXP_DATE
        if ($record[$this->_options['eff_date']] < $today) {
            foreach ($modified_to as $k => &$v) {
                // let the exp_date change, since it doesn't have any effect on the past
                if ($k == $this->_options['exp_date']) {
                    continue;
                }
                // make sure the value has actually changed (sometimes re-setting the same value will give a false positive)
                if ($v == $record[$k]) {
                    continue;
                }
                // some other modified field was found, which is not allowed (since it would affect the past)
                throw new Doctrine_Temporal_PastModificationException("Won't modify current record (except terminating it). Requested to save $k = $v as of $today.");
            }
        }
    }



    /**
     * note: listener hook fires after record hook
     * (non-PHPdoc)
     * @see branch/vendors/doctrine/Doctrine/Record/Doctrine_Record_Listener#postSave()
     */
    public function postSave(Doctrine_Event $event) {
        // housekeeping
        $record = $event->getInvoker();

        // modify any neighbors that need to shift
        if ($this->_options['shift_neighbors_on_save']) {
            $this->shiftNeighboringRecords($record);
        }

        // modify the child records that depend on this record
        $this->limitChildDates($record);
    }



    /**
     * Shift the eff/exp dates of any records in the DB that neighbor this record
     * NOTE: this must run during postSave() to avoid circular nesting issues
     * @return null
     */
    protected function shiftNeighboringRecords($record) {
        // housekeeping
        $eff_date = $this->_options['eff_date'];
        $exp_date = $this->_options['exp_date'];

            // is there a previous segment that should be extended (if this segment's eff_date has increased)?
            $neighbor = $record->getPrevious(true);
            if ($neighbor && $record->$eff_date > $neighbor->$exp_date) {
                $neighbor->$exp_date = $record->$eff_date;
                $neighbor->save();
            }
            // is there a next segment that should be extended (if this segment's exp_date has decreased)?
            $neighbor = $record->getNext(true);
            if ($neighbor && !is_null($record->$exp_date) && $record->$exp_date < $neighbor->$eff_date) {
                $neighbor->$eff_date = $record->$exp_date;
                $neighbor->save();
            }
    }



    /**
     * Shift the eff/exp dates of this table so that it fits within its parent(s) (parent_eff <= child_eff < child_exp <= parent_exp)
     * @param $record Doctrine_Record
     * @return null
     */
    protected function limitParentDates(Doctrine_Record $child) {
            // loop through temporal relationships and make sure this date range is inside the parent's range
            foreach ($this->_options['parents'] as &$parent) {
                    try {
                        $parent_obj = $child->$parent; // TODO causes $parent->_oldValues to be erased
                        $child->setDatesWithinParent($parent_obj);
                    }
                    catch (Doctrine_Record_SavingNonsenseException $e) {
                        $child->delete();
                    }
            }
    }



    /**
     * Shift the eff/exp dates of any children of this table (i.e. 'customer_subscriptions' records are children of 'subscriptions')
     * so that (parent_eff <= child_eff < child_exp <= parent_exp)
     * @param $parent_record Doctrine_Record
     * @return null
     */
    protected function limitChildDates(Doctrine_Record &$parent_record) {
        foreach ($this->_options['children'] as $child_relation) {
            foreach($parent_record->$child_relation as $key => &$child_record) {
                try {
                    $child_record->setDatesWithinParent($parent_record);
                }
                catch (Doctrine_Record_SavingNonsenseException $e) {
                    $parent_record->$child_relation->remove($key);
                    $child_record->delete();
                }
            }
        }
    }
}
