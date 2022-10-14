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
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

jimport('joomla.html.parameter');

class K2HelperPermissions
{
    public static function setPermissions()
    {
        $params = K2HelperUtilities::getParams('com_k2');
        $user = Factory::getUser();
        if ($user->guest) {
            return;
        }
        $K2User = K2HelperPermissions::getK2User($user->id);
        if (!is_object($K2User)) {
            return;
        }
        $K2UserGroup = K2HelperPermissions::getK2UserGroup($K2User->group);
        if (is_null($K2UserGroup)) {
            return;
        }
        $K2Permissions = K2Permissions::getInstance();
        $permissions = new Registry($K2UserGroup->permissions);
        $K2Permissions->permissions = $permissions;
        if ($permissions->get('categories') == 'none') {
            return;
        } elseif ($permissions->get('categories') == 'all') {
            if ($permissions->get('add') && $permissions->get('frontEdit') && $params->get('frontendEditing')) {
                $K2Permissions->actions[] = 'add.category.all';
                $K2Permissions->actions[] = 'tag';
                $K2Permissions->actions[] = 'extraFields';
            }
            if ($permissions->get('editOwn') && $permissions->get('frontEdit') && $params->get('frontendEditing')) {
                $K2Permissions->actions[] = 'editOwn.item.' . $user->id;
                $K2Permissions->actions[] = 'tag';
                $K2Permissions->actions[] = 'extraFields';
            }
            if ($permissions->get('editAll') && $permissions->get('frontEdit') && $params->get('frontendEditing')) {
                $K2Permissions->actions[] = 'editAll.category.all';
                $K2Permissions->actions[] = 'tag';
                $K2Permissions->actions[] = 'extraFields';
            }
            if ($permissions->get('publish') && $permissions->get('frontEdit') && $params->get('frontendEditing')) {
                $K2Permissions->actions[] = 'publish.category.all';
            }
            if ($permissions->get('comment')) {
                $K2Permissions->actions[] = 'comment.category.all';
            }
            if ($permissions->get('editPublished')) {
                $K2Permissions->actions[] = 'editPublished.category.all';
            }
        } else {
            $selectedCategories = $permissions->get('categories', null);
            if (is_string($selectedCategories)) {
                $searchIDs[] = $selectedCategories;
            } else {
                $searchIDs = $selectedCategories;
            }
            if ($permissions->get('inheritance')) {
                $model = K2Model::getInstance('Itemlist', 'K2Model');
                $categories = $model->getCategoryTree($searchIDs);
            } else {
                $categories = $searchIDs;
            }
            if (is_array($categories) && count($categories)) {
                foreach ($categories as $category) {
                    if ($permissions->get('add') && $permissions->get('frontEdit') && $params->get('frontendEditing')) {
                        $K2Permissions->actions[] = 'add.category.' . $category;
                        $K2Permissions->actions[] = 'tag';
                        $K2Permissions->actions[] = 'extraFields';
                    }
                    if ($permissions->get('editOwn') && $permissions->get('frontEdit') && $params->get('frontendEditing')) {
                        $K2Permissions->actions[] = 'editOwn.item.' . $user->id . '.' . $category;
                        $K2Permissions->actions[] = 'tag';
                        $K2Permissions->actions[] = 'extraFields';
                    }
                    if ($permissions->get('editAll') && $permissions->get('frontEdit') && $params->get('frontendEditing')) {
                        $K2Permissions->actions[] = 'editAll.category.' . $category;
                        $K2Permissions->actions[] = 'tag';
                        $K2Permissions->actions[] = 'extraFields';
                    }
                    if ($permissions->get('publish') && $permissions->get('frontEdit') && $params->get('frontendEditing')) {
                        $K2Permissions->actions[] = 'publish.category.' . $category;
                    }
                    if ($permissions->get('comment')) {
                        $K2Permissions->actions[] = 'comment.category.' . $category;
                    }
                    if ($permissions->get('editPublished')) {
                        $K2Permissions->actions[] = 'editPublished.category.' . $category;
                    }
                }
            }
        }
        return;
    }

    public static function checkPermissions()
    {
        $view = Factory::getApplication()->input->getCmd('view');
        if ($view != 'item') {
            return;
        }
        $task = Factory::getApplication()->input->getCmd('task');
        $user = Factory::getUser();
        $app = Factory::getApplication();
        if ($user->guest && ($task == 'add' || $task == 'edit')) {
            $uri = JURI::getInstance();
            $return = base64_encode($uri->toString());
            $app->enqueueMessage(Text::_('K2_YOU_NEED_TO_LOGIN_FIRST'), 'notice');
            $app->redirect('index.php?option=com_users&view=login&return=' . $return . '&tmpl=component');
        }

        switch ($task) {

            case 'add':
                if (!K2HelperPermissions::canAddItem()) {
                    JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
                }
                break;

            case 'edit':
            case 'deleteAttachment':
            case 'checkin':
                $cid = Factory::getApplication()->input->getInt('cid');
                if ($cid) {
                    Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
                    $item = Table::getInstance('K2Item', 'Table');
                    $item->load($cid);

                    if (!K2HelperPermissions::canEditItem($item->created_by, $item->catid)) {
                        // Handle in a different way the case when user can add an item but not edit it.
                        if ($task == 'edit' && !$user->guest && $item->created_by == $user->id && (int)$item->modified == 0 && K2HelperPermissions::canAddItem()) {
                            echo '<script>parent.location.href = "' . Uri::root() . '";</script>';
                            exit;
                        } else {
                            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
                        }
                    }
                }
                break;

            case 'save':
                $cid = Factory::getApplication()->input->getInt('id');
                if ($cid) {
                    Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
                    $item = Table::getInstance('K2Item', 'Table');
                    $item->load($cid);

                    if (!K2HelperPermissions::canEditItem($item->created_by, $item->catid)) {
                        JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
                    }
                } else {
                    if (!K2HelperPermissions::canAddItem()) {
                        JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
                    }
                }

                break;

            case 'tag':
                if (!K2HelperPermissions::canAddTag()) {
                    JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
                }
                break;

            case 'extraFields':
                if (!K2HelperPermissions::canRenderExtraFields()) {
                    JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
                }
                break;
        }
    }

    public static function getK2User($userID)
    {
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_users WHERE userID = " . (int)$userID;
        $db->setQuery($query);
        $row = $db->loadObject();
        return $row;
    }

    public static function getK2UserGroup($id)
    {
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_user_groups WHERE id = " . (int)$id;
        $db->setQuery($query);
        $row = $db->loadObject();
        return $row;
    }

    public static function canAddItem($category = false)
    {
        $user = Factory::getUser();
        $K2Permissions = K2Permissions::getInstance();
        if (in_array('add.category.all', $K2Permissions->actions)) {
            return true;
        }
        if ($category) {
            return in_array('add.category.' . $category, $K2Permissions->actions);
        }
        $db = Factory::getDbo();
        $query = "SELECT id FROM #__k2_categories WHERE published=1 AND trash=0";
        $query .= " AND access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ")";
        $db->setQuery($query);
        $categories = $db->loadColumn();
        foreach ($categories as $category) {
            if (in_array('add.category.' . $category, $K2Permissions->actions)) {
                return true;
            }
        }

        return false;
    }

    public static function canAddToAll()
    {
        $K2Permissions = K2Permissions::getInstance();
        return in_array('add.category.all', $K2Permissions->actions);
    }

    public static function canEditItem($itemOwner, $itemCategory)
    {
        $K2Permissions = K2Permissions::getInstance();
        if (in_array('editAll.category.all', $K2Permissions->actions) || in_array('editOwn.item.' . $itemOwner, $K2Permissions->actions) || in_array('editOwn.item.' . $itemOwner . '.' . $itemCategory, $K2Permissions->actions) || in_array('editAll.category.' . $itemCategory, $K2Permissions->actions)) {
            return true;
        } else {
            return false;
        }
    }

    public static function canPublishItem($itemCategory)
    {
        $K2Permissions = K2Permissions::getInstance();
        if (in_array('publish.category.all', $K2Permissions->actions) || in_array('publish.category.' . $itemCategory, $K2Permissions->actions)) {
            return true;
        } else {
            return false;
        }
    }

    public static function canAddTag()
    {
        $K2Permissions = K2Permissions::getInstance();
        return in_array('tag', $K2Permissions->actions);
    }

    public static function canRenderExtraFields()
    {
        $K2Permissions = K2Permissions::getInstance();
        return in_array('extraFields', $K2Permissions->actions);
    }

    public static function canAddComment($itemCategory)
    {
        $K2Permissions = K2Permissions::getInstance();
        return in_array('comment.category.all', $K2Permissions->actions) || in_array('comment.category.' . $itemCategory, $K2Permissions->actions);
    }

    public static function canEditPublished($itemCategory)
    {
        $K2Permissions = K2Permissions::getInstance();
        return in_array('editPublished.category.all', $K2Permissions->actions) || in_array('editPublished.category.' . $itemCategory, $K2Permissions->actions);
    }
}

class K2Permissions
{
    public $actions = array();
    public $permissions = null;

    public static function getInstance()
    {
        static $instance;
        if (!is_object($instance)) {
            $instance = new K2Permissions();
        }
        return $instance;
    }
}
