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
use Joomla\CMS\Table\Table;

jimport('joomla.application.component.model');

class K2ModelSettings extends K2Model
{
    public function save()
    {
        $app = Factory::getApplication();
        $component = Table::getInstance('component');
        $component->loadByOption('com_k2');
        $post = Factory::getApplication()->input->getArray($_POST);
        $component->bind($post);
        if (!$component->check()) {
            $app->enqueueMessage($component->getError(), 'error');
            return false;
        }
        if (!$component->store()) {
            $app->enqueueMessage($component->getError(), 'error');
            return false;
        }
        return true;
    }

    public function &getParams()
    {
        static $instance;
        if ($instance == null) {
            $component = Table::getInstance('component');
            $component->loadByOption('com_k2');
            $instance = new JParameter($component->params, JPATH_ADMINISTRATOR . '/components/com_k2/config.xml');
        }
        return $instance;
    }
}
