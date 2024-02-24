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

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Uri\Uri;

jimport('joomla.application.component.view');

class K2ViewCategory extends K2View
{
    public function display($tpl = null)
    {
        $document = Factory::getDocument();

        if (version_compare(JVERSION, '4.0.0-dev', 'lt')) HTMLHelper::_('behavior.modal');

        $model = $this->getModel();
        $category = $model->getData();
        OutputFilter::objectHTMLSafe($category, ENT_QUOTES, array('params', 'plugins'));
        if (!$category->id) {
            $category->published = 1;
        }
        $this->row = $category;

        // Editor
        /* since J4 compatibility */
		// get user editor
	    $editor = !empty(Factory::getUser()->getParam('editor')) ? Factory::getUser()->getParam('editor') : Factory::getConfig()->get('editor');
	    $wysiwyg = Editor::getInstance($editor);
	    $editor = $wysiwyg->display('description', $category->description, '100%', '250px', '', '', array('pagebreak', 'readmore'));
	    $this->catEditor = $editor;
	    $onSave = '';

        $this->onSave = $onSave;

        // JS
        $document->addScriptDeclaration("
            var K2BasePath = '" . URI::base(true) . "/';
            Joomla.submitbutton = function(pressbutton) {
                if (pressbutton == 'cancel') {
                    Joomla.submitform(pressbutton);
                    return;
                }
                if (\$K2.trim(\$K2('#name').val()) == '') {
                    alert('" . Text::_('K2_A_CATEGORY_MUST_AT_LEAST_HAVE_A_TITLE', true) . "');
                } else {
                    " . $onSave . "
                    Joomla.submitform(pressbutton);
                }
            };
        ");

        $lists = array();
        $lists['published'] = HTMLHelper::_('select.booleanlist', 'published', 'class="inputbox"', $category->published);
        $lists['access'] = HTMLHelper::_('access.level', 'access', $category->access, '', false);
        $query = 'SELECT ordering AS value, name AS text FROM #__k2_categories ORDER BY ordering';
        $lists['ordering'] = null;
        $categories[] = HTMLHelper::_('select.option', '0', Text::_('K2_NONE_ONSELECTLISTS'));

        require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/categories.php';
        $categoriesModel = K2Model::getInstance('Categories', 'K2Model');
        $tree = $categoriesModel->categoriesTree($category, true, false);
        $categories = array_merge($categories, $tree);
        $lists['parent'] = HTMLHelper::_('select.genericlist', $categories, 'parent', 'class="inputbox"', 'value', 'text', $category->parent);

        $extraFieldsModel = K2Model::getInstance('ExtraFields', 'K2Model');
        $groups = $extraFieldsModel->getGroups(true); // Fetch entire extra field group list
        $group[] = HTMLHelper::_('select.option', '0', Text::_('K2_NONE_ONSELECTLISTS'), 'id', 'name');
        $group = array_merge($group, $groups);
        $lists['extraFieldsGroup'] = HTMLHelper::_('select.genericlist', $group, 'extraFieldsGroup', 'class="inputbox" size="1" ', 'id', 'name', $category->extraFieldsGroup);

        $languages = HTMLHelper::_('contentlanguage.existing', true, true);
        $lists['language'] = HTMLHelper::_('select.genericlist', $languages, 'language', '', 'value', 'text', $category->language);

        // Plugin Events
        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */
        /* since J4 compatibility */
        $K2Plugins = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(&$category, 'category'));
        $this->K2Plugins = $K2Plugins;

        // Parameters
        $params = ComponentHelper::getParams('com_k2');
        $this->params = $params;

        jimport('joomla.form.form');
        $form = Form::getInstance('categoryForm', JPATH_COMPONENT_ADMINISTRATOR . '/models/category.xml');
		$cparams = isset($category->params) ? json_decode($category->params) : $category->params;
        $values = array('params' => $cparams);
        $form->bind($values);
        $inheritFrom = (isset($values['params']->inheritFrom)) ? $values['params']->inheritFrom : 0;
        $this->form = $form;

        $categories[0] = HTMLHelper::_('select.option', '0', Text::_('K2_NONE_ONSELECTLISTS'));
        $lists['inheritFrom'] = HTMLHelper::_('select.genericlist', $categories, 'params[inheritFrom]', 'class="inputbox"', 'value', 'text', $inheritFrom);

        $this->lists = $lists;

        // Disable Joomla menu
        Factory::getApplication()->input->set('hidemainmenu', 1);

        // Toolbar
        (Factory::getApplication()->input->getInt('cid')) ? $title = Text::_('K2_EDIT_CATEGORY') : $title = Text::_('K2_ADD_CATEGORY');
        JToolBarHelper::title($title, 'k2.png');

        JToolBarHelper::apply();
        JToolBarHelper::save();
        $saveNewIcon = 'save-new.png';
        JToolBarHelper::custom('saveAndNew', $saveNewIcon, 'save_f2.png', 'K2_SAVE_AND_NEW', false);
        JToolBarHelper::cancel();

        parent::display($tpl);
    }
}
