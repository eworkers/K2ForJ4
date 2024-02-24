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
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.view');

class K2ViewExtraFields extends K2View
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
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'groupname', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $search));
        $filter_type = $app->getUserStateFromRequest($option . $view . 'filter_type', 'filter_type', '', 'string');
        $filter_group = $app->getUserStateFromRequest($option . $view . 'filter_group', 'filter_group', '', 'string');

        $model = $this->getModel();
        $total = $model->getTotal();
        if ($limitstart > $total - $limit) {
            $limitstart = max(0, (int)(ceil($total / $limit) - 1) * $limit);
            Factory::getApplication()->input->set('limitstart', $limitstart);
        }
        $extraFields = $model->getData();
        foreach ($extraFields as $key => $extraField) {
            $extraField->status = HTMLHelper::_('jgrid.published', $extraField->published, $key);
            $values = json_decode($extraField->value);
            if (isset($values[0]->alias) && !empty($values[0]->alias)) {
                $extraField->alias = $values[0]->alias;
            } else {
                $filter = InputFilter::getInstance();
                $extraField->alias = $filter->clean($extraField->name, 'WORD');
            }
        }
        $this->rows = $extraFields;

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

        $extraFieldGroups = $model->getGroups(true);
        $groups[] = HTMLHelper::_('select.option', '0', Text::_('K2_SELECT_GROUP'));

        foreach ($extraFieldGroups as $extraFieldGroup) {
            $groups[] = HTMLHelper::_('select.option', $extraFieldGroup->id, $extraFieldGroup->name);
        }
        $lists['group'] = HTMLHelper::_('select.genericlist', $groups, 'filter_group', '', 'value', 'text', $filter_group);

        $typeOptions[] = HTMLHelper::_('select.option', 0, Text::_('K2_SELECT_TYPE'));

        $typeOptions[] = HTMLHelper::_('select.option', 'textfield', Text::_('K2_TEXT_FIELD'));
        $typeOptions[] = HTMLHelper::_('select.option', 'textarea', Text::_('K2_TEXTAREA'));
        $typeOptions[] = HTMLHelper::_('select.option', 'select', Text::_('K2_DROPDOWN_SELECTION'));
        $typeOptions[] = HTMLHelper::_('select.option', 'multipleSelect', Text::_('K2_MULTISELECT_LIST'));
        $typeOptions[] = HTMLHelper::_('select.option', 'radio', Text::_('K2_RADIO_BUTTONS'));
        $typeOptions[] = HTMLHelper::_('select.option', 'link', Text::_('K2_LINK'));
        $typeOptions[] = HTMLHelper::_('select.option', 'csv', Text::_('K2_CSV_DATA'));
        $typeOptions[] = HTMLHelper::_('select.option', 'labels', Text::_('K2_SEARCHABLE_LABELS'));
        $typeOptions[] = HTMLHelper::_('select.option', 'date', Text::_('K2_DATE'));
        $typeOptions[] = HTMLHelper::_('select.option', 'image', Text::_('K2_IMAGE'));
        $typeOptions[] = HTMLHelper::_('select.option', 'header', Text::_('K2_HEADER'));

        $lists['type'] = HTMLHelper::_('select.genericlist', $typeOptions, 'filter_type', '', 'value', 'text', $filter_type);

        $this->lists = $lists;

        // Toolbar
        ToolBarHelper::title(Text::_('K2_EXTRA_FIELDS'), 'k2.png');

        ToolBarHelper::addNew();
        ToolBarHelper::editList();
        ToolBarHelper::publishList();
        ToolBarHelper::unpublishList();
        ToolBarHelper::deleteList('K2_ARE_YOU_SURE_YOU_WANT_TO_DELETE_SELECTED_EXTRA_FIELDS', 'remove', 'K2_DELETE');

        ToolBarHelper::preferences('com_k2', '(window.innerHeight) * 0.9', '(window.innerWidth) * 0.7', 'K2_SETTINGS');

        $this->loadHelper('html');
        K2HelperHTML::subMenu();

        $ordering = ($this->lists['order'] == 'ordering');
        $this->ordering = $ordering;

        // Joomla 3.x drag-n-drop sorting variables
        if ($ordering) {
            HTMLHelper::_('sortablelist.sortable', 'k2ExtraFieldsList', 'adminForm', strtolower($this->lists['order_Dir']), 'index.php?option=com_k2&view=extrafields&task=saveorder&format=raw');
        }
        $document = Factory::getDocument();
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

        parent::display($tpl);
    }
}
