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

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\Registry\Registry;

jimport('joomla.application.component.model');

class K2ModelItems extends K2Model
{
    private $getTotal;

    public function getData()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'i.id', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
        $filter_trash = $app->getUserStateFromRequest($option . $view . 'filter_trash', 'filter_trash', 0, 'int');
        $filter_featured = $app->getUserStateFromRequest($option . $view . 'filter_featured', 'filter_featured', -1, 'int');
        $filter_category = $app->getUserStateFromRequest($option . $view . 'filter_category', 'filter_category', 0, 'int');
        $filter_author = $app->getUserStateFromRequest($option . $view . 'filter_author', 'filter_author', 0, 'int');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\"\-_]/u', '', $search));
        $tag = $app->getUserStateFromRequest($option . $view . 'tag', 'tag', 0, 'int');
        $language = $app->getUserStateFromRequest($option . $view . 'language', 'language', '', 'string');

        $query = "/* Backend / K2 / Items */ SELECT SQL_CALC_FOUND_ROWS i.*, g.name AS groupname, c.name AS category, v.name AS author, w.name AS moderator, u.name AS editor
            FROM #__k2_items AS i
            LEFT JOIN #__k2_categories AS c ON c.id = i.catid
            LEFT JOIN #__groups AS g ON g.id = i.access
            LEFT JOIN #__users AS u ON u.id = i.checked_out
            LEFT JOIN #__users AS v ON v.id = i.created_by
            LEFT JOIN #__users AS w ON w.id = i.modified_by";

        if ($params->get('showTagFilter') && $tag) {
            $query .= " LEFT JOIN #__k2_tags_xref AS tags_xref ON tags_xref.itemID = i.id";
        }

        $query .= " WHERE i.trash={$filter_trash}";

        if ($search) {
            // Detect exact search phrase using double quotes in search string
            if (substr($search, 0, 1) == '"' && substr($search, -1) == '"') {
                $exact = true;
            } else {
                $exact = false;
            }

            // Now completely strip double quotes
            $search = trim(str_replace('"', '', $search));

            // Escape remaining string
            $escaped = $db->escape($search, true);

            // Full phrase or set of words
            if (strpos($escaped, ' ') !== false && !$exact) {
                $escaped = explode(' ', $escaped);
                $quoted = array();
                foreach ($escaped as $key => $escapedWord) {
                    $quoted[] = $db->Quote('%' . $escapedWord . '%', false);
                }
                if ($params->get('adminSearch') == 'full') {
                    foreach ($quoted as $quotedWord) {
                        $query .= " AND (
                            LOWER(i.title) LIKE " . $quotedWord . " OR
                            LOWER(i.introtext) LIKE " . $quotedWord . " OR
                            LOWER(i.`fulltext`) LIKE " . $quotedWord . " OR
                            LOWER(i.extra_fields_search) LIKE " . $quotedWord . " OR
                            LOWER(i.image_caption) LIKE " . $quotedWord . " OR
                            LOWER(i.image_credits) LIKE " . $quotedWord . " OR
                            LOWER(i.video_caption) LIKE " . $quotedWord . " OR
                            LOWER(i.video_credits) LIKE " . $quotedWord . " OR
                            LOWER(i.metadesc) LIKE " . $quotedWord . " OR
                            LOWER(i.metakey) LIKE " . $quotedWord . "
                        )";
                    }
                } else {
                    foreach ($quoted as $quotedWord) {
                        $query .= " AND LOWER(i.title) LIKE " . $quotedWord;
                    }
                }
            } // Single word or exact phrase to search for (wrapped in double quotes in the search block)
            else {
                $quoted = $db->Quote('%' . $escaped . '%', false);

                if ($params->get('adminSearch') == 'full') {
                    $query .= " AND (
                        LOWER(i.title) LIKE " . $quoted . " OR
                        LOWER(i.introtext) LIKE " . $quoted . " OR
                        LOWER(i.`fulltext`) LIKE " . $quoted . " OR
                        LOWER(i.extra_fields_search) LIKE " . $quoted . " OR
                        LOWER(i.image_caption) LIKE " . $quoted . " OR
                        LOWER(i.image_credits) LIKE " . $quoted . " OR
                        LOWER(i.video_caption) LIKE " . $quoted . " OR
                        LOWER(i.video_credits) LIKE " . $quoted . " OR
                        LOWER(i.metadesc) LIKE " . $quoted . " OR
                        LOWER(i.metakey) LIKE " . $quoted . "
                    )";
                } else {
                    $query .= " AND LOWER(i.title) LIKE " . $quoted;
                }
            }
        }

        if ($filter_state > -1) {
            $query .= " AND i.published={$filter_state}";
        }

        if ($filter_featured > -1) {
            $query .= " AND i.featured={$filter_featured}";
        }

        if ($filter_category > 0) {
            if ($params->get('showChildCatItems')) {
                K2Model::addIncludePath(JPATH_SITE . '/components/com_k2/models');
                $itemListModel = K2Model::getInstance('Itemlist', 'K2Model');
                $categories = $itemListModel->getCategoryTree($filter_category);
                $sql = @implode(',', $categories);
                $query .= " AND i.catid IN ({$sql})";
            } else {
                $query .= " AND i.catid={$filter_category}";
            }
        }

        if ($filter_author > 0) {
            $query .= " AND i.created_by={$filter_author}";
        }

        if ($params->get('showTagFilter') && $tag) {
            $query .= " AND tags_xref.tagID = {$tag}";
        }

        if ($language) {
            $query .= " AND (i.language = " . $db->Quote($language) . " OR i.language = '*')";
        }

        if ($filter_order == 'i.ordering') {
            $query .= " ORDER BY i.catid, i.ordering {$filter_order_Dir}";
        } else {
            $query .= " ORDER BY {$filter_order} {$filter_order_Dir}";
        }

        $query = str_ireplace('#__groups', '#__viewlevels', $query);
        $query = str_ireplace('g.name', 'g.title', $query);

        // Plugin Events
        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        // Trigger K2 plugins
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onK2BeforeSetQuery', array(&$query));

        $db->setQuery($query, $limitstart, $limit);
        $rows = $db->loadObjectList();

        if (count($rows)) {
            $db->setQuery('SELECT FOUND_ROWS();');
            $this->getTotal = $db->loadResult();
        }

        return $rows;
    }

    public function getTotal()
    {
        return $this->getTotal;
    }

    public function publish()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load($id);
            $row->published = 1;
            $row->store();
        }

        // Plugins Events
        PluginHelper::importPlugin('content');
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        // Trigger content & finder plugins when state changes
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onContentChangeState', array('com_k2.item', $cid, 1));
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderChangeState', array('com_k2.item', $cid, 1));

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=items&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=items');
        }
    }

    public function unpublish()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load($id);
            $row->published = 0;
            $row->store();
        }

        // Plugins Events
        PluginHelper::importPlugin('content');
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        // Trigger content & finder plugins when state changes
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onContentChangeState', array('com_k2.item', $cid, 0));
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderChangeState', array('com_k2.item', $cid, 0));

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=items&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=items');
        }
    }

    public function saveorder()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid', array(0), 'post', 'array');
        $total = count($cid);
        $order = Factory::getApplication()->input->getVar('order', array(0), 'post', 'array');
        ArrayHelper::toInteger($order, array(0));
        $groupings = array();
        for ($i = 0; $i < $total; $i++) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load((int)$cid[$i]);
            $groupings[] = $row->catid;
            if ($row->ordering != $order[$i]) {
                $row->ordering = $order[$i];
                /* since J4 compatibility */
                try {
                    $row->store();
                } catch (Exception $e) {
                    Factory::getApplication()->enqueueMessage(Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
                }
            }
        }
        if (!$params->get('disableCompactOrdering')) {
            $groupings = array_unique($groupings);
            foreach ($groupings as $group) {
                $row = Table::getInstance('K2Item', 'Table');
                $row->reorder('catid = ' . (int)$group . ' AND trash=0');
            }
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        return true;
    }

    public function orderup()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Item', 'Table');
        $row->load($cid[0]);
        $row->move(-1, 'catid = ' . (int)$row->catid . ' AND trash=0');
        if (!$params->get('disableCompactOrdering')) {
            $row->reorder('catid = ' . (int)$row->catid . ' AND trash=0');
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $msg = Text::_('K2_NEW_ORDERING_SAVED');
        $app->enqueueMessage($msg);
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=items&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=items');
        }
    }

    public function orderdown()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Item', 'Table');
        $row->load($cid[0]);
        $row->move(1, 'catid = ' . (int)$row->catid . ' AND trash=0');
        if (!$params->get('disableCompactOrdering')) {
            $row->reorder('catid = ' . (int)$row->catid . ' AND trash=0');
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $msg = Text::_('K2_NEW_ORDERING_SAVED');
        $app->enqueueMessage($msg);
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=items&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=items');
        }
    }

    public function savefeaturedorder()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid', array(0), 'post', 'array');
        $total = count($cid);
        $order = Factory::getApplication()->input->getVar('order', array(0), 'post', 'array');
        ArrayHelper::toInteger($order, array(0));
        $groupings = array();
        for ($i = 0; $i < $total; $i++) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load((int)$cid[$i]);
            $groupings[] = $row->catid;
            if ($row->featured_ordering != $order[$i]) {
                $row->featured_ordering = $order[$i];
                /* since J4 compatibility */
                try {
                    $row->store();
                } catch (Exception $e) {
                    Factory::getApplication()->enqueueMessage(Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
                }
            }
        }
        if (!$params->get('disableCompactOrdering')) {
            $groupings = array_unique($groupings);
            foreach ($groupings as $group) {
                $row = Table::getInstance('K2Item', 'Table');
                $row->reorder('featured = 1 AND trash=0', 'featured_ordering');
            }
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        return true;
    }

    public function featuredorderup()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Item', 'Table');
        $row->load($cid[0]);
        $row->move(-1, 'featured=1 AND trash=0', 'featured_ordering');
        if (!$params->get('disableCompactOrdering')) {
            $row->reorder('featured=1 AND trash=0', 'featured_ordering');
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $msg = Text::_('K2_NEW_ORDERING_SAVED');
        $app->enqueueMessage($msg);
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=items&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=items');
        }
    }

    public function featuredorderdown()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row = Table::getInstance('K2Item', 'Table');
        $row->load($cid[0]);
        $row->move(1, 'featured=1 AND trash=0', 'featured_ordering');
        if (!$params->get('disableCompactOrdering')) {
            $row->reorder('featured=1 AND trash=0', 'featured_ordering');
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $msg = Text::_('K2_NEW_ORDERING_SAVED');
        $app->enqueueMessage($msg);
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=items&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=items');
        }
    }

    public function accessregistered()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $row = Table::getInstance('K2Item', 'Table');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row->load($cid[0]);
        $row->access = 1;
        if (!$row->check()) {
            return $row->getError();
        }
        if (!$row->store()) {
            return $row->getError();
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $msg = Text::_('K2_NEW_ACCESS_SETTING_SAVED');
        $app->enqueueMessage($msg);
        $app->redirect('index.php?option=com_k2&view=items');
    }

    public function accessspecial()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $row = Table::getInstance('K2Item', 'Table');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row->load($cid[0]);
        $row->access = 2;
        if (!$row->check()) {
            return $row->getError();
        }
        if (!$row->store()) {
            return $row->getError();
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $msg = Text::_('K2_NEW_ACCESS_SETTING_SAVED');
        $app->enqueueMessage($msg);
        $app->redirect('index.php?option=com_k2&view=items');
    }

    public function accesspublic()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $row = Table::getInstance('K2Item', 'Table');
        $cid = Factory::getApplication()->input->getVar('cid');
        $row->load($cid[0]);
        $row->access = 0;
        if (!$row->check()) {
            return $row->getError();
        }
        if (!$row->store()) {
            return $row->getError();
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $msg = Text::_('K2_NEW_ACCESS_SETTING_SAVED');
        $app->enqueueMessage($msg);
        $app->redirect('index.php?option=com_k2&view=items');
    }

    public function copy($batch = false)
    {
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $itemModel = K2Model::getInstance('Item', 'K2Model');
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        $row = Table::getInstance('K2Item', 'Table');
        $copies = array();
        $nullDate = $db->getNullDate();

        // Define media extensions
        $videoExtensions = array(
            "avi",
            "m4v",
            "mkv",
            "mp4",
            "ogv",
            "webm"
        );
        $audioExtensions = array(
            "flac",
            "m4a",
            "mp3",
            "oga",
            "ogg",
            "wav"
        );

        foreach ($cid as $id) {
            // Load source item
            $item = Table::getInstance('K2Item', 'Table');
            $item->load($id);
            $item->id = (int)$item->id;

            // Source images
            $sourceImage = JPATH_ROOT . '/media/k2/items/src/' . md5("Image" . $item->id) . '.jpg';
            $sourceImageXS = JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_XS.jpg';
            $sourceImageS = JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_S.jpg';
            $sourceImageM = JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_M.jpg';
            $sourceImageL = JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_L.jpg';
            $sourceImageXL = JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_XL.jpg';
            $sourceImageGeneric = JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_Generic.jpg';

            // Source gallery
            $sourceGallery = JPATH_ROOT . '/media/k2/galleries/' . $item->id;
            $sourceGalleryTag = $item->gallery;

            // Source media
            preg_match_all("#^{(.*?)}(.*?){#", $item->video, $matches, PREG_PATTERN_ORDER);

            $mediaType = $matches[1][0];
            $mediaFile = $matches[2][0];

            if (in_array($mediaType, $videoExtensions) || in_array($mediaType, $audioExtensions)) {
                // Videos
                if (File::exists(JPATH_ROOT . '/media/k2/videos/' . $mediaFile . '.' . $mediaType)) {
                    $sourceMedia = $mediaFile . '.' . $mediaType;
                }
                // Audio
                if (File::exists(JPATH_ROOT . '/media/k2/audio/' . $mediaFile . '.' . $mediaType)) {
                    $sourceMedia = $mediaFile . '.' . $mediaType;
                }
            }

            // Source tags
            $query = "SELECT * FROM #__k2_tags_xref WHERE itemID={$item->id}";
            $db->setQuery($query);
            $sourceTags = $db->loadObjectList();

            // Source Attachments
            $sourceAttachments = $itemModel->getAttachments($item->id);

            // Save target item
            $row = Table::getInstance('K2Item', 'Table');
            $row = $item;
            $row->id = null;
            $row->title = Text::_('K2_COPY_OF') . ' ' . $item->title;
            $row->hits = 0;
            $row->published = 0;
            $datenow = Factory::getDate();
            $row->created = $datenow->toSql();
            $row->modified = $nullDate;
            $row->store();
            $copies[] = $row->id;

            // Target images
            if (File::exists($sourceImage)) {
                File::copy($sourceImage, JPATH_ROOT . '/media/k2/items/src/' . md5("Image" . $row->id) . '.jpg');
            }
            if (File::exists($sourceImageXS)) {
                File::copy($sourceImageXS, JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_XS.jpg');
            }
            if (File::exists($sourceImageS)) {
                File::copy($sourceImageS, JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_S.jpg');
            }
            if (File::exists($sourceImageM)) {
                File::copy($sourceImageM, JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_M.jpg');
            }
            if (File::exists($sourceImageL)) {
                File::copy($sourceImageL, JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_L.jpg');
            }
            if (File::exists($sourceImageXL)) {
                File::copy($sourceImageXL, JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_XL.jpg');
            }
            if (File::exists($sourceImageGeneric)) {
                File::copy($sourceImageGeneric, JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_Generic.jpg');
            }

            // Target gallery
            if ($sourceGalleryTag) {
                if (StringHelper::strpos($sourceGalleryTag, 'http://') || StringHelper::strpos($sourceGalleryTag, 'https://')) {
                    $row->gallery = $sourceGalleryTag;
                } else {
                    $row->gallery = '{gallery}' . $row->id . '{/gallery}';
                    if (Folder::exists($sourceGallery)) {
                        Folder::copy($sourceGallery, JPATH_ROOT . '/media/k2/galleries/' . $row->id);
                    }
                }
            }

            // Target media
            if (isset($sourceMedia)) {
                if (File::exists(JPATH_ROOT . '/media/k2/videos/' . $sourceMedia)) {
                    File::copy(JPATH_ROOT . '/media/k2/videos/' . $sourceMedia, JPATH_ROOT . '/media/k2/videos/' . $row->id . '.' . $mediaType);
                    $row->video = $row->id . '.' . $mediaType;
                    //$row->video = '{'.$mediaType.'}'.$row->id.'{/'.$mediaType.'}';
                }
                if (File::exists(JPATH_ROOT . '/media/k2/audio/' . $sourceMedia)) {
                    File::copy(JPATH_ROOT . '/media/k2/audio/' . $sourceMedia, JPATH_ROOT . '/media/k2/audio/' . $row->id . '.' . $mediaType);
                    $row->video = $row->id . '.' . $mediaType;
                    //$row->video = '{'.$mediaType.'}'.$row->id.'{/'.$mediaType.'}';
                }
            }

            // Target attachments
            $path = $params->get('attachmentsFolder', null);
            if (is_null($path)) {
                $savepath = JPATH_ROOT . '/media/k2/attachments';
            } else {
                $savepath = $path;
            }

            foreach ($sourceAttachments as $attachment) {
                if (File::exists($savepath . '/' . $attachment->filename)) {
                    File::copy($savepath . '/' . $attachment->filename, $savepath . '/' . $row->id . '_' . $attachment->filename);
                    $attachmentRow = Table::getInstance('K2Attachment', 'Table');
                    $attachmentRow->itemID = $row->id;
                    $attachmentRow->title = $attachment->title;
                    $attachmentRow->titleAttribute = $attachment->titleAttribute;
                    $attachmentRow->filename = $row->id . '_' . $attachment->filename;
                    $attachmentRow->hits = 0;
                    $attachmentRow->store();
                }
            }

            // Target tags
            foreach ($sourceTags as $tag) {
                $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, " . (int)$tag->tagID . ", " . (int)$row->id . ")";
                $db->setQuery($query);
                $db->execute();
            }

            $row->store();
        }
        if ($batch) {
            return $copies;
        } else {
            $app->enqueueMessage(Text::_('K2_COPY_COMPLETED'));
            $app->redirect('index.php?option=com_k2&view=items');
        }
    }

    public function featured()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load($id);
            if ($row->featured == 1) {
                $row->featured = 0;
            } else {
                $row->featured = 1;
                $row->featured_ordering = 1;
            }
            $row->store();
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $app->enqueueMessage(Text::_('K2_ITEMS_CHANGED'));
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=items&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=items');
        }
    }

    public function trash()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        ArrayHelper::toInteger($cid);
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load($id);
            $row->trash = 1;
            $row->store();
        }

        // Plugins Events
        PluginHelper::importPlugin('content');
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        // Trigger content & finder plugins when state changes
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onContentChangeState', array('com_k2.item', $cid, -2));
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderChangeState', array('com_k2.item', $cid, 0));

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $app->enqueueMessage(Text::_('K2_ITEMS_MOVED_TO_TRASH'));
        $app->redirect('index.php?option=com_k2&view=items');
    }

    public function restore()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        $warning = false;
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load($id);
            $query = "SELECT COUNT(*) FROM #__k2_categories WHERE id=" . (int)$row->catid . " AND trash = 0";
            $db->setQuery($query);
            $result = $db->loadResult();
            if ($result) {
                $row->trash = 0;
                $row->store();
            } else {
                $warning = true;
            }
        }

        // Plugins Events
        PluginHelper::importPlugin('content');
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        // Trigger content & finder plugins when state changes
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onContentChangeState', array('com_k2.item', $cid, $row->published));
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onFinderChangeState', array('com_k2.item', $cid, 1));

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        if ($warning) {
            $app->enqueueMessage(Text::_('K2_SOME_OF_THE_ITEMS_HAVE_NOT_BEEN_RESTORED_BECAUSE_THEY_BELONG_TO_A_CATEGORY_WHICH_IS_IN_TRASH'), 'notice');
        }
        $app->enqueueMessage(Text::_('K2_ITEMS_RESTORED'));
        $app->redirect('index.php?option=com_k2&view=items');
    }

    public function remove()
    {
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $itemModel = K2Model::getInstance('Item', 'K2Model');
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');

        // Plugin Events
        PluginHelper::importPlugin('content');
        PluginHelper::importPlugin('finder');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        // Define media extensions
        $videoExtensions = array(
            "avi",
            "m4v",
            "mkv",
            "mp4",
            "ogv",
            "webm"
        );
        $audioExtensions = array(
            "flac",
            "m4a",
            "mp3",
            "oga",
            "ogg",
            "wav"
        );

        foreach ($cid as $id) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load($id);
            $row->id = (int)$row->id;

            // Delete images
            if (File::exists(JPATH_ROOT . '/media/k2/items/src/' . md5("Image" . $row->id) . '.jpg')) {
                File::delete(JPATH_ROOT . '/media/k2/items/src/' . md5("Image" . $row->id) . '.jpg');
            }
            if (File::exists(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_XS.jpg')) {
                File::delete(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_XS.jpg');
            }
            if (File::exists(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_S.jpg')) {
                File::delete(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_S.jpg');
            }
            if (File::exists(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_M.jpg')) {
                File::delete(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_M.jpg');
            }
            if (File::exists(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_L.jpg')) {
                File::delete(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_L.jpg');
            }
            if (File::exists(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_XL.jpg')) {
                File::delete(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_XL.jpg');
            }
            if (File::exists(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_Generic.jpg')) {
                File::delete(JPATH_ROOT . '/media/k2/items/cache/' . md5("Image" . $row->id) . '_Generic.jpg');
            }

            // Delete gallery
            if (Folder::exists(JPATH_ROOT . '/media/k2/galleries/' . $row->id)) {
                Folder::delete(JPATH_ROOT . '/media/k2/galleries/' . $row->id);
            }

            // Delete media
            preg_match_all("#^{(.*?)}(.*?){#", $row->video, $matches, PREG_PATTERN_ORDER);

            $mediaType = $matches[1][0];
            $mediaFile = $matches[2][0];

            if (in_array($mediaType, $videoExtensions) || in_array($mediaType, $audioExtensions)) {
                // Videos
                if (File::exists(JPATH_ROOT . '/media/k2/videos/' . $mediaFile . '.' . $mediaType)) {
                    File::delete(JPATH_ROOT . '/media/k2/videos/' . $mediaFile . '.' . $mediaType);
                }
                // Audio
                if (File::exists(JPATH_ROOT . '/media/k2/audio/' . $mediaFile . '.' . $mediaType)) {
                    File::delete(JPATH_ROOT . '/media/k2/audio/' . $mediaFile . '.' . $mediaType);
                }
            }

            // Delete attachments
            $path = $params->get('attachmentsFolder', null);
            if (is_null($path)) {
                $savepath = JPATH_ROOT . '/media/k2/attachments';
            } else {
                $savepath = $path;
            }

            $attachments = $itemModel->getAttachments($row->id);

            foreach ($attachments as $attachment) {
                if (File::exists($savepath . '/' . $attachment->filename)) {
                    File::delete($savepath . '/' . $attachment->filename);
                }
            }

            $query = "DELETE FROM #__k2_attachments WHERE itemID={$row->id}";
            $db->setQuery($query);
            $db->execute();

            // Delete tags
            $query = "DELETE FROM #__k2_tags_xref WHERE itemID={$row->id}";
            $db->setQuery($query);
            $db->execute();

            // Delete comments
            $query = "DELETE FROM #__k2_comments WHERE itemID={$row->id}";
            $db->setQuery($query);
            $db->execute();

            $row->delete($id);

            // Trigger content & finder plugins after the delete event
            /* since J4 compatibility */
            Factory::getApplication()->triggerEvent('onContentAfterDelete', array('com_k2.item', $row));
            /* since J4 compatibility */
            Factory::getApplication()->triggerEvent('onFinderAfterDelete', array('com_k2.item', $row));
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=items');
    }

    public function import()
    {
        $app = Factory::getApplication();
        jimport('joomla.filesystem.file');
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__sections";
        $db->setQuery($query);
        $sections = $db->loadObjectList();

        $query = "SELECT COUNT(*) FROM #__k2_items";
        $db->setQuery($query);
        $result = $db->loadResult();
        if ($result) {
            $preserveItemIDs = false;
        } else {
            $preserveItemIDs = true;
        }

        $xml = new JSimpleXML;
        $xml->loadFile(JPATH_COMPONENT . '/models/category.xml');
        $categoryParams = class_exists('JParameter') ? new JParameter('') : new Registry('');

        foreach ($xml->document->params as $paramGroup) {
            foreach ($paramGroup->param as $param) {
                if ($param->attributes('type') != 'spacer' && $param->attributes('name')) {
                    $categoryParams->set($param->attributes('name'), $param->attributes('default'));
                }
            }
        }
        $categoryParams = $categoryParams->toString();

        $xml = new JSimpleXML;
        $xml->loadFile(JPATH_COMPONENT . '/models/item.xml');
        $itemParams = class_exists('JParameter') ? new JParameter('') : new Registry('');

        foreach ($xml->document->params as $paramGroup) {
            foreach ($paramGroup->param as $param) {
                if ($param->attributes('type') != 'spacer' && $param->attributes('name')) {
                    $itemParams->set($param->attributes('name'), $param->attributes('default'));
                }
            }
        }
        $itemParams = $itemParams->toString();

        $query = "SELECT id, name FROM #__k2_tags";
        $db->setQuery($query);
        $tags = $db->loadObjectList();

        if (is_null($tags)) {
            $tags = array();
        }

        foreach ($sections as $section) {
            $K2Category = Table::getInstance('K2Category', 'Table');
            $K2Category->name = $section->title;
            $K2Category->alias = $section->title;
            $K2Category->description = $section->description;
            $K2Category->parent = 0;
            $K2Category->published = $section->published;
            $K2Category->access = $section->access;
            $K2Category->ordering = $section->ordering;
            $K2Category->image = $section->image;
            $K2Category->trash = 0;
            $K2Category->params = $categoryParams;
            $K2Category->check();
            $K2Category->store();
            if (File::exists(JPATH_SITE . '/images/stories/' . $section->image)) {
                File::copy(JPATH_SITE . '/images/stories/' . $section->image, JPATH_SITE . '/media/k2/categories/' . $K2Category->image);
            }
            $query = "SELECT * FROM #__categories WHERE section = " . (int)$section->id;
            $db->setQuery($query);
            $categories = $db->loadObjectList();

            foreach ($categories as $category) {
                $K2Subcategory = Table::getInstance('K2Category', 'Table');
                $K2Subcategory->name = $category->title;
                $K2Subcategory->alias = $category->title;
                $K2Subcategory->description = $category->description;
                $K2Subcategory->parent = $K2Category->id;
                $K2Subcategory->published = $category->published;
                $K2Subcategory->access = $category->access;
                $K2Subcategory->ordering = $category->ordering;
                $K2Subcategory->image = $category->image;
                $K2Subcategory->trash = 0;
                $K2Subcategory->params = $categoryParams;
                $K2Subcategory->check();
                $K2Subcategory->store();
                if (File::exists(JPATH_SITE . '/images/stories/' . $category->image)) {
                    File::copy(JPATH_SITE . '/images/stories/' . $category->image, JPATH_SITE . '/media/k2/categories/' . $K2Subcategory->image);
                }

                $query = "SELECT article.*, xref.content_id
                FROM #__content AS article
                LEFT JOIN #__content_frontpage AS xref ON article.id = xref.content_id
                WHERE catid = " . (int)$category->id;
                $db->setQuery($query);
                $items = $db->loadObjectList();

                foreach ($items as $item) {
                    $K2Item = Table::getInstance('K2Item', 'Table');
                    $K2Item->title = $item->title;
                    $K2Item->alias = $item->title;
                    $K2Item->catid = $K2Subcategory->id;
                    if ($item->state < 0) {
                        $K2Item->trash = 1;
                    } else {
                        $K2Item->trash = 0;
                        $K2Item->published = $item->state;
                    }
                    $K2Item->featured = ($item->content_id) ? 1 : 0;
                    $K2Item->introtext = $item->introtext;
                    $K2Item->fulltext = $item->fulltext;
                    $K2Item->created = $item->created;
                    $K2Item->created_by = $item->created_by;
                    $K2Item->created_by_alias = $item->created_by_alias;
                    $K2Item->modified = $item->modified;
                    $K2Item->modified_by = $item->modified_by;
                    $K2Item->publish_up = $item->publish_up;
                    $K2Item->publish_down = $item->publish_down;
                    $K2Item->access = $item->access;
                    $K2Item->ordering = $item->ordering;
                    $K2Item->hits = $item->hits;
                    $K2Item->metadesc = $item->metadesc;
                    $K2Item->metadata = $item->metadata;
                    $K2Item->metakey = $item->metakey;
                    $K2Item->params = $itemParams;
                    $K2Item->check();
                    if ($preserveItemIDs) {
                        $K2Item->id = $item->id;
                        $db->insertObject('#__k2_items', $K2Item);
                    } else {
                        $K2Item->store();
                    }

                    if (!empty($item->metakey)) {
                        $itemTags = explode(',', $item->metakey);
                        foreach ($itemTags as $itemTag) {
                            $itemTag = StringHelper::trim($itemTag);
                            if (in_array($itemTag, ArrayHelper::getColumn($tags, 'name'))) {
                                $query = "SELECT id FROM #__k2_tags WHERE name=" . $db->Quote($itemTag);
                                $db->setQuery($query);
                                $id = $db->loadResult();
                                $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {$id}, {$K2Item->id})";
                                $db->setQuery($query);
                                $db->execute();
                            } else {
                                $K2Tag = Table::getInstance('K2Tag', 'Table');
                                $K2Tag->name = $itemTag;
                                $K2Tag->published = 1;
                                $K2Tag->store();
                                $tags[] = $K2Tag;
                                $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {$K2Tag->id}, {$K2Item->id})";
                                $db->setQuery($query);
                                $db->execute();
                            }
                        }
                    }
                }
            }
        }

        // Handle uncategorized articles
        $query = "SELECT * FROM #__content WHERE sectionid = 0";
        $db->setQuery($query);
        $items = $db->loadObjectList();

        if ($items) {
            $K2Uncategorised = Table::getInstance('K2Category', 'Table');
            $K2Uncategorised->name = 'Uncategorized';
            $K2Uncategorised->alias = 'Uncategorized';
            $K2Uncategorised->parent = 0;
            $K2Uncategorised->published = 1;
            $K2Uncategorised->access = 0;
            $K2Uncategorised->ordering = 0;
            $K2Uncategorised->trash = 0;
            $K2Uncategorised->params = $categoryParams;
            $K2Uncategorised->check();
            $K2Uncategorised->store();

            foreach ($items as $item) {
                $K2Item = Table::getInstance('K2Item', 'Table');
                $K2Item->title = $item->title;
                $K2Item->alias = $item->title;
                $K2Item->catid = $K2Uncategorised->id;
                if ($item->state < 0) {
                    $K2Item->trash = 1;
                } else {
                    $K2Item->trash = 0;
                    $K2Item->published = $item->state;
                }
                $K2Item->introtext = $item->introtext;
                $K2Item->fulltext = $item->fulltext;
                $K2Item->created = $item->created;
                $K2Item->created_by = $item->created_by;
                $K2Item->created_by_alias = $item->created_by_alias;
                $K2Item->modified = $item->modified;
                $K2Item->modified_by = $item->modified_by;
                $K2Item->publish_up = $item->publish_up;
                $K2Item->publish_down = $item->publish_down;
                $K2Item->access = $item->access;
                $K2Item->ordering = $item->ordering;
                $K2Item->hits = $item->hits;
                $K2Item->metadesc = $item->metadesc;
                $K2Item->metadata = $item->metadata;
                $K2Item->metakey = $item->metakey;
                $K2Item->params = $itemParams;
                $K2Item->check();
                if ($preserveItemIDs) {
                    $K2Item->id = $item->id;
                    $db->insertObject('#__k2_items', $K2Item);
                } else {
                    $K2Item->store();
                }

                if (!empty($item->metakey)) {
                    $itemTags = explode(',', $item->metakey);
                    foreach ($itemTags as $itemTag) {
                        $itemTag = StringHelper::trim($itemTag);
                        if (in_array($itemTag, ArrayHelper::getColumn($tags, 'name'))) {
                            $query = "SELECT id FROM #__k2_tags WHERE name=" . $db->Quote($itemTag);
                            $db->setQuery($query);
                            $id = $db->loadResult();
                            $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {$id}, {$K2Item->id})";
                            $db->setQuery($query);
                            $db->execute();
                        } else {
                            $K2Tag = Table::getInstance('K2Tag', 'Table');
                            $K2Tag->name = $itemTag;
                            $K2Tag->published = 1;
                            $K2Tag->store();
                            $tags[] = $K2Tag;
                            $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {$K2Tag->id}, {$K2Item->id})";
                            $db->setQuery($query);
                            $db->execute();
                        }
                    }
                }
            }
        }
        $app->enqueueMessage(Text::_('K2_IMPORT_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=items');
    }

    public function importJ16()
    {
        jimport('joomla.filesystem.file');
        jimport('joomla.html.parameter');
        jimport('joomla.utilities.xmlelement');
        $app = Factory::getApplication();
        $db = Factory::getDbo();

        $query = "SELECT COUNT(*) FROM #__k2_categories";
        $db->setQuery($query);
        $result = $db->loadResult();
        if ($result) {
            $preserveCategoryIDs = false;
        } else {
            $preserveCategoryIDs = true;
        }

        $query = "SELECT COUNT(*) FROM #__k2_items";
        $db->setQuery($query);
        $result = $db->loadResult();
        if ($result) {
            $preserveItemIDs = false;
        } else {
            $preserveItemIDs = true;
        }
        $xml = new SimpleXMLElement(file_get_contents(JPATH_COMPONENT . '/models/category.xml'));
        $categoryParams = class_exists('JParameter') ? new JParameter('') : new Registry('');
        foreach ($xml->params as $paramGroup) {
            foreach ($paramGroup->param as $param) {
                if ((string)$param->attributes()->type != 'spacer' && (string)$param->attributes()->name) {
                    $categoryParams->set((string)$param->attributes()->name, (string)$param->attributes()->default);
                }
            }
        }
        $categoryParams = $categoryParams->toString();

        $xml = new SimpleXMLElement(file_get_contents(JPATH_COMPONENT . '/models/item.xml'));
        $itemParams = class_exists('JParameter') ? new JParameter('') : new Registry('');
        foreach ($xml->params as $paramGroup) {
            foreach ($paramGroup->param as $param) {
                if ((string)$param->attributes()->type != 'spacer' && (string)$param->attributes()->name) {
                    $itemParams->set((string)$param->attributes()->name, (string)$param->attributes()->default);
                }
            }
        }
        $itemParams = $itemParams->toString();

        $query = "SELECT id, name FROM #__k2_tags";
        $db->setQuery($query);
        $tags = $db->loadObjectList();

        if (is_null($tags)) {
            $tags = array();
        }

        $query = "SELECT * FROM #__categories WHERE extension = 'com_content'";
        $db->setQuery($query);
        $categories = $db->loadObjectList();
        $mapping = array();
        foreach ($categories as $category) {
            $category->params = json_decode($category->params);
            $category->image = $category->params->image;
            $K2Category = Table::getInstance('K2Category', 'Table');
            $K2Category->name = $category->title;
            $K2Category->alias = $category->title;
            $K2Category->description = $category->description;
            $K2Category->parent = $category->parent_id;
            if ($K2Category->parent == 1) {
                $K2Category->parent = 0;
            }
            $K2Category->published = $category->published;
            $K2Category->access = $category->access;
            $K2Category->ordering = $K2Category->getNextOrder('parent=' . (int)$category->parent_id);
            $K2Category->image = basename($category->image);
            $K2Category->trash = 0;
            $K2Category->language = $category->language;
            $K2Category->params = $categoryParams;
            $K2Category->check();
            if ($preserveCategoryIDs) {
                $K2Category->id = $category->id;
                $db->insertObject('#__k2_categories', $K2Category);
            } else {
                $K2Category->store();
                $mapping[$category->id] = $K2Category->id;
            }

            if ($K2Category->image && File::exists(realpath(JPATH_SITE . '/' . $category->image))) {
                File::copy(realpath(JPATH_SITE . '/' . $category->image), JPATH_SITE . '/media/k2/categories/' . $K2Category->image);
            }
            $query = "SELECT article.*, xref.content_id
                FROM #__content AS article
                LEFT JOIN #__content_frontpage AS xref ON article.id = xref.content_id
                WHERE catid = " . (int)$category->id;
            $db->setQuery($query);
            $items = $db->loadObjectList();

            foreach ($items as $item) {
                $K2Item = Table::getInstance('K2Item', 'Table');
                $K2Item->title = $item->title;
                $K2Item->alias = $item->title;
                $K2Item->catid = $K2Category->id;
                if ($item->state < 0) {
                    $K2Item->trash = 1;
                } else {
                    $K2Item->trash = 0;
                }
                $K2Item->published = 1;
                if ($item->state == 0) {
                    $K2Item->published = 0;
                }
                $K2Item->featured = ($item->content_id) ? 1 : 0;
                $K2Item->introtext = $item->introtext;
                $K2Item->fulltext = $item->fulltext;
                $K2Item->created = $item->created;
                $K2Item->created_by = $item->created_by;
                $K2Item->created_by_alias = $item->created_by_alias;
                $K2Item->modified = $item->modified;
                $K2Item->modified_by = $item->modified_by;
                $K2Item->publish_up = $item->publish_up;
                $K2Item->publish_down = $item->publish_down;
                $K2Item->access = $item->access;
                $K2Item->ordering = $item->ordering;
                $K2Item->hits = $item->hits;
                $K2Item->metadesc = $item->metadesc;
                $K2Item->metadata = $item->metadata;
                $K2Item->metakey = $item->metakey;
                $K2Item->params = $itemParams;
                $K2Item->language = $item->language;
                $K2Item->check();

                if ($preserveItemIDs) {
                    $K2Item->id = $item->id;
                    $db->insertObject('#__k2_items', $K2Item);
                } else {
                    $K2Item->store();
                }

                $item->tags = array();
                if (class_exists('JHelperTags')) {
                    $tagsHelper = new JHelperTags;
                    $tagsHelper->getItemTags('com_content.article', $item->id);
                    $tags = $tagsHelper->itemTags;
                    foreach ($tags as $tag) {
                        $item->tags[] = $tag->title;
                    }
                }

                if (!empty($item->metakey) || count($item->tags)) {
                    $itemTags = array_merge(explode(',', $item->metakey), $item->tags);
                    $itemTags = array_filter($itemTags);
                    $itemTags = array_unique($itemTags);
                    foreach ($itemTags as $itemTag) {
                        $itemTag = StringHelper::trim($itemTag);
                        if ($itemTag) {

                            // Check if the tag exists already, otherwise insert it as a K2 tag
                            $query = "SELECT id FROM #__k2_tags WHERE name=" . $db->Quote($itemTag);
                            $db->setQuery($query);
                            $id = $db->loadResult();
                            if ($id) {
                                $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {$id}, {$K2Item->id})";
                            } else {
                                $K2Tag = Table::getInstance('K2Tag', 'Table');
                                $K2Tag->name = $itemTag;
                                $K2Tag->published = 1;
                                $K2Tag->store();
                                $tags[] = $K2Tag;
                                $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {$K2Tag->id}, {$K2Item->id})";
                            }
                            $db->setQuery($query);
                            $db->execute();

                            /*
                            // OLD
                            if (in_array($itemTag, ArrayHelper::getColumn($tags, 'name'))) {
                                $query = "SELECT id FROM #__k2_tags WHERE name=".$db->Quote($itemTag);
                                $db->setQuery($query);
                                $id = $db->loadResult();
                                if ($id) {
                                    $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {$id}, {$K2Item->id})";
                                    $db->setQuery($query);
                                    $db->execute();
                                }
                            } else {
                                $K2Tag = Table::getInstance('K2Tag', 'Table');
                                $K2Tag->name = $itemTag;
                                $K2Tag->published = 1;
                                $K2Tag->store();
                                $tags[] = $K2Tag;
                                $query = "INSERT INTO #__k2_tags_xref (`id`, `tagID`, `itemID`) VALUES (NULL, {$K2Tag->id}, {$K2Item->id})";
                                $db->setQuery($query);
                                $db->execute();
                            }
                            */
                        }
                    }
                }
            }
        }

        foreach ($mapping as $oldID => $newID) {
            $query = "UPDATE #__k2_categories SET parent=" . $newID . " WHERE parent=" . $oldID;
            $db->setQuery($query);
            $db->execute();
        }
        $app->enqueueMessage(Text::_('K2_IMPORT_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=items');
    }

    public function getItemsAuthors()
    {
        $db = $this->getDBO();
        $query = "SELECT id, name, block
            FROM #__users
            WHERE id IN (SELECT DISTINCT created_by FROM #__k2_items)
            ORDER BY name";
        /*
        $query = "SELECT u.id, u.name, u.block
            FROM #__users as u
            RIGHT JOIN #__k2_items as i on u.id = i.created_by
            GROUP BY u.id
            ORDER BY u.name";
        */
        $db->setQuery($query);
        $rows = $db->loadObjectList();

        return $rows;
    }

    public function saveBatch()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        $batchMode = Factory::getApplication()->input->getCmd('batchMode');
        $catid = Factory::getApplication()->input->getInt('batchCategory');
        $access = Factory::getApplication()->input->getCmd('batchAccess');
        $author = Factory::getApplication()->input->getInt('batchAuthor');
        $language = Factory::getApplication()->input->getVar('batchLanguage');
        if ($batchMode == 'clone') {
            $cid = $this->copy(true);
        }
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Item', 'Table');
            $row->load($id);
            if ($catid) {
                $row->catid = $catid;
                $row->ordering = $row->getNextOrder('catid = ' . (int)$row->catid . ' AND published = 1');
            }
            if ($access) {
                $row->access = $access;
            }
            if ($author) {
                $row->created_by = $author;
                $row->created_by_alias = '';
            }
            if ($language) {
                $row->language = $language;
            }
            $row->store();
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');

        $app->enqueueMessage(Text::_('K2_BATCH_COMPLETED'));
        $app->redirect('index.php?option=com_k2&view=items');
    }
}
