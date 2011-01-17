<?php
class Doctrine_Template_Temporal extends Doctrine_Template {

    const QUERY_CLASS_NAME = 'Doctrine_Temporal_Query';


    private static $enforce_temporal_constraints = true;

    protected $_options = array(
        'type'                      => 'date',
        'eff_date'                  => 'eff_date',
        'exp_date'                  => 'exp_date',
        'unique_fields'             => array(),
        'parents'                   => array(),
        'children'                  => array(),
        'allow_past_modifications'  => true,
        'shift_neighbors_on_save'   => false,
    );
    protected $date_format = null;


    /**
     * override option defaults during class setup
     * @param $options any k-v pairs to set
     */
    public function __construct(Array $options=array()) {
        // set the basic options array
        $this->_options = array_merge($this->_options, $options);
        $this->date_format = ($this->_options['type'] == 'date') ? 'Y-m-d' : 'c';
    }



    public static function setTemporalEnforcement($enforce = true) {
        self::$enforce_temporal_constraints = $enforce;
    }



    public static function isTemporalEnforcementSet() {
        return self::$enforce_temporal_constraints;
    }



    public function getTimePeriod() {
        $mine = $this->getInvoker();
        return new Doctrine_Temporal_TimePeriod($mine->getEffectiveDate(), $mine->getExpirationDate());
    }



    /**
     * Setup the Temporal listener to watch for inserts/updates
     * @return void
     */
    public function setTableDefinition() {
        // NOT NULL will default to 0000-00-00, which still works for comparison operators <, <=
        $this->hasColumn($this->_options['eff_date'], $this->_options['type'], null /*length*/, array('notnull' => true));
        $this->hasColumn($this->_options['exp_date'], $this->_options['type']);
        $table = $this->getTable();
        $temporal_listener = new Doctrine_Template_Listener_Temporal($table, $this->_options);
        $this->addListener($temporal_listener);
    }



    public function createNewTemporalSegment($eff_date = null, $save = true) {
        // housekeeping
        $record = $this->getInvoker();
        if (is_null($eff_date)) {
            $eff_date = date($this->date_format);
        }

        // if the calling code requested a new segment at the start of the existing segment, then just return the existing one
        // this is the same as deleting the underlying record and creating a new one, which is what would happen on ->save() anyway.
        if ($record->getEffectiveDate() >= $eff_date) {
            return $record;
        }

        // create a copy
        $data = $record->getData();
        $data[$this->_options['eff_date']] = $eff_date;

        if ($this->_table->getIdentifierType() === Doctrine_Core::IDENTIFIER_AUTOINC) {
            $id = $this->_table->getIdentifier();
            unset($data[$id]);
        }

        $copy = $this->getTable()->create($data);

        // expire the current record
        $record->{$this->_options['exp_date']} = $eff_date;
        if($save) {
            $record->save();
            $copy->save();
        }

        return $copy;
    }



    /**
     * Terminate this temporal segment (as of today by default). Either sets exp_date, or deletes the record if the exp_date <= eff_date
     * @param $exp_date what date to terminate the segment on
     * @return boolean true if the record still exists (its exp_date was set to today) or false if it doesn't (the record was deleted)
     */
    public function terminate($exp_date = null) {
        // housekeeping
        $record = $this->getInvoker();
        if (is_null($exp_date)) {
            $exp_date = date($this->date_format);
        }

        // do the termination
        $record[$this->_options['exp_date']] = $exp_date;
        if ($record->getEffectiveDate() >= $record->getExpirationDate()) {
            $record->delete();
            return false;
        }
        else {
            $record->save(); // also terminates all related records
            return true;
        }
    }



    /**
     * getter for eff_date (where calling code doesn't know column name)
     * @return unknown_type
     */
    public function getEffectiveDate() {
        return $this->getInvoker()->{$this->_options['eff_date']};
    }



    /**
     * getter for exp_date (where calling code doesn't know column name)
     * @return unknown_type
     */
    public function getExpirationDate() {
        return $this->getInvoker()->{$this->_options['exp_date']};
    }



    /**
     * check whether this record overlaps any other records with the same temporally unique key(s)
     * if the enforcement mode is to shift existing records, then shift the existing ones to make room.
     * if the mode is to not allow non-unique saves, then throw an exception.
     * NOTE: this function happens during preSave()
     * @return null
     */
    public function enforceTemporalUniqueness() {
        if ($this->_options['shift_neighbors_on_save']) {
            $this->shiftOverlappingRecords();
        }
        elseif ($this->getOverlappingRecords(true)) { // true: just want the count
            throw new Doctrine_Temporal_UniqueKeyException("Won't save record that violates temporal unique constraint.");
        }
    }



    /**
     * get underlying database records with the same temporally unique values, where (this.eff < overlap.exp and this.exp > overlap.eff)
     * @return Doctrine_Collection
     */
    public function getOverlappingRecords($count_only = false) {
        // if no temporal uniqueness is required, then let the client save anything.
        if (!count($this->_options['unique_fields'])) {
            if ($count_only) {
                return 0;
            }
            else {
                return array();
            }
        }

        // housekeeping
        $record = $this->getInvoker();
        $eff_date = $record->getEffectiveDate();
        $exp_date = $record->getExpirationDate();

        // the base query sets the date range we're looking for
        $query = Doctrine_Query::create()
            ->from      ($record->getTable()->getComponentName())
            ->where     ("({$this->_options['exp_date']} IS NULL OR {$this->_options['exp_date']} > ?)", $eff_date)
        ;
        if (!is_null($exp_date)) {
            $query->andWhere ($this->_options['eff_date'].' < ?', $exp_date);
        }


        // add temporal uniqueness constraints
        $this->restrictRecordFromQuery($query, $record);
        $this->addTemporalUniquenessToQuery($query, $record);

        // fetch data
        if ($count_only) {
            $overlapping_records = $query->count();
        }
        else {
            $overlapping_records = $query->execute();
        }

        return $overlapping_records;
    }



    /**
     * Based on the last-changed exp_date, extend any temporal children that had already ended at this record's end date.
     * This function should be called AFTER $parent->save()
     * Example: a subscription ends on 10/1, and is extended to 10/15 and saved.
     *          This function will extend SubscriptionProduct links that ended on 10/1 to 10/15.
     * @param $cascade if true, then grandchildren (ad infinitum) will also be extended
     * @return unknown_type
     */
    public function extendChildDates(array $modified_from, $cascade = true) {
        $parent_record = $this->getInvoker();
        Msg::indent('Temporal->extendChildDates() for parent record ' . get_class($parent_record) . " {$parent_record->id}");
            if (array_key_exists($this->_options['eff_date'], $modified_from)) {
                // the effective date has moved, so see if any children are affected.
                $this->extendChildDate(
                    $parent_record,
                    $this->_options['eff_date'],
                    $modified_from[$this->_options['eff_date']],
                    $parent_record[$this->_options['eff_date']],
                    $cascade
                );
            }
            if (array_key_exists($this->_options['exp_date'], $modified_from)) {
                // the expiration date has moved, so see if any children are affected.
                $this->extendChildDate(
                    $parent_record,
                    $this->_options['exp_date'],
                    $modified_from[$this->_options['exp_date']],
                    $parent_record[$this->_options['exp_date']],
                    $cascade
                );
            }
    }
    private function extendChildDate(Doctrine_Record &$parent_record, $date_field, $original_value, $new_value, $cascade) {
        foreach ($this->_options['children'] as $child_relation) {
            foreach($parent_record->$child_relation as $key => &$child_record) {
                    if ($child_record->$date_field == $original_value) {
                        $child_record->$date_field = $new_value;
                        $modified_from = $child_record->getModified(true); // true: get old value
                        $child_record->save();
                        if ($cascade) {
                            $child_record->extendChildDates($modified_from, true);
                        }
                    }
            }
        }
    }



    /**
     * Shift the eff/exp dates of any records in the DB that overlap with this record
     * Here sums the rules:
     * - no temporal duplicates allowed (overlapping dates + unique columns - for instance, group+product for a given subscription)
     * - if eff_date and exp_date match existing record exactly, then just update this record.
     * - if dates don't match, then update existing records so that the new record nests between/beside them
     * NOTE: this has to run during preSave() to avoid temporal uniqueness constraint violation
     * @return null
     */
    protected function shiftOverlappingRecords() {
        // housekeeping
        $record = $this->getInvoker();

            // find any overlapping records in the database, so we can make a temporally consistent update
            $overlapping_records = $record->getOverlappingRecords();
            if (!$overlapping_records) {
                return;
            }

            // here, there is at least 1 overlapping record that doesn't match what we're saving (i.e. shouldn't be updated in place).
            // Modify the overlapping record(s) so that the new one will fit between/beside them.
            foreach ($overlapping_records as $overlapping_record) {
                $this->shiftOverlappingRecord($overlapping_record);
            }
    }



    /**
     * shift the start/end dates of DB records that overlap the current record
     * @param $saving_record the record that hasn't been saved to the database yet
     * @param $existing_record the record in the database that needs to be modified to make room for the new record
     * @return unknown_type
     */
    private function shiftOverlappingRecord(Doctrine_Record &$existing_record) {
        // housekeeping
        $saving_record = $this->getInvoker();

        $eff_date = $this->_options['eff_date'];
        $exp_date = $this->_options['exp_date'];
        if ($saving_record->containsPeriod($existing_record)) {
            // If the new dates CONTAIN the existing dates, then just replace the existing record.
            $existing_record->delete();
        }
        elseif ($existing_record->containsPeriod($saving_record)) {
            // overlapping record contains this record (eff < $saving_record < exp)
            if ($saving_record->begins($existing_record)) {
                // here, we need to shift the existing record's effective date to start after the saving record
                $existing_record->$eff_date = $saving_record->$exp_date;
                $existing_record->save();
            }
            else {
                // here, we need to shift the existing record's expiration date to end when the new one begins.
                // we'll do it below, so that we can use its currently set exp_date for the next test.

                // do we also need to create a 3rd record? (if the new one divides the existing one, then the existing one is split into 2)
                if (!$saving_record->ends($existing_record)) {
                    $existing_copy = $existing_record->copy();
                    $existing_copy->$eff_date = $saving_record->$exp_date;
                    $existing_copy->save();
                }

                // end the existing date at the start of the saving one
                $existing_record->$exp_date = $saving_record->$eff_date;
                $existing_record->save();
            }
        }
        elseif ($existing_record->getEffectiveDate() < $saving_record->getEffectiveDate()) {
            // expire the existing record
            $existing_record->$exp_date = $saving_record->$eff_date;
            $existing_record->save();
        }
        elseif ($existing_record->getEffectiveDate() > $saving_record->getEffectiveDate()) {
            // start the new record at the end of this one.
            $new_eff_date = $saving_record->$exp_date;
            if (is_null($new_eff_date)) {
                $existing_record->delete();
            }
            else {
                $existing_record->$eff_date = $new_eff_date;
                $existing_record->save();
            }
        }
    }



    /**
     * get the record that precedes this one in time
     * @return Doctrine_Record
     */
    public function getPrevious($use_old_values = false) {
        return $this->getTemporalNeighbor('left', $use_old_values);
    }



    /**
     * get the record that succeeds this one in time
     * @return Doctrine_Record
     */
    public function getNext($use_old_values = false) {
        return $this->getTemporalNeighbor('right', $use_old_values);
    }



    /**
     * get either the previous or next database row, which has the same temporally unique values, and whose date range abuts this one.
     * @param $direction 'left' or 'right'
     * @return unknown_type
     */
    private function getTemporalNeighbor($direction, $use_old_values = false) {
        // housekeeping
        $record = $this->getInvoker();
        if ($direction == 'left') {
            $my_field = $this->_options['eff_date'];
            $neighbor_field = $this->_options['exp_date'];
        }
        else {
            $my_field = $this->_options['exp_date'];
            $neighbor_field = $this->_options['eff_date'];
        }
        if ($use_old_values) {
            $old = $record->getModified(true, true);
            if (array_key_exists($my_field, $old)) {
                $my_field_value = $old[$my_field];
            }
            else {
                $my_field_value = $record->$my_field;
            }
        }
        else {
            $my_field_value = $record->$my_field;
        }

        // the base query sets the date range we're looking for
        $query = Doctrine_Query::create()
            ->from      ($this->getTable()->getComponentName())
            ->where     ("$neighbor_field = ?", $my_field_value);
        ;

        // add temporal uniqueness constraints
        $this->addTemporalUniquenessToQuery($query, $record);

        // fetch data - should be only 1 unless there's corrupt data
        $neighbor = $query->fetchOne();
        return $neighbor;
    }



    public function isExpired() {
        $record = $this->getInvoker();
        if (is_null($record->exp_date)) {
            return false;
        }
        if ($record->exp_date > date($this->date_format)) {
            return false;
        }
        return true;
    }



    /**
     * sets this record's temporal dates between a parent record's temporal dates (so that parent_eff <= my_eff < my_exp <= parent_exp)
     * @param $record Doctrine_Record
     * @return null
     */
    public function setDatesWithinParent(Doctrine_Record $parent) {
        $child = $this->getInvoker();
        if ($parent->getEffectiveDate() > $child->getEffectiveDate()) {
            $child->{$this->_options['eff_date']} = $parent->getEffectiveDate();
        }
        if (!is_null($parent->getExpirationDate())
        && (    is_null($child->getExpirationDate())
            ||  $parent->getExpirationDate() < $child->getExpirationDate())
        ) {
            $child->{$this->_options['exp_date']} = $parent->getExpirationDate();
        }

        // sanity check: if we end up with nonsense, then self-destruct.
        if (!is_null($child->getExpirationDate()) && $child->getExpirationDate() <= $child->getEffectiveDate()) {
            throw new Doctrine_Record_SavingNonsenseException("Nonsense child record with id {$child->id} should be deleted by parent");
        }
    }



    /**
     * make sure the given record is NOT returned by the query by adding its ID value(s) to the WHERE clause (where id != {this_id} or similar)
     * NOTE: the query needs to be querying the same table as the record
     * @param $query
     * @param $record
     * @return null
     */
    private function restrictRecordFromQuery(Doctrine_Query &$query, Doctrine_Record &$record){
        // restrict query to not return the row that we're working on. This prevents updates from triggering unnecessary nesting
        $id = $record->identifier();
        if (!$id) {
            return;
        }
        $where_text = '('.implode(' != ? OR ', array_keys($id)).' != ?)';
        $query->addWhere($where_text, array_values($id));
    }



    /**
     * add temporally unique constraint to a query by referring to this table's temporally unique fields
     * for example, add 'WHERE group_id = {my_group_id} AND product_id = {my_product_id} for a Subscription query.
     * NOTE: the query needs to be querying the same table as the record
     * @param $query
     * @param $record
     * @return unknown_type
     */
    private function addTemporalUniquenessToQuery($query, $record) {
        // add to the query any unique fields that have been defined for this table
        foreach ($this->_options['unique_fields'] as $fieldname) {
            if (!array_key_exists($fieldname, $record->identifier())) { // don't do this for ID columns (they were filtered out above)
				// workaround for Doctrine bug: sometimes Doctrine_Record::_oid (and therefore $record->$fieldname)
                // gets out of sync with record identifier
				if (is_object($record->$fieldname) && count($record->$fieldname->identifier()) == 1) {
					$id_array = $record->$fieldname->identifier();
					$keys = array_keys($id_array);
					$query->addWhere($fieldname.' = ?', $id_array[$keys[0]]);
				}
				else {
	                $query->addWhere($fieldname.' = ?', $record->$fieldname);
				}
            }
        }
    }



    /**
     * checks whether the eff_date and exp_date of this record contain a given single date (eff <= date <= exp)
     * @return boolean
     */
    public function containsDate($date) {
        return $this->getInvoker()->getTimePeriod()->containsDate($date);
    }



    /**
     * checks whether this record's time period fully contains/engulfs the given record's time period
     * @param $theirs Doctrine_Record (or a TimePeriod)
     * @param $exp_date_inclusive boolean set to false to require that this record's exp_date be GREATER than that passed in
     * @return boolean
     */
    public function containsPeriod($theirs, $exp_date_inclusive = true) {
        return $this->getInvoker()->getTimePeriod()->containsPeriod($theirs->getTimePeriod(), $exp_date_inclusive);
    }



    /**
     * checks whether the eff_date and exp_date of this record overlap a given date range
     * @param $theirs - either another temporal record, or a Doctrine_Temporal_TimePeriod
     * @return boolean
     */
    public function overlaps($theirs) {
        return $this->doTimePeriodFunction('overlaps', $theirs);
    }



    /**
     * returns true if the given record's start or end date equals this record's end or start date, respectively.
     * @param $theirs
     * @return boolean
     */
    public function borders($theirs) {
        return $this->doTimePeriodFunction('borders', $theirs);
    }



    /**
     * returns true if this record is contained by the given record, and their start dates are the same (given.eff == my.eff < my.exp < given.exp)
     * @param $theirs
     * @return boolean
     */
    public function begins($theirs) {
        return $this->doTimePeriodFunction('begins', $theirs);
    }



    /**
     * returns true if this record is contained by the given record, and their start dates are the same (given.eff == my.eff < my.exp < given.exp)
     * @param $theirs
     * @return boolean
     */
    public function ends(Doctrine_Record $theirs) {
        return $this->doTimePeriodFunction('ends', $theirs);
    }



    /**
     * returns true if this record ends before the given one (i.e. this exp < theirs, or this exp. is not null and theirs is.)
     * @param $theirs
     * @return boolean
     */
    public function endsBefore(Doctrine_Record $theirs) {
        return $this->doTimePeriodFunction('endsBefore', $theirs);
    }



    /**
     */
    public function endsBeforeDate($theirs) {
        $theirs = new Doctrine_Temporal_TimePeriod($theirs, $theirs);
        return $this->doTimePeriodFunction('endsBefore', $theirs);
    }



    public function setLengthDays($days) {
        $record = $this->getInvoker();
        $tp = new Doctrine_Temporal_TimePeriod($record->eff_date, $record->eff_date);
        $tp->setLengthDays($days);
        $record->exp_date = $tp->getExpirationDate();
    }



    private function doTimePeriodFunction($function, $theirs) {
        return $this->getInvoker()->getTimePeriod()->$function($theirs->getTimePeriod());
    }
}

