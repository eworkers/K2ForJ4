<?php
/**
 * @version    2.11.x
 * @package    K2
 * @author     JoomlaWorks https://www.joomlaworks.net
 * @copyright  Copyright (c) 2006 - 2022 JoomlaWorks Ltd. All rights reserved.
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 */

// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\MenuField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementMenuItem extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        $db = Factory::getDbo();

        // load the list of menu types
        // TODO: move query to model
        $query = 'SELECT menutype, title' . ' FROM #__menu_types' . ' ORDER BY title';
        $db->setQuery($query);
        $menuTypes = $db->loadObjectList();

        $where = '';
        if ($state = $node->attributes('state')) {
            $where .= ' AND published = ' . (int)$state;
        }

        // load the list of menu items
        // TODO: move query to model
        $query = 'SELECT id, parent_id, title, menutype, type, published' . ' FROM #__menu' . $where . ' ORDER BY menutype, parent_id, ordering';

        $db->setQuery($query);
        $menuItems = $db->loadObjectList();

        // establish the hierarchy of the menu
        // TODO: use node model
        $children = array();

        if ($menuItems) {
            // first pass - collect children
            foreach ($menuItems as $v) {
                $v->parent = $v->parent_id;
                $v->name = $v->title;
                $pt = $v->parent;
                $list = @$children[$pt] ? $children[$pt] : array();
                array_push($list, $v);
                $children[$pt] = $list;
            }
        }

        // second pass - get an indent list of the items
        $list = HTMLHelper::_('menu.treerecurse', 0, '', array(), $children, 9999, 0, 0);

        foreach ($list as $item) {
            $item->treename = StringHelper::str_ireplace('&#160;', ' -', $item->treename);
            $mitems[] = HTMLHelper::_('select.option', $item->id, '   ' . $item->treename);
        }

        // assemble into menutype groups
        $n = count($list);
        $groupedList = array();
        foreach ($list as $k => $v) {
            $groupedList[$v->menutype][] = &$list[$k];
        }

        // assemble menu items to the array
        $options = array();
        $options[] = HTMLHelper::_('select.option', '', '- ' . Text::_('K2_SELECT_MENU_ITEM') . ' -');

        foreach ($menuTypes as $type) {
            if ($type != '') {
                $options[] = HTMLHelper::_('select.option', '0', '&nbsp;', 'value', 'text', true);
                $options[] = HTMLHelper::_('select.option', $type->menutype, $type->title . ' - ' . Text::_('K2_TOP'), 'value', 'text', true);
            }
            if (isset($groupedList[$type->menutype])) {
                $n = count($groupedList[$type->menutype]);
                for ($i = 0; $i < $n; $i++) {
                    $item = &$groupedList[$type->menutype][$i];

                    //If menutype is changed but item is not saved yet, use the new type in the list
                    if (Factory::getApplication()->input->getString('option', '', 'get') == 'com_menus') {
                        $currentItemArray = Factory::getApplication()->input->getVar('cid', array(0), '', 'array');
                        $currentItemId = (int)$currentItemArray[0];
                        $currentItemType = Factory::getApplication()->input->getString('type', $item->type, 'get');
                        if ($currentItemId == $item->id && $currentItemType != $item->type) {
                            $item->type = $currentItemType;
                        }
                    }

                    $disable = @strpos($node->attributes('disable'), $item->type) !== false ? true : false;

                    if ($item->published == 0) {
                        $item->treename .= ' [**' . JText::_('K2_UNPUBLISHED') . '**]';
                    }
                    if ($item->published == -2) {
                        $item->treename .= ' [**' . JText::_('K2_TRASHED') . '**]';
                    }

                    $options[] = HTMLHelper::_('select.option', $item->id, $item->treename, 'value', 'text', $disable);
                }
            }
        }

        $fieldName = $name;

        return HTMLHelper::_('select.genericlist', $options, $fieldName, 'class="inputbox"', 'value', 'text', $value, $control_name . $name);
    }
}

class JFormFieldMenuItem extends K2ElementMenuItem
{
    public $type = 'MenuItem';
}

class JElementMenuItem extends K2ElementMenuItem
{
    public $_name = 'MenuItem';
}
