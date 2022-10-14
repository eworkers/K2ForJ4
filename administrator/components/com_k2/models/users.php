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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Filesystem\File;
use Joomla\Registry\Registry;

jimport('joomla.application.component.model');

JTable::addIncludePath(JPATH_COMPONENT . '/tables');

class K2ModelUsers extends K2Model
{
    public function getData()
    {
        $app = Factory::getApplication();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'juser.name', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', '', 'word');
        $filter_status = $app->getUserStateFromRequest($option . $view . 'filter_status', 'filter_status', -1, 'int');
        $filter_group = $app->getUserStateFromRequest($option . $view . 'filter_group', 'filter_group', '', 'string');
        $filter_group_k2 = $app->getUserStateFromRequest($option . $view . 'filter_group_k2', 'filter_group_k2', '', 'string');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $search));

        $query = "SELECT juser.*, k2user.group, k2group.name AS groupname, k2user.image AS image
            FROM #__users AS juser
            LEFT JOIN #__k2_users AS k2user ON juser.id = k2user.userID
            LEFT JOIN #__k2_user_groups AS k2group ON k2user.group = k2group.id
        ";

        if ($filter_group) {
            $query .= " LEFT JOIN #__user_usergroup_map AS `map` ON juser.id = map.user_id";
        }

        $query .= " WHERE juser.id > 0";

        if ($filter_status > -1) {
            $query .= " AND juser.block = {$filter_status}";
        }

        if ($filter_group) {
            $query .= " AND `map`.group_id =" . (int)$filter_group;
        }

        if ($filter_group_k2) {
            $query .= " AND k2user.group = " . $db->Quote($filter_group_k2);
        }

        if ($search) {
            $escaped = $db->escape($search, true);
            $query .= " AND (LOWER(juser.name) LIKE " . $db->Quote('%' . $escaped . '%', false) . " OR LOWER(juser.email) LIKE " . $db->Quote('%' . $escaped . '%', false) . ")";
        }

        if (!$filter_order) {
            $filter_order = "juser.name";
        }

        $query .= " ORDER BY {$filter_order} {$filter_order_Dir}";
        $db->setQuery($query, $limitstart, $limit);
        $rows = $db->loadObjectList();

        if (count($rows)) {
            foreach ($rows as $row) {
                $IDs[] = $row->id;
            }
            $query = "SELECT map.user_id, COUNT(map.group_id) AS group_count, GROUP_CONCAT(g2.title SEPARATOR '\n') AS group_names
                FROM #__user_usergroup_map AS map
                LEFT JOIN #__usergroups AS g2 ON g2.id = map.group_id
                WHERE map.user_id IN (" . implode(',', $IDs) . ")
                GROUP BY map.user_id";
            $db->setQuery($query);
            $groups = $db->loadObjectList();
            foreach ($rows as $row) {
                foreach ($groups as $group) {
                    if ($row->id == $group->user_id) {
                        $row->usertype = nl2br($group->group_names);
                    }
                }
            }
        }

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
        $filter_status = $app->getUserStateFromRequest($option . $view . 'filter_status', 'filter_status', -1, 'int');
        $filter_group = $app->getUserStateFromRequest($option . $view . 'filter_group', 'filter_group', '', 'string');
        $filter_group_k2 = $app->getUserStateFromRequest($option . $view . 'filter_group_k2', 'filter_group_k2', '', 'string');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $search));

        $query = "SELECT COUNT(DISTINCT juser.id) FROM #__users as juser " . "LEFT JOIN #__k2_users as k2user ON juser.id=k2user.userID " . "LEFT JOIN #__k2_user_groups as k2group ON k2user.group=k2group.id ";

        if ($filter_group) {
            $query .= " LEFT JOIN #__user_usergroup_map as `map` ON juser.id=map.user_id ";
        }

        $query .= " WHERE juser.id>0";

        if ($filter_status > -1) {
            $query .= " AND juser.block = {$filter_status}";
        }

        if ($filter_group) {
            $query .= " AND `map`.group_id =" . (int)$filter_group;
        }

        if ($filter_group_k2) {
            $query .= " AND k2user.group = " . $db->Quote($filter_group_k2);
        }

        if ($search) {
            $escaped = $db->escape($search, true);
            $query .= " AND (LOWER( juser.name ) LIKE " . $db->Quote('%' . $escaped . '%', false) . " OR LOWER( juser.email ) LIKE " . $db->Quote('%' . $escaped . '%', false) . ")";
        }

        $db->setQuery($query);
        $total = $db->loadResult();
        return $total;
    }

    public function remove()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        $db = Factory::getDbo();
        $query = "DELETE FROM #__k2_users WHERE userID IN(" . implode(',', $cid) . ")";
        $db->setQuery($query);
        $db->execute();
        $cache = Factory::getCache('com_k2');
        $cache->clean();
        $app->enqueueMessage(Text::_('K2_USER_PROFILE_DELETED'));
        $app->redirect('index.php?option=com_k2&view=users');
    }

    public function getUserGroups($type = 'joomla')
    {
        $db = Factory::getDbo();

        if ($type == 'joomla') {
            $query = "SELECT a.lft AS lft, a.id AS value, a.title AS text, COUNT(DISTINCT b.id) AS level
                    FROM #__usergroups AS a
                    LEFT JOIN #__usergroups AS b ON a.lft > b.lft AND a.rgt < b.rgt
                    GROUP BY a.id
                    ORDER BY a.lft ASC";

            $db->setQuery($query);
            $groups = $db->loadObjectList();
            $userGroups = array();

            foreach ($groups as $group) {
                if ($group->lft >= 10) {
                    $group->lft = (int)$group->lft - 10;
                }
                $group->text = $this->indent($group->level, '- ') . $group->text;

                array_push($userGroups, $group);
            }
        } else {
            $query = "SELECT * FROM #__k2_user_groups";
            $db->setQuery($query);
            $userGroups = $db->loadObjectList();
        }

        return $userGroups;
    }

    public function indent($times, $char = '&nbsp;&nbsp;&nbsp;&nbsp;', $start_char = '', $end_char = '')
    {
        $return = $start_char;
        for ($i = 0; $i < $times; $i++) {
            $return .= $char;
        }
        $return .= $end_char;
        return $return;
    }

    public function checkLogin($id)
    {
        $db = Factory::getDbo();
        $query = "SELECT COUNT(s.userid) FROM #__session AS s WHERE s.userid = " . (int)$id;
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
    }

    public function hasProfile($id)
    {
        $db = Factory::getDbo();
        $query = "SELECT id FROM #__k2_users WHERE userID = " . (int)$id;
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
    }

    public function enable()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        $db = Factory::getDbo();
        $query = "UPDATE #__users SET block=0 WHERE id IN(" . implode(',', $cid) . ")";
        $db->setQuery($query);
        $db->execute();
        $app->enqueueMessage(Text::_('K2_USERS_ENABLED'));
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=users&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=users');
        }
    }

    public function disable()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        $db = Factory::getDbo();
        $query = "UPDATE #__users SET block=1 WHERE id IN(" . implode(',', $cid) . ")";
        $db->setQuery($query);
        $db->execute();
        $app->enqueueMessage(Text::_('K2_USERS_DISABLED'));
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=users&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=users');
        }
    }

    public function delete()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        $db = Factory::getDbo();
        if (in_array($user->id, $cid)) {
            foreach ($cid as $key => $id) {
                if ($id == $user->id) {
                    unset($cid[$key]);
                }
            }
            $app->enqueueMessage(Text::_('K2_YOU_CANNOT_DELETE_YOURSELF'), 'notice');
        }
        if (count($cid) < 1) {
            $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
            $app->redirect('index.php?option=com_k2&view=users');
        }
        PluginHelper::importPlugin('user');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                    $dispatcher = JDispatcher::getInstance();
        */
        $iAmSuperAdmin = $user->authorise('core.admin');
        foreach ($cid as $key => $id) {
            $table = Table::getInstance('user');
            $table->load($id);
            $allow = $user->authorise('core.delete', 'com_users');
            // Don't allow non-super-admin to delete a super admin
            $allow = (!$iAmSuperAdmin && Access::check($id, 'core.admin')) ? false : $allow;
            if ($allow) {
                // Get users data for the users to delete.
                $user_to_delete = Factory::getUser($id);
                // Fire the onUserBeforeDelete event.
                /* since J4 compatibility */
                Factory::getApplication()->triggerEvent('onUserBeforeDelete', array($table->getProperties()));
                if (!$table->delete($id)) {
                    $this->setError($table->getError());
                    return false;
                } else {
                    // Trigger the onUserAfterDelete event.
                    /* since J4 compatibility */
                    Factory::getApplication()->triggerEvent('onUserAfterDelete', array($user_to_delete->getProperties(), true, $this->getError()));
                }
            } else {
                // Prune items that you can't change.
                unset($cid[$key]);
                JFactory::getApplication()->enqueueMessage(Text::_('JERROR_CORE_DELETE_NOT_PERMITTED'), 'WARNING');
            }
        }
        $IDsToDelete = $cid;
        $query = "DELETE FROM #__k2_users WHERE userID IN(" . implode(',', $IDsToDelete) . ") AND userID!={$user->id}";
        $db->setQuery($query);
        $db->execute();
        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=users');
    }

    public function saveMove()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        $group = Factory::getApplication()->input->getVar('group');
        $k2group = Factory::getApplication()->input->getInt('k2group');
        ArrayHelper::toInteger($group);
        $group = array_filter($group);
        if (count($group)) {
            foreach ($cid as $id) {
                $query = "DELETE FROM #__user_usergroup_map WHERE user_id = " . $id;
                $db->setQuery($query);
                $db->execute();
                $query = "INSERT INTO #__user_usergroup_map VALUES (" . $id . ", " . implode("), (" . $id . ", ", $group) . ")";
                $db->setQuery($query);
                $db->execute();
            }
        }

        if ($k2group) {
            foreach ($cid as $id) {
                $query = "SELECT COUNT(*) FROM #__k2_users WHERE userID = " . $id;
                $db->setQuery($query);
                $result = $db->loadResult();
                if ($result) {
                    $query = "UPDATE #__k2_users SET `group`={$k2group} WHERE userID = " . $id;
                } else {
                    $user = Factory::getUser($id);
                    $query = "INSERT INTO #__k2_users VALUES ('', {$id}, {$db->Quote($user->username)}, '', '', '', '', {$k2group}, '', '', '', '')";
                }
                $db->setQuery($query);
                $db->execute();
            }
        }
        $app->enqueueMessage(Text::_('K2_MOVE_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=users');
    }

    public function import()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $db->setQuery("SELECT id, title AS name FROM #__usergroups");
        $usergroups = $db->loadObjectList();
        $xml = new SimpleXMLElement(file_get_contents(JPATH_COMPONENT . '/models/usergroup.xml'));
        $permissions = class_exists('JParameter') ? new JParameter('') : new Registry('');
        foreach ($xml->params as $paramGroup) {
            foreach ($paramGroup->param as $param) {
                $attribute = $param->attributes()->type;
                if ($attribute != 'spacer') {
                    $permissions->set((string)$param->attributes()->name, (string)$param->attributes()->default);
                }
            }
        }

        $permissions->set('inheritance', 0);
        $permissions->set('categories', 'all');
        $permissions = $permissions->toString();

        foreach ($usergroups as $usergroup) {
            $K2UserGroup = Table::getInstance('K2UserGroup', 'Table');
            $K2UserGroup->name = StringHelper::trim($usergroup->name) . " (Imported from Joomla)";
            $K2UserGroup->permissions = $permissions;
            $K2UserGroup->store();

            $query = "SELECT * FROM #__users AS user JOIN #__user_usergroup_map AS map ON user.id = map.user_id
                WHERE map.group_id = " . $usergroup->id;

            $db->setQuery($query);
            $users = $db->loadObjectList();

            foreach ($users as $user) {
                $query = "SELECT COUNT(*) FROM #__k2_users WHERE userID={$user->id}";
                $db->setQuery($query);
                $result = $db->loadResult();
                if (!$result) {
                    $K2User = Table::getInstance('K2User', 'Table');
                    $K2User->userID = $user->id;
                    $K2User->group = $K2UserGroup->id;
                    $K2User->store();
                }
            }
        }
        $app->enqueueMessage(Text::_('K2_IMPORT_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=users');
    }
}
