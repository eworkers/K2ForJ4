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
use Joomla\CMS\Date\Date;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;

require_once(JPATH_SITE . '/components/com_k2/helpers/route.php');
require_once(JPATH_SITE . '/components/com_k2/helpers/utilities.php');

class modK2CommentsHelper
{
    public static function getLatestComments(&$params)
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $config = Factory::getConfig();

        // Time used for DB queries
        $jnow = Factory::getDate();
        $now = $jnow->toSql();
        $nullDate = $db->getNullDate();

        // Time used for comment rendering
        $isNow = new Date();
        $tzoffset = new DateTimeZone($app->getCfg('offset'));
        $isNow->setTimezone($tzoffset);

        $componentParams = ComponentHelper::getParams('com_k2');

        $limit = $params->get('comments_limit', '5');
        $cid = $params->get('category_id', null);

        // Get ACL
        $user = Factory::getUser();
        $userLevels = array_unique($user->getAuthorisedViewLevels());
        $aclCheck = 'IN(' . implode(',', $userLevels) . ')';

        // Get language on Joomla 2.5+
        $languageFilter = '';
        if ($app->getLanguageFilter()) {
            $languageTag = Factory::getLanguage()->getTag();
            $languageFilter = $db->Quote($languageTag) . ", " . $db->Quote('*');
        }

        $query = "SELECT c.*, i.catid, i.title, i.alias, category.alias AS catalias, category.name AS categoryname
            FROM #__k2_comments AS c
            LEFT JOIN #__k2_items AS i ON i.id = c.itemID
            LEFT JOIN #__k2_categories AS category ON category.id = i.catid
            WHERE i.published = 1
                AND (i.publish_up = " . $db->Quote($nullDate) . " OR i.publish_up <= " . $db->Quote($now) . ")
                AND (i.publish_down = " . $db->Quote($nullDate) . " OR i.publish_down >= " . $db->Quote($now) . ")
                AND i.trash = 0
                AND i.access {$aclCheck}
                AND category.published = 1
                AND category.trash = 0
                AND category.access {$aclCheck}
                AND c.published = 1";

        if ($params->get('catfilter') && !is_null($cid)) {
            if (is_array($cid)) {
                $query .= " AND i.catid IN(" . implode(',', $cid) . ")";
            } else {
                $query .= " AND i.catid = " . (int)$cid;
            }
        }

        if ($languageFilter) {
            $query .= " AND i.language IN ({$languageFilter}) AND category.language IN ({$languageFilter})";
        }

        $query .= " ORDER BY c.id DESC";

        $db->setQuery($query, 0, $limit);
        $rows = $db->loadObjectList();

        $pattern = "@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@";

        $comments = array();

        if (count($rows)) {
            foreach ($rows as $row) {

                // Relative comment date
                if ($params->get('commentDateFormat') == 'relative') {
                    $created = new Date($row->commentDate);
                    $diff = $isNow->toUnix() - $created->toUnix();
                    $dayDiff = floor($diff / 86400);

                    if ($dayDiff == 0) {
                        if ($diff < 5) {
                            $row->commentDate = Text::_('K2_JUST_NOW');
                        } elseif ($diff < 60) {
                            $row->commentDate = $diff . ' ' . Text::_('K2_SECONDS_AGO');
                        } elseif ($diff < 120) {
                            $row->commentDate = Text::_('K2_1_MINUTE_AGO');
                        } elseif ($diff < 3600) {
                            $row->commentDate = floor($diff / 60) . ' ' . Text::_('K2_MINUTES_AGO');
                        } elseif ($diff < 7200) {
                            $row->commentDate = Text::_('K2_1_HOUR_AGO');
                        } elseif ($diff < 86400) {
                            $row->commentDate = floor($diff / 3600) . ' ' . Text::_('K2_HOURS_AGO');
                        }
                    }
                }

                // Comment text
                $row->commentText = K2HelperUtilities::wordLimit($row->commentText, $params->get('comments_word_limit'));
                $row->commentText = preg_replace($pattern, '<a target="_blank" rel="nofollow" href="\0">\0</a>', $row->commentText);

                // Comment anchor link
                $row->itemLink = urldecode(Route::_(K2HelperRoute::getItemRoute($row->itemID . ':' . urlencode($row->alias), $row->catid . ':' . urlencode($row->catalias))));
                $row->link = $row->itemLink . "#comment{$row->id}";

                // Categoty link
                $row->catLink = urldecode(Route::_(K2HelperRoute::getCategoryRoute($row->catid . ':' . urlencode($row->catalias))));

                // User
                if ($row->userID > 0) {
                    $row->userLink = Route::_(K2HelperRoute::getUserRoute($row->userID));
                    $getExistingUser = Factory::getUser($row->userID);
                    $row->userUsername = $getExistingUser->username;
                } else {
                    $row->userUsername = $row->userName;
                }

                // Switch between commenter name and username
                if ($params->get('commenterName', 1) == 2) {
                    $row->userName = $row->userUsername;
                }

                // User avatar
                $row->userImage = '';
                if ($params->get('commentAvatar')) {
                    $row->userImage = K2HelperUtilities::getAvatar($row->userID, $row->commentEmail, $componentParams->get('commenterImgWidth'));
                }

                // Populate the output array
                $comments[] = $row;
            }

            return $comments;
        }
    }

    public static function getTopCommenters(&$params)
    {
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/tables');

        $db = Factory::getDbo();

        $componentParams = ComponentHelper::getParams('com_k2');

        $limit = $params->get('commenters_limit', '5');

        $query = "SELECT COUNT(id) as counter, userName, userID, commentEmail
        	FROM #__k2_comments
        	WHERE userID > 0
        		AND published = 1
        	GROUP BY userID
        	ORDER BY counter DESC";
        $db->setQuery($query, 0, $limit);
        $rows = $db->loadObjectList();

        $pattern = "@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@";

        $commenters = array();

        if (count($rows)) {
            foreach ($rows as $row) {
                if ($row->counter > 0) {

                    // User link
                    $row->link = Route::_(K2HelperRoute::getUserRoute($row->userID));

                    // User name
                    if ($params->get('commenterNameOrUsername', 1) == 2) {
                        $getExistingUser = Factory::getUser($row->userID);
                        $row->userName = $getExistingUser->username;
                    }

                    // User avatar
                    if ($params->get('commentAvatar')) {
                        $row->userImage = K2HelperUtilities::getAvatar($row->userID, $row->commentEmail, $componentParams->get('commenterImgWidth'));
                    }

                    // User's last comment
                    if ($params->get('commenterLatestComment')) {
                        $query = "SELECT * FROM #__k2_comments WHERE userID = " . (int)$row->userID . " AND published = 1 ORDER BY commentDate DESC";

                        $db->setQuery($query, 0, 1);
                        $comment = $db->loadObject();

                        $item = Table::getInstance('K2Item', 'Table');
                        $item->load($comment->itemID);

                        $category = Table::getInstance('K2Category', 'Table');
                        $category->load($item->catid);

                        $row->latestCommentText = $comment->commentText;
                        $row->latestCommentText = preg_replace($pattern, '<a target="_blank" rel="nofollow" href="\0">\0</a>', $row->latestCommentText);

                        $row->latestCommentLink = urldecode(Route::_(K2HelperRoute::getItemRoute($item->id . ':' . urlencode($item->alias), $item->catid . ':' . urlencode($category->alias)))) . "#comment{$comment->id}";

                        $row->latestCommentDate = $comment->commentDate;
                    }

                    // Populate the output array
                    $commenters[] = $row;
                }
            }

            return $commenters;
        }
    }
}
