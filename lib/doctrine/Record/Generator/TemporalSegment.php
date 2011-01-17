<?php
class Doctrine_Record_Generator_TemporalSegment extends Doctrine_Record_Generator {
    // only set fields that override Doctrine_Record_Generator_TemporalSegmentParent and Doctrine_Template_TemporallySegmentedObject
    protected $_options = array(
        // for Doctrine_Record_Generator_TemporalSegment
        'className'         => '%CLASS%Segment',
        'parent_fk'         => null,
        'unique_fields'     => array(),
        // for Doctrine_Record_Generator
        'generateFiles'     => false,
        'generatePath'   => false,
        'builderOptions' => array(),
        'identifier'     => false,
        'table'          => false,
        'pluginTable'    => false,
        'children'       => array(),
        'cascadeDelete'  => true,
        'appLevelDelete' => false
    );

    public function __construct(array $options = array()) {
        if ($path = Doctrine_Template_TemporallySegmentedObject::getSegmentFileGenerationPath()) {
            $this->_options['generateFiles'] = true;
            $this->_options['generatePath'] = $path;
            $this->_options['builderOptions'] = array_merge(
                $this->_options['builderOptions'],
                Doctrine_Template_TemporallySegmentedObject::getSegmentFileBuilderOptions()
            );
        }
        $this->_options = array_merge($this->_options, $options);
    }

    public function setTableDefinition() {
        $this->setPrimaryKey();
        $this->setParentFk();
        $this->moveFieldsToChild();
    }

    private function setPrimaryKey() {
        $this->_table->setColumn('id', 'integer', 10,
            array(
                'type'          => 'integer',
                'unsigned'      => true,
                'primary'       => true,
                'autoincrement' => true,
                'length'        => '10',
            )
        );
    }

    private function setParentFk() {
        $this->inheritParentField($this->getParentPrimaryKeyName(), $this->getParentForeignKeyName(), array('notnull' => true));
    }

    /**
     * move all the segmented fields to the Segment table
     * @return null
     */
    private function moveFieldsToChild() {
        // first, get the list of segmented fields.
        $parent = $this->_options['table'];
        $parent_id = array($this->getParentPrimaryKeyName());
        $parent_fields = $parent->getFieldNames();
        $segmented_fields = array_diff($parent_fields, $parent_id, $this->_options['unique_fields']);

        // move each one from parent to child
        foreach ($segmented_fields as $field) {
            $this->inheritParentField($field);
            $parent->removeColumn($field);
        }
    }

    public function getParentForeignKeyName() {
        if (is_null(@$this->_options['parent_fk'])) {
            $parent_table_name = $this->_options['table']->getComponentName();
            $this->_options['parent_fk'] = strtolower(preg_replace('/([A-Z])/', '_$1', $parent_table_name)) . '_id';
            $this->_options['parent_fk'] = ltrim($this->_options['parent_fk'], '_');
        }
        return $this->_options['parent_fk'];
    }

    private function getParentPrimaryKeyName() {
        static $parent_pk = null;
        if (is_null($parent_pk)) {
            if (is_array($this->_options['table']->getIdentifier())) {
                throw new Doctrine_Temporal_Exception("Unable to create segmented object for table {$parent->getComponentName()} with a complex primary key");
            }
            $parent = $this->_options['table'];
            $parent_pk = $parent->getIdentifier();
        }
        return $parent_pk;
    }

    private function inheritParentField($field_name, $child_field_name = null, $options = array(), $strip_identity = true) {
        if (is_null($child_field_name)) {
            $child_field_name = $field_name;
        }

        $parent_field = $this->_options['table']->getColumnDefinition($field_name);
        if ($strip_identity) {
            unset($parent_field['autoincrement']);
            unset($parent_field['primary']);
        }
        $parent_field = array_merge($parent_field, $options);
        $this->_table->setColumn($child_field_name, $parent_field['type'], $parent_field['length'], $parent_field);
    }
}
