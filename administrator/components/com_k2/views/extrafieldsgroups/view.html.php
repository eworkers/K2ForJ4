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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.view');

class K2ViewExtraFieldsGroups extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');

        $params = ComponentHelper::getParams('com_k2');
        $this->params = $params;

        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', '', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', '', 'word');

        $model = $this->getModel();
        $total = $model->getTotalGroups();
        if ($limitstart > $total - $limit) {
            $limitstart = max(0, (int)(ceil($total / $limit) - 1) * $limit);
            Factory::getApplication()->input->set('limitstart', $limitstart);
        }
        $extraFieldGroups = $model->getGroups();

        $this->rows = $extraFieldGroups;

        jimport('joomla.html.pagination');
        $pageNav = new Pagination($total, $limitstart, $limit);
        $this->page = $pageNav;

        // Toolbar
        JToolBarHelper::title(Text::_('K2_EXTRA_FIELD_GROUPS'), 'k2.png');

        JToolBarHelper::addNew();
        JToolBarHelper::editList();
        JToolBarHelper::deleteList('', 'remove', 'K2_DELETE');

        JToolBarHelper::preferences('com_k2', '(window.innerHeight) * 0.9', '(window.innerWidth) * 0.7', 'K2_SETTINGS');

        $this->loadHelper('html');
        K2HelperHTML::subMenu();

        // JS
        $document = Factory::getDocument();
        $document->addScriptDeclaration("
            Joomla.submitbutton = function(pressbutton) {
                if (pressbutton == 'remove') {
                    if (confirm('" . Text::_('K2_WARNING_ARE_YOU_SURE_YOU_WANT_TO_DELETE_SELECTED_EXTRA_FIELDS_GROUPS_DELETING_THE_GROUPS_WILL_ALSO_DELETE_THE_ASSIGNED_EXTRA_FIELDS', true) . "')){
                        Joomla.submitform(pressbutton);
                    }
                } else {
                    Joomla.submitform(pressbutton);
                }
            };
        ");

        parent::display($tpl);
    }
}
