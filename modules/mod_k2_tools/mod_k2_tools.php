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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;

$language = Factory::getLanguage();
$language->load('com_k2.dates', JPATH_ADMINISTRATOR, null, true);

require_once(dirname(__FILE__) . '/helper.php');

// Params
$moduleclass_sfx = $params->get('moduleclass_sfx', '');
$module_usage = $params->get('module_usage', 0);
$authorAvatarWidthSelect = $params->get('authorAvatarWidthSelect', 'custom');
$authorAvatarWidth = $params->get('authorAvatarWidth', 50);
$button = $params->get('button');
$imagebutton = $params->get('imagebutton');
$button_pos = $params->get('button_pos', 'left');
$button_text = $params->get('button_text', Text::_('K2_SEARCH'));
$text = $params->get('text', Text::_('K2_SEARCH'));
$searchItemId = $params->get('searchItemId', '');

// API
$document = Factory::getDocument();
$app = Factory::getApplication();

// Output
switch ($module_usage) {
    case '0':
        $months = modK2ToolsHelper::getArchive($params);
        if (count((array)$months)) {
            require(ModuleHelper::getLayoutPath('mod_k2_tools', 'archive'));
        }
        break;

    case '1':
        // User avatar
        if ($authorAvatarWidthSelect == 'inherit') {
            $componentParams = ComponentHelper::getParams('com_k2');
            $avatarWidth = $componentParams->get('userImageWidth');
        } else {
            $avatarWidth = $authorAvatarWidth;
        }
        $authors = modK2ToolsHelper::getAuthors($params);
        require(ModuleHelper::getLayoutPath('mod_k2_tools', 'authors'));
        break;

    case '2':
        $calendar = modK2ToolsHelper::calendar($params);
        require(ModuleHelper::getLayoutPath('mod_k2_tools', 'calendar'));
        break;

    case '3':
        $breadcrumbs = modK2ToolsHelper::breadcrumbs($params);
        $path = $breadcrumbs[0];
        $title = $breadcrumbs[1];
        require(ModuleHelper::getLayoutPath('mod_k2_tools', 'breadcrumbs'));
        break;

    case '4':
        $output = modK2ToolsHelper::treerecurse($params, 0, 0, true);
        require(ModuleHelper::getLayoutPath('mod_k2_tools', 'categories'));
        break;

    case '5':
        echo modK2ToolsHelper::treeselectbox($params);
        break;

    case '6':
        $categoryFilter = modK2ToolsHelper::getSearchCategoryFilter($params);
        $action = Route::_(K2HelperRoute::getSearchRoute($searchItemId));
        require(ModuleHelper::getLayoutPath('mod_k2_tools', 'search'));
        break;

    case '7':
        $tags = modK2ToolsHelper::tagCloud($params);
        if (count((array)$tags)) {
            require(ModuleHelper::getLayoutPath('mod_k2_tools', 'tags'));
        }
        break;

    case '8':
        $customcode = modK2ToolsHelper::renderCustomCode($params);
        require(ModuleHelper::getLayoutPath('mod_k2_tools', 'customcode'));
        break;

    case '9':
        $selectedTags = (array)$params->get('selectedTags');
        $selectedTagsLimit = (int)$params->get('selectedTagsLimit', 0);
        if (count((array)$selectedTags)) {
            require(ModuleHelper::getLayoutPath('mod_k2_tools', 'selected_tags'));
        }
        break;
}
