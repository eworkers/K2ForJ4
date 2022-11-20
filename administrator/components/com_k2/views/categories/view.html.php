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

use Joomla\String\StringHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;

jimport('joomla.application.component.view');

class K2ViewCategories extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $document = Factory::getDocument();
        $user = Factory::getUser();

        $params = ComponentHelper::getParams('com_k2');
        $this->params = $params;

        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');

        $context = Factory::getApplication()->input->getCmd('context');

        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'c.ordering', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', '', 'word');
        $filter_trash = $app->getUserStateFromRequest($option . $view . 'filter_trash', 'filter_trash', 0, 'int');
        $filter_category = $app->getUserStateFromRequest($option . $view . 'filter_category', 'filter_category', 0, 'int');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $language = $app->getUserStateFromRequest($option . $view . 'language', 'language', '', 'string');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\"\-_]/u', '', $search));
        $model = $this->getModel();
        $total = $model->getTotal();
        $task = Factory::getApplication()->input->getCmd('task');
        if ($limitstart > $total - $limit) {
            $limitstart = max(0, (int)(ceil($total / $limit) - 1) * $limit);
            Factory::getApplication()->input->set('limitstart', $limitstart);
        }

        $categories = $model->getData();
        $categoryModel = K2Model::getInstance('Category', 'K2Model');

        // JS
        $document->addScriptDeclaration("
            var K2SelectItemsError = '" . Text::_('K2_SELECT_SOME_ITEMS_FIRST', true) . "';
            Joomla.submitbutton = function(pressbutton) {
                if (pressbutton == 'trash') {
                    var answer = confirm('" . Text::_('K2_WARNING_YOU_ARE_ABOUT_TO_TRASH_THE_SELECTED_CATEGORIES_THEIR_CHILDREN_CATEGORIES_AND_ALL_THEIR_INCLUDED_ITEMS', true) . "')
                    if (answer) {
                        Joomla.submitform(pressbutton);
                    } else {
                        return;
                    }
                } else {
                    Joomla.submitform(pressbutton);
                }
            };
        ");

        $langs = LanguageHelper::getLanguages();
        $langsMapping = array();
        $langsMapping['*'] = JText::_('K2_ALL');
        foreach ($langs as $lang) {
            $langsMapping[$lang->lang_code] = $lang->title;
        }

        for ($i = 0; $i < count($categories); $i++) {
            $categories[$i]->status = HTMLHelper::_('jgrid.published', $categories[$i]->published, $i, '', $filter_trash == 0 && $context != 'modalselector');
            if ($params->get('showItemsCounterAdmin')) {
                $categories[$i]->numOfItems = $categoryModel->countCategoryItems($categories[$i]->id);
                $categories[$i]->numOfTrashedItems = $categoryModel->countCategoryItems($categories[$i]->id, 1);
            }
            $categories[$i]->canChange = $user->authorise('core.edit.state', 'com_k2.category.' . $categories[$i]->id);

            // Detect the category template
            $categoryParams = json_decode($categories[$i]->params);
            $categories[$i]->template = $categoryParams->theme;
            $categories[$i]->language = $categories[$i]->language ? $categories[$i]->language : '*';
            if (isset($langsMapping)) {
                $categories[$i]->language = $langsMapping[$categories[$i]->language];
            }
            if (!$categories[$i]->template) {
                $categories[$i]->template = 'default';
            }
        }

        $this->rows = $categories;

        // Show message for trash entries in Categories
        if (count($categories) && $filter_trash) {
            $app->enqueueMessage(Text::_('K2_ALL_TRASHED_ITEMS_IN_A_CATEGORY_MUST_BE_DELETED_FIRST'));
        }

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

        $filter_trash_options[] = JHTML::_('select.option', 0, Text::_('K2_CURRENT'));
        $filter_trash_options[] = JHTML::_('select.option', 1, Text::_('K2_TRASHED'));
        $lists['trash'] = JHTML::_('select.genericlist', $filter_trash_options, 'filter_trash', '', 'value', 'text', $filter_trash);

        $filter_state_options[] = JHTML::_('select.option', -1, Text::_('K2_SELECT_STATE'));
        $filter_state_options[] = JHTML::_('select.option', 1, Text::_('K2_PUBLISHED'));
        $filter_state_options[] = JHTML::_('select.option', 0, Text::_('K2_UNPUBLISHED'));
        $lists['state'] = JHTML::_('select.genericlist', $filter_state_options, 'filter_state', '', 'value', 'text', $filter_state);

        require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/categories.php';
        $categoriesModel = K2Model::getInstance('Categories', 'K2Model');
        $categories_option[] = JHTML::_('select.option', 0, Text::_('K2_SELECT_CATEGORY'));
        $categoriesFilter = $categoriesModel->categoriesTree(null, true, false);
        $categoriesTree = $categoriesFilter;
        $categories_options = @array_merge($categories_option, $categoriesFilter);
        $lists['categories'] = JHTML::_('select.genericlist', $categories_options, 'filter_category', '', 'value', 'text', $filter_category);

        // Batch Operations
        $extraFieldsModel = K2Model::getInstance('ExtraFields', 'K2Model');
        $extraFieldsGroups = $extraFieldsModel->getGroups(true); // Fetch entire extra field group list
        $options = array();
        $options[] = JHTML::_('select.option', '', '- ' . Text::_('K2_LEAVE_UNCHANGED') . ' -');
        $options[] = JHTML::_('select.option', '0', Text::_('K2_NONE_ONSELECTLISTS'));
        foreach ($extraFieldsGroups as $extraFieldsGroup) {
            $name = $extraFieldsGroup->name;
            $options[] = JHTML::_('select.option', $extraFieldsGroup->id, $name);
        }
        $lists['batchExtraFieldsGroups'] = JHTML::_('select.genericlist', $options, 'batchExtraFieldsGroups', '', 'value', 'text', null);

        array_unshift($categoriesTree, HTMLHelper::_('select.option', '0', Text::_('K2_NONE_ONSELECTLISTS')));
        array_unshift($categoriesTree, HTMLHelper::_('select.option', '', '- ' . Text::_('K2_LEAVE_UNCHANGED') . ' -'));

        $lists['batchCategories'] = JHTML::_('select.genericlist', $categoriesTree, 'batchCategory', '', 'value', 'text', null);

        $lists['batchAccess'] = JHTML::_('access.level', 'batchAccess', null, '', array(HTMLHelper::_('select.option', '', '- ' . Text::_('K2_LEAVE_UNCHANGED') . ' -')));

        $languages = JHTML::_('contentlanguage.existing', true, true);
        array_unshift($languages, HTMLHelper::_('select.option', '', '- ' . Text::_('K2_LEAVE_UNCHANGED') . ' -'));
        $lists['batchLanguage'] = JHTML::_('select.genericlist', $languages, 'batchLanguage', '', 'value', 'text', null);

        $languages = JHTML::_('contentlanguage.existing', true, true);
        array_unshift($languages, JHTML::_('select.option', '', Text::_('K2_SELECT_LANGUAGE')));
        $lists['language'] = JHTML::_('select.genericlist', $languages, 'language', '', 'value', 'text', $language);
        $this->lists = $lists;

        // Toolbar
        JToolBarHelper::title(Text::_('K2_CATEGORIES'), 'k2.png');
        $toolbar = JToolBar::getInstance('toolbar');

        if ($filter_trash == 1) {
            JToolBarHelper::deleteList('K2_ARE_YOU_SURE_YOU_WANT_TO_DELETE_SELECTED_CATEGORIES', 'remove', 'K2_DELETE');
            JToolBarHelper::custom('restore', 'publish.png', 'publish_f2.png', 'K2_RESTORE', true);
        }

        $this->loadHelper('html');
        K2HelperHTML::subMenu();

        $this->filter_trash = $filter_trash;
        $template = $app->getTemplate();
        $this->template = $template;
        $ordering = (($this->lists['order'] == 'c.ordering' || $this->lists['order'] == 'c.parent, c.ordering') && (!$this->filter_trash));
        $this->ordering = $ordering;

        // Joomla 3.0 drag-n-drop sorting variables
        if ($ordering) {
            HTMLHelper::_('sortablelist.sortable', 'k2CategoriesList', 'adminForm', strtolower($this->lists['order_Dir']), 'index.php?option=com_k2&view=categories&task=saveorder&format=raw');
        }
        $document->addScriptDeclaration('
                Joomla.orderTable = function() {
                    table = document.getElementById("sortTable");
                    direction = document.getElementById("directionTable");
                    order = table.options[table.selectedIndex].value;
                    if (order != "' . $this->lists['order'] . '") {
                        dirn = "asc";
                    } else {
                        dirn = direction.options[direction.selectedIndex].value;
                    }
                    Joomla.tableOrdering(order, dirn, "");
                }
            ');

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $user  = Factory::getApplication()->getIdentity();

        // Get the toolbar object instance
        $toolbar = Toolbar::getInstance('toolbar');

        $toolbar->addNew('add');
        $toolbar->edit('edit')->listCheck(true);

        $dropdown = $toolbar->dropdownButton('status-group')
            ->text('JTOOLBAR_CHANGE_STATUS')
            ->toggleSplit(false)
            ->icon('icon-ellipsis-h')
            ->buttonClass('btn btn-action')
            ->listCheck(true);

        $childBar = $dropdown->getChildToolbar();
        $childBar->publish('publish')->listCheck(true);
        $childBar->unpublish('unpublish')->listCheck(true);
        $childBar->trash('banners.trash')->listCheck(true);
        $childBar->standardButton('copy', 'K2_COPY', 'copy')->listCheck(true);

        $batchButton = '<joomla-toolbar-button id="toolbar-batch"><button id="K2BatchButton" class="btn btn-small" href="#"><i class="icon-edit"></i>' . Text::_('K2_BATCH') . '</button></joomla-toolbar-button>';

        $toolbar->customButton('batch')->html($batchButton);

        if ($user->authorise('core.admin', 'com_k2') || $user->authorise('core.options', 'com_k2')) {
            $toolbar->preferences('com_k2', 'K2_SETTINGS');
            if ($user->gid > 23 && !$this->params->get('hideImportButton')) {
                $buttonUrl = JURI::base() . 'index.php?option=com_k2&amp;view=items&amp;task=import';
                $buttonText = Text::_('K2_IMPORT_JOOMLA_CONTENT');
                $button = '<joomla-toolbar-button id="toolbar-import-content"><a id="K2ImportContentButton" class="btn btn-small" href="' . $buttonUrl . '"><i class="icon-archive"></i>' . $buttonText . '</a></joomla-toolbar-button>';
                $toolbar->customButton('Import')->html($button);
            }
        }
    }
}
