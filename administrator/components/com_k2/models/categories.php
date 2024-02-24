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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;

jimport('joomla.application.component.model');

class K2ModelCategories extends K2Model
{
    public function getData()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\"\-_]/u', '', $search));
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'c.ordering', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', '', 'word');
        $filter_trash = $app->getUserStateFromRequest($option . $view . 'filter_trash', 'filter_trash', 0, 'int');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $language = $app->getUserStateFromRequest($option . $view . 'language', 'language', '', 'string');
        $filter_category = $app->getUserStateFromRequest($option . $view . 'filter_category', 'filter_category', 0, 'int');

        $query = "SELECT c.*, g.name AS groupname, exfg.name as extra_fields_group FROM #__k2_categories as c LEFT JOIN #__groups AS g ON g.id = c.access LEFT JOIN #__k2_extra_fields_groups AS exfg ON exfg.id = c.extraFieldsGroup WHERE c.id>0";

        if (!$filter_trash) {
            $query .= " AND c.trash=0";
        }

        if ($search) {

            // Detect exact search phrase using double quotes in search string
            if (substr($search, 0, 1) == '"' && substr($search, -1) == '"') {
                $exact = true;
            } else {
                $exact = false;
            }

            // Now completely strip double quotes
            $search = trim(str_replace('"', '', $search));

            // Escape remaining string
            $escaped = $db->escape($search, true);

            // Full phrase or set of words
            if (strpos($escaped, ' ') !== false && !$exact) {
                $escaped = explode(' ', $escaped);
                $quoted = array();
                foreach ($escaped as $key => $escapedWord) {
                    $quoted[] = $db->Quote('%' . $escapedWord . '%', false);
                }
                if ($params->get('adminSearch') == 'full') {
                    foreach ($quoted as $quotedWord) {
                        $query .= " AND ( " .
                            "LOWER(c.name) LIKE " . $quotedWord . " " .
                            "OR LOWER(c.description) LIKE " . $quotedWord . " " .
                            " )";
                    }
                } else {
                    foreach ($quoted as $quotedWord) {
                        $query .= " AND LOWER(c.name) LIKE " . $quotedWord;
                    }
                }
            } // Single word or exact phrase to search for (wrapped in double quotes in the search block)
            else {
                $quoted = $db->Quote('%' . $escaped . '%', false);

                if ($params->get('adminSearch') == 'full') {
                    $query .= " AND ( " .
                        "LOWER(c.name) LIKE " . $quoted . " " .
                        "OR LOWER(c.description) LIKE " . $quoted . " " .
                        " )";
                } else {
                    $query .= " AND LOWER(c.name) LIKE " . $quoted;
                }
            }
        }

        if ($filter_state > -1) {
            $query .= " AND c.published={$filter_state}";
        }
        if ($language) {
            $query .= " AND (c.language = " . $db->Quote($language) . " OR c.language = '*')";
        }

        if ($filter_category) {
            K2Model::addIncludePath(JPATH_SITE . '/components/com_k2/models');
            $ItemlistModel = K2Model::getInstance('Itemlist', 'K2Model');
            $tree = $ItemlistModel->getCategoryTree($filter_category);
            $query .= " AND c.id IN (" . implode(',', $tree) . ")";
        }

        $query .= " ORDER BY {$filter_order} {$filter_order_Dir}";

        $query = StringHelper::str_ireplace('#__groups', '#__viewlevels', $query);
        $query = StringHelper::str_ireplace('g.name AS groupname', 'g.title AS groupname', $query);

        $db->setQuery($query);
        $rows = $db->loadObjectList();
        foreach ($rows as $row) {
            $row->parent_id = $row->parent;
            $row->title = $row->name;
        }
        $categories = array();

        if ($search) {
            foreach ($rows as $row) {
                $row->treename = $row->name;
                $categories[] = $row;
            }
        } else {
            if ($filter_category) {
                $db->setQuery('SELECT parent FROM #__k2_categories WHERE id = ' . $filter_category);
                $root = $db->loadResult();
            } elseif ($language && count($categories)) {
                $root = $categories[0]->parent;
            } else {
                $root = 0;
            }
            $categories = $this->indentRows($rows, $root);
        }
        if (isset($categories)) {
            $total = count($categories);
        } else {
            $total = 0;
        }
        jimport('joomla.html.pagination');
        $pageNav = new Pagination($total, $limitstart, $limit);
        $categories = @array_slice($categories, $pageNav->limitstart, $pageNav->limit);
        foreach ($categories as $category) {
            $category->parameters = class_exists('JParameter') ? new JParameter($category->params) : new Registry($category->params);
            if ($category->parameters->get('inheritFrom')) {
                $db->setQuery("SELECT name FROM #__k2_categories WHERE id = " . (int)$category->parameters->get('inheritFrom'));
                $category->inheritFrom = $db->loadResult();
            } else {
                $category->inheritFrom = '';
            }
        }
        return $categories;
    }

    public function getTotal()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . '.limitstart', 'limitstart', 0, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\"\-_]/u', '', $search));
        $filter_trash = $app->getUserStateFromRequest($option . $view . 'filter_trash', 'filter_trash', 0, 'int');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', 1, 'int');
        $language = $app->getUserStateFromRequest($option . $view . 'language', 'language', '', 'string');
        $filter_category = $app->getUserStateFromRequest($option . $view . 'filter_category', 'filter_category', 0, 'int');

        $query = "SELECT COUNT(*) FROM #__k2_categories WHERE id>0";

        if (!$filter_trash) {
            $query .= " AND trash=0";
        }

        if ($search) {
            // Detect exact search phrase using double quotes in search string
            if (substr($search, 0, 1) == '"' && substr($search, -1) == '"') {
                $exact = true;
            } else {
                $exact = false;
            }

            // Now completely strip double quotes
            $search = trim(str_replace('"', '', $search));

            // Escape remaining string
            $escaped = $db->escape($search, true);

            // Full phrase or set of words
            if (strpos($escaped, ' ') !== false && !$exact) {
                $escaped = explode(' ', $escaped);
                $quoted = array();
                foreach ($escaped as $key => $escapedWord) {
                    $quoted[] = $db->Quote('%' . $escapedWord . '%', false);
                }
                if ($params->get('adminSearch') == 'full') {
                    foreach ($quoted as $quotedWord) {
                        $query .= " AND (LOWER(name) LIKE " . $quotedWord . " OR LOWER(description) LIKE " . $quotedWord . ")";
                    }
                } else {
                    foreach ($quoted as $quotedWord) {
                        $query .= " AND LOWER(name) LIKE " . $quotedWord;
                    }
                }
            } // Single word or exact phrase to search for (wrapped in double quotes in the search block)
            else {
                $quoted = $db->Quote('%' . $escaped . '%', false);

                if ($params->get('adminSearch') == 'full') {
                    $query .= " AND (LOWER(name) LIKE " . $quoted . " OR LOWER(description) LIKE " . $quoted . ")";
                } else {
                    $query .= " AND LOWER(name) LIKE " . $quoted;
                }
            }
        }

        if ($filter_state > -1) {
            $query .= " AND published={$filter_state}";
        }

        if ($language) {
            $query .= " AND (language = " . $db->Quote($language) . " OR language = '*')";
        }

        if ($filter_category) {
            K2Model::addIncludePath(JPATH_SITE . '/components/com_k2/models');
            $ItemlistModel = K2Model::getInstance('Itemlist', 'K2Model');
            $tree = $ItemlistModel->getCategoryTree($filter_category);
            $query .= " AND id IN (" . implode(',', $tree) . ")";
        }

        $db->setQuery($query);
        $total = $db->loadResult();
        return $total;
    }

    public function indentRows(&$rows, $root = 0)
    {
        $children = array();
        if (count($rows)) {
            foreach ($rows as $v) {
                $pt = $v->parent;
                $list = @$children[$pt] ? $children[$pt] : array();
                array_push($list, $v);
                $children[$pt] = $list;
            }
        }
        $categories = HTMLHelper::_('menu.treerecurse', $root, '', array(), $children);
        return $categories;
    }

    public function publish()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Category', 'Table');
            $row->load($id);
            $row->published = 1;
            $row->store();
        }
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderChangeState', array('com_k2.category', $cid, 1));
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=categories&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=categories');
        }
    }

    public function unpublish()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Category', 'Table');
            $row->load($id);
            $row->published = 0;
            $row->store();
        }
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderChangeState', array('com_k2.category', $cid, 0));
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=categories&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=categories');
        }
    }

    public function saveorder()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid', array(0), 'post', 'array');
        $total = count($cid);
        $order = Factory::getApplication()->input->getVar('order', array(0), 'post', 'array');
        ArrayHelper::toInteger($order, array(0));
        $groupings = array();
        for ($i = 0; $i < $total; $i++) {
            $row = Table::getInstance('K2Category', 'Table');
            $row->load(( int )$cid[$i]);
            $groupings[] = $row->parent;
            if ($row->ordering != $order[$i]) {
                $row->ordering = $order[$i];
                /* since J4 compatibility */
                try {
                    $row->store();
                } catch (Exception $e) {
                    Factory::getApplication()->enqueueMessage(Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
                }
            }
        }
        if (!$params->get('disableCompactOrdering')) {
            $groupings = array_unique($groupings);
            foreach ($groupings as $group) {
                $row = Table::getInstance('K2Category', 'Table');
                $row->reorder('parent = ' . ( int )$group . ' AND trash=0');
            }
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        return true;
    }

    public function orderup()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Category', 'Table');
        $row->load($cid[0]);
        $row->move(-1, 'parent = ' . $row->parent . ' AND trash=0');
        if (!$params->get('disableCompactOrdering')) {
            $row->reorder('parent = ' . (int)$row->parent . ' AND trash=0');
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $msg = Text::_('K2_NEW_ORDERING_SAVED');
        $app->enqueueMessage($msg);
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=categories&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=categories');
        }
    }

    public function orderdown()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Category', 'Table');
        $row->load($cid[0]);
        $row->move(1, 'parent = ' . $row->parent . ' AND trash=0');
        if (!$params->get('disableCompactOrdering')) {
            $row->reorder('parent = ' . (int)$row->parent . ' AND trash=0');
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $msg = Text::_('K2_NEW_ORDERING_SAVED');
        $app->enqueueMessage($msg);
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=categories&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=categories');
        }
    }

    public function accessregistered()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $row = Table::getInstance('K2Category', 'Table');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row->load($cid[0]);
        $row->access = 1;
        if (!$row->check()) {
            return $row->getError();
        }
        if (!$row->store()) {
            return $row->getError();
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $msg = Text::_('K2_NEW_ACCESS_SETTING_SAVED');
        $app->enqueueMessage($msg);
        $app->redirect('index.php?option=com_k2&view=categories');
    }

    public function accessspecial()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $row = Table::getInstance('K2Category', 'Table');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row->load($cid[0]);
        $row->access = 2;
        if (!$row->check()) {
            return $row->getError();
        }
        if (!$row->store()) {
            return $row->getError();
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $msg = Text::_('K2_NEW_ACCESS_SETTING_SAVED');
        $app->enqueueMessage($msg);
        $app->redirect('index.php?option=com_k2&view=categories');
    }

    public function accesspublic()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $row = Table::getInstance('K2Category', 'Table');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row->load($cid[0]);
        $row->access = 0;
        if (!$row->check()) {
            return $row->getError();
        }
        if (!$row->store()) {
            return $row->getError();
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $msg = Text::_('K2_NEW_ACCESS_SETTING_SAVED');
        $app->enqueueMessage($msg);
        $app->redirect('index.php?option=com_k2&view=categories');
    }

    public function trash()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Category', 'Table');
        ArrayHelper::toInteger($cid);
        K2Model::addIncludePath(JPATH_SITE . '/components/com_k2/models');
        $model = K2Model::getInstance('Itemlist', 'K2Model');
        $categories = $model->getCategoryTree($cid);
        $sql = @implode(',', $categories);
        $db = Factory::getDbo();
        $query = "UPDATE #__k2_categories SET trash=1  WHERE id IN ({$sql})";
        $db->setQuery($query);
        $db->execute();
        $query = "UPDATE #__k2_items SET trash=1  WHERE catid IN ({$sql})";
        $db->setQuery($query);
        $db->execute();

        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderChangeState', array('com_k2.category', $cid, 0));
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $app->enqueueMessage(Text::_('K2_CATEGORIES_MOVED_TO_TRASH'));
        $app->redirect('index.php?option=com_k2&view=categories');
    }

    public function restore()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        $warning = false;
        $restored = array();
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Category', 'Table');
            $row->load($id);
            if ((int)$row->parent == 0) {
                $row->trash = 0;
                $row->store();
                $restored[] = $id;
            } else {
                $query = "SELECT COUNT(*) FROM #__k2_categories WHERE id={$row->parent} AND trash = 0";
                $db->setQuery($query);
                $result = $db->loadResult();
                if ($result) {
                    $row->trash = 0;
                    $row->store();
                    $restored[] = $id;
                } else {
                    $warning = true;
                }
            }
        }
        // Restore also the items of the categories
        if (count($restored)) {
            ArrayHelper::toInteger($restored);
            $db->setQuery('UPDATE #__k2_items SET trash = 0 WHERE catid IN (' . implode(',', $restored) . ') AND trash = 1');
            $db->execute();
        }
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderChangeState', array('com_k2.category', $cid, 1));
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        if ($warning) {
            $app->enqueueMessage(Text::_('K2_SOME_OF_THE_CATEGORIES_HAVE_NOT_BEEN_RESTORED_BECAUSE_THEIR_PARENT_CATEGORY_IS_IN_TRASH'), 'notice');
        }
        $app->enqueueMessage(Text::_('K2_CATEGORIES_MOVED_TO_TRASH'));
        $app->redirect('index.php?option=com_k2&view=categories');
    }

    public function remove()
    {
        $app = Factory::getApplication();
        jimport('joomla.filesystem.file');
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        $warningItems = false;
        $warningChildren = false;
        $cid = array_reverse($cid);
        for ($i = 0; $i < count($cid); $i++) {
            $row = Table::getInstance('K2Category', 'Table');
            $row->load($cid[$i]);

            $query = "SELECT COUNT(*) FROM #__k2_items WHERE catid={$cid[$i]}";
            $db->setQuery($query);
            $num = $db->loadResult();

            if ($num > 0) {
                $warningItems = true;
            }

            $query = "SELECT COUNT(*) FROM #__k2_categories WHERE parent={$cid[$i]}";
            $db->setQuery($query);
            $children = $db->loadResult();

            if ($children > 0) {
                $warningChildren = true;
            }

            if ($children == 0 && $num == 0) {
                if ($row->image) {
                    File::delete(JPATH_ROOT . '/media/k2/categories/' . $row->image);
                }
                $row->delete($cid[$i]);
                /* since J4 compatibility */
                Factory::getApplication()->triggerEvent('onFinderAfterDelete', array('com_k2.category', $row));
            }
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        if ($warningItems) {
            $app->enqueueMessage(Text::_('K2_SOME_OF_THE_CATEGORIES_HAVE_NOT_BEEN_DELETED_BECAUSE_THEY_HAVE_ITEMS'), 'notice');
        }
        if ($warningChildren) {
            $app->enqueueMessage(Text::_('K2_SOME_OF_THE_CATEGORIES_HAVE_NOT_BEEN_DELETED_BECAUSE_THEY_HAVE_CHILD_CATEGORIES'), 'notice');
        }

        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=categories');
    }

    public function categoriesTree($row = null, $hideTrashed = false, $hideUnpublished = true)
    {
        $db = Factory::getDbo();
        if (isset($row->id)) {
            $idCheck = ' AND id != ' . (int)$row->id;
        } else {
            $idCheck = null;
        }
        if (!isset($row->parent)) {
            if (is_null($row)) {
                $row = new stdClass;
            }
            $row->parent = 0;
        }
        $query = "SELECT m.* FROM #__k2_categories m WHERE id > 0 {$idCheck}";

        if ($hideUnpublished) {
            $query .= " AND published = 1";
        }

        if ($hideTrashed) {
            $query .= " AND trash = 0";
        }

        $query .= " ORDER BY parent, ordering";
        $db->setQuery($query);
        $mitems = $db->loadObjectList();
        $children = array();
        if ($mitems) {
            foreach ($mitems as $v) {
                if ($v->language != '*') {
                    $v->title = $v->name . ' [' . $v->language . ']';
                } else {
                    $v->title = $v->name;
                }
                $v->parent_id = $v->parent;
                $pt = $v->parent;
                $list = @$children[$pt] ? $children[$pt] : array();
                array_push($list, $v);
                $children[$pt] = $list;
            }
        }
        $list = HTMLHelper::_('menu.treerecurse', 0, '', array(), $children, 9999, 0, 0);
        $mitems = array();
        foreach ($list as $item) {
            $item->treename = StringHelper::str_ireplace('&#160;', '- ', $item->treename);
            if (!$item->published) {
                $item->treename .= ' [**' . Text::_('K2_UNPUBLISHED_CATEGORY') . '**]';
            }
            if ($item->trash) {
                $item->treename .= ' [**' . Text::_('K2_TRASHED_CATEGORY') . '**]';
            }
            $mitems[] = HTMLHelper::_('select.option', $item->id, $item->treename);
        }
        return $mitems;
    }

    public function copy($batch = false)
    {
        jimport('joomla.filesystem.file');
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        $copies = array();
        foreach ($cid as $id) {
            // Load source category
            $category = Table::getInstance('K2Category', 'Table');
            $category->load($id);

            // Save target category
            $row = Table::getInstance('K2Category', 'Table');
            $row = $category;
            $row->id = null;
            $row->name = Text::_('K2_COPY_OF') . ' ' . $category->name;
            $row->published = 0;
            $row->store();
            $copies[] = $row->id;
            // Target image
            if ($category->image && File::exists(JPATH_SITE . '/media/k2/categories/' . $category->image)) {
                File::copy(JPATH_SITE . '/media/k2/categories/' . $category->image, JPATH_SITE . '/media/k2/categories/' . $row->id . '.jpg');
                $row->image = $row->id . '.jpg';
                $row->store();
            }
        }
        if ($batch) {
            return $copies;
        } else {
            $app->enqueueMessage(Text::_('K2_COPY_COMPLETED'));
            $app->redirect('index.php?option=com_k2&view=categories');
        }
    }

    public function saveBatch()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        $batchMode = Factory::getApplication()->input->getCmd('batchMode');
        $catid = Factory::getApplication()->input->getCmd('batchCategory');
        $access = Factory::getApplication()->input->getCmd('batchAccess');
        $extraFieldsGroups = Factory::getApplication()->input->getCmd('batchExtraFieldsGroups');
        $language = Factory::getApplication()->input->getVar('batchLanguage');
        if ($batchMode == 'clone') {
            $cid = $this->copy(true);
        }
        if (in_array($catid, $cid)) {
            $app->redirect('index.php?option=com_k2&view=categories');
            return;
        }
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Category', 'Table');
            $row->load($id);
            if (is_numeric($catid) && $catid != '') {
                $row->parent = $catid;
                $row->ordering = $row->getNextOrder('parent = ' . (int)$catid . ' AND published = 1');
            }
            if ($access) {
                $row->access = $access;
            }
            if (is_numeric($extraFieldsGroups) && $extraFieldsGroups != '') {
                $row->extraFieldsGroup = intval($extraFieldsGroups);
            }
            if ($language) {
                $row->language = $language;
            }
            $row->store();
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $app->enqueueMessage(Text::_('K2_BATCH_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=categories');
    }
}
