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
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\FormField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementTemplate extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        jimport('joomla.filesystem.folder');
        $app = Factory::getApplication();
        $fieldName = $name;
        $componentPath = JPATH_SITE . '/components/com_k2/templates';
        $componentFolders = Folder::folders($componentPath);
        $db = Factory::getDbo();
        $query = "SELECT template FROM #__template_styles WHERE client_id = 0 AND home = 1";
        $db->setQuery($query);
        $defaultemplate = $db->loadResult();

        if (Folder::exists(JPATH_SITE . '/templates/' . $defaultemplate . '/html/com_k2/templates')) {
            $templatePath = JPATH_SITE . '/templates/' . $defaultemplate . '/html/com_k2/templates';
        } else {
            $templatePath = JPATH_SITE . '/templates/' . $defaultemplate . '/html/com_k2';
        }

        if (Folder::exists($templatePath)) {
            $templateFolders = Folder::folders($templatePath);
            $folders = @array_merge($templateFolders, $componentFolders);
            $folders = @array_unique($folders);
        } else {
            $folders = $componentFolders;
        }

        $exclude = 'default';
        $options = array();
        foreach ($folders as $folder) {
            if (preg_match(chr(1) . $exclude . chr(1), $folder)) {
                continue;
            }
            $options[] = HTMLHelper::_('select.option', $folder, $folder);
        }

        array_unshift($options, HTMLHelper::_('select.option', '', '-- ' . Text::_('K2_USE_DEFAULT') . ' --'));

        return HTMLHelper::_('select.genericlist', $options, $fieldName, 'class="form-select"', 'value', 'text', $value);
    }
}

class JFormFieldTemplate extends K2ElementTemplate
{
    public $type = 'template';
}

class JElementTemplate extends K2ElementTemplate
{
    public $_name = 'template';
}
