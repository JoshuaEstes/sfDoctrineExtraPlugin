<?php
define('ENABLE_TSO_CLASSNAME_HACK', true);
class Doctrine_Template_TemporallySegmentedObject extends Doctrine_Template_Temporal {

    protected $_options = array(
        'unique_fields'             => array(),
        'parents'                   => array(),
        'children'                  => array(),
        'parent_alias'              => 'Parent',
        'child_alias'               => 'Segments',
        'type'                      => 'date',
        'allow_past_modifications'  => true,
        'shift_neighbors_on_save'   => false,
        'eff_date'                  => 'eff_date',
        'exp_date'                  => 'exp_date',
        'segment_eff_date'          => 'eff_date',
        'segment_exp_date'          => 'exp_date',
    );

    private static $file_generation_path = null;
    private static $file_builder_options = array();

    public function __construct(array $options = array()) {
        parent::__construct($options);

        $segment_options = $this->_options;
        $segment_options['parents'][] = $segment_options['parent_alias']; // enforce temporal parent/child constraints
        if (isset($segment_options['segment_classname'])) {
            $segment_options['className'] = $segment_options['segment_classname'];
            unset($segment_options['segment_classname']);
        }
        else {
            unset($segment_options['className']); // let it default
        }
        unset($segment_options['children']); // interferes with Doctrine_Record_Generator
        $this->_plugin = new Doctrine_Record_Generator_TemporalSegment($segment_options);

        $this->_options['children'][] = $this->_options['child_alias']; // enforce temporal parent/child constraints
    }



    /**
     * (non-PHPdoc)
     * intercept a call to create a segment on the parent, and create a new segment within the parent's segment set instead
     * @see vendors_doctrine/Extensions/Temporal/lib/Doctrine/Template/Doctrine_Template_Temporal#createNewTemporalSegment($eff_date, $save)
     */
    public function createNewTemporalSegment($eff_date = null, $save = true) {
        $segment = $this->getCurrentSegment($eff_date);
        return $segment->createNewTemporalSegment($eff_date, $save);
    }



    /**
     * get the currently active temporal segment attached to this record, or the first future record, or the last past record.
     * no validation is done here; errors will be thrown by relation mechanism if no Segments relation is defined
     * @return Doctrine_Record
     */
    public function getCurrentSegment($date = null) {
        // housekeeping
        if (is_null($date)) {
            $date = date($this->date_format);
        }
        $record = $this->getInvoker();
        $first_future_segment = null;

        // search through segments
        foreach ($record->Segments as $s) {
            if ($s->containsDate($date)) {
                return $s;
            }

            // don't consider any past segments as potential results
            // (it's easiest to check eff_date - this gives an accurate answer because we already know it's not current)
            if ($s->getEffectiveDate() < $date) {
                continue;
            }

            // pouplate any future record as a default result
            if (is_null($first_future_segment)) {
                $first_future_segment = $s;
                continue;
            }

            // is it a future record that starts before our current result?
            if($s->getEffectiveDate() < $first_future_segment->getEffectiveDate()) {
                $first_future_segment = $s;
            }
        }
        if (is_null($first_future_segment)) {
            return $this->getLastSegment();
        }
        return $first_future_segment;
    }



    /**
     * get the last temporal segment attached to this record (even if it's in the future)
     * no validation is done here; errors will be thrown by relation mechanism if no Segments relation is defined
     * @return Doctrine_Record
     */
    public function getLastSegment() {
        $record = $this->getInvoker();
        $last_segment = null;
        foreach ($record->Segments as $s) {
            if (is_null($last_segment) || $s['eff_date'] > $last_segment['eff_date']) {
                $last_segment = $s;
            }
        }
        return $last_segment;
    }



    public function setUp() {
        $this->createChildTable();
        $this->setRelations();
        $this->setChildActAs();
    }



    private function createChildTable() {
        $this->_plugin->initialize($this->_table);
        if (ENABLE_TSO_CLASSNAME_HACK && !$this->_plugin->getTable()) {
            // HACK
            // the table wasn't built, probably because generateFiles is set to false. Set it here so that we can use it later.
            // what we're actually doing is mimicking the second 1/2 of initialize(), but without the class generation steps.
            $this->_plugin->buildTable();
            $fk = $this->_plugin->buildForeignKeys($this->_table);
            $this->_plugin->getTable()->setColumns($fk);
            $this->_plugin->buildRelation();
            $this->_plugin->setTableDefinition();
            $this->_plugin->setUp();
            //$this->_plugin->generateClassFromTable($this->_table); // here is the hack
            $this->_plugin->buildChildDefinitions();
            $this->_plugin->getTable()->initIdentifier();
        }
    }



    private function setRelations() {
        $parent = $this->_table;
        $child = $this->_plugin->getTable();

        $parent_id = $this->_table->getIdentifier();
        $parent_fk = $this->_plugin->getParentForeignKeyName();
        // set 'segments' relation from parent to child
        $table_label = "{$child->getComponentName()} as {$this->_options['child_alias']}";
        $parent->hasMany($table_label, array(
            'local'     => $parent_id,
            'foreign'   => $parent_fk,
            'orderBy'   => $this->_options['segment_eff_date'],
            'cascade'   => array('delete'),
        ));
        // set 'parent' relation from child to parent
        $table_label = "{$parent->getComponentName()} as {$this->_options['parent_alias']}";
        $child->hasOne($table_label, array(
             'local'    => $parent_fk,
             'foreign'  => $parent_id,
        ));
    }



    private function setChildActAs() {
        // set custom Segment options (these are different than the parent)
        $options = $this->_options;
        $options['unique_fields']    = array($this->_plugin->getParentForeignKeyName());
        $options['parents']          = array($this->_options['parent_alias']);
        $options['eff_date']         = $this->_options['segment_eff_date'];
        $options['exp_date']         = $this->_options['segment_exp_date'];

        // filter to only set the relevant stuff
        $desired_keys = array(
            'type'                      => 0,
            'eff_date'                  => 0,
            'exp_date'                  => 0,
            'unique_fields'             => 0,
            'parents'                   => 0,
            'allow_past_modifications'  => 0,
            'shift_neighbors_on_save'   => 0,
        );
        $options = array_intersect_key($options, $desired_keys);
        $template = new Doctrine_Template_Temporal($options);
        $this->_plugin->getTable()->getRecordInstance()->actAs($template);
    }



    public static function setSegmentFileGeneration($path = null) {
        self::$file_generation_path = $path;
    }



    public static function getSegmentFileGenerationPath() {
        return self::$file_generation_path;
    }



    public static function setSegmentFileBuilderOptions(array $options) {
        self::$file_builder_options = $options;
    }



    public static function getSegmentFileBuilderOptions() {
        return self::$file_builder_options;
    }
}

