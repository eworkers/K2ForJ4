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
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\ModuleHelper;

$app = Factory::getApplication();
$language = $app->getLanguage();
$language->load('com_k2.dates', JPATH_ADMINISTRATOR, null, true);

require_once(dirname(__FILE__) . '/helper.php');

// Params
$moduleclass_sfx = $params->get('moduleclass_sfx', '');
$getTemplate = $params->get('getTemplate', 'Default');
$itemAuthorAvatarWidthSelect = $params->get('itemAuthorAvatarWidthSelect', 'custom');
$itemAuthorAvatarWidth = $params->get('itemAuthorAvatarWidth', 50);
$itemCustomLinkTitle = $params->get('itemCustomLinkTitle', '');
$itemCustomLinkURL = trim($params->get('itemCustomLinkURL'));
$itemCustomLinkMenuItem = $params->get('itemCustomLinkMenuItem');

if ($itemCustomLinkURL && $itemCustomLinkURL != 'http://' && $itemCustomLinkURL != 'https://') {
    if ($itemCustomLinkTitle == '') {
        if (strpos($itemCustomLinkURL, '://') !== false) {
            $linkParts = explode('://', $itemCustomLinkURL);
            $itemCustomLinkURL = $linkParts[1];
        }
        $itemCustomLinkTitle = $itemCustomLinkURL;
    }
} elseif ($itemCustomLinkMenuItem) {
    $menu = $app->getMenu();
    $menuLink = $menu->getItem($itemCustomLinkMenuItem);
	$ignoredTypes = ['heading', 'separator'];
    if (!empty($menuLink) && (!in_array($menuLink->type, $ignoredTypes))) {
        if (!$itemCustomLinkTitle) {
            $itemCustomLinkTitle = $menuLink->title;
        }
		if($menuLink->type == 'url'){
			$itemCustomLinkURL = $menuLink->link;
		}
		else $itemCustomLinkURL = Route::_('index.php?&Itemid=' . $menuLink->id);

    } else {
        $itemCustomLinkTitle = '';
        $itemCustomLinkURL = '';
    }
}

// Make params backwards compatible
$params->set('itemCustomLinkTitle', $itemCustomLinkTitle);
$params->set('itemCustomLinkURL', $itemCustomLinkURL);

// Get component params
$componentParams = ComponentHelper::getParams('com_k2');

// User avatar
if ($itemAuthorAvatarWidthSelect == 'inherit') {
    $avatarWidth = $componentParams->get('userImageWidth');
} else {
    $avatarWidth = $itemAuthorAvatarWidth;
}

$items = modK2ContentHelper::getItems($params);

if (is_array($items) && count($items)) {
    require(ModuleHelper::getLayoutPath('mod_k2_content', $getTemplate . '/default'));
}
