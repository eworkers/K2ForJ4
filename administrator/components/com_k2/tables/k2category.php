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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

require_once JPATH_ADMINISTRATOR . '/components/com_k2/tables/table.php';

class TableK2Category extends K2Table
{
    /* since J4 compatibility */
    /* dirty fix fix non NULL field with no default value since MySQL drivers in 4.0 use STRICT_TRANS_TABLES */
    /* todo */
    /* init $plugins at relevant model */
    public $id = null;
    public $name = null;
    public $alias = null;
    public $description = null;
    public $parent = null;
    public $extraFieldsGroup = null;
    public $published = null;
    public $image = null;
    public $access = null;
    public $ordering = null;
    public $params = null;
    public $trash = null;
    public $plugins = '';
    public $language = null;

    public function __construct(&$db)
    {
        parent::__construct('#__k2_categories', 'id', $db);
    }

    public function load($oid = null, $reset = false)
    {
        static $K2CategoriesInstances = array();
        if (isset($K2CategoriesInstances[$oid])) {
            return $this->bind($K2CategoriesInstances[$oid]);
        }

        $k = $this->_tbl_key;

        if ($oid !== null) {
            $this->$k = $oid;
        }

        $oid = $this->$k;

        if ($oid === null) {
            return false;
        }
        $this->reset();

        $db = $this->getDBO();

        $query = 'SELECT *' . ' FROM ' . $this->_tbl . ' WHERE ' . $this->_tbl_key . ' = ' . $db->Quote($oid);
        $db->setQuery($query);

        try {
            $result = $db->loadAssoc();
            $K2CategoriesInstances[$oid] = $result;
            if ($result) {
                $K2CategoriesInstances[$oid] = $result;
                return $this->bind($K2CategoriesInstances[$oid]);
            }
        } catch (Exception $e) {
            JFactory::getApplication()->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
            return false;
        }
    }

    public function check()
    {
        jimport('joomla.filter.output');
        $params = ComponentHelper::getParams('com_k2');
        $this->name = StringHelper::trim($this->name);
        if ($this->name == '') {
            $this->setError(Text::_('K2_CATEGORY_MUST_HAVE_A_NAME'));
            return false;
        }
        if (empty($this->alias)) {
            $this->alias = $this->name;
        }

        /* Offload the alias processing block to a simplified external function/method call */
        if (Factory::getConfig()->get('unicodeslugs') == 1) {
            $this->alias = OutputFilter::stringURLUnicodeSlug($this->alias);
        } // Transliterate properly...
        else {
            // Detect the site language we will transliterate
            if ($this->language == '*') {
                $langParams = ComponentHelper::getParams('com_languages');
                $languageTag = $langParams->get('site');
            } else {
                $languageTag = $this->language;
            }
            $language = Language::getInstance($languageTag);
            $this->alias = $language->transliterate($this->alias);
            $this->alias = OutputFilter::stringURLSafe($this->alias);
            if (trim(str_replace('-', '', $this->alias)) == '') {
                $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
            }
        }

        if ($params->get('enforceSEFReplacements')) {
            $SEFReplacements = array();
            $items = explode(',', $params->get('SEFReplacements'));
            foreach ($items as $item) {
                if (!empty($item)) {
                    @list($src, $dst) = explode('|', trim($item));
                    $SEFReplacements[trim($src)] = trim($dst);
                }
            }

            foreach ($SEFReplacements as $key => $value) {
                $this->alias = str_replace($key, $value, $this->alias);
            }

            $this->alias = trim($this->alias, '-.');
        }

        // Check if alias already exists. If so warn the user
        $params = ComponentHelper::getParams('com_k2');
        if ($params->get('k2Sef') && !$params->get('k2SefInsertCatId')) {
            $db = Factory::getDbo();
            $db->setQuery("SELECT id FROM #__k2_categories WHERE alias = " . $db->quote($this->alias) . " AND id != " . (int)$this->id);
            $result = count($db->loadObjectList());
            if ($result > 0) {
                $this->alias .= '-' . ((int)$result + 1);
                $app = Factory::getApplication();
                $app->enqueueMessage(Text::_('K2_WARNING_DUPLICATE_TITLE_ALIAS_DETECTED'), 'notice');
            }
        }

        return true;
    }

    public function bind($array, $ignore = '')
    {
        if (key_exists('params', $array) && is_array($array['params'])) {
            $registry = new Registry();
            $registry->loadArray($array['params']);
            $array['params'] = $registry->toString();
        }
        if (key_exists('plugins', $array) && is_array($array['plugins'])) {
            $registry = new Registry();
            $registry->loadArray($array['plugins']);
            $array['plugins'] = $registry->toString();
        }


        return parent::bind($array, $ignore);
    }
}
