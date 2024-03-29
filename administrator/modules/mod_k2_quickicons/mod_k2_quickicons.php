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
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Uri\Uri;

$user = Factory::getUser();

if (!$user->authorise('core.manage', 'com_k2')) {
    return;
}
$language = Factory::getLanguage();
$language->load('com_k2.dates', JPATH_ADMINISTRATOR);
if ($user->authorise('core.admin', 'com_k2')) {
    $user->gid = 1000;
} else {
    $user->gid = 1;
}

// JoomlaWorks reference parameters
$mod_name = "mod_k2_quickicons";
$mod_copyrights_start = "\n\n<!-- JoomlaWorks \"K2 QuickIcons\" Module starts here -->\n";
$mod_copyrights_end = "\n<!-- JoomlaWorks \"K2 QuickIcons\" Module ends here -->\n\n";

// API
$app = Factory::getApplication();
$document = Factory::getDocument();
$user = Factory::getUser();

// Module parameters
$moduleclass_sfx = $params->get('moduleclass_sfx', '');
$modCSSStyling = (int)$params->get('modCSSStyling', 1);
$modLogo = (int)$params->get('modLogo', 1);

// Component parameters
$componentParams = ComponentHelper::getParams('com_k2');

// Load CSS & JS
K2HelperHTML::loadHeadIncludes(true, false, true, false);
if ($modCSSStyling) {
    $document->addStyleSheet(URI::base(true).'/modules/'.$mod_name.'/tmpl/css/style.css?v='.K2_CURRENT_VERSION);
}

// Output content with template
echo $mod_copyrights_start;
$layout = 'default';
if (version_compare(JVERSION, '4.0.0-dev', 'ge'))
{
	$layout = $params->get('layout', 'default');
}
require(ModuleHelper::getLayoutPath($mod_name, $layout));
echo $mod_copyrights_end;
