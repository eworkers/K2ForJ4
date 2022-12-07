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
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.model');

JTable::addIncludePath(JPATH_COMPONENT . '/tables');

class K2ModelExtraFields extends K2Model
{
    public function getData()
    {
        $app = Factory::getApplication();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'groupname', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $search));
        $filter_type = $app->getUserStateFromRequest($option . $view . 'filter_type', 'filter_type', '', 'string');
        $filter_group = $app->getUserStateFromRequest($option . $view . 'filter_group', 'filter_group', 0, 'int');

        $query = "SELECT exf.*, exfg.name as groupname FROM #__k2_extra_fields AS exf LEFT JOIN #__k2_extra_fields_groups exfg ON exf.group=exfg.id  WHERE exf.id>0";

        if ($filter_state > -1) {
            $query .= " AND published={$filter_state}";
        }

        if ($search) {
            $escaped = $db->escape($search, true);
            $query .= " AND LOWER( exf.name ) LIKE " . $db->Quote('%' . $escaped . '%', false);
        }

        if ($filter_type) {
            $query .= " AND `type`=" . $db->Quote($filter_type);
        }

        if ($filter_group) {
            $query .= " AND `group`={$filter_group}";
        }

        if (!$filter_order) {
            $filter_order = '`group`';
        }

        if ($filter_order == 'ordering') {
            $query .= " ORDER BY `group`, ordering {$filter_order_Dir}";
        } else {
            $query .= " ORDER BY {$filter_order} {$filter_order_Dir}, `group`, ordering";
        }

        $db->setQuery($query, $limitstart, $limit);
        $rows = $db->loadObjectList();
        return $rows;
    }

    public function getTotal()
    {
        $app = Factory::getApplication();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . '.limitstart', 'limitstart', 0, 'int');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', 1, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $search));
        $filter_type = $app->getUserStateFromRequest($option . $view . 'filter_type', 'filter_type', '', 'string');
        $filter_group = $app->getUserStateFromRequest($option . $view . 'filter_group', 'filter_group', '', 'string');

        $query = "SELECT COUNT(*) FROM #__k2_extra_fields WHERE id>0";

        if ($filter_state > -1) {
            $query .= " AND published={$filter_state}";
        }

        if ($search) {
            $escaped = $db->escape($search, true);
            $query .= " AND LOWER( name ) LIKE " . $db->Quote('%' . $escaped . '%', false);
        }

        if ($filter_type) {
            $query .= " AND `type`=" . $db->Quote($filter_type);
        }

        if ($filter_group) {
            $query .= " AND `group`=" . $db->Quote($filter_group);
        }

        $db->setQuery($query);
        $total = $db->loadresult();
        return $total;
    }

    public function publish()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2ExtraField', 'Table');
            $row->load($id);
            $row->published = 1;
            $row->store();
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $app->redirect('index.php?option=com_k2&view=extrafields');
    }

    public function unpublish()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2ExtraField', 'Table');
            $row->load($id);
            $row->published = 0;
            $row->store();
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $app->redirect('index.php?option=com_k2&view=extrafields');
    }

    public function saveorder()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid', array(0), 'post', 'array');
        $total = count($cid);
        $order = Factory::getApplication()->input->getVar('order', array(0), 'post', 'array');
        ArrayHelper::toInteger($order, array(0));
        $groupings = array();
        for ($i = 0; $i < $total; $i++) {
            $row = Table::getInstance('K2ExtraField', 'Table');
            $row->load((int)$cid[$i]);
            $groupings[] = $row->group;
            if ($row->ordering != $order[$i]) {
                $row->ordering = $order[$i];
                /* since J4 compatibility */
                try {
                    $row->store();
                } catch (Exception $e) {
                    JFactory::getApplication()->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
                }
            }
        }
        $params = ComponentHelper::getParams('com_k2');
        if (!$params->get('disableCompactOrdering')) {
            $groupings = array_unique($groupings);
            foreach ($groupings as $group) {
                $row = Table::getInstance('K2ExtraField', 'Table');
                $row->reorder("`group` = " . (int)$group);
            }
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        return true;
    }

    public function orderup()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2ExtraField', 'Table');
        $row->load($cid[0]);
        $row->move(-1, "`group` = '{$row->group}'");
        $params = ComponentHelper::getParams('com_k2');
        if (!$params->get('disableCompactOrdering')) {
            $row->reorder("`group` = " . (int)$row->group);
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $msg = Text::_('K2_NEW_ORDERING_SAVED');
        $app->enqueueMessage($msg);
        $app->redirect('index.php?option=com_k2&view=extrafields');
    }

    public function orderdown()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2ExtraField', 'Table');
        $row->load($cid[0]);
        $row->move(1, "`group` = '{$row->group}'");
        $params = ComponentHelper::getParams('com_k2');
        if (!$params->get('disableCompactOrdering')) {
            $row->reorder("`group` = " . (int)$row->group);
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $msg = Text::_('K2_NEW_ORDERING_SAVED');
        $app->enqueueMessage($msg);
        $app->redirect('index.php?option=com_k2&view=extrafields');
    }

    public function remove()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2ExtraField', 'Table');
            $row->load($id);
            $row->delete($id);
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=extrafields');
    }

    public function getExtraFieldsGroup()
    {
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2ExtraFieldsGroup', 'Table');
        $row->load($cid);
        return $row;
    }

    public function getGroups($filter = false)
    {
        $app = Factory::getApplication();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_extra_fields_groups ORDER BY `name`";
        if ($filter) {
            $db->setQuery($query);
        } else {
            $db->setQuery($query, $limitstart, $limit);
        }

        $rows = $db->loadObjectList();
        for ($i = 0; $i < count($rows); $i++) {
            $query = "SELECT name FROM #__k2_categories WHERE extraFieldsGroup = " . (int)$rows[$i]->id;
            $db->setQuery($query);
            $categories = $db->loadColumn();
            if (is_array($categories)) {
                $rows[$i]->categories = implode(', ', $categories);
            } else {
                $rows[$i]->categories = '';
            }
        }
        return $rows;
    }

    public function getTotalGroups()
    {
        $db = Factory::getDbo();
        $query = "SELECT COUNT(*) FROM #__k2_extra_fields_groups";
        $db->setQuery($query);
        $total = $db->loadResult();
        return $total;
    }

    public function saveGroup()
    {
        $app = Factory::getApplication();
        $id = Factory::getApplication()->input->getInt('id');
        $row = Table::getInstance('K2ExtraFieldsGroup', 'Table');
        if (!$row->bind(Factory::getApplication()->input->getArray($_POST))) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=extrafieldsgroups');
        }

        if (!$row->check()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=extrafieldsgroup&cid=' . $row->id);
        }

        if (!$row->store()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=extrafieldsgroup');
        }

        switch (Factory::getApplication()->input->getCmd('task')) {
            case 'apply':
                $msg = Text::_('K2_CHANGES_TO_GROUP_SAVED');
                $link = 'index.php?option=com_k2&view=extrafieldsgroup&cid=' . $row->id;
                break;
            case 'saveAndNew':
                $msg = Text::_('K2_GROUP_SAVED');
                $link = 'index.php?option=com_k2&view=extrafieldsgroup';
                break;
            case 'save':
            default:
                $msg = Text::_('K2_GROUP_SAVED');
                $link = 'index.php?option=com_k2&view=extrafieldsgroups';
                break;
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $app->enqueueMessage($msg);
        $app->redirect($link);
    }

    public function removeGroups()
    {
        $app = Factory::getApplication();
        $db = &Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        foreach ($cid as $id) {
            $row = Table::getInstance('K2ExtraFieldsGroup', 'Table');
            $row->load($id);
            $query = "DELETE FROM #__k2_extra_fields WHERE `group`={$id}";
            $db->setQuery($query);
            $db->execute();
            $row->delete($id);
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=extrafieldsgroups');
    }
}
