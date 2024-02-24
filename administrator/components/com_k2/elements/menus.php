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

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\MenuField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementMenus extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        $fieldName = $name;
        $db = Factory::getDbo();
        $query = "SELECT menutype, title FROM #__menu_types";
        $db->setQuery($query);
        $menus = $db->loadObjectList();
        $options = array();
        $options[] = HTMLHelper::_('select.option', '', Text::_('K2_NONE_ONSELECTLISTS'));
        foreach ($menus as $menu) {
            $options[] = HTMLHelper::_('select.option', $menu->menutype, $menu->title);
        }
        return HTMLHelper::_('select.genericlist', $options, $fieldName, 'class="inputbox"', 'value', 'text', $value);
    }
}

class JFormFieldMenus extends K2ElementMenus
{
    public $type = 'menus';
}

class JElementMenus extends K2ElementMenus
{
    public $_name = 'menus';
}
