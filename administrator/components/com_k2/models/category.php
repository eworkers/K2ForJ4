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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.model');

JTable::addIncludePath(JPATH_COMPONENT . '/tables');

class K2ModelCategory extends K2Model
{
    public function getData()
    {
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Category', 'Table');
        $row->load($cid);
        return $row;
    }

    public function save()
    {
        $app = Factory::getApplication();
        jimport('joomla.filesystem.file');
        require_once(JPATH_SITE . '/media/k2/assets/vendors/verot/class.upload.php/src/class.upload.php');
        $row = Table::getInstance('K2Category', 'Table');
        $params = ComponentHelper::getParams('com_k2');

        // Plugin Events
        PluginHelper::importPlugin('k2');
        PluginHelper::importPlugin('content');
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        if (!$row->bind(Factory::getApplication()->input->getArray($_POST))) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=categories');
        }

        $isNew = ($row->id) ? false : true;

        // Trigger K2 plugins
        /* since J4 compatibility */
        $result = Factory::getApplication()->triggerEvent('onBeforeK2Save', array(&$row, $isNew));

        if (in_array(false, $result, true)) {
            JFactory::getApplication()->enqueueMessage($row->getError(), 'ERROR');
            return false;
        }

        // Trigger content & finder plugins before the save event
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onContentBeforeSave', array('com_k2.category', $row, $isNew, ''));
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderBeforeSave', array('com_k2.category', $row, $isNew));

        $row->description = Factory::getApplication()->input->post->getRaw('description', '');
        if ($params->get('xssFiltering')) {
            $filter = new InputFilter(array(), array(), 1, 1, 0);
            $row->description = $filter->clean($row->description);
        }

        if (!$row->id) {
            $row->ordering = $row->getNextOrder('parent = ' . (int)$row->parent . ' AND trash=0');
        }

        if (!$row->check()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=category&cid=' . $row->id);
        }

        if (!$row->store()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=categories');
        }

        if (!$params->get('disableCompactOrdering')) {
            $row->reorder('parent = ' . (int)$row->parent . ' AND trash=0');
        }

        if ((int)$params->get('imageMemoryLimit')) {
            ini_set('memory_limit', (int)$params->get('imageMemoryLimit') . 'M');
        }

        $files = Factory::getApplication()->input->files->getArray($_FILES);

        $savepath = JPATH_ROOT . '/media/k2/categories/';

        $existingImage = Factory::getApplication()->input->getVar('existingImage');
        if (($files['image']['error'] == 0 || $existingImage) && !Factory::getApplication()->input->getBool('del_image')) {
            if ($files['image']['error'] == 0) {
                $image = $files['image'];
            } else {
                $image = JPATH_SITE . '/' . Path::clean($existingImage);
            }

            $handle = new Upload($image);
            if ($handle->uploaded) {
                $handle->file_auto_rename = false;
                $handle->jpeg_quality = $params->get('imagesQuality', '85');
                $handle->file_overwrite = true;
                $handle->file_new_name_body = $row->id;
                $handle->image_resize = true;
                $handle->image_ratio_y = true;
                $handle->image_x = $params->get('catImageWidth', '100');
                $handle->Process($savepath);
                if ($files['image']['error'] == 0) {
                    $handle->Clean();
                }
            } else {
                $app->enqueueMessage($handle->error, 'error');
                $app->redirect('index.php?option=com_k2&view=categories');
            }
            $row->image = $handle->file_dst_name;
        }

        if (Factory::getApplication()->input->getBool('del_image')) {
            $savedRow = Table::getInstance('K2Category', 'Table');
            $savedRow->load($row->id);
            if (File::exists(JPATH_ROOT . '/media/k2/categories/' . $savedRow->image)) {
                File::delete(JPATH_ROOT . '/media/k2/categories/' . $savedRow->image);
            }
            $row->image = '';
        }

        if (!$row->store()) {
            $app->enqueueMessage($row->getError(), 'error');
            $app->redirect('index.php?option=com_k2&view=categories');
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        // Trigger K2 plugins
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onAfterK2Save', array(&$row, $isNew));

        // Trigger content & finder plugins after the save event
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onContentAfterSave', array('com_k2.category', &$row, $isNew));
        /* since J4 compatibility */
        $results = Factory::getApplication()->triggerEvent('onFinderAfterSave', array('com_k2.category', $row, $isNew));

        switch (Factory::getApplication()->input->getCmd('task')) {
            case 'apply':
                $msg = Text::_('K2_CHANGES_TO_CATEGORY_SAVED');
                $link = 'index.php?option=com_k2&view=category&cid=' . $row->id;
                break;
            case 'saveAndNew':
                $msg = Text::_('K2_CATEGORY_SAVED');
                $link = 'index.php?option=com_k2&view=category';
                break;
            case 'save':
            default:
                $msg = Text::_('K2_CATEGORY_SAVED');
                $link = 'index.php?option=com_k2&view=categories';
                break;
        }
        $app->enqueueMessage($msg);
        $app->redirect($link);
    }

    public function countCategoryItems($catid, $trash = 0)
    {
        $db = Factory::getDbo();
        $catid = (int)$catid;
        $query = "SELECT COUNT(*) FROM #__k2_items WHERE catid={$catid} AND trash = " . (int)$trash;
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result;
    }
}
