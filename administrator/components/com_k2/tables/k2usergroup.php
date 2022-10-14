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
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

require_once JPATH_ADMINISTRATOR . '/components/com_k2/tables/table.php';

class TableK2UserGroup extends K2Table
{

    var $id = null;
    var $name = null;
    var $permissions = null;

    function __construct(&$db)
    {

        parent::__construct('#__k2_user_groups', 'id', $db);
    }

    function check()
    {
        $this->name = StringHelper::trim($this->name);
        if ($this->name == '') {
            $this->setError(Text::_('K2_GROUP_CANNOT_BE_EMPTY'));
            return false;
        }
        return true;
    }

    function bind($array, $ignore = '')
    {

        if (key_exists('params', $array) && is_array($array['params'])) {
            $registry = new Registry();
            $registry->loadArray($array['params']);
            if (Factory::getApplication()->input->getVar('categories') == 'all' || Factory::getApplication()->input->getVar('categories') == 'none')
                $registry->set('categories', Factory::getApplication()->input->getVar('categories'));
            $array['permissions'] = $registry->toString();
        }
        return parent::bind($array, $ignore);
    }

}
