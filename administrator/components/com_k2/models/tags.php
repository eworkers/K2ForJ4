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
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.model');

JTable::addIncludePath(JPATH_COMPONENT . '/tables');

class K2ModelTags extends K2Model
{
    function getData()
    {
        $app = Factory::getApplication();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'id', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $search));

        $query = "SELECT #__k2_tags.*, (SELECT COUNT(*) FROM #__k2_tags_xref WHERE #__k2_tags_xref.tagID = #__k2_tags.id) AS numOfItems FROM #__k2_tags";

        $conditions = array();

        if ($filter_state > -1) {
            $conditions[] = "published={$filter_state}";
        }
        if ($search) {
            $escaped = $db->escape($search, true);
            $conditions[] = "LOWER( name ) LIKE " . $db->Quote('%' . $escaped . '%', false);
        }

        if (count($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        if (!$filter_order) {
            $filter_order = "name";
        }

        $query .= " ORDER BY {$filter_order} {$filter_order_Dir}";

        $db->setQuery($query, $limitstart, $limit);
        $rows = $db->loadObjectList();
        return $rows;
    }

    function getTotal()
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

        $query = "SELECT COUNT(*) FROM #__k2_tags WHERE id > 0";

        if ($filter_state > -1) {
            $query .= " AND published={$filter_state}";
        }

        if ($search) {
            $escaped = $db->escape($search, true);
            $query .= " AND LOWER( name ) LIKE " . $db->Quote('%' . $escaped . '%', false);
        }

        $db->setQuery($query);
        $total = $db->loadresult();
        return $total;
    }

    function publish()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Tag', 'Table');
            $row->load($id);
            $row->published = 1;
            $row->store();
        }
        $cache = Factory::getCache('com_k2');
        $cache->clean();
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=tags&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=tags');
        }
    }

    function unpublish()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Tag', 'Table');
            $row->load($id);
            $row->published = 0;
            $row->store();
        }
        $cache = Factory::getCache('com_k2');
        $cache->clean();
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=tags&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=tags');
        }
    }

    function remove()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Tag', 'Table');
            $row->load($id);
            $row->delete($id);
        }
        $cache = Factory::getCache('com_k2');
        $cache->clean();
        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=tags');
    }

    function getFilter()
    {
        $db = Factory::getDbo();
        $query = "SELECT name, id FROM #__k2_tags ORDER BY name";
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        return $rows;
    }

    function countTagItems($id)
    {
        $db = Factory::getDbo();
        $query = "SELECT COUNT(*) FROM #__k2_tags_xref WHERE tagID = " . (int)$id;
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
    }

    function removeOrphans()
    {
        $db = Factory::getDbo();
        $db->setQuery("DELETE FROM #__k2_tags WHERE id NOT IN (SELECT tagID FROM #__k2_tags_xref GROUP BY tagID)");
        $db->execute();
        $app = Factory::getApplication();
        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=tags');
    }
}
