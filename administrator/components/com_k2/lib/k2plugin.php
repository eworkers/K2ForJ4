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
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;

jimport('joomla.plugin.plugin');

JLoader::register('K2Parameter', JPATH_ADMINISTRATOR . '/components/com_k2/lib/k2parameter.php');

if (!defined('K2_PLUGIN_API')) {
    define('K2_PLUGIN_API', true);
}

class K2Plugin extends CMSPlugin
{

    /**
     * Below we list all available BACKEND events, to trigger K2 plugins and generate additional fields in the item, category and user forms.
     */

    /* ------------ Functions to render plugin parameters in the backend - no need to change anything ------------ */
    public function onRenderAdminForm(&$item, $type, $tab = '')
    {
        $app = Factory::getApplication();
        $manifest = JPATH_SITE . '/plugins/k2/' . $this->pluginName . '/' . $this->pluginName . '.xml';
        if (!empty($tab)) {
            $path = $type . '-' . $tab;
        } else {
            $path = $type;
        }
        if (!isset($item->plugins)) {
            $item->plugins = null;
        }

        jimport('joomla.form.form');
        $form = Form::getInstance('plg_k2_' . $this->pluginName . '_' . $path, $manifest, array(), true, 'fields[@group="' . $path . '"]');
        $values = array();
        if ($item->plugins) {
            foreach (json_decode($item->plugins) as $name => $value) {
                $count = 1;
                $values[str_replace($this->pluginName, '', $name, $count)] = $value;
            }
            $form->bind($values);
        }
        $fields = '';
        foreach ($form->getFieldset() as $field) {
            if (strpos($field->name, '[]') !== false) {
                $search = 'name="' . $field->name . '"';
                $replace = 'name="plugins[' . $this->pluginName . str_replace('[]', '', $field->name) . '][]"';
            } else {
                $search = 'name="' . $field->name . '"';
                $replace = 'name="plugins[' . $this->pluginName . $field->name . ']"';
            }
            $input = StringHelper::str_ireplace($search, $replace, $field->__get('input'));
            $fields .= $field->__get('label') . ' ' . $input;
        }

        if ($fields) {
            $plugin = new stdClass;
            $plugin->name = $this->pluginNameHumanReadable;
            $plugin->fields = $fields;
            return $plugin;
        }
    }
}
