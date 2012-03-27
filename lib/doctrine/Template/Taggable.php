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
 * Add tagging capabilities to your models
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
class Doctrine_Template_Taggable extends Doctrine_Template
{
    protected $_options = array(
        'tagClass'      => 'TaggableTag',
        'tagField'      => 'name',
        'tagAlias'      => 'Tags',
        'className'     => '%CLASS%TaggableTag',
        'toString'      => false,
        'generateFiles' => true,
        'table'         => false,
        'pluginTable'   => false,
        'children'      => array()
    );

    public function __construct(array $options = array())
    {
        $this->_options['generatePath'] = sfConfig::get('sf_lib_dir') . '/model/doctrine';
        $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
        $this->_plugin = new Doctrine_Taggable($this->_options);
    }

    public function setUp()
    {
        $this->_plugin->initialize($this->_table);

        $options = array(
            'local'    => 'tag_id',
            'foreign'  => 'id',
            'refClass' => $this->_plugin->getTable()->getOption('name')
        );

        Doctrine::getTable($this->_options['tagClass'])->bind(array($this->_table->getComponentName(), $options), Doctrine_Relation::MANY);
    }

    public function getTagNames()
    {
        $tagField = $this->_options['tagField'];
        $tagNames = array();
        foreach ($this->getInvoker()->Tags as $tag) {
            $tagNames[] = $tag[$tagField];
        }
        return $tagNames;
    }

    public function getTagsString($sep = ', ')
    {
        return implode($sep, $this->getTagNames());
    }

    public function setTags($tags)
    {
        $tagIds = $this->getTagIds($tags);
        $invoker = $this->getInvoker();
        $invoker->unlink('Tags');
        $invoker->link('Tags', $tagIds);
    }

    public function addTags($tags)
    {
        $tagIds = $this->getTagIds($tags);
        $invoker = $this->getInvoker();
        $invoker->link('Tags', $tagIds);
    }

    public function removeTags($tags)
    {
        $tagIds = $this->getTagIds($tags);
        $invoker = $this->getInvoker();
        $invoker->unlink('Tags', $tagIds);
    }

    public function removeAllTags()
    {
        $invoker = $this->getInvoker();
        $invoker->unlink('Tags');
    }

    public function getRelatedRecords($hydrationMode = Doctrine::HYDRATE_RECORD)
    {
        return $this->getInvoker()->getTable()
            ->createQuery('a')
            ->leftJoin('a.Tags t')
            ->whereIn('t.id', $this->getCurrentTagIds())
            ->andWhere('a.id != ?', $this->getInvoker()->id)
            ->execute(array(), $hydrationMode);
    }

    public function getCurrentTagIds()
    {
        $tagIds = array();
        foreach ($this->getInvoker()->Tags as $tag) {
            $tagIds[] = $tag['id'];
        }
        return $tagIds;
    }

    public function getTagIds($tags)
    {
        if (is_string($tags)) {
            $tagClass = $this->_options['tagClass'];
            $tagField = $this->_options['tagField'];

            $sep = strpos($tags, ',') !== false ? ',':' ';
            $tags = explode($sep, $tags);
            $tagNames = array();
            foreach(array_keys($tags) as $key) {
                $tagName = trim($tags[$key]);
                if ($tagName) {
                    $tagNames[strtolower($tagName)] = $tagName;
                }
            }

            $tagsList = array();
            if (count($tagNames)) {
                $existingTags = Doctrine_Query::create()
                    ->from($tagClass.' t INDEXBY t.'.$tagField)
                    ->whereIn('t.'.$tagField, array_keys($tagNames))
                    ->fetchArray();

                foreach(array_keys($existingTags) as $tag) {
                    $tagsList[] = $existingTags[$tag]['id'];
                    unset($tagNames[strtolower($tag)]);
                }

                if (count($tagNames)) {
                    foreach($tagNames as $tagName) {
                        $tag = new $tagClass();
                        $tag->$tagField = $tagName;
                        $tag->save();
                        $tagsList[] = $tag['id'];
                    }
                }
            }

            return $tagsList;
        } else if (is_array($tags)) {
            if (is_numeric(current($tags))) {
                return $tags;
            } else {
                return $this->getTagIds(implode(', ', $tags));
            }
        } else if ($tags instanceof Doctrine_Collection) {
            return $tags->getPrimaryKeys();
        } else {
            throw new Doctrine_Exception('Invalid $tags data provided. Must be a string of tags, an array of tag ids, or a Doctrine_Collection of tag records.');
        }
    }
}