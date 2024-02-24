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

use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\String\StringHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.view');

class K2ViewTags extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $document = Factory::getDocument();
        $user = Factory::getUser();

        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $task = Factory::getApplication()->input->getCmd('task');

        $context = Factory::getApplication()->input->getCmd('context');

        $params = ComponentHelper::getParams('com_k2');
        $this->params = $params;

        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'id', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $search));

        $model = $this->getModel();
        $total = $model->getTotal();
        $tags = $model->getData();

        if ($limitstart > $total - $limit) {
            $limitstart = max(0, (int)(ceil($total / $limit) - 1) * $limit);
            Factory::getApplication()->input->set('limitstart', $limitstart);
        }

        foreach ($tags as $key => $tag) {
            $tag->status = HTMLHelper::_('jgrid.published', $tag->published, $key, '', $context != 'modalselector');
        }
        $this->rows = $tags;

        jimport('joomla.html.pagination');
        $pageNav = new Pagination($total, $limitstart, $limit);
        $this->page = $pageNav;

        $lists = array();
        $lists['search'] = $search;
        $lists['order_Dir'] = $filter_order_Dir;
        $lists['order'] = $filter_order;

        $filter_state_options[] = HTMLHelper::_('select.option', -1, Text::_('K2_SELECT_STATE'));
        $filter_state_options[] = HTMLHelper::_('select.option', 1, Text::_('K2_PUBLISHED'));
        $filter_state_options[] = HTMLHelper::_('select.option', 0, Text::_('K2_UNPUBLISHED'));
        $lists['state'] = HTMLHelper::_('select.genericlist', $filter_state_options, 'filter_state', '', 'value', 'text', $filter_state);

        $this->lists = $lists;

        // JS
        $document->addScriptDeclaration("
            Joomla.submitbutton = function(pressbutton) {
                if (pressbutton == 'remove') {
                    if (confirm('" . Text::_('K2_ARE_YOU_SURE_YOU_WANT_TO_DELETE_SELECTED_TAGS', true) . "')){
                        Joomla.submitform(pressbutton);
                    }
                } else {
                    Joomla.submitform(pressbutton);
                }
            };
        ");

        // Toolbar
        ToolBarHelper::title(Text::_('K2_TAGS'), 'k2.png');

        ToolBarHelper::addNew();
        ToolBarHelper::editList();
        ToolBarHelper::publishList();
        ToolBarHelper::unpublishList();
        ToolBarHelper::deleteList('', 'remove', 'K2_DELETE');
        ToolBarHelper::custom('removeOrphans', 'delete', 'delete', 'K2_DELETE_ORPHAN_TAGS', false);

        // Preferences (Parameters/Settings)
        ToolBarHelper::preferences('com_k2', '(window.innerHeight) * 0.9', '(window.innerWidth) * 0.7', 'K2_SETTINGS');

        $this->loadHelper('html');
        K2HelperHTML::subMenu();

        parent::display($tpl);
    }
}
