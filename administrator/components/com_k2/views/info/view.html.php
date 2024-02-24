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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

jimport('joomla.application.component.view');

class K2ViewInfo extends K2View
{
    public function display($tpl = null)
    {
        jimport('joomla.filesystem.file');
        $user = Factory::getUser();
        $db = Factory::getDbo();
        $db_version = $db->getVersion();
        $php_version = phpversion();
        $server = $this->get_server_software();
        $gd_check = extension_loaded('gd');
        $mb_check = extension_loaded('mbstring');

        $media_folder_check = is_writable(JPATH_ROOT . '/media/k2');
        $attachments_folder_check = is_writable(JPATH_ROOT . '/media/k2/attachments');
        $categories_folder_check = is_writable(JPATH_ROOT . '/media/k2/categories');
        $galleries_folder_check = is_writable(JPATH_ROOT . '/media/k2/galleries');
        $items_folder_check = is_writable(JPATH_ROOT . '/media/k2/items');
        $users_folder_check = is_writable(JPATH_ROOT . '/media/k2/users');
        $videos_folder_check = is_writable(JPATH_ROOT . '/media/k2/videos');
        $cache_folder_check = is_writable(JPATH_ROOT . '/cache');

        $this->server = $server;
        $this->php_version = $php_version;
        $this->db_version = $db_version;
        $this->gd_check = $gd_check;
        $this->mb_check = $mb_check;

        $this->media_folder_check = $media_folder_check;
        $this->attachments_folder_check = $attachments_folder_check;
        $this->categories_folder_check = $categories_folder_check;
        $this->galleries_folder_check = $galleries_folder_check;
        $this->items_folder_check = $items_folder_check;
        $this->users_folder_check = $users_folder_check;
        $this->videos_folder_check = $videos_folder_check;
        $this->cache_folder_check = $cache_folder_check;

        ToolBarHelper::title(Text::_('K2_INFORMATION'), 'k2.png');

        ToolBarHelper::preferences('com_k2', '(window.innerHeight) * 0.9', '(window.innerWidth) * 0.7', 'K2_SETTINGS');

        $this->loadHelper('html');
        K2HelperHTML::subMenu();

        parent::display($tpl);
    }

    private function get_server_software()
    {
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            return $_SERVER['SERVER_SOFTWARE'];
        } elseif (($sf = getenv('SERVER_SOFTWARE'))) {
            return $sf;
        } else {
            return Text::_('K2_NA');
        }
    }
}
