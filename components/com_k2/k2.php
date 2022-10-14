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
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;

$user = Factory::getUser();
if ($user->authorise('core.admin', 'com_k2'))
{
    $user->gid = 1000;
}
else
{
    $user->gid = 1;
}

JLoader::register('K2Controller', JPATH_COMPONENT.'/controllers/controller.php');
JLoader::register('K2View', JPATH_COMPONENT_ADMINISTRATOR.'/views/view.php');
JLoader::register('K2Model', JPATH_COMPONENT_ADMINISTRATOR.'/models/model.php');

JLoader::register('K2HelperRoute', JPATH_COMPONENT.'/helpers/route.php');
JLoader::register('K2HelperPermissions', JPATH_COMPONENT.'/helpers/permissions.php');
JLoader::register('K2HelperUtilities', JPATH_COMPONENT.'/helpers/utilities.php');

K2HelperPermissions::setPermissions();
K2HelperPermissions::checkPermissions();

$controller = Factory::getApplication()->input->getWord('view', 'itemlist');
$task = Factory::getApplication()->input->getWord('task');

if ($controller == 'media')
{
    $controller = 'item';
    if ($task != 'connector')
    {
        $task = 'media';
    }
}

if ($controller == 'users')
{
    $controller = 'item';
    $task = 'users';
}

jimport('joomla.filesystem.file');
jimport('joomla.html.parameter');

if (File::exists(JPATH_COMPONENT.'/controllers/'.$controller.'.php'))
{
    $classname = 'K2Controller'.$controller;
    if(!class_exists($classname))
        require_once(JPATH_COMPONENT.'/controllers/'.$controller.'.php');
    $controller = new $classname();
    $controller->execute($task);
    $controller->redirect();
}
else
{
    JFactory::getApplication()->enqueueMessage(Text::_('K2_NOT_FOUND'), 'ERROR');
}

if (Factory::getApplication()->input->getCmd('format') != 'json')
{
    echo "\n<!-- JoomlaWorks \"K2\" (v".K2_CURRENT_VERSION.") | Learn more about K2 at https://getk2.org -->\n\n";
}
