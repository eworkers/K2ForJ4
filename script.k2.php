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
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Language\Text;

// Installer & un-installer for Joomla 2.5+
class Com_K2InstallerScript
{
    protected $minimumPHPVersion = '5.3.10';

    protected $minimumJoomlaVersion = '3.9';

    private $app;

    public function __construct()
    {
        $this->app = JFactory::getApplication();
    }

    /**
     * Method to run before an install/update/uninstall method
     *
     * @param string $type   Type
     * @param object $parent Parent
     *
     * @return boolean
     */
    public function preflight($type, $parent)
    {
        // Check the minimum Joomla version
        if (!$this->metRequirement("joomla"))
        {
            $this->uninstall($parent);
            return false;
        }

        // Check the minimum PHP version
        if (!$this->metRequirement("php"))
        {
            $this->uninstall($parent);
            return false;
        }
    }
    public function postflight($type, $parent)
    {
        $db = Factory::getDbo();

        $status = new stdClass;
        $status->modules = array();
        $status->plugins = array();

        $src = $parent->getParent()->getPath('source');

        $k2AlreadyInstalled = File::exists(JPATH_SITE.'/modules/mod_k2_content/mod_k2_content.php');

        // Get extension manifest
        $manifest = $parent->getParent()->manifest;


        // Install K2 modules
        $modules = $manifest->xpath('modules/module');
        foreach ($modules as $module) {
            $name = (string)$module->attributes()->module;
            $client = (string)$module->attributes()->client;
            if (is_null($client)) {
                $client = 'site';
            }
            $path = ($client == 'administrator') ? $src.'/administrator/modules/'.$name : $src.'/modules/'.$name;

            $installer = new Installer;
            $result = $installer->install($path);
            if ($result) {
                $root = ($client == 'administrator') ? JPATH_ADMINISTRATOR : JPATH_SITE;
                if (File::exists($root.'/modules/'.$name.'/'.$name.'.xml')) {
                    File::delete($root.'/modules/'.$name.'/'.$name.'.xml');
                }
                File::move($root.'/modules/'.$name.'/'.$name.'.j25.xml', $root.'/modules/'.$name.'/'.$name.'.xml');
            }

            $status->modules[] = array('name' => $name, 'client' => $client, 'result' => $result);

            if ($client == 'administrator' && !$k2AlreadyInstalled) {
                $position = 'cpanel';
                $db->setQuery("UPDATE #__modules SET `position`=".$db->quote($position).", `published`='1' WHERE `module`=".$db->quote($name));
                $db->execute();

                $db->setQuery("SELECT id FROM #__modules WHERE `module` = ".$db->quote($name));
                $id = (int)$db->loadResult();

                $db->setQuery("INSERT IGNORE INTO #__modules_menu (`moduleid`,`menuid`) VALUES (".$id.", 0)");
                $db->execute();
            }
        }

        // Install K2 plugins
        $plugins = $manifest->xpath('plugins/plugin');
        foreach ($plugins as $plugin) {
            $name = (string)$plugin->attributes()->plugin;
            $group = (string)$plugin->attributes()->group;
            $path = $src.'/plugins/'.$group;
            if (Folder::exists($src.'/plugins/'.$group.'/'.$name)) {
                $path = $src.'/plugins/'.$group.'/'.$name;
            }

            $installer = new Installer;
            $result = $installer->install($path);
            if ($result && $group != 'finder') {
                if (File::exists(JPATH_SITE.'/plugins/'.$group.'/'.$name.'/'.$name.'.xml')) {
                    File::delete(JPATH_SITE.'/plugins/'.$group.'/'.$name.'/'.$name.'.xml');
                }
                File::move(JPATH_SITE.'/plugins/'.$group.'/'.$name.'/'.$name.'.j25.xml', JPATH_SITE.'/plugins/'.$group.'/'.$name.'/'.$name.'.xml');
            }

            if ($group != 'finder') {
                $query = "UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND element=".$db->Quote($name)." AND folder=".$db->Quote($group);
                $db->setQuery($query);
                $db->execute();
            }

            $status->plugins[] = array('name' => $name, 'group' => $group, 'result' => $result);
        }

        // File Cleanups
        if (File::exists(JPATH_ADMINISTRATOR.'/components/com_k2/admin.k2.php')) {
            File::delete(JPATH_ADMINISTRATOR.'/components/com_k2/admin.k2.php');
        }
        if (File::exists(JPATH_ADMINISTRATOR.'/components/com_k2/models/cpanel.php')) {
            File::delete(JPATH_ADMINISTRATOR.'/components/com_k2/models/cpanel.php');
        }

        // Clean up empty entries in #__k2_users table caused by an issue in the K2 user plugin.
        $query = "DELETE FROM #__k2_users WHERE userID = 0";
        $db->setQuery($query);
        $db->execute();

        // User groups (set first 2 user groups)
        $query = "SELECT COUNT(*) FROM #__k2_user_groups";
        $db->setQuery($query);
        $userGroupCount = $db->loadResult();

        if ($userGroupCount == 0) {
            $query = "INSERT INTO #__k2_user_groups (`name`, `permissions`) VALUES('Registered', '{\"comment\":\"1\",\"frontEdit\":\"0\",\"add\":\"0\",\"editOwn\":\"0\",\"editAll\":\"0\",\"publish\":\"0\",\"editPublished\":\"0\",\"inheritance\":\"0\",\"categories\":\"all\"}')";
            $db->setQuery($query);
            $db->execute();

            $query = "INSERT INTO #__k2_user_groups (`name`, `permissions`) VALUES('Site Owner', '{\"comment\":\"1\",\"frontEdit\":\"1\",\"add\":\"1\",\"editOwn\":\"1\",\"editAll\":\"1\",\"publish\":\"1\",\"editPublished\":\"1\",\"inheritance\":\"1\",\"categories\":\"all\"}')";
            $db->setQuery($query);
            $db->execute();
        }
        /*
        // TO DO: Check main folders for 0755 first and then apply this fix
        // Fix media manager folder permissions
        set_time_limit(0);
        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.path');
        $params = ComponentHelper::getParams('com_media');
        $root = $params->get('file_path', 'media');
        $mediaPath = JPATH_SITE.'/'.Path::clean($root);
        $folders = Folder::folders($mediaPath, '.', true, true, array());
        foreach ($folders as $folder) {
            @chmod($folder, 0755);
        }
        if (Folder::exists($mediaPath.'/'.'.tmb')) {
            @chmod($mediaPath.'/'.'.tmb', 0755);
        }
        if (Folder::exists($mediaPath.'/'.'.quarantine')) {
            @chmod($mediaPath.'/'.'.quarantine', 0755);
        }
        */

        // Display installation results
        $this->installationResults($status);
    }

    public function uninstall($parent)
    {
        $db = Factory::getDbo();

        $status = new stdClass;
        $status->modules = array();
        $status->plugins = array();

        // Get extension manifest
        $manifest = $parent->getParent()->manifest;

        // Remove K2 modules
        $modules = $manifest->xpath('modules/module');
        foreach ($modules as $module) {
            $name = (string)$module->attributes()->module;
            $client = (string)$module->attributes()->client;
            $db = Factory::getDbo();
            $query = "SELECT `extension_id` FROM `#__extensions` WHERE `type`='module' AND element = ".$db->Quote($name)."";
            $db->setQuery($query);
            $extensions = $db->loadColumn();
            if (count($extensions)) {
                foreach ($extensions as $id) {
                    $installer = new Installer;
                    $result = $installer->uninstall('module', $id);
                }
                $status->modules[] = array('name' => $name, 'client' => $client, 'result' => $result);
            }
        }

        // Remove K2 plugins
        $plugins = $manifest->xpath('plugins/plugin');
        foreach ($plugins as $plugin) {
            $name = (string)$plugin->attributes()->plugin;
            $group = (string)$plugin->attributes()->group;
            $query = "SELECT `extension_id` FROM #__extensions WHERE `type`='plugin' AND element = ".$db->Quote($name)." AND folder = ".$db->Quote($group);
            $db->setQuery($query);
            $extensions = $db->loadColumn();
            if (count($extensions)) {
                foreach ($extensions as $id) {
                    $installer = new Installer;
                    $result = $installer->uninstall('plugin', $id);
                }
                $status->plugins[] = array('name' => $name, 'group' => $group, 'result' => $result);
            }
        }

        // Display un-installation results
        $this->uninstallationResults($status);
    }

    public function update($type)
    {
        $db = Factory::getDbo();

        // Items
        $fields = $db->getTableColumns('#__k2_items');
        if (!array_key_exists('featured_ordering', $fields)) {
            $query = "ALTER TABLE #__k2_items ADD `featured_ordering` INT(11) NOT NULL default '0' AFTER `featured`";
            $db->setQuery($query);
            $db->execute();
        }
        if (!array_key_exists('language', $fields)) {
            $query = "ALTER TABLE #__k2_items ADD `language` CHAR(7) NOT NULL";
            $db->setQuery($query);
            $db->execute();

            $query = "ALTER TABLE #__k2_items ADD INDEX (`language`)";
            $db->setQuery($query);
            $db->execute();
        }
        if ($fields['introtext'] == 'text') {
            $query = "ALTER TABLE #__k2_items MODIFY `introtext` MEDIUMTEXT";
            $db->setQuery($query);
            $db->execute();
        }
        if ($fields['fulltext'] == 'text') {
            $query = "ALTER TABLE #__k2_items MODIFY `fulltext` MEDIUMTEXT";
            $db->setQuery($query);
            $db->execute();
        }
        if ($fields['video'] != 'text') {
            $query = "ALTER TABLE #__k2_items MODIFY `video` TEXT";
            $db->setQuery($query);
            $db->execute();
        }

        $query = "SHOW INDEX FROM #__k2_items";
        $db->setQuery($query);
        $itemIndices = $db->loadObjectList();
        $itemKeys_item = false;
        $itemKeys_idx_item = false;
        foreach ($itemIndices as $index) {
            if ($index->Key_name == 'item') {
                $itemKeys_item = true;
            }
            if ($index->Key_name == 'idx_item') {
                $itemKeys_idx_item = true;
            }
        }
        if ($itemKeys_item) {
            $query = "ALTER TABLE #__k2_items DROP INDEX `item`";
            $db->setQuery($query);
            $db->execute();
        }
        if (!$itemKeys_idx_item) {
            $query = "ALTER TABLE #__k2_items ADD INDEX `idx_item` (`published`,`publish_up`,`publish_down`,`trash`,`access`)";
            $db->setQuery($query);
            $db->execute();
        }

        // Categories
        $fields = $db->getTableColumns('#__k2_categories');
        if (!array_key_exists('language', $fields)) {
            $query = "ALTER TABLE #__k2_categories ADD `language` CHAR(7) NOT NULL";
            $db->setQuery($query);
            $db->execute();

            $query = "ALTER TABLE #__k2_categories ADD INDEX `idx_language` (`language`)";
            $db->setQuery($query);
            $db->execute();
        }

        // Comments (add index for comments count)
        $query = "SHOW INDEX FROM #__k2_comments";
        $db->setQuery($query);
        $indexes = $db->loadObjectList();
        $indexExists = false;
        foreach ($indexes as $index) {
            if ($index->Key_name == 'countComments' || $index->Key_name == 'idx_countComments') {
                $indexExists = true;
            }
        }
        if (!$indexExists) {
            $query = "ALTER TABLE #__k2_comments ADD INDEX `idx_countComments` (`itemID`, `published`)";
            $db->setQuery($query);
            $db->execute();
        }

        // Users
        $fields = $db->getTableColumns('#__k2_users');
        if (!array_key_exists('ip', $fields)) {
            $query = "ALTER TABLE `#__k2_users`
                ADD `ip` VARCHAR(45) NOT NULL ,
                ADD `hostname` VARCHAR(255) NOT NULL ,
                ADD `notes` TEXT NOT NULL";
            $db->setQuery($query);
            $db->execute();
        }

        // Users - add new ENUM option for "gender"
        $query = "SELECT DISTINCT gender FROM #__k2_users";
        $db->setQuery($query);
        $enumOptions = $db->loadColumn();
        if (count($enumOptions) < 3) {
            $query = "ALTER TABLE #__k2_users MODIFY COLUMN `gender` enum('m','f','n') NOT NULL DEFAULT 'n'";
            $db->setQuery($query);
            $db->execute();
        }

        // User groups (set first 2 user groups)
        $query = "SELECT COUNT(*) FROM #__k2_user_groups";
        $db->setQuery($query);
        $userGroupCount = $db->loadResult();

        if ($userGroupCount == 0) {
            $query = "INSERT INTO #__k2_user_groups (`name`, `permissions`) VALUES('Registered', '{\"comment\":\"1\",\"frontEdit\":\"0\",\"add\":\"0\",\"editOwn\":\"0\",\"editAll\":\"0\",\"publish\":\"0\",\"editPublished\":\"0\",\"inheritance\":\"0\",\"categories\":\"all\"}')";
            $db->setQuery($query);
            $db->execute();

            $query = "INSERT INTO #__k2_user_groups (`name`, `permissions`) VALUES('Site Owner', '{\"comment\":\"1\",\"frontEdit\":\"1\",\"add\":\"1\",\"editOwn\":\"1\",\"editAll\":\"1\",\"publish\":\"1\",\"editPublished\":\"1\",\"inheritance\":\"1\",\"categories\":\"all\"}')";
            $db->setQuery($query);
            $db->execute();
        }

        // Log for updates
        $query = "CREATE TABLE IF NOT EXISTS `#__k2_log` (
                `status` int(11) NOT NULL,
                `response` text NOT NULL,
                `timestamp` datetime NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;";
        $db->setQuery($query);
        $db->execute();

        // J4 MySQL drivers complains regarding STRICT_TRANS_TABLES
        // attachments
        $query = "ALTER TABLE #__k2_attachments CHANGE `itemID` `itemID` INT(11) NOT NULL DEFAULT '0'";
        $db->setQuery($query);
        $db->execute();

        // categories
        $query = "ALTER TABLE #__k2_categories CHANGE `extraFieldsGroup` `extraFieldsGroup` INT(11) NOT NULL DEFAULT '0'";
        $db->setQuery($query);
        $db->execute();

        $query = "ALTER TABLE #__k2_categories CHANGE `image` `image` VARCHAR(255) NOT NULL DEFAULT ''";
        $db->setQuery($query);
        $db->execute();

        // items
        $query = "ALTER TABLE #__k2_items CHANGE `checked_out` `checked_out` INT(10) NOT NULL DEFAULT '0'";
        $db->setQuery($query);
        $db->execute();

        $query = "ALTER TABLE #__k2_items CHANGE `hits` `hits` INT(10) NOT NULL DEFAULT '0'";
        $db->setQuery($query);
        $db->execute();

        $query = "ALTER TABLE #__k2_items CHANGE `image_credits` `image_credits` VARCHAR(255) NOT NULL DEFAULT ''";
        $db->setQuery($query);
        $db->execute();

        $query = "ALTER TABLE #__k2_items CHANGE `video_credits` `video_credits` VARCHAR(255) NOT NULL DEFAULT ''";
        $db->setQuery($query);
        $db->execute();

        // tags
        // mysql5.6 key compatibility
        $query = "ALTER TABLE #__k2_tags CHANGE `name` `name` VARCHAR(191) NOT NULL";
        $db->setQuery($query);
        $db->execute();

        // user_groups
        // mysql5.6 key compatibility
        $query = "ALTER TABLE #__k2_user_groups CHANGE `name` `name` VARCHAR(191) NOT NULL";
        $db->setQuery($query);
        $db->execute();

        // users
        // mysql5.6 key compatibility
        $query = "ALTER TABLE #__k2_users CHANGE `userName` `userName` VARCHAR(191) NOT NULL";
        $db->setQuery($query);
        $db->execute();

        // comments
        $query = "ALTER TABLE #__k2_comments CHANGE `commentURL` `commentURL` VARCHAR(255) NOT NULL DEFAULT ''";
        $db->setQuery($query);
        $db->execute();

    }

    private function installationResults($status)
    {
        $language = Factory::getLanguage();
        $language->load('com_k2');
        $rows = 0; ?>
        <img src="https://cdn.joomlaworks.org/joomla/extensions/k2/app/k2_logo.png" alt="K2" align="right" />
        <h2><?php echo Text::_('K2_INSTALLATION_STATUS'); ?></h2>
        <table class="adminlist table table-striped">
            <thead>
                <tr>
                    <th class="title" colspan="2"><?php echo Text::_('K2_EXTENSION'); ?></th>
                    <th width="30%"><?php echo Text::_('K2_STATUS'); ?></th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <tbody>
                <tr class="row0">
                    <td class="key" colspan="2"><?php echo 'K2 '.Text::_('K2_COMPONENT'); ?></td>
                    <td><strong><?php echo Text::_('K2_INSTALLED'); ?></strong></td>
                </tr>
                <?php if (count($status->modules)): ?>
                <tr>
                    <th><?php echo Text::_('K2_MODULE'); ?></th>
                    <th><?php echo Text::_('K2_CLIENT'); ?></th>
                    <th></th>
                </tr>
                <?php foreach ($status->modules as $module): ?>
                <tr class="row<?php echo(++$rows % 2); ?>">
                    <td class="key"><?php echo $module['name']; ?></td>
                    <td class="key"><?php echo ucfirst($module['client']); ?></td>
                    <td><strong><?php echo ($module['result'])?Text::_('K2_INSTALLED'):Text::_('K2_NOT_INSTALLED'); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php if (count($status->plugins)): ?>
                <tr>
                    <th><?php echo Text::_('K2_PLUGIN'); ?></th>
                    <th><?php echo Text::_('K2_GROUP'); ?></th>
                    <th></th>
                </tr>
                <?php foreach ($status->plugins as $plugin): ?>
                <tr class="row<?php echo(++$rows % 2); ?>">
                    <td class="key"><?php echo ucfirst($plugin['name']); ?></td>
                    <td class="key"><?php echo ucfirst($plugin['group']); ?></td>
                    <td><strong><?php echo ($plugin['result'])?Text::_('K2_INSTALLED'):Text::_('K2_NOT_INSTALLED'); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php
    }

    private function uninstallationResults($status)
    {
        $language = Factory::getLanguage();
        $language->load('com_k2');
        $rows = 0; ?>
        <img src="https://cdn.joomlaworks.org/joomla/extensions/k2/app/k2_logo.png" alt="K2" align="right" />
        <h2><?php echo Text::_('K2_REMOVAL_STATUS'); ?></h2>
        <table class="adminlist table table-striped">
            <thead>
                <tr>
                    <th class="title" colspan="2"><?php echo Text::_('K2_EXTENSION'); ?></th>
                    <th width="30%"><?php echo Text::_('K2_STATUS'); ?></th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <tbody>
                <tr class="row0">
                    <td class="key" colspan="2"><?php echo 'K2 '.Text::_('K2_COMPONENT'); ?></td>
                    <td><strong><?php echo Text::_('K2_REMOVED'); ?></strong></td>
                </tr>
                <?php if (count($status->modules)): ?>
                <tr>
                    <th><?php echo Text::_('K2_MODULE'); ?></th>
                    <th><?php echo Text::_('K2_CLIENT'); ?></th>
                    <th></th>
                </tr>
                <?php foreach ($status->modules as $module): ?>
                <tr class="row<?php echo(++$rows % 2); ?>">
                    <td class="key"><?php echo $module['name']; ?></td>
                    <td class="key"><?php echo ucfirst($module['client']); ?></td>
                    <td><strong><?php echo ($module['result'])?Text::_('K2_REMOVED'):Text::_('K2_NOT_REMOVED'); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (count($status->plugins)): ?>
                <tr>
                    <th><?php echo Text::_('K2_PLUGIN'); ?></th>
                    <th><?php echo Text::_('K2_GROUP'); ?></th>
                    <th></th>
                </tr>
                <?php foreach ($status->plugins as $plugin): ?>
                <tr class="row<?php echo(++$rows % 2); ?>">
                    <td class="key"><?php echo ucfirst($plugin['name']); ?></td>
                    <td class="key"><?php echo ucfirst($plugin['group']); ?></td>
                    <td><strong><?php echo ($plugin['result'])?Text::_('K2_REMOVED'):Text::_('K2_NOT_REMOVED'); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php
    }

    private function metRequirement($type = "joomla")
    {
        switch ($type)
        {
            case 'joomla':
                if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<'))
                {
                    $jv_err_msg = 'Your Joomla version "'.JVERSION. '" is not supported.\n This special version of K2 requires "'.$this->minimumJoomlaVersion.'+" in order to run';
                    $this->app->enqueueMessage(
                        JText::_($jv_err_msg),
                        'error'
                    );
                    return false;
                }
                break;
            case 'php':
                $php_err_msg = 'Your PHP version "'.PHP_VERSION. '" is not supported.\n This special version of K2 requires php"'.$this->minimumPHPVersion.'+" in order to run';
                if (version_compare(PHP_VERSION, $this->minimumPHPVersion, 'lt'))
                {
                    $this->app->enqueueMessage(
                        JText::_($php_err_msg),
                        'error'
                    );
                    return false;
                }
                break;
        }

        return true;
    }
}
