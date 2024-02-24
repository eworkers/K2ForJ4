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

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;

jimport('joomla.application.component.view');

class K2ViewUser extends K2View
{
    public function display($tpl = null)
    {
        $model = $this->getModel();
        $user = $model->getData();
        OutputFilter::objectHTMLSafe($user, ENT_QUOTES, array('params', 'plugins'));
        $joomlaUser = User::getInstance(Factory::getApplication()->input->getInt('cid'));

        $user->name = $joomlaUser->name;
        $user->userID = $joomlaUser->id;
        $this->row = $user;

        /* since J4 compatibility */
// get user editor
        $editor = Factory::getUser()->getParam('editor', 'tinymce');
        $wysiwyg = Editor::getInstance($editor);
        $editor = $wysiwyg->display('description', $user->description, '480px', '250px', '', '', false);
        $this->editor = $editor;

        $lists = array();
        $genderOptions[] = HTMLHelper::_('select.option', 'n', Text::_('K2_NOT_SPECIFIED'));
        $genderOptions[] = HTMLHelper::_('select.option', 'm', Text::_('K2_MALE'));
        $genderOptions[] = HTMLHelper::_('select.option', 'f', Text::_('K2_FEMALE'));
        $lists['gender'] = HTMLHelper::_('select.radiolist', $genderOptions, 'gender', '', 'value', 'text', $user->gender);

        $userGroupOptions = $model->getUserGroups();
        $lists['userGroup'] = HTMLHelper::_('select.genericlist', $userGroupOptions, 'group', 'class="inputbox"', 'id', 'name', $user->group);

        $this->lists = $lists;

        $params = ComponentHelper::getParams('com_k2');
        $this->params = $params;

        // Plugins
        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        /* since J4 compatibility */
        $K2Plugins = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(&$user, 'user'));
        $this->K2Plugins = $K2Plugins;

        // Disable Joomla menu
        Factory::getApplication()->input->set('hidemainmenu', 1);

        // Toolbar
        $toolbar = JToolBar::getInstance('toolbar');
        JToolBarHelper::title(Text::_('K2_USER'), 'k2.png');

        JToolBarHelper::apply();
        JToolBarHelper::save();
        JToolBarHelper::cancel();

        $editJoomlaUserButtonUrl = URI::base() . 'index.php?option=com_users&view=user&task=user.edit&id=' . $user->userID;
        $editJoomlaUserButton = '<a data-k2-modal="iframe" href="' . $editJoomlaUserButtonUrl . '" class="btn btn-small"><i class="icon-edit"></i>' . Text::_('K2_EDIT_JOOMLA_USER') . '</a>';
        $toolbar->prependButton('Custom', $editJoomlaUserButton);

        parent::display($tpl);
    }
}
