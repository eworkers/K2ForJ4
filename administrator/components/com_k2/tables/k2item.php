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
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Language;
use Joomla\Registry\Registry;

require_once JPATH_ADMINISTRATOR . '/components/com_k2/tables/table.php';

class TableK2Item extends K2Table
{
    /* since J4 compatibility */
    /* dirty fix fix non NULL field with no default value since MySQL drivers in 4.0 use STRICT_TRANS_TABLES */
    /* todo */
    /* init $image_caption, $image_credits, $video_caption, $video_credits,
       $extra_fields, $extra_fields_search, $plugins at relevant model
    */
    public $id = '';
    public $title = null;
    public $alias = null;
    public $catid = null;
    public $published = null;
    public $introtext = null;
    public $fulltext = null;
    public $image_caption = '';
    public $image_credits = '';
    public $video = null;
    public $video_caption = '';
    public $video_credits = '';
    public $gallery = null;
    public $extra_fields = '';
    public $extra_fields_search = '';
    public $created = null;
    public $created_by = null;
    public $created_by_alias = null;
    public $modified = null;
    public $modified_by = null;
    public $publish_up = null;
    public $publish_down = null;
    public $checked_out = null;
    public $checked_out_time = null;
    public $trash = null;
    public $access = null;
    public $ordering = null;
    public $featured = null;
    public $featured_ordering = null;
    public $hits = null;
    public $metadata = null;
    public $metadesc = null;
    public $metakey = null;
    public $params = null;
    public $plugins = '';
    public $language = null;

    public function __construct(&$db)
    {
        parent::__construct('#__k2_items', 'id', $db);
    }

    public function check()
    {
        jimport('joomla.filter.output');
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $this->title = StringHelper::trim($this->title);
        if ($this->title == '') {
            $this->setError(Text::_('K2_ITEM_MUST_HAVE_A_TITLE'));
            return false;
        }
        if (!$this->catid) {
            $this->setError(Text::_('K2_ITEM_MUST_HAVE_A_CATEGORY'));
            return false;
        }
        if (empty($this->alias)) {
            $this->alias = $this->title;
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

        // Check if the item alias already exists, warn the user if it does and append the item ID to it.
        $params = ComponentHelper::getParams('com_k2');
        if ($params->get('k2Sef') && !$params->get('k2SefInsertItemId')) {
            $db = Factory::getDbo();
            if ($this->id) {
                $db->setQuery("SELECT id FROM #__k2_items WHERE alias = " . $db->quote($this->alias) . " AND id != " . (int)$this->id);
                $result = count($db->loadObjectList());
                if ($result > 0) {
                    $this->alias .= '-' . (int)$this->id;
                    $app->enqueueMessage(Text::_('K2_WARNING_DUPLICATE_TITLE_ALIAS_DETECTED'), 'notice');
                }
            } else {
                $db->setQuery("SELECT id FROM #__k2_items WHERE alias = " . $db->quote($this->alias));
                $result = count($db->loadObjectList());
                if ($result > 0) {
                    $this->alias .= '-' . date('YmdHi');
                    $app->enqueueMessage(Text::_('K2_WARNING_DUPLICATE_TITLE_ALIAS_DETECTED'), 'notice');
                }
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

    public function getNextOrder($where = '', $column = 'ordering')
    {
        $query = "SELECT MAX({$column}) FROM #__k2_items";
        $query .= ($where ? " WHERE " . $where : "");
        $this->_db->setQuery($query);

        /* since J4 compatibility */
        try {
            $maxord = $this->_db->loadResult();
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
            return false;
        }
        return $maxord + 1;
    }

    public function reorder($where = '', $column = 'ordering')
    {
        $w = ($where) ? " AND " . $where : "";
        $k = $this->_tbl_key;
        $query = "SELECT {$this->_tbl_key}, {$column} FROM #__k2_items WHERE {$column} > 0 {$w} ORDER BY {$column}";
        $this->_db->setQuery($query);
        /* since J4 compatibility */
        try {
            $orders = $this->_db->loadObjectList();
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
            return false;
        }

        for ($i = 0, $n = count($orders); $i < $n; $i++) {
            if ($orders[$i]->$column >= 0) {
                if ($orders[$i]->$column != $i + 1) {
                    $orders[$i]->$column = $i + 1;
                    $query = "UPDATE #__k2_items SET {$column} = " . (int)$orders[$i]->$column . " WHERE {$k} = " . $this->_db->Quote($orders[$i]->$k);
                    $this->_db->setQuery($query);
                    $this->_db->execute();
                }
            }
        }

        return true;
    }

    public function move($dirn, $where = '', $column = 'ordering')
    {
        $k = $this->_tbl_key;

        $sql = "SELECT $this->_tbl_key, {$column} FROM $this->_tbl";

        if ($dirn < 0) {
            $sql .= ' WHERE ' . $column . ' < ' . (int)$this->$column;
            $sql .= ($where ? ' AND ' . $where : '');
            $sql .= ' ORDER BY ' . $column . ' DESC';
        } elseif ($dirn > 0) {
            $sql .= ' WHERE ' . $column . ' > ' . (int)$this->$column;
            $sql .= ($where ? ' AND ' . $where : '');
            $sql .= ' ORDER BY ' . $column;
        } else {
            $sql .= ' WHERE ' . $column . ' = ' . (int)$this->$column;
            $sql .= ($where ? ' AND ' . $where : '');
            $sql .= ' ORDER BY ' . $column;
        }

        $this->_db->setQuery($sql, 0, 1);

        $row = null;
        $row = $this->_db->loadObject();

        if (isset($row)) {
            $query = 'UPDATE ' . $this->_tbl . ' SET ' . $column . ' = ' . (int)$row->$column . ' WHERE ' . $this->_tbl_key . ' = ' . $this->_db->Quote($this->$k);
            $this->_db->setQuery($query);
            /* since J4 compatibility */
            try {
                $this->_db->execute();
            } catch (Exception $e) {
                Factory::getApplication()->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
            }

            $query = 'UPDATE ' . $this->_tbl . ' SET ' . $column . ' = ' . (int)$this->$column . ' WHERE ' . $this->_tbl_key . ' = ' . $this->_db->Quote($row->$k);
            $this->_db->setQuery($query);

            /* since J4 compatibility */
            try {
                $this->_db->execute();
            } catch (Exception $e) {
                Factory::getApplication()->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
            }
            $this->$column = $row->$column;
        } else {
            $query = 'UPDATE ' . $this->_tbl . ' SET ' . $column . ' = ' . (int)$this->$column . ' WHERE ' . $this->_tbl_key . ' = ' . $this->_db->Quote($this->$k);
            $this->_db->setQuery($query);

            /* since J4 compatibility */
            try {
                $this->_db->execute();
            } catch (Exception $e) {
                Factory::getApplication()->enqueueMessage(JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()), 'ERROR');
            }
        }
        return true;
    }
}
