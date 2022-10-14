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

use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\FormField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementModuleTemplate extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        jimport('joomla.filesystem.folder');
        $moduleName = $node->attributes()->modulename;
        $moduleTemplatesPath = JPATH_SITE . '/modules/' . $moduleName . '/tmpl';
        $moduleTemplatesFolders = Folder::folders($moduleTemplatesPath);

        $db = Factory::getDbo();
        $query = "SELECT template FROM #__template_styles WHERE client_id = 0 AND home = 1";
        $db->setQuery($query);
        $defaultemplate = $db->loadResult();
        $templatePath = JPATH_SITE . '/templates/' . $defaultemplate . '/html/' . $moduleName;

        if (Folder::exists($templatePath)) {
            $templateFolders = Folder::folders($templatePath);
            $folders = @array_merge($templateFolders, $moduleTemplatesFolders);
            $folders = @array_unique($folders);
        } else {
            $folders = $moduleTemplatesFolders;
        }

        $exclude = 'Default';
        $options = array();

        foreach ($folders as $folder) {
            if (preg_match(chr(1) . $exclude . chr(1), $folder)) {
                continue;
            }
            $options[] = JHTML::_('select.option', $folder, $folder);
        }

        array_unshift($options, JHTML::_('select.option', 'Default', '-- ' . Text::_('K2_USE_DEFAULT') . ' --'));

        $fieldName = $name;

        return JHTML::_('select.genericlist', $options, $fieldName, 'class="inputbox"', 'value', 'text', $value);
    }
}

class JFormFieldModuleTemplate extends K2ElementModuleTemplate
{
    public $type = 'moduletemplate';
}

class JElementModuleTemplate extends K2ElementModuleTemplate
{
    public $_name = 'moduletemplate';
}
