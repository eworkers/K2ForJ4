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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.model');

Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/tables');

class K2ModelItemlist extends K2Model
{
    private $getTotal;

    public function getData($ordering = null)
    {
        $user = Factory::getUser();
        $aid = $user->get('aid');
        $db = Factory::getDbo();
        $task = Factory::getApplication()->input->getCmd('task');
        $limitstart = Factory::getApplication()->input->getInt('limitstart', 0);
        $limit = Factory::getApplication()->input->getInt('limit', 10);
        $config = Factory::getConfig();

        $params = K2HelperUtilities::getParams('com_k2');

        if ($task == 'search') {
            $params->set('googleSearch', 0);
        }

        // For Falang
        $falang_driver = PluginHelper::getPlugin('system', 'falangdriver');

        $jnow = Factory::getDate();
        $now = $jnow->toSql();

        $nullDate = $db->getNullDate();

        $query = "/* Frontend / K2 / Items */ SELECT SQL_CALC_FOUND_ROWS i.*,";

        if ($ordering == 'modified') {
            $query .= " CASE WHEN i.modified = 0 THEN i.created ELSE i.modified END AS lastChanged,";
        }

        $query .= " c.name AS categoryname, c.id AS categoryid, c.alias AS categoryalias, c.params AS categoryparams";

        if ($ordering == 'best') {
            $query .= ", (r.rating_sum/r.rating_count) AS rating";
        }

        $query .= " FROM #__k2_items AS i";

        // Enforce certain INDEX when filtering by dates
        if ($ordering == 'date' || $ordering == 'rdate') {
            $query .= " USE INDEX (idx_item)";
        }

        $query .= " INNER JOIN #__k2_categories AS c ON c.id = i.catid";

        if ($ordering == 'best') {
            $query .= " LEFT JOIN #__k2_rating AS r ON r.itemID = i.id";
        }

        /*
        // Changed the query for the tag case for better performance
        if ($task == 'tag') {
            $query .= " LEFT JOIN #__k2_tags_xref AS tags_xref ON tags_xref.itemID = i.id LEFT JOIN #__k2_tags AS tags ON tags.id = tags_xref.tagID";
        }
        */

        if ($task == 'user' && !$user->guest && $user->id == Factory::getApplication()->input->getInt('id')) {
            $query .= " WHERE";
        } else {
            $query .= " WHERE i.published = 1 AND";
        }

        $userACL = array_unique($user->getAuthorisedViewLevels());
        $query .= " i.access IN(" . implode(',', $userACL) . ") AND i.trash = 0 AND c.published = 1 AND c.access IN(" . implode(',', $userACL) . ") AND c.trash = 0";

        $app = Factory::getApplication();
        $languageFilter = $app->getLanguageFilter();
        if ($languageFilter) {
            $languageTag = Factory::getLanguage()->getTag();
            $query .= " AND c.language IN(" . $db->quote($languageTag) . ", " . $db->quote('*') . ") AND i.language IN(" . $db->quote($languageTag) . ", " . $db->quote('*') . ")";
        }

        if (!($task == 'user' && !$user->guest && $user->id == Factory::getApplication()->input->getInt('id'))) {
            $query .= " AND (i.publish_up = " . $db->Quote($nullDate) . " OR i.publish_up <= " . $db->Quote($now) . ")";
            $query .= " AND (i.publish_down = " . $db->Quote($nullDate) . " OR i.publish_down >= " . $db->Quote($now) . ")";
            /*
            $query .= " AND (i.publish_up IS NULL OR i.publish_up <= NOW()) AND (i.publish_down IS NULL OR i.publish_down >= NOW())";
            */
        }

        // Build query depending on task
        switch ($task) {
            case 'category':
                $id = Factory::getApplication()->input->getInt('id');

                $category = Table::getInstance('K2Category', 'Table');
                $category->load($id);
                $cparams = class_exists('JParameter') ? new JParameter($category->params) : new Registry($category->params);

                if ($cparams->get('inheritFrom')) {
                    $parent = Table::getInstance('K2Category', 'Table');
                    $parent->load($cparams->get('inheritFrom'));
                    $cparams = class_exists('JParameter') ? new JParameter($parent->params) : new Registry($parent->params);
                }

                if ($cparams->get('catCatalogMode')) {
                    $query .= " AND c.id={$id} ";
                } else {
                    $categories = $this->getCategoryTree($id);
                    sort($categories);
                    $sql = @implode(',', $categories);
                    $query .= " AND c.id IN({$sql})";
                }

                break;

            case 'user':
                $id = Factory::getApplication()->input->getInt('id');
                $query .= " AND i.created_by={$id} AND i.created_by_alias=''";
                $categories = $params->get('userCategoriesFilter', null);
                if (is_array($categories)) {
                    if (count($categories)) {
                        sort($categories);
                        $query .= " AND c.id IN(" . implode(',', $categories) . ")";
                    }
                }
                if (is_string($categories) && $categories > 0) {
                    $query .= " AND c.id = {$categories}";
                }
                break;

            case 'search':
                $badchars = array(
                    '#',
                    '>',
                    '<',
                    '\\'
                );
                $search = StringHelper::trim(StringHelper::str_ireplace($badchars, '', Factory::getApplication()->input->getString('searchword', null)));
                $sql = $this->prepareSearch($search);
                if (!empty($sql)) {
                    $query .= $sql;
                } else {
                    $rows = array();
                    return $rows;
                }
                break;

            case 'date':
                if ((Factory::getApplication()->input->getInt('month')) && (Factory::getApplication()->input->getInt('year'))) {
                    $month = Factory::getApplication()->input->getInt('month');
                    $year = Factory::getApplication()->input->getInt('year');
                    $query .= " AND MONTH(i.created) = {$month} AND YEAR(i.created)={$year}";
                    if (Factory::getApplication()->input->getInt('day')) {
                        $day = Factory::getApplication()->input->getInt('day');
                        $query .= " AND DAY(i.created) = {$day}";
                    }

                    if (Factory::getApplication()->input->getInt('catid')) {
                        $catid = Factory::getApplication()->input->getInt('catid');
                        $query .= " AND c.id={$catid}";
                    }
                }
                break;

            case 'tag':
                $tag = Factory::getApplication()->input->getString('tag');

                jimport('joomla.filesystem.file');

                if (File::exists(JPATH_ADMINISTRATOR . '/components/com_falang/falang.php') && $task == 'tag') {
                    $lang = $config->get('jflang');

                    $sql = "SELECT reference_id
                        FROM #__falang_content AS fc
                        LEFT JOIN #__languages AS fl ON fc.language_id = fl.lang_id
                        WHERE fc.value = " . $db->Quote($tag) . "
                            AND fc.reference_table = 'k2_tags'
                            AND fc.reference_field = 'name'
                            AND fc.published=1";
                    $db->setQuery($sql, 0, 1);
                    $result = $db->loadResult();
                }

                if (!isset($result) || $result < 1) {
                    $sql = "SELECT id FROM #__k2_tags WHERE name=" . $db->Quote($tag);
                    $db->setQuery($sql, 0, 1);
                    $result = $db->loadResult();
                }

                $query .= " AND i.id IN(SELECT itemID FROM #__k2_tags_xref WHERE tagID=" . (int)$result . ")";

                /*
                if (isset($result) && $result > 0) {
                    $query .= " AND (tags.id) = {$result}";
                } else {
                    $query .= " AND (tags.name) = ".$db->Quote($tag);
                }
                */

                $categories = $params->get('categoriesFilter', null);
                if (is_array($categories)) {
                    sort($categories);
                    $query .= " AND c.id IN(" . implode(',', $categories) . ")";
                }
                if (is_string($categories)) {
                    $query .= " AND c.id = {$categories}";
                }
                break;

            default:
                $searchIDs = $params->get('categories');
                if (is_array($searchIDs) && count($searchIDs)) {
                    if ($params->get('catCatalogMode')) {
                        sort($searchIDs);
                        $sql = @implode(',', $searchIDs);
                        $query .= " AND c.id IN({$sql})";
                    } else {
                        $result = $this->getCategoryTree($searchIDs);
                        if (count($result)) {
                            sort($result);
                            $sql = @implode(',', $result);
                            $query .= " AND c.id IN({$sql})";
                        }
                    }
                }
                break;
        }

        // Set featured flag
        if ($task == 'category' || empty($task)) {
            if (Factory::getApplication()->input->getInt('featured') == '0') {
                $query .= " AND i.featured != 1";
            } elseif (Factory::getApplication()->input->getInt('featured') == '2') {
                $query .= " AND i.featured = 1";
            }
        }

        // Set ordering
        switch ($ordering) {
            case 'date':
                $orderby = 'i.created ASC';
                break;

            case 'rdate':
                $orderby = 'i.created DESC';
                break;

            case 'alpha':
                $orderby = 'i.title';
                break;

            case 'ralpha':
                $orderby = 'i.title DESC';
                break;

            case 'order':
                if (Factory::getApplication()->input->getInt('featured') == '2') {
                    $orderby = 'i.featured_ordering';
                } else {
                    $orderby = 'c.ordering, i.ordering';
                }
                break;

            case 'rorder':
                if (Factory::getApplication()->input->getInt('featured') == '2') {
                    $orderby = 'i.featured_ordering DESC';
                } else {
                    $orderby = 'c.ordering DESC, i.ordering DESC';
                }
                break;

            case 'featured':
                $orderby = 'i.featured DESC, i.created DESC';
                break;

            case 'hits':
                $orderby = 'i.hits DESC';
                break;

            case 'rand':
                $orderby = 'RAND()';
                break;

            case 'best':
                $orderby = 'rating DESC';
                break;

            case 'modified':
                $orderby = 'lastChanged DESC';
                break;

            case 'publishUp':
                $orderby = 'i.publish_up DESC';
                break;

            case 'id':
            default:
                $orderby = 'i.id DESC';
                break;
        }

        if ($task == 'tag') {
            $query .= ' GROUP BY i.id';
        }

        $query .= ' ORDER BY ' . $orderby;

        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onK2BeforeSetQuery', array(&$query));

        $db->setQuery($query, $limitstart, $limit);
        $rows = $db->loadObjectList();

        if (count($rows)) {
            // For Falang
            if (!empty($falang_driver)) {
                $db->setQuery($query, $limitstart, $limit);
                $db->loadResult(false);
                $db->setQuery('SELECT FOUND_ROWS();');
                $this->getTotal = $db->loadResult(false);
                return $rows;
            }

            $db->setQuery('SELECT FOUND_ROWS();');
            $this->getTotal = $db->loadResult();
        }

        return $rows;
    }

    public function getTotal()
    {
        return $this->getTotal;
    }

    public function getCategoryTree($categories, $associations = false)
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $aid = (int)$user->get('aid');
        if (!is_array($categories)) {
            $categories = (array)$categories;
        }
        ArrayHelper::toInteger($categories);
        $categories = array_unique($categories);
        sort($categories);
        $key = implode('|', $categories);
        $clientID = $app->getClientId();
        static $K2CategoryTreeInstances = array();
        if (isset($K2CategoryTreeInstances[$clientID]) && array_key_exists($key, $K2CategoryTreeInstances[$clientID])) {
            return $K2CategoryTreeInstances[$clientID][$key];
        }
        $array = $categories;
        while (count($array)) {
            $query = "SELECT id
                        FROM #__k2_categories
                        WHERE parent IN(" . implode(',', $array) . ")
                            AND id NOT IN(" . implode(',', $array) . ")";
            if ($app->isClient('site')) {
                $query .= " AND published=1 AND trash=0";
                $query .= " AND access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ")";
                if ($app->getLanguageFilter()) {
                    $query .= " AND language IN(" . $db->Quote(Factory::getLanguage()->getTag()) . ", " . $db->Quote('*') . ")";
                }
            }
            $db->setQuery($query);
            $array = $db->loadColumn();
            $categories = array_merge($categories, $array);
        }
        ArrayHelper::toInteger($categories);
        $categories = array_unique($categories);
        $K2CategoryTreeInstances[$clientID][$key] = $categories;
        return $categories;
    }

    public function getCategoryFirstChildren($catid, $ordering = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $aid = $user->get('aid');
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_categories WHERE parent={$catid} AND published=1 AND trash=0";

        $query .= " AND access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ") ";
        if ($app->getLanguageFilter()) {
            $query .= " AND language IN(" . $db->Quote(Factory::getLanguage()->getTag()) . ", " . $db->Quote('*') . ")";
        }

        switch ($ordering) {

            case 'order':
                $order = " ordering ASC";
                break;

            case 'alpha':
                $order = " name ASC";
                break;

            case 'ralpha':
                $order = " name DESC";
                break;

            case 'reversedefault':
                $order = " id DESC";
                break;

            default:
                $order = " id ASC";
                break;
        }

        $query .= " ORDER BY {$order}";

        $db->setQuery($query);
        /* since J4 compatibility */
        try {
            $rows = $db->loadObjectList();
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
            return false;
        }
        return $rows;
    }

    public function countCategoryItems($id)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $aid = (int)$user->get('aid');
        $id = (int)$id;
        $db = Factory::getDbo();

        $jnow = Factory::getDate();
        $now = $jnow->toSql();
        $nullDate = $db->getNullDate();

        $categories = $this->getCategoryTree($id);
        $query = "SELECT COUNT(*) FROM #__k2_items WHERE catid IN(" . implode(',', $categories) . ") AND published=1 AND trash=0";

        $query .= " AND access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ")";
        if ($app->getLanguageFilter()) {
            $query .= " AND language IN(" . $db->Quote(Factory::getLanguage()->getTag()) . ", " . $db->Quote('*') . ")";
        }

        $query .= " AND (publish_up = " . $db->Quote($nullDate) . " OR publish_up <= " . $db->Quote($now) . ") AND (publish_down = " . $db->Quote($nullDate) . " OR publish_down >= " . $db->Quote($now) . ")";
        $db->setQuery($query);
        $total = $db->loadResult();
        return $total;
    }

    public function getUserProfile($id = null)
    {
        $db = Factory::getDbo();
        if (is_null($id)) {
            $id = Factory::getApplication()->input->getInt('id');
        } else {
            $id = (int)$id;
        }
        $query = "SELECT id, gender, description, image, url, `group`, plugins FROM #__k2_users WHERE userID={$id}";
        $db->setQuery($query);
        $row = $db->loadObject();
        return $row;
    }

    public function getAuthorLatest($itemID, $limit, $userID)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $aid = (int)$user->get('aid');
        $itemID = (int)$itemID;
        $userID = (int)$userID;
        $limit = (int)$limit;
        $db = Factory::getDbo();

        $params = K2HelperUtilities::getParams('com_k2');

        $jnow = Factory::getDate();
        $now = $jnow->toSql();
        $nullDate = $db->getNullDate();

        $query = "SELECT i.*, c.alias AS categoryalias
            FROM #__k2_items AS i
            LEFT JOIN #__k2_categories c ON c.id = i.catid
            WHERE i.id != {$itemID}
                AND i.published = 1
                AND (i.publish_up = " . $db->Quote($nullDate) . " OR i.publish_up <= " . $db->Quote($now) . ")
                AND (i.publish_down = " . $db->Quote($nullDate) . " OR i.publish_down >= " . $db->Quote($now) . ")";

        $query .= " AND i.access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ")";
        if ($app->getLanguageFilter()) {
            $query .= " AND i.language IN(" . $db->Quote(Factory::getLanguage()->getTag()) . ", " . $db->Quote('*') . ")";
        }

        $query .= " AND i.trash = 0
            AND i.created_by = {$userID}
            AND i.created_by_alias=''
            AND c.published = 1";

        $query .= " AND c.access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ")";
        if ($app->getLanguageFilter()) {
            $query .= " AND c.language IN(" . $db->Quote(Factory::getLanguage()->getTag()) . ", " . $db->Quote('*') . ")";
        }

        $query .= " AND c.trash = 0
            ORDER BY i.created DESC";

        $db->setQuery($query, 0, $limit);
        $rows = $db->loadObjectList();

        foreach ($rows as $item) {
            // Image
            $item->imageXSmall = '';
            $item->imageSmall = '';
            $item->imageMedium = '';
            $item->imageLarge = '';
            $item->imageXLarge = '';

            $imageTimestamp = '';
            $dateModified = ((int)$item->modified) ? $item->modified : '';
            if ($params->get('imageTimestamp', 1) && $dateModified) {
	            $dateTimeObj = new DateTime($dateModified);
	            $imageTimestamp = '?t=' . IntlDateFormatter::formatObject($dateTimeObj,'YMMdd_hhmmss');
            }

            $imageFilenamePrefix = md5("Image" . $item->id);
            $imagePathPrefix = Uri::base(true) . '/media/k2/items/cache/' . $imageFilenamePrefix;

            // Check if the "generic" variant exists
            if (File::exists(JPATH_SITE . '/media/k2/items/cache/' . $imageFilenamePrefix . '_Generic.jpg')) {
                $item->imageGeneric = $imagePathPrefix . '_Generic.jpg' . $imageTimestamp;
                $item->imageXSmall = $imagePathPrefix . '_XS.jpg' . $imageTimestamp;
                $item->imageSmall = $imagePathPrefix . '_S.jpg' . $imageTimestamp;
                $item->imageMedium = $imagePathPrefix . '_M.jpg' . $imageTimestamp;
                $item->imageLarge = $imagePathPrefix . '_L.jpg' . $imageTimestamp;
                $item->imageXLarge = $imagePathPrefix . '_XL.jpg' . $imageTimestamp;

                $item->imageProperties = new stdClass;
                $item->imageProperties->filenamePrefix = $imageFilenamePrefix;
                $item->imageProperties->pathPrefix = $imagePathPrefix;
            }
        }
        return $rows;
    }

    public function getRelatedItems($itemID, $tags, $params)
    {
        $app = Factory::getApplication();
        $limit = $params->get('itemRelatedLimit', 10);
        $itemID = (int)$itemID;

        foreach ($tags as $tag) {
            $tagIDs[] = $tag->id;
        }
        ArrayHelper::toInteger($tagIDs);
        sort($tagIDs);
        $sql = implode(',', $tagIDs);

        $user = Factory::getUser();
        $aid = (int)$user->get('aid');
        $db = Factory::getDbo();

        $jnow = Factory::getDate();
        $now = $jnow->toSql();
        $nullDate = $db->getNullDate();

        $query = "SELECT itemID
            FROM #__k2_tags_xref
            WHERE tagID IN ({$sql})
                AND itemID != {$itemID}
            GROUP BY itemID";
        $db->setQuery($query);

        $itemsIDs = $db->loadColumn();

        if (!count($itemsIDs)) {
            return array();
        }
        sort($itemsIDs);
        $sql = implode(',', $itemsIDs);

        $query = "SELECT i.*, c.alias AS categoryalias
            FROM #__k2_items AS i
            LEFT JOIN #__k2_categories AS c ON c.id = i.catid
            WHERE i.published = 1
                AND i.trash = 0
                AND (i.publish_up = " . $db->Quote($nullDate) . " OR i.publish_up <= " . $db->Quote($now) . ")
                AND (i.publish_down = " . $db->Quote($nullDate) . " OR i.publish_down >= " . $db->Quote($now) . ")";

        $query .= " AND i.access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ")";
        if ($app->getLanguageFilter()) {
            $query .= " AND i.language IN(" . $db->Quote(Factory::getLanguage()->getTag()) . ", " . $db->Quote('*') . ")";
        }

        $query .= " AND c.access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ")";
        if ($app->getLanguageFilter()) {
            $query .= " AND c.language IN(" . $db->Quote(Factory::getLanguage()->getTag()) . ", " . $db->Quote('*') . ")";
        }

        $query .= " AND c.published = 1 AND c.trash = 0 AND i.id IN({$sql}) ORDER BY i.id DESC";

        $db->setQuery($query, 0, $limit);
        $rows = $db->loadObjectList();
        K2Model::addIncludePath(JPATH_COMPONENT . '/models');
        $model = K2Model::getInstance('Item', 'K2Model');
        for ($key = 0, $keyTotal = count($rows); $key < $keyTotal; $key++) {
            if (!$params->get('itemRelatedMedia')) {
                $rows[$key]->video = null;
            }
            if (!$params->get('itemRelatedImageGallery')) {
                $rows[$key]->gallery = null;
            }
            $rows[$key] = $model->prepareItem($rows[$key], 'relatedByTag', '');
            $rows[$key] = $model->execPlugins($rows[$key], 'relatedByTag', '');
            K2HelperUtilities::setDefaultImage($rows[$key], 'relatedByTag', $params);
        }
        return $rows;
    }

    public function prepareSearch($search)
    {
        jimport('joomla.filesystem.file');
        $db = Factory::getDbo();
        $language = Factory::getLanguage();
        $defaultLang = $language->getDefault();
        $currentLang = $language->getTag();

        $search = trim($search);
        $length = StringHelper::strlen($search);

        $sql = '';

        if (Factory::getApplication()->input->getVar('categories')) {
            $categories = @explode(',', Factory::getApplication()->input->getVar('categories'));
            ArrayHelper::toInteger($categories);
            sort($categories);
            $sql .= " AND c.id IN(" . @implode(',', $categories) . ")";
        }

        if (empty($search)) {
            return $sql;
        }

        if (StringHelper::substr($search, 0, 1) == '"' && StringHelper::substr($search, $length - 1, 1) == '"') {
            $type = 'exact';
        } else {
            $type = 'any';
        }

        if ($type == 'exact') {
            $search = StringHelper::trim($search, '"');

            $escaped = $db->escape($search, true);
            $quoted = $db->Quote('%' . $escaped . '%', false);

            $sql .= " AND (
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
            $search = strtolower(trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $search)));

            $searchwords = explode(' ', $search);
            if (count($searchwords)) {
            } else {
                $searchwords = [$search];
            }

            foreach ($searchwords as $searchword) {
                if (strlen($searchword) > 2) {
                    $escaped = $db->escape($searchword, true);
                    $quoted = $db->Quote('%' . $escaped . '%', false);

                    $sql .= " AND (
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
                }
            }
        }

        return $sql;
    }

    public function getModuleItems($moduleID)
    {
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__modules WHERE id={$moduleID} AND published=1 AND client_id=0";
        $db->setQuery($query, 0, 1);
        $module = $db->loadObject();
        $format = Factory::getApplication()->input->getWord('format');
        if (is_null($module)) {
            throw new \Exception(Text::_('K2_NOT_FOUND'), 404);
        } else {
            $params = class_exists('JParameter') ? new JParameter($module->params) : new Registry($module->params);
            switch ($module->module) {

                case 'mod_k2_content':
                    require_once(JPATH_SITE . '/modules/mod_k2_content/helper.php');
                    $helper = new modK2ContentHelper;
                    $items = $helper->getItems($params, $format);
                    break;

                case 'mod_k2_comments':
                    if ($params->get('module_usage') == 1) {
                        throw new \Exception(Text::_('K2_NOT_FOUND'), 404);
                    }

                    require_once(JPATH_SITE . '/modules/mod_k2_comments/helper.php');
                    $helper = new modK2CommentsHelper;
                    $items = $helper->getLatestComments($params);

                    foreach ($items as $item) {
                        $item->title = $item->userName . ' ' . Text::_('K2_COMMENTED_ON') . ' ' . $item->title;
                        $item->introtext = $item->commentText;
                        $item->created = $item->commentDate;
                        $item->id = $item->itemID;
                    }
                    break;

                default:
                    throw new \Exception(Text::_('K2_NOT_FOUND'), 404);
            }

            $result = new stdClass;
            $result->items = $items;
            $result->title = $module->title;
            $result->module = $module->module;
            $result->params = $module->params;
            return $result;
        }
    }

    public function getCategoriesTree()
    {
        $app = Factory::getApplication();
        $clientID = $app->getClientId();
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $aid = (int)$user->get('aid');

        $query = "SELECT id, name, parent FROM #__k2_categories";
        if ($app->isClient('site')) {
            $query .= " WHERE published=1 AND trash=0";
            $query .= " AND access IN(" . implode(',', $user->getAuthorisedViewLevels()) . ")";
            if ($app->getLanguageFilter()) {
                $query .= " AND language IN(" . $db->Quote(Factory::getLanguage()->getTag()) . ", " . $db->Quote('*') . ")";
            }
        }
        $query .= " ORDER BY parent";
        $db->setQuery($query);

        $categories = $db->loadObjectList();
        $tree = array();
        return $this->buildTree($categories);
    }

    public function buildTree(array &$categories, $parent = 0)
    {
        $branch = array();
        foreach ($categories as &$category) {
            if ($category->parent == $parent) {
                $children = $this->buildTree($categories, $category->id);
                if ($children) {
                    $category->children = $children;
                }
                $branch[$category->id] = $category;
            }
        }
        return $branch;
    }

    public function getTreePath($tree, $id)
    {
        if (array_key_exists($id, $tree)) {
            return array($id);
        } else {
            foreach ($tree as $key => $root) {
                if (isset($root->children) && is_array($root->children)) {
                    $retry = $this->getTreePath($root->children, $id);

                    if ($retry) {
                        $retry[] = $key;
                        return $retry;
                    }
                }
            }
        }
        return null;
    }

    // Deprecated function, left for compatibility reasons
    public function getCategoryChildren($catid, $clear = false)
    {
        static $array = array();
        if ($clear) {
            $array = array();
        }
        $user = Factory::getUser();
        $aid = (int)$user->get('aid');
        $catid = (int)$catid;
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_categories WHERE parent={$catid} AND published=1 AND trash=0 AND access<={$aid} ORDER BY ordering";
        $db->setQuery($query);
        $rows = $db->loadObjectList();

        foreach ($rows as $row) {
            array_push($array, $row->id);
            if ($this->hasChildren($row->id)) {
                $this->getCategoryChildren($row->id);
            }
        }
        return $array;
    }

    // Deprecated function, left for compatibility reasons
    public function hasChildren($id)
    {
        $user = Factory::getUser();
        $aid = (int)$user->get('aid');
        $id = (int)$id;
        $db = Factory::getDbo();
        $query = "SELECT * FROM #__k2_categories WHERE parent={$id} AND published=1 AND trash=0 AND access<={$aid} ";
        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (count($rows)) {
            return true;
        } else {
            return false;
        }
    }
}
