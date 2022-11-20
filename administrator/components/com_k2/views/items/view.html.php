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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Toolbar\Toolbar;

jimport('joomla.application.component.view');

class K2ViewItems extends K2View
{
    public function display($tpl = null)
    {
        jimport('joomla.filesystem.file');
        $app = Factory::getApplication();
        $document = Factory::getDocument();

        $user = Factory::getUser();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');

        $params = ComponentHelper::getParams('com_k2');
        $this->params = $params;

        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'i.id', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
        $filter_trash = $app->getUserStateFromRequest($option . $view . 'filter_trash', 'filter_trash', 0, 'int');
        $filter_featured = $app->getUserStateFromRequest($option . $view . 'filter_featured', 'filter_featured', -1, 'int');
        $filter_category = $app->getUserStateFromRequest($option . $view . 'filter_category', 'filter_category', 0, 'int');
        $filter_author = $app->getUserStateFromRequest($option . $view . 'filter_author', 'filter_author', 0, 'int');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\"\-_]/u', '', $search));
        $tag = $app->getUserStateFromRequest($option . $view . 'tag', 'tag', 0, 'int');
        $language = $app->getUserStateFromRequest($option . $view . 'language', 'language', '', 'string');

        $db = Factory::getDbo();
        $nullDate = $db->getNullDate();

        // JS
        $document->addScriptDeclaration("
            var K2SelectItemsError = '" . Text::_('K2_SELECT_SOME_ITEMS_FIRST', true) . "';
            \$K2(document).ready(function() {
                \$K2('#K2ImportContentButton').click(function(event) {
                    var answer = confirm('" . Text::_('K2_WARNING_YOU_ARE_ABOUT_TO_IMPORT_ALL_SECTIONS_CATEGORIES_AND_ARTICLES_FROM_JOOMLAS_CORE_CONTENT_COMPONENT_COM_CONTENT_INTO_K2_IF_THIS_IS_THE_FIRST_TIME_YOU_IMPORT_CONTENT_TO_K2_AND_YOUR_SITE_HAS_MORE_THAN_A_FEW_THOUSAND_ARTICLES_THE_PROCESS_MAY_TAKE_A_FEW_MINUTES_IF_YOU_HAVE_EXECUTED_THIS_OPERATION_BEFORE_DUPLICATE_CONTENT_MAY_BE_PRODUCED', true) . "');
                    if(!answer){
                        event.preventDefault();
                    }
                });
            });
        ");

        $this->nullDate = $nullDate;

        if ($filter_featured == 1 && $filter_order == 'i.ordering') {
            $filter_order = 'i.featured_ordering';
            Factory::getApplication()->input->set('filter_order', 'i.featured_ordering');
        }

        if ($filter_featured != 1 && $filter_order == 'i.featured_ordering') {
            $filter_order = 'i.ordering';
            Factory::getApplication()->input->set('filter_order', 'i.ordering');
        }

        $model = $this->getModel();
        $items = $model->getData();
        $total = $model->getTotal();
        if ($limitstart > $total - $limit) {
            $limitstart = max(0, (int)(ceil($total / $limit) - 1) * $limit);
            Factory::getApplication()->input->set('limitstart', $limitstart);
        }

        $langs = LanguageHelper::getLanguages();
        $langsMapping = array();
        $langsMapping['*'] = JText::_('K2_ALL');
        foreach ($langs as $lang) {
            $langsMapping[$lang->lang_code] = $lang->title;
        }

        foreach ($items as $key => $item) {
            $item->status = HTMLHelper::_('jgrid.published', $item->published, $key, '', ($filter_trash == 0), 'cb', $item->publish_up, $item->publish_down);
            $states = array(
                1 => array(
                    'featured',
                    'K2_FEATURED',
                    'K2_REMOVE_FEATURED_FLAG',
                    'K2_FEATURED',
                    false,
                    'publish',
                    'publish'
                ),
                0 => array(
                    'featured',
                    'K2_NOT_FEATURED',
                    'K2_FLAG_AS_FEATURED',
                    'K2_NOT_FEATURED',
                    false,
                    'unpublish',
                    'unpublish'
                ),
            );
            $item->featuredStatus = HTMLHelper::_('jgrid.state', $states, $item->featured, $key, '', $filter_trash == 0);
            $item->canChange = $user->authorise('core.edit.state', 'com_k2.item.' . $item->id);
            $item->language = $item->language ? $item->language : '*';
            if (isset($langsMapping)) {
                $item->language = $langsMapping[$item->language];
            }
        }
        $this->rows = $items;

        $lists = array();

        // Detect exact search phrase using double quotes in search string
        if (substr($search, 0, 1) == '"' && substr($search, -1) == '"') {
            $lists['search'] = "\"" . trim(str_replace('"', '', $search)) . "\"";
        } else {
            $lists['search'] = trim(str_replace('"', '', $search));
        }

        if (!$filter_order) {
            $filter_order = 'category';
        }
        $lists['order_Dir'] = $filter_order_Dir;
        $lists['order'] = $filter_order;

        $filter_trash_options[] = JHTML::_('select.option', 0, Text::_('K2_CURRENT'));
        $filter_trash_options[] = JHTML::_('select.option', 1, Text::_('K2_TRASHED'));
        $lists['trash'] = JHTML::_('select.genericlist', $filter_trash_options, 'filter_trash', '', 'value', 'text', $filter_trash);

        require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/categories.php';
        $categoriesModel = K2Model::getInstance('Categories', 'K2Model');
        $categories_option[] = JHTML::_('select.option', 0, Text::_('K2_SELECT_CATEGORY'));
        $categories = $categoriesModel->categoriesTree(null, true, false);
        $categories_options = @array_merge($categories_option, $categories);
        $lists['categories'] = JHTML::_('select.genericlist', $categories_options, 'filter_category', '', 'value', 'text', $filter_category);

        $authors = $model->getItemsAuthors();
        $options = array();
        $options[] = JHTML::_('select.option', 0, Text::_('K2_NO_USER'));
        foreach ($authors as $author) {
            $name = $author->name;
            if ($author->block) {
                $name .= ' [' . Text::_('K2_USER_DISABLED') . ']';
            }
            $options[] = JHTML::_('select.option', $author->id, $name);
        }
        $lists['authors'] = JHTML::_('select.genericlist', $options, 'filter_author', '', 'value', 'text', $filter_author);

        $filter_state_options[] = JHTML::_('select.option', -1, Text::_('K2_SELECT_PUBLISHING_STATE'));
        $filter_state_options[] = JHTML::_('select.option', 1, Text::_('K2_PUBLISHED'));
        $filter_state_options[] = JHTML::_('select.option', 0, Text::_('K2_UNPUBLISHED'));
        $lists['state'] = JHTML::_('select.genericlist', $filter_state_options, 'filter_state', '', 'value', 'text', $filter_state);

        $filter_featured_options[] = JHTML::_('select.option', -1, Text::_('K2_SELECT_FEATURED_STATE'));
        $filter_featured_options[] = JHTML::_('select.option', 1, Text::_('K2_FEATURED'));
        $filter_featured_options[] = JHTML::_('select.option', 0, Text::_('K2_NOT_FEATURED'));
        $lists['featured'] = JHTML::_('select.genericlist', $filter_featured_options, 'filter_featured', '', 'value', 'text', $filter_featured);

        if ($params->get('showTagFilter')) {
            $tagsModel = K2Model::getInstance('Tags', 'K2Model');
            $options = $tagsModel->getFilter();
            $option = new stdClass;
            $option->id = 0;
            $option->name = Text::_('K2_SELECT_TAG');
            array_unshift($options, $option);
            $lists['tag'] = JHTML::_('select.genericlist', $options, 'tag', '', 'id', 'name', $tag);
        }

        if (version_compare(JVERSION, '1.6.0', 'ge')) {
            $languages = JHTML::_('contentlanguage.existing', true, true);
            array_unshift($languages, JHTML::_('select.option', '', Text::_('K2_SELECT_LANGUAGE')));
            $lists['language'] = JHTML::_('select.genericlist', $languages, 'language', '', 'value', 'text', $language);
        }

        // Batch Operations
        $categoriesModel = K2Model::getInstance('Categories', 'K2Model');
        $categories = $categoriesModel->categoriesTree(null, true, false);
        array_unshift($categories, HTMLHelper::_('select.option', '', '- ' . Text::_('K2_LEAVE_UNCHANGED') . ' -'));
        $lists['batchCategories'] = JHTML::_('select.genericlist', $categories, 'batchCategory', '', 'value', 'text');
        $lists['batchAccess'] = version_compare(JVERSION, '2.5', 'ge') ? JHTML::_('access.level', 'batchAccess', null, '', array(HTMLHelper::_('select.option', '', '- ' . Text::_('K2_LEAVE_UNCHANGED') . ' -'))) : str_replace('size="3"', "", JHTML::_('list.accesslevel', $item));

        if (version_compare(JVERSION, '2.5.0', 'ge')) {
            $languages = JHTML::_('contentlanguage.existing', true, true);
            array_unshift($languages, HTMLHelper::_('select.option', '', '- ' . Text::_('K2_LEAVE_UNCHANGED') . ' -'));
            $lists['batchLanguage'] = JHTML::_('select.genericlist', $languages, 'batchLanguage', '', 'value', 'text', null);
        }

        $model = $this->getModel('items');
        $authors = $model->getItemsAuthors();
        $options = array();
        $options[] = JHTML::_('select.option', '', '- ' . Text::_('K2_LEAVE_UNCHANGED') . ' -');
        foreach ($authors as $author) {
            $name = $author->name;
            if ($author->block) {
                $name .= ' [' . Text::_('K2_USER_DISABLED') . ']';
            }
            $options[] = JHTML::_('select.option', $author->id, $name);
        }
        $lists['batchAuthor'] = JHTML::_('select.genericlist', $options, 'batchAuthor', '', 'value', 'text', null);
        $this->lists = $lists;

        // Pagination
        jimport('joomla.html.pagination');
        $pageNav = new Pagination($total, $limitstart, $limit);
        $this->page = $pageNav;

        // Augment with plugin events
        $filters = array();
        $columns = array();

        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onK2BeforeAssignFilters', array(&$filters));
        $this->filters = $filters;
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onK2BeforeAssignColumns', array(&$columns));
        $this->columns = $columns;

        // Toolbar
        JToolBarHelper::title(Text::_('K2_ITEMS'), 'k2.png');

        if ($filter_trash == 1) {
            JToolBarHelper::deleteList('K2_ARE_YOU_SURE_YOU_WANT_TO_DELETE_SELECTED_ITEMS', 'remove', 'K2_DELETE');
            JToolBarHelper::custom('restore', 'publish.png', 'publish_f2.png', 'K2_RESTORE', true);
        }

        $this->loadHelper('html');
        K2HelperHTML::subMenu();

        $template = $app->getTemplate();
        $this->template = $template;
        $this->filter_featured = $filter_featured;
        $this->filter_trash = $filter_trash;
        $this->user = $user;
        $dateFormat = Text::_('K2_J16_DATE_FORMAT');
        $this->dateFormat = $dateFormat;

        $ordering = (($this->lists['order'] == 'i.ordering' || $this->lists['order'] == 'category' || ($this->filter_featured > 0 && $this->lists['order'] == 'i.featured_ordering')) && (!$this->filter_trash));
        $this->ordering = $ordering;

        Table::addIncludePath(JPATH_COMPONENT . '/tables');
        $table = Table::getInstance('K2Item', 'Table');
        $this->table = $table;

        // Joomla 3.x drag-n-drop sorting variables
        if ($ordering) {
            $action = $this->filter_featured == 1 ? 'savefeaturedorder' : 'saveorder';
            HTMLHelper::_('sortablelist.sortable', 'k2ItemsList', 'adminForm', strtolower($this->lists['order_Dir']), 'index.php?option=com_k2&view=items&task=' . $action . '&format=raw');
        }
        $document->addScriptDeclaration('
                /* K2 */
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
        $childBar->standardButton('featured', 'K2_TOGGLE_FEATURED_STATE', 'featured')->listCheck(true);
        $childBar->trash('trash')->listCheck(true);
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
