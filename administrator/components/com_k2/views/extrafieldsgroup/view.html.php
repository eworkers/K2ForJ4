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

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

jimport('joomla.application.component.view');

class K2ViewExtraFieldsGroup extends K2View
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $extraFieldsGroup = $model->getExtraFieldsGroup();
        OutputFilter::objectHTMLSafe($extraFieldsGroup);
        $this->row = $extraFieldsGroup;

        // Disable Joomla menu
        Factory::getApplication()->input->set('hidemainmenu', 1);

        // Toolbar
        $title = (Factory::getApplication()->input->getInt('cid')) ? Text::_('K2_EDIT_EXTRA_FIELD_GROUP') : Text::_('K2_ADD_EXTRA_FIELD_GROUP');
        JToolBarHelper::title($title, 'k2.png');

        JToolBarHelper::apply();
        JToolBarHelper::save();
        JToolBarHelper::custom('saveAndNew', 'save-new.png', 'save_f2.png', 'K2_SAVE_AND_NEW', false);
        JToolBarHelper::cancel();

        // JS
        $document = Factory::getDocument();
        $document->addScriptDeclaration("
            Joomla.submitbutton = function(pressbutton) {
                if (pressbutton == 'cancel') {
                    Joomla.submitform(pressbutton);
                    return;
                }
                if (\$K2.trim(\$K2('#name').val()) == '') {
                    alert('" . Text::_('K2_GROUP_NAME_CANNOT_BE_EMPTY', true) . "');
                } else {
                    Joomla.submitform(pressbutton);
                }
            };
        ");

        parent::display($tpl);
    }
}
