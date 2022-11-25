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
use Joomla\CMS\Component\ComponentHelper;

jimport('joomla.application.component.model');

JTable::addIncludePath(JPATH_COMPONENT . '/tables');

class K2ModelTag extends K2Model
{
    public function getData()
    {
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Tag', 'Table');
        $row->load($cid);
        return $row;
    }

    public function save()
    {
        $app = Factory::getApplication();
        $row = Table::getInstance('K2Tag', 'Table');

        if (!$row->bind(Factory::getApplication()->input->getArray($_POST))) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=tags');
        }

        if (!$row->check()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=tag&cid=' . $row->id);
        }

        if (!$row->store()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=tags');
        }

        parent::cleanCache('com_k2');

        switch (Factory::getApplication()->input->getCmd('task')) {
            case 'apply':
                $msg = Text::_('K2_CHANGES_TO_TAG_SAVED');
                $link = 'index.php?option=com_k2&view=tag&cid=' . $row->id;
                break;
            case 'saveAndNew':
                $msg = Text::_('K2_TAG_SAVED');
                $link = 'index.php?option=com_k2&view=tag';
                break;
            case 'save':
            default:
                $msg = Text::_('K2_TAG_SAVED');
                $link = 'index.php?option=com_k2&view=tags';
                break;
        }
        $app->enqueueMessage($msg);
        $app->redirect($link);
    }

    public function addTag()
    {
        $app = Factory::getApplication();

        $user = Factory::getUser();
        $params = ComponentHelper::getParams('com_k2');
        if ($user->gid < 24 && $params->get('lockTags')) {
            throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
        }

        $tag = Factory::getApplication()->input->getString('tag');
        $tag = str_replace('-', '', $tag);
        $tag = str_replace('.', '', $tag);

        $response = new stdClass;
        $response->name = $tag;

        if (empty($tag)) {
            $response->msg = Text::_('K2_YOU_NEED_TO_ENTER_A_TAG', true);
            echo json_encode($response);
            $app->close();
        }

        $db = Factory::getDbo();
        $query = "SELECT COUNT(*) FROM #__k2_tags WHERE name=" . $db->Quote($tag);
        $db->setQuery($query);
        $result = $db->loadResult();

        if ($result > 0) {
            $response->msg = Text::_('K2_TAG_ALREADY_EXISTS', true);
            echo json_encode($response);
            $app->close();
        }

        $row = Table::getInstance('K2Tag', 'Table');
        $row->name = $tag;
        $row->published = 1;
        $row->store();

        parent::cleanCache('com_k2');

        $response->id = $row->id;
        $response->status = 'success';
        $response->msg = Text::_('K2_TAG_ADDED_TO_AVAILABLE_TAGS_LIST', true);
        echo json_encode($response);

        $app->close();
    }

    public function tags()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $word = Factory::getApplication()->input->getString('q', null);
        $id = Factory::getApplication()->input->getInt('id');
        $word = $db->Quote($db->escape($word, true) . '%', false);

        if ($id) {
            $query = "SELECT id,name FROM #__k2_tags WHERE name LIKE " . $word;
            $db->setQuery($query);
            $result = $db->loadObjectList();
        } else {
            $query = "SELECT name FROM #__k2_tags WHERE name LIKE " . $word;
            $db->setQuery($query);
            $result = $db->loadColumn();
        }

        echo json_encode($result);
        $app->close();
    }
}
