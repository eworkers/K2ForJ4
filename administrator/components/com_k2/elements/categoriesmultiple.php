<?php
/**
 * @version    2.11.x
 * @package    K2
 * @author     JoomlaWorks https://www.joomlaworks.net
 * @copyright  Copyright (c) 2006 - 2022 JoomlaWorks Ltd. All rights reserved.
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 */

// no direct access
defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementCategoriesMultiple extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        $document = Factory::getDocument();

        $db = Factory::getDbo();
        $query = 'SELECT m.* FROM #__k2_categories m WHERE trash = 0 ORDER BY parent, ordering';
        $db->setQuery($query);
        $mitems = $db->loadObjectList();
        $children = array();
        if ($mitems) {
            foreach ($mitems as $v) {
                $v->title = $v->name;
                $v->parent_id = $v->parent;
                $pt = $v->parent;
                $list = @$children[$pt] ? $children[$pt] : array();
                array_push($list, $v);
                $children[$pt] = $list;
            }
        }
        $list = JHTML::_('menu.treerecurse', 0, '', array(), $children, 9999, 0, 0);
        $mitems = array();

        foreach ($list as $item) {
            $item->treename = StringHelper::str_ireplace('&#160;', '- ', $item->treename);
            $mitems[] = JHTML::_('select.option', $item->id, '   ' . $item->treename);
        }

        $doc = Factory::getDocument();
        $js = "
			\$K2(document).ready(function(){

				\$K2('#jform_params_catfilter0').click(function(){
					\$K2('#jformparamscategory_id').attr('disabled', 'disabled');
					\$K2('#jformparamscategory_id option').each(function() {
						\$K2(this).attr('selected', 'selected');
					});
					\$K2('#jformparamscategory_id').trigger('liszt:updated');
				});

				\$K2('#jform_params_catfilter1').click(function(){
					\$K2('#jformparamscategory_id').removeAttr('disabled');
					\$K2('#jformparamscategory_id option').each(function() {
						\$K2(this).removeAttr('selected');
					});
					\$K2('#jformparamscategory_id').trigger('liszt:updated');
				});

				if (\$K2('#jform_params_catfilter0').attr('checked')) {
					\$K2('#jformparamscategory_id').attr('disabled', 'disabled');
					\$K2('#jformparamscategory_id option').each(function() {
						\$K2(this).attr('selected', 'selected');
					});
					\$K2('#jformparamscategory_id').trigger('liszt:updated');
				}

				if (\$K2('#jform_params_catfilter1').attr('checked')) {
					\$K2('#jformparamscategory_id').removeAttr('disabled');
					\$K2('#jformparamscategory_id').trigger('liszt:updated');
				}

			});
			";

        $fieldName = $name . '[]';

        $doc->addScriptDeclaration($js);
        $output = JHTML::_('select.genericlist', $mitems, $fieldName, 'class="inputbox" multiple="multiple" size="10"', 'value', 'text', $value);
        return $output;
    }
}

class JFormFieldCategoriesMultiple extends K2ElementCategoriesMultiple
{
    public $type = 'categoriesmultiple';
}

class JElementCategoriesMultiple extends K2ElementCategoriesMultiple
{
    public $_name = 'categoriesmultiple';
}
