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
use Joomla\CMS\Helper\ModuleHelper;

$user = Factory::getUser();

if (!$user->authorise('core.manage', 'com_k2'))
{
    return;
}

$language = Factory::getLanguage();
$language->load('com_k2.dates', JPATH_ADMINISTRATOR);

require_once(dirname(__FILE__).'/helper.php');

if ($params->get('latestItems', 1))
{
	$latestItems = modK2StatsHelper::getLatestItems();
}
if ($params->get('popularItems', 1))
{
	$popularItems = modK2StatsHelper::getPopularItems();
}
if ($params->get('mostCommentedItems', 1))
{
	$mostCommentedItems = modK2StatsHelper::getMostCommentedItems();
}
if ($params->get('latestComments', 1))
{
	$latestComments = modK2StatsHelper::getLatestComments();
}
if ($params->get('statistics', 1))
{
	$statistics = modK2StatsHelper::getStatistics();
}

require(ModuleHelper::getLayoutPath('mod_k2_stats'));
