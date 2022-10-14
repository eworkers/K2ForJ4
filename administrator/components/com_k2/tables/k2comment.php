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
use Joomla\String\StringHelper;

require_once JPATH_ADMINISTRATOR . '/components/com_k2/tables/table.php';

class TableK2Comment extends K2Table
{
    /* since J4 compatibility */
    /* dirty fix fix non NULL field with no default value since MySQL drivers in 4.0 use STRICT_TRANS_TABLES */
    /* todo */
    /* init $userID, $commentURL at relevant model
    */
    var $id = null;
    var $itemID = null;
    var $userID = 0;
    var $userName = null;
    var $commentDate = null;
    var $commentText = null;
    var $commentEmail = null;
    var $commentURL = '';
    var $published = null;

    function __construct(&$db)
    {
        parent::__construct('#__k2_comments', 'id', $db);
    }

    function check()
    {
        $this->commentText = StringHelper::trim($this->commentText);
    }

}
