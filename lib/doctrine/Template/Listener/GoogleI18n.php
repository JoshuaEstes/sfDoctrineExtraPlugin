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
 * Doctrine_Template_Listener_GoogleI18n
 *
 * @package     Doctrine
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.org
 * @since       1.2
 * @version     $Revision$
 */
class Doctrine_Template_Listener_GoogleI18n extends Doctrine_Record_Listener
{
    public function preSave(Doctrine_Event $event)
    {
        $invoker = $event->getInvoker();
        $template = $invoker->getTable()->getTemplate('Doctrine_Template_GoogleI18n');
        $options = $template->getOptions();

        // get the first langauge that was changed
        foreach ($invoker->Translation as $lang => $translation) {
            if ($translation->isModified()) {
                $from = $lang;
                break;
            }
        }

        // skip if no translation was updated
        if ( ! isset($from)) {
            return true;
        }

        foreach ($options['languages'] as $to) {
            if ($to == $from) {
                continue;
            }
            foreach ($invoker->Translation[$to] as $field => $value) {
                if ($invoker->Translation->getTable()->isIdentifier($field)) {
                    continue;
                }
                $fromValue = $invoker->Translation[$from]->$field;

                if ($options['skipEmpty'] && ! $fromValue) {
                    continue;
                }

                $translatedValue = $template->getTranslation($from, $to, $fromValue);
                $invoker->Translation[$to]->$field = $translatedValue;
            }
        }
    }
}
