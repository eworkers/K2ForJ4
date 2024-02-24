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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Helper\ModuleHelper;

$language = Factory::getLanguage();
$language->load('com_k2.dates', JPATH_ADMINISTRATOR, null, true);

require_once(dirname(__FILE__) . '/helper.php');

$moduleclass_sfx = $params->get('moduleclass_sfx', '');
$userGreetingText = $params->get('userGreetingText', '');
$userAvatarWidthSelect = $params->get('userAvatarWidthSelect', 'custom');
$userAvatarWidth = $params->get('userAvatarWidth', 50);

// Legacy params
$greeting = 0;

$type = modK2UserHelper::getType();
$return = modK2UserHelper::getReturnURL($params, $type);
$user = Factory::getUser();

$componentParams = ComponentHelper::getParams('com_k2');
$K2CommentsEnabled = $componentParams->get('comments');

// User avatar
if ($userAvatarWidthSelect == 'inherit') {
    $avatarWidth = $componentParams->get('userImageWidth');
} else {
    $avatarWidth = $userAvatarWidth;
}

// Load the right template
if ($user->guest) {
    // OpenID stuff (do not edit)
    if (PluginHelper::isEnabled('authentication', 'openid')) {
        $lang->load('plg_authentication_openid', JPATH_ADMINISTRATOR);
        $document = Factory::getDocument();
        $document->addScriptDeclaration("
			var Language = {};
			JLanguage.WHAT_IS_OPENID = '" . Text::_('K2_WHAT_IS_OPENID') . "';
			JLanguage.LOGIN_WITH_OPENID = '" . Text::_('K2_LOGIN_WITH_OPENID') . "';
			JLanguage.NORMAL_LOGIN = '" . Text::_('K2_NORMAL_LOGIN') . "';
			var modlogin = 1;
		");
        HTMLHelper::_('script', 'openid.js');
    }

    // Get user stuff (do not edit)
    $usersConfig = ComponentHelper::getParams('com_users');

    // Define some variables depending on Joomla version
    $passwordFieldName = 'password';
    $itemId = Factory::getApplication()->getMenu()->getActive()->id;
    $resetLink = Route::_('index.php?option=com_users&view=reset&Itemid=' . $itemId, false);
    $remindLink = Route::_('index.php?option=com_users&view=remind&Itemid=' . $itemId, false);
    $registrationLink = Route::_('index.php?option=com_users&view=registration&Itemid=' . $itemId, false);

    $option = 'com_users';
    $task = 'user.login';

    require(ModuleHelper::getLayoutPath('mod_k2_user', 'login'));
} else {
    $itemId = Factory::getApplication()->getMenu()->getActive()->id;
    $user->profile = modK2UserHelper::getProfile($params);
    $user->numOfComments = modK2UserHelper::countUserComments($user->id);
    $menu = modK2UserHelper::getMenu($params);

    if (is_object($user->profile) && isset($user->profile->addLink)) {
        $addItemLink = $user->profile->addLink;
    }
    $viewProfileLink = Route::_(K2HelperRoute::getUserRoute($user->id));
    $editProfileLink = Route::_('index.php?option=com_users&view=profile&layout=edit&Itemid=' . $itemId, false);
    $profileLink = $editProfileLink; // B/C
    $editCommentsLink = Route::_('index.php?option=com_k2&view=comments&tmpl=component&template=system&context=modalselector');

    $option = 'com_users';
    $task = 'user.logout';

    require(ModuleHelper::getLayoutPath('mod_k2_user', 'userblock'));
}
