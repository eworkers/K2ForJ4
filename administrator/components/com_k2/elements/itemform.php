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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementItemForm extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        $document = Factory::getDocument();
        $document->addScriptDeclaration("
        	/* Mootools Snippet */
			window.addEvent('domready', function() {
				if($('request-options')) {
					$$('.panel')[0].setStyle('display', 'none');
				}
				if($('jform_browserNav')) {
					$('jform_browserNav').setProperty('value', 2);
					$('jform_browserNav').getElements('option')[0].destroy();
				}
				if($('browserNav')) {
					$('browserNav').setProperty('value', 2);
					options = $('browserNav').getElements('option');
					if(options.length == 3) {
						options[0].remove();
					}
				}
			});
		");
        return '';
    }

    public function fetchTooltip($label, $description, &$node, $control_name, $name)
    {
        return '';
    }
}

class JFormFielditemform extends K2ElementItemForm
{
    public $type = 'itemform';
}

class JElementitemform extends K2ElementItemForm
{
    public $_name = 'itemform';
}
