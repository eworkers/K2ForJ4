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

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Filter\OutputFilter;

jimport('joomla.plugin.plugin');

class plgSystemK2 extends CMSPlugin
{
    public function onAfterInitialise()
    {
        // Determine Joomla version
        if (version_compare(JVERSION, '3.0', 'ge')) {
            define('K2_JVERSION', '30');
        } elseif (version_compare(JVERSION, '2.5', 'ge')) {
            define('K2_JVERSION', '25');
        } else {
            define('K2_JVERSION', '15');
        }

        // Define K2 version & build here
        define('K2_CURRENT_VERSION', '2.11.0');
        define('K2_BUILD_ID', '20220701');
        define('K2_BUILD', '<br />[Build ' . K2_BUILD_ID . ']'); // Use '' for LTS (?) or "<br />[Build '.K2_BUILD_ID.']" for rolling build (release)

        // Define the DS constant (for backwards compatibility with old template overrides & 3rd party K2 extensions)
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        // Import Joomla classes
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');
        jimport('joomla.application.component.controller');
        jimport('joomla.application.component.model');
        jimport('joomla.application.component.view');

        // Get application & K2 component params
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $config = Factory::getConfig();
        $params = ComponentHelper::getParams('com_k2');

        // Load the K2 classes
        JLoader::register('K2Table', JPATH_ADMINISTRATOR . '/components/com_k2/tables/table.php');
        JLoader::register('K2Controller', JPATH_BASE . '/components/com_k2/controllers/controller.php');
        JLoader::register('K2Model', JPATH_ADMINISTRATOR . '/components/com_k2/models/model.php');
        if ($app->isClient('site')) {
            K2Model::addIncludePath(JPATH_SITE . '/components/com_k2/models');
        } else {
            // Fix warning under Joomla 1.5 caused by conflict in model names
            K2Model::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/models');
        }
        JLoader::register('K2View', JPATH_ADMINISTRATOR . '/components/com_k2/views/view.php');
        JLoader::register('K2HelperHTML', JPATH_ADMINISTRATOR . '/components/com_k2/helpers/html.php');
        JLoader::register('K2HelperUtilities', JPATH_SITE . '/components/com_k2/helpers/utilities.php');

        // Define JoomFish compatibility version.
        if (File::exists(JPATH_ADMINISTRATOR . '/components/com_joomfish/joomfish.php')) {
            define('K2_JF_ID', 'lang_id');
        }

        // Backend only
        if (!$app->isClient('administrator')) {
            return;
        }

        /* since J4 compatibility */
        // Moved to onBeforeCompileHead because Document not exists in Application until dispatch happen
        /*
        // K2 Metrics
        if ($app->isClient('administrator') && $params->get('gatherStatistics', 1)) {
            $option = Factory::getApplication()->input->getCmd('option');
            $view = Factory::getApplication()->input->getCmd('view');
            $viewsToRun = array('items', 'categories', 'tags', 'comments', 'users', 'usergroups', 'extrafields', 'extrafieldsgroups', '');
            if ($option == 'com_k2' && in_array($view, $viewsToRun)) {
                require_once(JPATH_ADMINISTRATOR.'/components/com_k2/helpers/stats.php');
                if (K2HelperStats::shouldLog()) {
                    K2HelperStats::getScripts();
                }
            }
        }
        */

        // --- JoomFish integration [start] ---
        $option = Factory::getApplication()->input->get('option');
        $task = Factory::getApplication()->input->get('task');
        $type = Factory::getApplication()->input->getCmd('catid');
        if ($option == 'com_joomfish') {
            CMSPlugin::loadLanguage('com_k2', JPATH_ADMINISTRATOR);
            Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/tables');

            if (($task == 'translate.apply' || $task == 'translate.save') && $type == 'k2_items') {
                $language_id = Factory::getApplication()->input->getInt('select_language_id');
                $reference_id = Factory::getApplication()->input->getInt('reference_id');
                $objects = array();
                $variables = Factory::getApplication()->input->getArray($_POST);

                foreach ($variables as $key => $value) {
                    if ((bool)stristr($key, 'K2ExtraField_')) {
                        $object = new stdClass;
                        $object->id = substr($key, 13);
                        $object->value = $value;
                        $objects[] = $object;
                    }
                }

                $extra_fields = json_encode($objects);
                $extra_fields_search = '';

                foreach ($objects as $object) {
                    $extra_fields_search .= $this->getSearchValue($object->id, $object->value);
                    $extra_fields_search .= ' ';
                }

                $user = Factory::getUser();

                $db = Factory::getDbo();
                $query = "SELECT COUNT(*) FROM #__jf_content WHERE reference_field = 'extra_fields' AND language_id = {$language_id} AND reference_id = {$reference_id} AND reference_table='k2_items'";
                $db->setQuery($query);
                $result = $db->loadResult();

                if ($result > 0) {
                    $query = "UPDATE #__jf_content SET value=" . $db->Quote($extra_fields) . " WHERE reference_field = 'extra_fields' AND language_id = {$language_id} AND reference_id = {$reference_id} AND reference_table='k2_items'";
                    $db->setQuery($query);
                    $db->execute();
                } else {
                    $modified = date("Y-m-d H:i:s");
                    $modified_by = $user->id;
                    $published = Factory::getApplication()->input->getVar('published', 0);
                    $query = "INSERT INTO #__jf_content (`id`, `language_id`, `reference_id`, `reference_table`, `reference_field` ,`value`, `original_value`, `original_text`, `modified`, `modified_by`, `published`) VALUES (NULL, {$language_id}, {$reference_id}, 'k2_items', 'extra_fields', " . $db->Quote($extra_fields) . ", '','', " . $db->Quote($modified) . ", {$modified_by}, {$published} )";
                    $db->setQuery($query);
                    $db->execute();
                }

                $query = "SELECT COUNT(*) FROM #__jf_content WHERE reference_field = 'extra_fields_search' AND language_id = {$language_id} AND reference_id = {$reference_id} AND reference_table='k2_items'";
                $db->setQuery($query);
                $result = $db->loadResult();

                if ($result > 0) {
                    $query = "UPDATE #__jf_content SET value=" . $db->Quote($extra_fields_search) . " WHERE reference_field = 'extra_fields_search' AND language_id = {$language_id} AND reference_id = {$reference_id} AND reference_table='k2_items'";
                    $db->setQuery($query);
                    $db->execute();
                } else {
                    $modified = date("Y-m-d H:i:s");
                    $modified_by = $user->id;
                    $published = Factory::getApplication()->input->getVar('published', 0);
                    $query = "INSERT INTO #__jf_content (`id`, `language_id`, `reference_id`, `reference_table`, `reference_field` ,`value`, `original_value`, `original_text`, `modified`, `modified_by`, `published`) VALUES (NULL, {$language_id}, {$reference_id}, 'k2_items', 'extra_fields_search', " . $db->Quote($extra_fields_search) . ", '','', " . $db->Quote($modified) . ", {$modified_by}, {$published} )";
                    $db->setQuery($query);
                    $db->execute();
                }
            }

            if (($task == 'translate.edit' || $task == 'translate.apply') && $type == 'k2_items') {
                if ($task == 'translate.edit') {
                    $cid = Factory::getApplication()->input->getVar('cid');
                    $array = explode('|', $cid[0]);
                    $reference_id = $array[1];
                }

                if ($task == 'translate.apply') {
                    $reference_id = Factory::getApplication()->input->getInt('reference_id');
                }

                $item = Table::getInstance('K2Item', 'Table');
                $item->load($reference_id);
                $category_id = $item->catid;
                $language_id = Factory::getApplication()->input->getInt('select_language_id');
                $category = Table::getInstance('K2Category', 'Table');
                $category->load($category_id);
                $group = $category->extraFieldsGroup;
                $db = Factory::getDbo();
                $query = "SELECT * FROM #__k2_extra_fields WHERE `group`=" . $db->Quote($group) . " AND published=1 ORDER BY ordering";
                $db->setQuery($query);
                $extraFields = $db->loadObjectList();

                $output = '';
                if (count($extraFields)) {
                    $output .= '<h1>' . Text::_('K2_EXTRA_FIELDS') . '</h1>';
                    $output .= '<h2>' . Text::_('K2_ORIGINAL') . '</h2>';
                    foreach ($extraFields as $extrafield) {
                        $extraField = json_decode($extrafield->value);
                        $output .= trim($this->renderOriginal($extrafield, $reference_id));
                    }
                }

                if (count($extraFields)) {
                    $output .= '<h2>' . Text::_('K2_TRANSLATION') . '</h2>';
                    foreach ($extraFields as $extrafield) {
                        $extraField = json_decode($extrafield->value);
                        $output .= trim($this->renderTranslated($extrafield, $reference_id));
                    }
                }

                $pattern = '/\r\n|\r|\n/';

                // Load CSS & JS
                // removed in j4 JHTML::_('behavior.framework');
                $document = Factory::getDocument();
                $document->addScriptDeclaration("
                    window.addEvent('domready', function(){
                        var target = $$('table.adminform');
                        target.setProperty('id', 'adminform');
                        var div = new Element('div', {'id': 'K2ExtraFields'}).setHTML('" . preg_replace($pattern, '', $output) . "').injectInside($('adminform'));
                    });
                ");
            }

            if (($task == 'translate.apply' || $task == 'translate.save') && $type == 'k2_extra_fields') {
                $language_id = Factory::getApplication()->input->getInt('select_language_id');
                $reference_id = Factory::getApplication()->input->getInt('reference_id');
                $extraFieldType = Factory::getApplication()->input->getVar('extraFieldType');

                $objects = array();
                $values = Factory::getApplication()->input->getVar('option_value');
                $names = Factory::getApplication()->input->getVar('option_name');
                $target = Factory::getApplication()->input->getVar('option_target');

                for ($i = 0; $i < count($values); $i++) {
                    $object = new stdClass;
                    $object->name = $names[$i];

                    if ($extraFieldType == 'select' || $extraFieldType == 'multipleSelect' || $extraFieldType == 'radio') {
                        $object->value = $i + 1;
                    } elseif ($extraFieldType == 'link') {
                        if (substr($values[$i], 0, 4) == 'http') {
                            $values[$i] = $values[$i];
                        } else {
                            $values[$i] = 'http://' . $values[$i];
                        }
                        $object->value = $values[$i];
                    } else {
                        $object->value = $values[$i];
                    }

                    $object->target = $target[$i];
                    $objects[] = $object;
                }

                $value = json_encode($objects);

                $user = Factory::getUser();

                $db = Factory::getDbo();
                $query = "SELECT COUNT(*) FROM #__jf_content WHERE reference_field = 'value' AND language_id = {$language_id} AND reference_id = {$reference_id} AND reference_table='k2_extra_fields'";
                $db->setQuery($query);
                $result = $db->loadResult();

                if ($result > 0) {
                    $query = "UPDATE #__jf_content SET value=" . $db->Quote($value) . " WHERE reference_field = 'value' AND language_id = {$language_id} AND reference_id = {$reference_id} AND reference_table='k2_extra_fields'";
                    $db->setQuery($query);
                    $db->execute();
                } else {
                    $modified = date("Y-m-d H:i:s");
                    $modified_by = $user->id;
                    $published = Factory::getApplication()->input->getVar('published', 0);
                    $query = "INSERT INTO #__jf_content (`id`, `language_id`, `reference_id`, `reference_table`, `reference_field` ,`value`, `original_value`, `original_text`, `modified`, `modified_by`, `published`) VALUES (NULL, {$language_id}, {$reference_id}, 'k2_extra_fields', 'value', " . $db->Quote($value) . ", '','', " . $db->Quote($modified) . ", {$modified_by}, {$published} )";
                    $db->setQuery($query);
                    $db->execute();
                }
            }

            if (($task == 'translate.edit' || $task == 'translate.apply') && $type == 'k2_extra_fields') {
                if ($task == 'translate.edit') {
                    $cid = Factory::getApplication()->input->getVar('cid');
                    $array = explode('|', $cid[0]);
                    $reference_id = $array[1];
                }

                if ($task == 'translate.apply') {
                    $reference_id = Factory::getApplication()->input->getInt('reference_id');
                }

                $extraField = Table::getInstance('K2ExtraField', 'Table');
                $extraField->load($reference_id);
                $language_id = Factory::getApplication()->input->getInt('select_language_id');

                if ($extraField->type == 'multipleSelect' || $extraField->type == 'select' || $extraField->type == 'radio') {
                    $subheader = '<strong>' . Text::_('K2_OPTIONS') . '</strong>';
                } else {
                    $subheader = '<strong>' . Text::_('K2_DEFAULT_VALUE') . '</strong>';
                }

                $objects = json_decode($extraField->value);
                $output = '<input type="hidden" value="' . $extraField->type . '" name="extraFieldType" />';
                if (count($objects)) {
                    $output .= '<h1>' . Text::_('K2_EXTRA_FIELDS') . '</h1>';
                    $output .= '<h2>' . Text::_('K2_ORIGINAL') . '</h2>';
                    $output .= $subheader . '<br />';

                    foreach ($objects as $object) {
                        $output .= '<p>' . $object->name . '</p>';
                        if ($extraField->type == 'textfield' || $extraField->type == 'textarea') {
                            $output .= '<p>' . $object->value . '</p>';
                        }
                    }
                }

                $db = Factory::getDbo();
                $query = "SELECT `value` FROM #__jf_content WHERE reference_field = 'value' AND language_id = {$language_id} AND reference_id = {$reference_id} AND reference_table='k2_extra_fields'";
                $db->setQuery($query);
                $result = $db->loadResult();

                $translatedObjects = json_decode($result);

                if (count($objects)) {
                    $output .= '<h2>' . Text::_('K2_TRANSLATION') . '</h2>';
                    $output .= $subheader . '<br />';
                    foreach ($objects as $key => $value) {
                        if (isset($translatedObjects[$key])) {
                            $value = $translatedObjects[$key];
                        }

                        if ($extraField->type == 'textarea') {
                            $output .= '<p><textarea name="option_name[]" cols="30" rows="15"> ' . $value->name . '</textarea></p>';
                        } else {
                            $output .= '<p><input type="text" name="option_name[]" value="' . $value->name . '" /></p>';
                        }
                        $output .= '<p><input type="hidden" name="option_value[]" value="' . $value->value . '" /></p>';
                        $output .= '<p><input type="hidden" name="option_target[]" value="' . $value->target . '" /></p>';
                    }
                }

                $pattern = '/\r\n|\r|\n/';

                // Load CSS & JS
                // removed in j4 HTMLHelper::_('behavior.framework');
                $document = Factory::getDocument();
                $document->addScriptDeclaration("
                    window.addEvent('domready', function(){
                        var target = $$('table.adminform');
                        target.setProperty('id', 'adminform');
                        var div = new Element('div', {'id': 'K2ExtraFields'}).setHTML('" . preg_replace($pattern, '', $output) . "').injectInside($('adminform'));
                    });
                ");
            }
        }
        // --- JoomFish integration [finish] ---

        return;
    }

    /* since J4 compatibility */
    // Document not exists in Application until dispatch happen
    public function onBeforeCompileHead()
    {
        // K2 Metrics
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        if ($app->isClient('administrator') && $params->get('gatherStatistics', 1)) {
            $option = Factory::getApplication()->input->getCmd('option');
            $view = Factory::getApplication()->input->getCmd('view');
            $viewsToRun = array('items', 'categories', 'tags', 'comments', 'users', 'usergroups', 'extrafields', 'extrafieldsgroups', '');
            if ($option == 'com_k2' && in_array($view, $viewsToRun)) {
                require_once(JPATH_ADMINISTRATOR . '/components/com_k2/helpers/stats.php');
                if (K2HelperStats::shouldLog()) {
                    K2HelperStats::getScripts();
                }
            }
        }
    }

    public function onAfterRoute()
    {
        $app = Factory::getApplication();
        $document = Factory::getDocument();
        $user = Factory::getUser();

        $params = ComponentHelper::getParams('com_k2');

        $basepath = ($app->isClient('site')) ? JPATH_SITE : JPATH_ADMINISTRATOR;
        CMSPlugin::loadLanguage('com_k2', $basepath);
        CMSPlugin::loadLanguage('com_k2.dates', JPATH_ADMINISTRATOR, null, true);
    }

    public function onAfterDispatch()
    {
        $app = Factory::getApplication();
        /* since J4 compatibility */
        $loadHeader = true;
        if ($app->isClient('administrator') || (Factory::getApplication()->input->getCmd('option') == 'com_k2' && (Factory::getApplication()->input->getCmd('task') == 'add' || Factory::getApplication()->input->getCmd('task') == 'edit'))) {
            $loadHeader = false;
        }
        // Load required CSS & JS
        if ($loadHeader) {
            K2HelperHTML::loadHeadIncludes();
        }


        if ($app->isClient('administrator')) {
            return;
        }

        $params = ComponentHelper::getParams('com_k2');
        if (!$params->get('K2UserProfile')) {
            return;
        }

        $document = Factory::getDocument();

        $option = Factory::getApplication()->input->getCmd('option');
        $view = Factory::getApplication()->input->getCmd('view');
        $task = Factory::getApplication()->input->getCmd('task');
        $layout = Factory::getApplication()->input->getCmd('layout');
        $user = Factory::getUser();

        // Import plugins
        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        $active = Factory::getApplication()->getMenu()->getActive();
        if (isset($active->query['layout'])) {
            $layout = $active->query['layout'];
        }

        // B/C code for reCAPTCHA
        $params->set('recaptchaV2', true);

        // Extend user forms with K2 fields
        if (($option == 'com_user' && $view == 'register') || ($option == 'com_users' && $view == 'registration')) {
            if ($params->get('recaptchaOnRegistration') && $params->get('recaptcha_public_key')) {
                $document->addScript('https://www.google.com/recaptcha/api.js?onload=onK2RecaptchaLoaded&render=explicit');
                $document->addScriptDeclaration('
                function onK2RecaptchaLoaded() {
                    grecaptcha.render("recaptcha", {
                        "sitekey": "' . $params->get('recaptcha_public_key') . '",
                        "theme": "' . $params->get('recaptcha_theme', 'light') . '"
                    });
                }
                ');
                $recaptchaClass = 'k2-recaptcha-v2';
            }

            if (!$user->guest) {
                $app->enqueueMessage(Text::_('K2_YOU_ARE_ALREADY_REGISTERED_AS_A_MEMBER'), 'notice');
                $app->redirect(JURI::root());
                $app->close();
            }
            require_once(JPATH_SITE . '/components/com_users/controller.php');
            $controller = new UsersController;

            $view = $controller->getView($view, 'html');
            $view->addTemplatePath(JPATH_SITE . '/components/com_k2/templates');
            $view->addTemplatePath(JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates');
            $view->addTemplatePath(JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2');
            // Allow temporary template loading with ?template=
            $template = Factory::getApplication()->input->getCmd('template');
            if (isset($template)) {
                $view->addTemplatePath(JPATH_SITE . '/templates/' . $template . '/html/com_k2');
            }

            $view->setLayout('register');

            $K2User = new stdClass;

            $K2User->description = '';
            $K2User->gender = 'n';
            $K2User->image = '';
            $K2User->url = '';
            $K2User->plugins = '';

            if ($params->get('K2ProfileEditor')) {
                /* since J4 compatibility */
// get user editor
                $editor = Factory::getUser()->getParam('editor', 'tinymce');
                $wysiwyg = JEditor::getInstance($editor);
                $editor = $wysiwyg->display('description', $K2User->description, '100%', '250px', '', '', false);
            } else {
                $editor = '<textarea id="description" class="k2-plain-text-editor" name="description"></textarea>';
            }
            $this->editor = $editor;

            $lists = array();
            $genderOptions[] = JHTML::_('select.option', 'n', Text::_('K2_NOT_SPECIFIED'));
            $genderOptions[] = JHTML::_('select.option', 'm', Text::_('K2_MALE'));
            $genderOptions[] = JHTML::_('select.option', 'f', Text::_('K2_FEMALE'));
            $lists['gender'] = JHTML::_('select.radiolist', $genderOptions, 'gender', '', 'value', 'text', $K2User->gender);

            $this->lists = $lists;
            $this->K2Params = $params;
            $this->recaptchaClass = $recaptchaClass;

            /* since J4 compatibility */
            $K2Plugins = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
                &$K2User,
                'user'
            ));
            $this->K2Plugins = $K2Plugins;

            $this->K2User = $K2User;
            $this->user = $user;
            $pathway = $app->getPathway();
            $pathway->setPathway(null);

            $nameFieldName = 'jform[name]';
            $this->nameFieldName = $nameFieldName;
            $usernameFieldName = 'jform[username]';
            $this->usernameFieldName = $usernameFieldName;
            $emailFieldName = 'jform[email1]';
            $this->emailFieldName = $emailFieldName;
            $passwordFieldName = 'jform[password1]';
            $this->passwordFieldName = $passwordFieldName;
            $passwordVerifyFieldName = 'jform[password2]';
            $this->passwordVerifyFieldName = $passwordVerifyFieldName;
            $optionValue = 'com_users';
            $this->optionValue = $optionValue;
            $taskValue = 'registration.register';
            $this->taskValue = $taskValue;
            ob_start();
            $view->display();
            $contents = ob_get_clean();
            $document->setBuffer($contents, 'component');
        }

        if (($option == 'com_user' && $view == 'user' && ($task == 'edit' || $layout == 'form')) || ($option == 'com_users' && $view == 'profile' && ($layout == 'edit' || $task == 'profile.edit'))) {
            if ($user->guest) {
                $uri = Uri::getInstance();

                $url = 'index.php?option=com_users&view=login&return=' . base64_encode($uri->toString());
                $app->enqueueMessage(Text::_('K2_YOU_NEED_TO_LOGIN_FIRST'), 'notice');
                $app->redirect(Route::_($url, false));
            }

            require_once(JPATH_SITE . '/components/com_users/controller.php');
            $controller = new UsersController;

            $view = $controller->getView($view, 'html');
            $view->addTemplatePath(JPATH_SITE . '/components/com_k2/templates');
            $view->addTemplatePath(JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates');
            $view->addTemplatePath(JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2');
            // Allow temporary template loading with ?template=
            $template = Factory::getApplication()->input->getCmd('template');
            if (isset($template)) {
                $view->addTemplatePath(JPATH_SITE . '/templates/' . $template . '/html/com_k2');
            }

            $view->setLayout('profile');

            $model = K2Model::getInstance('Itemlist', 'K2Model');
            $K2User = $model->getUserProfile($user->id);
            if (!is_object($K2User)) {
                $K2User = new stdClass;
                $K2User->description = '';
                $K2User->gender = 'n';
                $K2User->url = '';
                $K2User->image = null;
            }
            OutputFilter::objectHTMLSafe($K2User);

            if ($params->get('K2ProfileEditor')) {
                /* since J4 compatibility */
// get user editor
                $editor = Factory::getUser()->getParam('editor', 'tinymce');
                $wysiwyg = JEditor::getInstance($editor);
                $editor = $wysiwyg->display('description', $K2User->description, '100%', '250px', '', '', false);
            } else {
                $editor = '<textarea id="description" class="k2-plain-text-editor" name="description"></textarea>';
            }
            $this->editor = $editor;

            $lists = array();
            $genderOptions[] = JHTML::_('select.option', 'n', Text::_('K2_NOT_SPECIFIED'));
            $genderOptions[] = JHTML::_('select.option', 'm', Text::_('K2_MALE'));
            $genderOptions[] = JHTML::_('select.option', 'f', Text::_('K2_FEMALE'));
            $lists['gender'] = JHTML::_('select.radiolist', $genderOptions, 'gender', '', 'value', 'text', $K2User->gender);

            $this->lists = $lists;

            /* since J4 compatibility */
            $K2Plugins = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
                &$K2User,
                'user'
            ));
            $this->K2Plugins = $K2Plugins;

            $this->K2User = $K2User;
            $this->K2Params = $params;

            // Asssign some variables depending on Joomla version
            $nameFieldName = 'jform[name]';
            $this->nameFieldName = $nameFieldName;
            $emailFieldName = 'jform[email1]';
            $this->emailFieldName = $emailFieldName;
            $passwordFieldName = 'jform[password1]';
            $this->passwordFieldName = $passwordFieldName;
            $passwordVerifyFieldName = 'jform[password2]';
            $this->passwordVerifyFieldName = $passwordVerifyFieldName;
            $usernameFieldName = 'jform[username]';
            $this->usernameFieldName = $usernameFieldName;
            $idFieldName = 'jform[id]';
            $this->idFieldName = $idFieldName;
            $optionValue = 'com_users';
            $this->optionValue = $optionValue;
            $taskValue = 'profile.save';
            $this->taskValue = $taskValue;

            ob_start();
            $active = Factory::getApplication()->getMenu()->getActive();
            if (isset($active->query['layout']) && $active->query['layout'] != 'profile') {
                $active->query['layout'] = 'profile';
            }
            $this->user = $user;
            $view->display();
            $contents = ob_get_clean();
            $document->setBuffer($contents, 'component');
        }
    }

    public function onAfterRender()
    {
        $app = Factory::getApplication();

        if ($app->isClient('site')) {
            $config = Factory::getConfig();
            $document = Factory::getDocument();
            $user = Factory::getUser();
            $params = ComponentHelper::getParams('com_k2');
            $response = JFactory::getApplication()->getBody();

            // Use proper headers for JSON/JSONP
            if (Factory::getApplication()->input->getCmd('format') == 'json') {

                if (Factory::getApplication()->input->getCmd('callback')) {
                    $document->setMimeEncoding('application/javascript');
                }
            }

            // Check caching state in Joomla
            $cacheTime = 0;
            $caching = $config->get('caching');
            $cacheTime = $config->get('cachetime');
            $cacheTTL = $cacheTime * 60;

            // Set caching HTTP headers
            if ($user->guest) {
                if ($caching) {
                    JFactory::getApplication()->allowCache(true);
                    JFactory::getApplication()->setHeader('Cache-Control', 'public, max-age=' . $cacheTTL . ', stale-while-revalidate=' . ($cacheTTL * 2) . ', stale-if-error=' . ($cacheTTL * 5), true);
                    JFactory::getApplication()->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + $cacheTTL) . ' GMT', true);
                    JFactory::getApplication()->setHeader('Pragma', 'public', true);
                }
                JFactory::getApplication()->setHeader('X-Logged-In', 'False', true);
            } else {
                JFactory::getApplication()->setHeader('X-Logged-In', 'True', true);
            }
            JFactory::getApplication()->setHeader('X-Content-Powered-By', 'K2 v' . K2_CURRENT_VERSION . ' (by JoomlaWorks)', true);

            // Set additional caching HTTP headers defined as custom script tag in the <head>
            if ($caching) {
                preg_match("#<script type=\"application/x\-k2\-headers\">(.*?)</script>#is", $response, $getK2CacheHeaders);
                if (is_array($getK2CacheHeaders) && !empty($getK2CacheHeaders[1])) {
                    $getK2CacheHeaders = json_decode(trim($getK2CacheHeaders[1]));
                    if (is_object($getK2CacheHeaders)) {
                        JFactory::getApplication()->allowCache(true);
                        foreach ($getK2CacheHeaders as $type => $value) {
                            JFactory::getApplication()->setHeader($type, $value, true);
                        }
                    }
                }
            }

            // OpenGraph meta tags
            if ($params->get('facebookMetatags', 1)) {
                $searches = array(
                    '<meta name="og:url"',
                    '<meta name="og:title"',
                    '<meta name="og:type"',
                    '<meta name="og:image"',
                    '<meta name="og:description"'
                );
                $replacements = array(
                    '<meta property="og:url"',
                    '<meta property="og:title"',
                    '<meta property="og:type"',
                    '<meta property="og:image"',
                    '<meta property="og:description"'
                );
                if (strpos($response, 'http://ogp.me/ns#') === false) {
                    $searches[] = '<html ';
                    $searches[] = '<html>';
                    $replacements[] = '<html prefix="og: http://ogp.me/ns#" ';
                    $replacements[] = '<html prefix="og: http://ogp.me/ns#">';
                }
                $response = str_ireplace($searches, $replacements, $response);
                JFactory::getApplication()->setBody($response);
            }
        }
    }



    /* ============================================ */
    /* ============= Helper Functions ============= */
    /* ============================================ */

    public function getSearchValue($id, $currentValue)
    {
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/tables');
        $row = Table::getInstance('K2ExtraField', 'Table');
        $row->load($id);
        $jsonObject = json_decode($row->value);
        $value = '';
        if ($row->type == 'textfield' || $row->type == 'textarea') {
            $value = $currentValue;
        } elseif ($row->type == 'multipleSelect' || $row->type == 'link') {
            foreach ($jsonObject as $option) {
                if (@in_array($option->value, $currentValue)) {
                    $value .= $option->name . ' ';
                }
            }
        } else {
            foreach ($jsonObject as $option) {
                if ($option->value == $currentValue) {
                    $value .= $option->name;
                }
            }
        }
        return $value;
    }

    public function renderOriginal($extraField, $itemID)
    {
        $app = Factory::getApplication();
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/tables');
        $item = Table::getInstance('K2Item', 'Table');
        $item->load($itemID);

        $defaultValues = json_decode($extraField->value);

        foreach ($defaultValues as $value) {
            if ($extraField->type == 'textfield' || $extraField->type == 'textarea') {
                $active = $value->value;
            } elseif ($extraField->type == 'link') {
                $active[0] = $value->name;
                $active[1] = $value->value;
                $active[2] = $value->target;
            } else {
                $active = '';
            }
        }

        if (isset($item)) {
            $currentValues = json_decode($item->extra_fields);
            if (count($currentValues)) {
                foreach ($currentValues as $value) {
                    if ($value->id == $extraField->id) {
                        $active = $value->value;
                    }
                }
            }
        }

        $output = '';

        switch ($extraField->type) {
            case 'textfield':
                $output = '<div><strong>' . $extraField->name . '</strong><br /><input type="text" disabled="disabled" name="OriginalK2ExtraField_' . $extraField->id . '" value="' . $active . '" /></div><br /><br />';
                break;

            case 'textarea':
                $output = '<div><strong>' . $extraField->name . '</strong><br /><textarea disabled="disabled" name="OriginalK2ExtraField_' . $extraField->id . '" rows="10" cols="40">' . $active . '</textarea></div><br /><br />';
                break;

            case 'link':
                $output = '<div><strong>' . $extraField->name . '</strong><br /><input disabled="disabled" type="text" name="OriginalK2ExtraField_' . $extraField->id . '[]" value="' . $active[0] . '" /></div><br /><br />';
                break;
        }

        return $output;
    }

    public function renderTranslated($extraField, $itemID)
    {
        $app = Factory::getApplication();
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/tables');
        $item = Table::getInstance('K2Item', 'Table');
        $item->load($itemID);

        $defaultValues = json_decode($extraField->value);

        foreach ($defaultValues as $value) {
            if ($extraField->type == 'textfield' || $extraField->type == 'textarea') {
                $active = $value->value;
            } elseif ($extraField->type == 'link') {
                $active[0] = $value->name;
                $active[1] = $value->value;
                $active[2] = $value->target;
            } else {
                $active = '';
            }
        }

        if (isset($item)) {
            $currentValues = json_decode($item->extra_fields);
            if (count($currentValues)) {
                foreach ($currentValues as $value) {
                    if ($value->id == $extraField->id) {
                        $active = $value->value;
                    }
                }
            }
        }

        $language_id = Factory::getApplication()->input->getInt('select_language_id');
        $db = Factory::getDbo();
        $query = "SELECT `value` FROM #__jf_content WHERE reference_field = 'extra_fields' AND language_id = {$language_id} AND reference_id = {$itemID} AND reference_table='k2_items'";
        $db->setQuery($query);
        $result = $db->loadResult();
        $currentValues = json_decode($result);
        if (count($currentValues)) {
            foreach ($currentValues as $value) {
                if ($value->id == $extraField->id) {
                    $active = $value->value;
                }
            }
        }

        $output = '';

        switch ($extraField->type) {
            case 'textfield':
                $output = '<div><strong>' . $extraField->name . '</strong><br /><input type="text" name="K2ExtraField_' . $extraField->id . '" value="' . $active . '" /></div><br /><br />';
                break;

            case 'textarea':
                $output = '<div><strong>' . $extraField->name . '</strong><br /><textarea name="K2ExtraField_' . $extraField->id . '" rows="10" cols="40">' . $active . '</textarea></div><br /><br />';
                break;

            case 'select':
                $output = '<div style="display:none;">' . JHTML::_('select.genericlist', $defaultValues, 'K2ExtraField_' . $extraField->id, '', 'value', 'name', $active) . '</div>';
                break;

            case 'multipleSelect':
                $output = '<div style="display:none;">' . JHTML::_('select.genericlist', $defaultValues, 'K2ExtraField_' . $extraField->id . '[]', 'multiple="multiple"', 'value', 'name', $active) . '</div>';
                break;

            case 'radio':
                $output = '<div style="display:none;">' . JHTML::_('select.radiolist', $defaultValues, 'K2ExtraField_' . $extraField->id, '', 'value', 'name', $active) . '</div>';
                break;

            case 'link':
                $output = '<div><strong>' . $extraField->name . '</strong><br /><input type="text" name="K2ExtraField_' . $extraField->id . '[]" value="' . $active[0] . '" /><br /><input type="hidden" name="K2ExtraField_' . $extraField->id . '[]" value="' . $active[1] . '" /><br /><input type="hidden" name="K2ExtraField_' . $extraField->id . '[]" value="' . $active[2] . '" /></div><br /><br />';
                break;
        }

        return $output;
    }
}
