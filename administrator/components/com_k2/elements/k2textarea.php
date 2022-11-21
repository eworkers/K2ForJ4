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
use Joomla\CMS\Form\FormField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementK2textarea extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        // Attributes
        $fieldName = $name;
        if ($node->attributes()->chars) {
            $chars = $node->attributes()->chars;
        }
        if ($node->attributes()->cols) {
            $cols = $node->attributes()->cols;
        }
        if ($node->attributes()->rows) {
            $rows = $node->attributes()->rows;
        }

        if (!$value) {
            $value = '';
        }

        // Output
        return '<textarea name="' . $fieldName . '" rows="' . $rows . '" cols="' . $cols . '" data-k2-chars="' . $chars . '">' . $value . '</textarea>';
    }
}

class JFormFieldK2textarea extends K2ElementK2textarea
{
    public $type = 'k2textarea';
}

class JElementK2textarea extends K2ElementK2textarea
{
    public $_name = 'k2textarea';
}
