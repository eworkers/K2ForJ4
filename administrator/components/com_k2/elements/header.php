<?php
/**
 * @version    2.11.x
 * @package    K2
 * @author     JoomlaWorks https://www.joomlaworks.net
 * @copyright  Copyright (c) 2006 - 2022 JoomlaWorks Ltd. All rights reserved.
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\FormField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementHeader extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        $additionalCssClass = '';
        if ($node->attributes()->class) {
            $additionalCssClass = ' '.$node->attributes()->class;
        }
        return '<div class="jwHeaderContainer'.$additionalCssClass.'"><div class="jwHeaderContent">'.JText::_($value).'</div><div class="jwHeaderClr"></div></div>';
    }

    public function fetchTooltip($label, $description, &$node, $control_name, $name)
    {
        return null;
    }
}

class JFormFieldHeader extends K2ElementHeader
{
    public $type = 'header';
}

class JElementHeader extends K2ElementHeader
{
    public $_name = 'header';
}
