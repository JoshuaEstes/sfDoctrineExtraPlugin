<?php
/*
 *  $Id$
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
 * Behavior for adding Tagging features to your models
 *
 * @package     Doctrine
 * @subpackage  Template
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.phpdoctrine.org
 * @since       1.0
 * @version     $Revision$
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class Doctrine_Taggable extends Doctrine_Record_Generator
{
    protected $_options = array(
        'builderOptions' => array(),
        'tagField'       => null,
    );

    public function __construct(array $options = array())
    {
        $this->_options['generatePath'] = sfConfig::get('sf_lib_dir') . '/model/doctrine';
        $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
    }

    public function setTableDefinition()
    {
        $this->hasColumn('tag_id', 'integer', null, array('primary' => true));
    }

    public function buildRelation()
    {
        $options = array(
            'local'    => 'tag_id',
            'foreign'  => 'id',
            'onDelete' => 'CASCADE',
            'onUpdate' => 'CASCADE'
        );

        $this->_table->bind(array($this->_options['tagClass'], $options), Doctrine_Relation::ONE);

        $options = array(
            'local'    => 'id',
            'foreign'  => 'tag_id',
            'refClass' => $this->getOption('table')->getComponentName() . $this->_options['tagClass']
        );

        $this->getOption('table')->bind(array($this->_options['tagClass'] . ' as ' . $this->_options['tagAlias'], $options), Doctrine_Relation::MANY);

        parent::buildRelation();
    }

    public function setUp()
    {
        $tag = new Doctrine_Taggable_Tag();
        if (!empty($this->_options['builderOptions'])) {
             $tag->setOption('builderOptions', $this->_options['builderOptions']);
        }
        $tag->setOption('parent', $this);
        $tag->setOption('tagField', $this->_options['tagField']);
        $tag->setOption('toString', $this->_options['tagField']);
        $this->addChild($tag);
    }
}