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
use Joomla\CMS\Table\Table;

jimport('joomla.html.parameter');

class K2HelperPermissions
{
    public static function checkPermissions()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $task = Factory::getApplication()->input->getCmd('task');
        $id = ($task == 'apply' || $task == 'save') ? Factory::getApplication()->input->getInt('id') : Factory::getApplication()->input->getVar('cid');

        // Generic access check
        if (!$user->authorise('core.manage', $option)) {
            JFactory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'WARNING');
            $app->redirect('index.php');
        }

        // Determine actions for everything else
        $action = false;
        if ($app->isClient('administrator') && $view != '' && $view != 'info') {
            switch ($task) {
                case '':
                case 'save':
                case 'apply':
                    if (!$id) {
                        $action = 'core.create';
                    } else {
                        $action = 'core.edit';
                    }
                    break;
                case 'trash':
                case 'remove':
                    $action = 'core.delete';
                    break;
                case 'publish':
                case 'unpublish':
                case 'featured':
                    $action = 'core.edit.state';
            }

            // Edit or edit own action
            if ($action == 'core.edit' && $view == 'item' && $id) {
                Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
                $item = Table::getInstance('K2Item', 'Table');
                $item->load($id);
                if ($item->created_by == $user->id) {
                    $action = 'core.edit.own';
                }
            }

            // Check the determined action
            if ($action) {
                if (!$user->authorise($action, $option)) {
                    JFactory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'WARNING');
                    $app->redirect('index.php?option=com_k2');
                }
            }
        }
    }
}
