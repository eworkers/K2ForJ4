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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.model');

JTable::addIncludePath(JPATH_COMPONENT . '/tables');

class K2ModelUser extends K2Model
{
    public function getData()
    {
        $cid = Factory::getApplication()->input->getInt('cid');
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_users WHERE userID = " . $cid;
        $db->setQuery($query);
        $row = $db->loadObject();
        if (!$row) {
            $row = Table::getInstance('K2User', 'Table');
        }
        return $row;
    }

    public function save()
    {
        $app = Factory::getApplication();
        jimport('joomla.filesystem.file');
        $row = Table::getInstance('K2User', 'Table');
        $params = ComponentHelper::getParams('com_k2');

        if (!$row->bind(Factory::getApplication()->input->getArray($_POST))) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=users');
        }

        $row->description = Factory::getApplication()->input->getVar('description', '', 'post', 'string', 2);
        if ($params->get('xssFiltering')) {
            $filter = new InputFilter(array(), array(), 1, 1, 0);
            $row->description = $filter->clean($row->description);
        }
        $jUser = Factory::getUser($row->userID);
        $row->userName = $jUser->name;

        if (!$row->store()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=users');
        }

        // Image
        if ((int)$params->get('imageMemoryLimit')) {
            ini_set('memory_limit', (int)$params->get('imageMemoryLimit') . 'M');
        }

        $file = Factory::getApplication()->input->files->getArray($_FILES);

        require_once(JPATH_SITE . '/media/k2/assets/vendors/verot/class.upload.php/src/class.upload.php');
        $savepath = JPATH_ROOT . '/media/k2/users/';

        if (isset($file['image']) && $file['image']['error'] == 0 && !Factory::getApplication()->input->getBool('del_image')) {
            $handle = new Upload($file['image']);
            $handle->allowed = array('image/*');
            if ($handle->uploaded) {
                $handle->file_auto_rename = false;
                $handle->file_overwrite = true;
                $handle->file_new_name_body = $row->id;
                $handle->image_resize = true;
                $handle->image_ratio_y = true;
                $handle->image_x = $params->get('userImageWidth', '100');
                $handle->Process($savepath);
                $handle->Clean();
            } else {
                $app->enqueueMessage($handle->error, 'error');
                $app->redirect('index.php?option=com_k2&view=users');
            }
            $row->image = $handle->file_dst_name;
        }

        if (Factory::getApplication()->input->getBool('del_image')) {
            $current = Table::getInstance('K2User', 'Table');
            $current->load($row->id);
            $currentImage = basename($current->image);
            if (File::exists(JPATH_ROOT . '/media/k2/users/' . $currentImage)) {
                File::delete(JPATH_ROOT . '/media/k2/users/' . $currentImage);
            }
            $row->image = '';
        }

        if (!$row->check()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=user&cid=' . $row->id);
        }

        if (!$row->store()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=users');
        }

        $cache = Factory::getCache('com_k2');
        $cache->clean();

        switch (Factory::getApplication()->input->getCmd('task')) {
            case 'apply':
                $msg = Text::_('K2_CHANGES_TO_USER_SAVED');
                $link = 'index.php?option=com_k2&view=user&cid=' . $row->userID;
                break;
            case 'save':
            default:
                $msg = Text::_('K2_USER_SAVED');
                $link = 'index.php?option=com_k2&view=users';
                break;
        }
        $app->enqueueMessage($msg);
        $app->redirect($link);
    }

    public function getUserGroups()
    {
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_user_groups";
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        return $rows;
    }

    public function reportSpammer()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $id = (int)$this->getState('id');
        if (!$id) {
            return false;
        }
        $user = Factory::getUser();
        if ($user->id == $id) {
            $app->enqueueMessage(Text::_('K2_YOU_CANNOT_REPORT_YOURSELF'), 'error');
            return false;
        }
        $db = Factory::getDbo();

        // Unpublish user comments
        $db->setQuery("UPDATE #__k2_comments SET published = 0 WHERE userID = " . $id);
        $db->execute();
        $app->enqueueMessage(Text::_('K2_USER_COMMENTS_UNPUBLISHED'));

        // Unpublish user items
        $db->setQuery("UPDATE #__k2_items SET published = 0 WHERE created_by = " . $id);
        $db->execute();
        $app->enqueueMessage(Text::_('K2_USER_ITEMS_UNPUBLISHED'));

        // Report the user to stopforumspam.com
        // We need the IP for this, so the user has to be a registered K2 user
        $spammer = Factory::getUser($id);
        $db->setQuery("SELECT ip FROM #__k2_users WHERE userID=" . $id, 0, 1);
        $ip = $db->loadResult();
        $stopForumSpamApiKey = trim($params->get('stopForumSpamApiKey'));
        if ($ip && function_exists('fsockopen') && $stopForumSpamApiKey) {
            $data = "username=" . $spammer->username . "&ip_addr=" . $ip . "&email=" . $spammer->email . "&api_key=" . $stopForumSpamApiKey;
            $fp = fsockopen("www.stopforumspam.com", 80);
            fputs($fp, "POST /add.php HTTP/1.1\n");
            fputs($fp, "Host: www.stopforumspam.com\n");
            fputs($fp, "Content-type: application/x-www-form-urlencoded\n");
            fputs($fp, "Content-length: " . strlen($data) . "\n");
            fputs($fp, "Connection: close\n\n");
            fputs($fp, $data);
            fclose($fp);
            $app->enqueueMessage(Text::_('K2_USER_DATA_SUBMITTED_TO_STOPFORUMSPAM'));
        }

        // Finally block the user
        $db->setQuery("UPDATE #__users SET block = 1 WHERE id=" . $id);
        $db->execute();
        $app->enqueueMessage(Text::_('K2_USER_BLOCKED'));
        return true;
    }
}
