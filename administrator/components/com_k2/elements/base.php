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

K2HelperHTML::loadHeadIncludes(true, true, false, true);

jimport('joomla.form.formfield');
class K2Element extends FormField
{
    public function getInput()
    {
        /*
        if (method_exists($this,'fetchElement')) { // BC
           return $this->fetchElement($this->name, $this->value, $this->element, $this->options['control']);
        }
        return $this->fetchElementValue($this->name, $this->value, $this->element, $this->options['control']);
        */
        $controls = (!empty($this->options['control'])) ? $this->options['control'] : array();
        return $this->fetchElement($this->name, $this->value, $this->element, $controls);
    }

    public function getLabel()
    {
        /*
        if (method_exists($this, 'fetchElementName')) {
            return $this->fetchElementName($this->element['label'], $this->description, $this->element, $this->options['control'], $this->element['name'] = '');
        }
        */
        if (method_exists($this, 'fetchTooltip')) { // BC
            $controls = (!empty($this->options['control'])) ? $this->options['control'] : array();
            return $this->fetchTooltip($this->element['label'], $this->description, $this->element, $controls, $this->element['name'] = '');
        }
        return parent::getLabel();
    }

    public function render($layoutId, $data = array())
    {
        return $this->getInput();
    }
}
