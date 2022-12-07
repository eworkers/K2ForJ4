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
use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.model');

JTable::addIncludePath(JPATH_COMPONENT . '/tables');

class K2ModelComments extends K2Model
{

    function getData()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . $view . '.limitstart', 'limitstart', 0, 'int');
        $filter_order = $app->getUserStateFromRequest($option . $view . 'filter_order', 'filter_order', 'c.id', 'cmd');
        $filter_order_Dir = $app->getUserStateFromRequest($option . $view . 'filter_order_Dir', 'filter_order_Dir', 'DESC', 'word');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', -1, 'int');
        $filter_category = $app->getUserStateFromRequest($option . $view . 'filter_category', 'filter_category', 0, 'int');
        $filter_author = $app->getUserStateFromRequest($option . $view . 'filter_author', 'filter_author', 0, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\"\.\@\-_]/u', '', $search));

        $query = "SELECT c.*, i.title , i.catid,  i.alias AS itemAlias, i.created_by,  cat.alias AS catAlias, cat.name as catName FROM #__k2_comments AS c LEFT JOIN #__k2_items AS i ON c.itemID=i.id LEFT JOIN #__k2_categories AS cat ON cat.id=i.catid LEFT JOIN #__k2_users AS u ON c.userID=u.userID WHERE c.id>0";

        if ($filter_state > -1) {
            $query .= " AND c.published={$filter_state}";
        }

        if ($filter_category) {
            $query .= " AND i.catid={$filter_category}";
        }

        if ($filter_author) {
            $query .= " AND i.created_by={$filter_author}";
        }

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
                        $query .= " AND ( " .
                            "LOWER(c.commentText) LIKE " . $quotedWord . " " .
                            "OR LOWER(c.userName) LIKE " . $quotedWord . " " .
                            "OR LOWER(c.commentEmail) LIKE " . $quotedWord . " " .
                            "OR LOWER(c.commentURL) LIKE " . $quotedWord . " " .
                            "OR LOWER(i.title) LIKE " . $quotedWord . " " .
                            "OR LOWER(u.userName) LIKE " . $quotedWord . " " .
                            "OR LOWER(u.ip) LIKE " . $quotedWord . " " .
                            " )";
                    }
                } else {
                    foreach ($quoted as $quotedWord) {
                        $query .= " AND LOWER(c.commentText) LIKE " . $quotedWord;
                    }
                }
            } // Single word or exact phrase to search for (wrapped in double quotes in the search block)
            else {
                $quoted = $db->Quote('%' . $escaped . '%', false);

                if ($params->get('adminSearch') == 'full') {
                    $query .= " AND ( " .
                        "LOWER(c.commentText) LIKE " . $quoted . " " .
                        "OR LOWER(c.userName) LIKE " . $quoted . " " .
                        "OR LOWER(c.commentEmail) LIKE " . $quoted . " " .
                        "OR LOWER(c.commentURL) LIKE " . $quoted . " " .
                        "OR LOWER(i.title) LIKE " . $quoted . " " .
                        "OR LOWER(u.userName) LIKE " . $quoted . " " .
                        "OR LOWER(u.ip) LIKE " . $quoted . " " .
                        " )";
                } else {
                    $query .= " AND LOWER(c.commentText) LIKE " . $quoted;
                }
            }
        }

        if (!$filter_order) {
            $filter_order = "c.commentDate";
        }

        $query .= " ORDER BY {$filter_order} {$filter_order_Dir}";
        $db->setQuery($query, $limitstart, $limit);
        $rows = $db->loadObjectList();
        return $rows;
    }

    function getTotal()
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $db = Factory::getDbo();
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest($option . '.limitstart', 'limitstart', 0, 'int');
        $filter_state = $app->getUserStateFromRequest($option . $view . 'filter_state', 'filter_state', 1, 'int');
        $filter_category = $app->getUserStateFromRequest($option . $view . 'filter_category', 'filter_category', 0, 'int');
        $filter_author = $app->getUserStateFromRequest($option . $view . 'filter_author', 'filter_author', 0, 'int');
        $search = $app->getUserStateFromRequest($option . $view . 'search', 'search', '', 'string');
        $search = StringHelper::strtolower($search);
        $search = trim(preg_replace('/[^\p{L}\p{N}\s\"\.\@\-_]/u', '', $search));

        $query = "SELECT COUNT(*) FROM #__k2_comments AS c LEFT JOIN #__k2_items AS i ON c.itemID=i.id LEFT JOIN #__k2_users AS u ON c.userID=u.userID WHERE c.id>0";

        if ($filter_state > -1) {
            $query .= " AND c.published={$filter_state}";
        }

        if ($filter_category) {
            $query .= " AND i.catid={$filter_category}";
        }

        if ($filter_author) {
            $query .= " AND i.created_by={$filter_author}";
        }

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
                        $query .= " AND ( " .
                            "LOWER(c.commentText) LIKE " . $quotedWord . " " .
                            "OR LOWER(c.userName) LIKE " . $quotedWord . " " .
                            "OR LOWER(c.commentEmail) LIKE " . $quotedWord . " " .
                            "OR LOWER(c.commentURL) LIKE " . $quotedWord . " " .
                            "OR LOWER(i.title) LIKE " . $quotedWord . " " .
                            "OR LOWER(u.userName) LIKE " . $quotedWord . " " .
                            "OR LOWER(u.ip) LIKE " . $quotedWord . " " .
                            " )";
                    }
                } else {
                    foreach ($quoted as $quotedWord) {
                        $query .= " AND LOWER(c.commentText) LIKE " . $quotedWord;
                    }
                }
            } // Single word or exact phrase to search for (wrapped in double quotes in the search block)
            else {
                $quoted = $db->Quote('%' . $escaped . '%', false);

                if ($params->get('adminSearch') == 'full') {
                    $query .= " AND ( " .
                        "LOWER(c.commentText) LIKE " . $quoted . " " .
                        "OR LOWER(c.userName) LIKE " . $quoted . " " .
                        "OR LOWER(c.commentEmail) LIKE " . $quoted . " " .
                        "OR LOWER(c.commentURL) LIKE " . $quoted . " " .
                        "OR LOWER(i.title) LIKE " . $quoted . " " .
                        "OR LOWER(u.userName) LIKE " . $quoted . " " .
                        "OR LOWER(u.ip) LIKE " . $quoted . " " .
                        " )";
                } else {
                    $query .= " AND LOWER(c.commentText) LIKE " . $quoted;
                }
            }
        }
        $db->setQuery($query);
        $total = $db->loadresult();
        return $total;
    }

    function publish()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $cid = Factory::getApplication()->input->getVar('cid');
        if (!count($cid)) {
            $cid[] = Factory::getApplication()->input->getInt('commentID');
        }

        foreach ($cid as $id) {
            $row = Table::getInstance('K2Comment', 'Table');
            $row->load($id);
            if ($app->isClient('site')) {
                $item = Table::getInstance('K2Item', 'Table');
                $item->load($row->itemID);
                if ($item->created_by != $user->id) {
                    throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
                    $app->close();
                }
            }
            $row->published = 1;
            $row->store();
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        if (Factory::getApplication()->input->getCmd('format') == 'raw') {
            echo 'true';
            $app->close();
        }
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=comments&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=comments');
        }
    }

    function unpublish()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $cid = Factory::getApplication()->input->getVar('cid');
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Comment', 'Table');
            $row->load($id);
            if ($app->isClient('site')) {
                $item = Table::getInstance('K2Item', 'Table');
                $item->load($row->itemID);
                if ($item->created_by != $user->id) {
                    throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
                    $app->close();
                }
            }
            $row->published = 0;
            $row->store();
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=comments&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=comments');
        }
    }

    function remove()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $db = Factory::getDbo();
        $cid = Factory::getApplication()->input->getVar('cid');
        if (!count($cid)) {
            $cid[] = Factory::getApplication()->input->getInt('commentID');
        }
        foreach ($cid as $id) {
            $row = Table::getInstance('K2Comment', 'Table');
            $row->load($id);
            if ($app->isClient('site')) {
                $item = Table::getInstance('K2Item', 'Table');
                $item->load($row->itemID);
                if ($item->created_by != $user->id) {
                    throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
                    $app->close();
                }
            }
            $row->delete($id);
        }
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        if (Factory::getApplication()->input->getCmd('format') == 'raw') {
            echo 'true';
            $app->close();
        }
        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=comments&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=comments');
        }
    }

    function deleteUnpublished()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $userID = $user->id;
        if ($app->isClient('site')) {
            $query = "SELECT c.id FROM #__k2_comments AS c
			LEFT JOIN #__k2_items AS i ON c.itemID=i.id
			WHERE i.created_by = {$userID} AND c.published=0";
            $db->setQuery($query);
            $ids = $db->loadColumn();
            if (count($ids)) {
                $query = "DELETE FROM #__k2_comments WHERE id IN(" . implode(',', $ids) . ")";
                $db->setQuery($query);
                $db->execute();
            }
        } else {
            $query = "DELETE FROM #__k2_comments WHERE published=0";
            $db->setQuery($query);
            $db->execute();
        }

        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $app->enqueueMessage(Text::_('K2_DELETE_COMPLETED'));
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=comments&tmpl=component&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=comments');
        }
    }

    function save()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $db = Factory::getDbo();
        $id = Factory::getApplication()->input->getInt('commentID');
        $item = Table::getInstance('K2Item', 'Table');
        $row = Table::getInstance('K2Comment', 'Table');
        $row->load($id);
        if ($app->isClient('site')) {
            $item->load($row->itemID);
            if ($item->created_by != $user->id) {
                throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
            }
        }
        $row->commentText = Factory::getApplication()->input->getVar('commentText', '', 'default', 'string', 4);
        $row->store();
        parent::cleanCache('com_k2');
        parent::cleanCache('com_k2_extended');
        $response = new stdClass;
        $response->comment = $row->commentText;
        $response->message = Text::_('K2_COMMENT_SAVED');
        echo json_encode($response);
        $app->close();
    }

    function report()
    {
        $id = $this->getState('id');
        $name = StringHelper::trim($this->getState('name'));
        $reportReason = StringHelper::trim($this->getState('reportReason'));
        $params = K2HelperUtilities::getParams('com_k2');
        $user = Factory::getUser();
        $row = Table::getInstance('K2Comment', 'Table');
        $row->load($id);
        if (!$row->published) {
            $this->setError(Text::_('K2_COMMENT_NOT_FOUND'));
            return false;
        }
        if (empty($name)) {
            $this->setError(Text::_('K2_PLEASE_TYPE_YOUR_NAME'));
            return false;
        }
        if (empty($reportReason)) {
            $this->setError(Text::_('K2_PLEASE_TYPE_THE_REPORT_REASON'));
            return false;
        }
        if (($params->get('antispam') == 'recaptcha' || $params->get('antispam') == 'both') && $user->guest) {
            require_once JPATH_SITE . '/components/com_k2/helpers/utilities.php';
            if (!K2HelperUtilities::verifyRecaptcha()) {
                $this->setError(Text::_('K2_COULD_NOT_VERIFY_THAT_YOU_ARE_NOT_A_ROBOT'));
                return false;
            }
        }

        $app = Factory::getApplication();
        $mail = Factory::getMailer();
        $senderEmail = $app->getCfg('mailfrom');
        $senderName = $app->getCfg('fromname');

        $mail->setSender(array($senderEmail, $senderName));
        $mail->setSubject(Text::_('K2_COMMENT_REPORT'));
        $mail->IsHTML(true);

        switch (substr(strtoupper(PHP_OS), 0, 3)) {
            case 'WIN':
                $mail->LE = "\r\n";
                break;
            case 'MAC':
            case 'DAR':
                $mail->LE = "\r";
            default:
                break;
        }

        // K2 embedded email template (to do: move to separate HTML template/override)
        $body = "
        <strong>" . Text::_('K2_NAME') . "</strong>: " . $name . " <br/>
        <strong>" . Text::_('K2_REPORT_REASON') . "</strong>: " . $reportReason . " <br/>
        <strong>" . Text::_('K2_COMMENT') . "</strong>: " . nl2br($row->commentText) . " <br/>
        ";

        $mail->setBody($body);
        $mail->ClearAddresses();
        $mail->AddAddress($params->get('commentsReportRecipient', $app->getCfg('mailfrom')));
        $mail->Send();

        return true;
    }
}
