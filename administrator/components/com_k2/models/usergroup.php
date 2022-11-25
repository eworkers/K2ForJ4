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

use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.model');

JTable::addIncludePath(JPATH_COMPONENT . '/tables');

class K2ModelUserGroup extends K2Model
{
    public function getData()
    {
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2UserGroup', 'Table');
        $row->load($cid);
        return $row;
    }

    public function save()
    {
        $app = Factory::getApplication();
        $row = Table::getInstance('K2UserGroup', 'Table');

        if (!$row->bind(Factory::getApplication()->input->getArray($_POST))) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=usergroups');
        }

        if (!$row->check()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=usergroup&cid=' . $row->id);
        }

        if (!$row->store()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=usergroups');
        }

        parent::cleanCache('com_k2');

        switch (Factory::getApplication()->input->getCmd('task')) {
            case 'apply':
                $msg = Text::_('K2_CHANGES_TO_USER_GROUP_SAVED');
                $link = 'index.php?option=com_k2&view=usergroup&cid=' . $row->id;
                break;
            case 'saveAndNew':
                $msg = Text::_('K2_USER_GROUP_SAVED');
                $link = 'index.php?option=com_k2&view=usergroup';
                break;
            case 'save':
            default:
                $msg = Text::_('K2_USER_GROUP_SAVED');
                $link = 'index.php?option=com_k2&view=usergroups';
                break;
        }
        $app->enqueueMessage($msg);
        $app->redirect($link);
    }
}
