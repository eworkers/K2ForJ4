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
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Pagination\Pagination;

jimport('joomla.application.component.view');

class K2ViewComments extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $document = Factory::getDocument();
        $user = Factory::getUser();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');

        $params = ComponentHelper::getParams('com_k2');
        $this->params = $params;

        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'c.id', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $filter_category = $app->getUserStateFromRequest($option . $view . 'filter_category', 'filter_category', 0, 'int');
        $filter_author = $app->getUserStateFromRequest($option . $view . 'filter_author', 'filter_author', 0, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\"\.\@\-_]/u', '', $search));
        if ($app->isClient('site')) {
            $filter_author = $user->id;
            Factory::getApplication()->input->set('filter_author', $user->id);
        }
        $this->loadHelper('html');

        // Head includes
        K2HelperHTML::loadHeadIncludes(true, false, true, true);

        // JS
        $document->addScriptDeclaration("
			var K2Language = [
				'" . Text::_('K2_YOU_CANNOT_EDIT_TWO_COMMENTS_AT_THE_SAME_TIME', true) . "',
				'" . Text::_('K2_THIS_WILL_PERMANENTLY_DELETE_ALL_UNPUBLISHED_COMMENTS_ARE_YOU_SURE', true) . "',
				'" . Text::_('K2_REPORT_USER_WARNING', true) . "'
			];

			Joomla.submitbutton = function(pressbutton) {
				if (pressbutton == 'remove') {
					if (document.adminForm.boxchecked.value==0){
						alert('" . Text::_('K2_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST_TO_DELETE', true) . "');
						return false;
					}
					if (confirm('" . Text::_('K2_ARE_YOU_SURE_YOU_WANT_TO_DELETE_SELECTED_COMMENTS', true) . "')){
						Joomla.submitform(pressbutton);
					}
				} else if (pressbutton == 'deleteUnpublished') {
					if (confirm('" . Text::_('K2_THIS_WILL_PERMANENTLY_DELETE_ALL_UNPUBLISHED_COMMENTS_ARE_YOU_SURE', true) . "')){
						Joomla.submitform(pressbutton);
					}
				} else if (pressbutton == 'publish') {
					if (document.adminForm.boxchecked.value==0){
						alert('" . Text::_('K2_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST_TO_PUBLISH', true) . "');
						return false;
					}
					Joomla.submitform(pressbutton);
				} else if (pressbutton == 'unpublish') {
					if (document.adminForm.boxchecked.value==0){
						alert('" . Text::_('K2_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST_TO_UNPUBLISH', true) . "');
						return false;
					}
					Joomla.submitform(pressbutton);
				}  else {
					Joomla.submitform(pressbutton);
				}
			};
		");

        K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $model = K2Model::getInstance('Comments', 'K2Model');
        $total = $model->getTotal();
        $comments = $model->getData();

        if ($limitstart > $total - $limit) {
            $limitstart = max(0, (int)(ceil($total / $limit) - 1) * $limit);
            Factory::getApplication()->input->set('limitstart', $limitstart);
        }

        $reportLink = $app->isClient('administrator') ? 'index.php?option=com_k2&view=user&task=report&id=' : 'index.php?option=com_k2&view=comments&task=reportSpammer&id=';
        foreach ($comments as $key => $comment) {
            $comment->reportUserLink = false;
            $comment->commenterLastVisitIP = null;
            if ($comment->userID) {
                $db = Factory::getDbo();
                $db->setQuery("SELECT ip FROM #__k2_users WHERE userID = " . $comment->userID);
                $comment->commenterLastVisitIP = $db->loadResult();

                $commenter = Factory::getUser($comment->userID);
                if ($commenter->name) {
                    $comment->userName = $commenter->name;
                }
                if ($app->isClient('site')) {
                    if ($user->authorise('core.admin', 'com_k2')) {
                        $comment->reportUserLink = Route::_($reportLink . $comment->userID);
                    }
                } else {
                    $comment->reportUserLink = Route::_($reportLink . $comment->userID);
                }
            }

            if ($app->isClient('site')) {
                $comment->status = K2HelperHTML::stateToggler($comment, $key);
            } else {
                $comment->status = HTMLHelper::_('jgrid.published', $comment->published, $key);
            }
        }
        $this->rows = $comments;

        // Pagination
        jimport('joomla.html.pagination');
        $pageNav = new Pagination($total, $limitstart, $limit);
        $this->page = $pageNav;

        $lists = array();

        // Detect exact search phrase using double quotes in search string
        if (substr($search, 0, 1) == '"' && substr($search, -1) == '"') {
            $lists['search'] = "\"" . trim(str_replace('"', '', $search)) . "\"";
        } else {
            $lists['search'] = trim(str_replace('"', '', $search));
        }

        $lists['order_Dir'] = $filter_order_Dir;
        $lists['order'] = $filter_order;

        $filter_state_options[] = HTMLHelper::_('select.option', -1, Text::_('K2_SELECT_STATE'));
        $filter_state_options[] = HTMLHelper::_('select.option', 1, Text::_('K2_PUBLISHED'));
        $filter_state_options[] = HTMLHelper::_('select.option', 0, Text::_('K2_UNPUBLISHED'));
        $lists['state'] = HTMLHelper::_('select.genericlist', $filter_state_options, 'filter_state', '', 'value', 'text', $filter_state);

        require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/categories.php';
        $categoriesModel = K2Model::getInstance('Categories', 'K2Model');
        $categories_option[] = HTMLHelper::_('select.option', 0, Text::_('K2_SELECT_CATEGORY'));
        $categories = $categoriesModel->categoriesTree(null, true, false);
        $categories_options = @array_merge($categories_option, $categories);
        $lists['categories'] = HTMLHelper::_('select.genericlist', $categories_options, 'filter_category', '', 'value', 'text', $filter_category);

        require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/items.php';
        $itemsModel = K2Model::getInstance('Items', 'K2Model');
        $authors = $itemsModel->getItemsAuthors();
        $options = array();
        $options[] = HTMLHelper::_('select.option', 0, Text::_('K2_NO_USER'));
        foreach ($authors as $author) {
            $name = $author->name;
            if ($author->block) {
                $name .= ' [' . Text::_('K2_USER_DISABLED') . ']';
            }
            $options[] = HTMLHelper::_('select.option', $author->id, $name);
        }
        $lists['authors'] = HTMLHelper::_('select.genericlist', $options, 'filter_author', '', 'value', 'text', $filter_author);
        $this->lists = $lists;

        $dateFormat = Text::_('K2_J16_DATE_FORMAT');
        $this->dateFormat = $dateFormat;

        if ($app->isClient('administrator')) {
            // Toolbar
            ToolBarHelper::title(Text::_('K2_COMMENTS'), 'k2.png');

            ToolBarHelper::publishList();
            ToolBarHelper::unpublishList();
            ToolBarHelper::deleteList('', 'remove', 'K2_DELETE');
            ToolBarHelper::custom('deleteUnpublished', 'delete', 'delete', 'K2_DELETE_ALL_UNPUBLISHED', false);

            // Preferences (Parameters/Settings)
            ToolBarHelper::preferences('com_k2', '(window.innerHeight) * 0.9', '(window.innerWidth) * 0.7', 'K2_SETTINGS');
            K2HelperHTML::subMenu();

            $userEditLink = URI::base() . 'index.php?option=com_k2&view=user&cid=';
            $this->userEditLink = $userEditLink;
        }

        if ($app->isClient('site')) {
            // Enforce the "system" template in the frontend
            Factory::getApplication()->input->set('template', 'system');

            // CSS
            $document->addStyleSheet(URI::root(true) . '/templates/system/css/general.css');
            $document->addStyleSheet(URI::root(true) . '/templates/system/css/system.css');
        }

        parent::display($tpl);
    }
}
