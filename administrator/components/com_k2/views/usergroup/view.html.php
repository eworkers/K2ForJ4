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
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;

jimport('joomla.application.component.view');

class K2ViewUserGroup extends K2View
{
    public function display($tpl = null)
    {
        HTMLHelper::_('bootstrap.tooltip');

        $model = $this->getModel();
        $userGroup = $model->getData();
        OutputFilter::objectHTMLSafe($userGroup, ENT_QUOTES, 'permissions');
        $this->row = $userGroup;

        jimport('joomla.form.form');

        $form = Form::getInstance('permissions', JPATH_COMPONENT_ADMINISTRATOR . '/models/usergroup.xml');
        $values = array('params' => json_decode($userGroup->permissions));
        $form->bind($values);
        $inheritance = isset($values['params']->inheritance) ? $values['params']->inheritance : 0;
        $appliedCategories = isset($values['params']->categories) ? $values['params']->categories : '';
        $this->form = $form;
        $this->categories = $appliedCategories;

        $lists = array();
        require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/categories.php';
        $categoriesModel = K2Model::getInstance('Categories', 'K2Model');
        $categories = $categoriesModel->categoriesTree(null, true);
        $lists['categories'] = HTMLHelper::_('select.genericlist', $categories, 'params[categories][]', 'multiple="multiple" size="15"', 'value', 'text', $appliedCategories);
        $lists['inheritance'] = HTMLHelper::_('select.booleanlist', 'params[inheritance]', null, $inheritance);
        $this->lists = $lists;

        // Disable Joomla menu
        Factory::getApplication()->input->set('hidemainmenu', 1);

        // Toolbar
        $title = (Factory::getApplication()->input->getInt('cid')) ? Text::_('K2_EDIT_USER_GROUP') : Text::_('K2_ADD_USER_GROUP');
        ToolBarHelper::title($title, 'k2.png');
        ToolBarHelper::apply();
        ToolBarHelper::save();
        $saveNewIcon = 'save-new.png';
        ToolBarHelper::custom('saveAndNew', $saveNewIcon, 'save_f2.png', 'K2_SAVE_AND_NEW', false);
        ToolBarHelper::cancel();

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
