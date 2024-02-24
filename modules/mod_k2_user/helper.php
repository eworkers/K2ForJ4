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

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

JLoader::register('K2HelperRoute', JPATH_SITE . '/components/com_k2/helpers/route.php');
JLoader::register('K2HelperUtilities', JPATH_SITE . '/components/com_k2/helpers/utilities.php');

class modK2UserHelper
{
    public static function getReturnURL($params, $type)
    {
        if ($itemid = $params->get($type)) {
            $app = Factory::getApplication();
            $menu = $app->getMenu();
            $item = $menu->getItem($itemid);
            $url = 'index.php?Itemid=' . $item->id;
        } else {
            // stay on the same page
            $uri = Uri::getInstance();
            $url = $uri->toString(array('path', 'query', 'fragment'));
        }

        return base64_encode($url);
    }

    public static function getType()
    {
        $user = Factory::getUser();
        return (!$user->get('guest')) ? 'logout' : 'login';
    }

    public static function getProfile(&$params)
    {
        $user = Factory::getUser();
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_users WHERE userID=" . (int)$user->id;
        $db->setQuery($query, 0, 1);
        $profile = $db->loadObject();

        if ($profile) {
            if ($profile->image != '') {
                $profile->avatar = URI::root() . 'media/k2/users/' . $profile->image;
            }
            require_once(JPATH_SITE . '/components/com_k2/helpers/permissions.php');
            if (Factory::getApplication()->input->getCmd('option') != 'com_k2') {
                K2HelperPermissions::setPermissions();
            }
            if (K2HelperPermissions::canAddItem()) {
                $profile->addLink = Route::_('index.php?option=com_k2&view=item&task=add&tmpl=component&template=system&context=modalselector');
            }
            return $profile;
        }
    }

    public static function countUserComments($userID)
    {
        $db = Factory::getDbo();
        $query = "SELECT COUNT(*) FROM #__k2_comments WHERE userID=" . (int)$userID . " AND published=1";
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
    }

    public static function getMenu($params)
    {
        $items = array();
        $children = array();
        if ($params->get('menu')) {
            $app = Factory::getApplication();
            $menu = $app->getMenu();
            $items = $menu->getItems('menutype', $params->get('menu'));
        }
        foreach ($items as $item) {
            $item->name = $item->title;
            $item->parent = $item->parent_id;
            $index = $item->parent;
            $list = @$children[$index] ? $children[$index] : array();
            array_push($list, $item);
            $children[$index] = $list;
        }
        $items = JHTML::_('menu.treerecurse', 1, '', array(), $children, 9999, 0, 0);
        $links = array();
        foreach ($items as $item) {
            $item->flink = $item->link;
            switch ($item->type) {
                case 'separator':
                    continue 2;
                case 'url':
                    if ((strpos($item->link, 'index.php?') === 0) && (strpos($item->link, 'Itemid=') === false)) {
                        $item->flink = $item->link . '&Itemid=' . $item->id;
                    }
                    break;
                case 'alias':
                    $item->flink = 'index.php?Itemid=' . $item->params->get('aliasoptions');
                    break;
                default:
                    $router = JSite::getRouter();
                    if ($router->getMode() == JROUTER_MODE_SEF) {
                        $item->flink = 'index.php?Itemid=' . $item->id;
                    } else {
                        $item->flink .= '&Itemid=' . $item->id;
                    }
                    break;
            }
            if (strcasecmp(substr($item->flink, 0, 4), 'http') && (strpos($item->flink, 'index.php?') !== false)) {
                $item->flink = Route::_($item->flink, true, $item->params->get('secure'));
            } else {
                $item->flink = Route::_($item->flink);
            }
            $item->route = $item->flink;
            $links[] = $item;
        }
        return $links;
    }
}
