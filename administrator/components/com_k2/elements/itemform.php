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
        	var $ = jQuery.noConflict();
			$(document).ready(function(){
				if($('request-options')) {
                    if($('.panel')[0])
                        {
                            $('.panel')[0].css('display', 'none');
                        }
                }
				if($('jform_browserNav')) {
					$('#jform_browserNav').val(2);
					$('#jform_browserNav').find('option').get(0).remove();
					$('#jform_browserNav').trigger('liszt:updated');
				}
				if($('browserNav')) {
					$('#browserNav').val(2);
					if($('#browserNav').length == 3) {
						$('#browserNav').find('option').get(0).remove();
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
