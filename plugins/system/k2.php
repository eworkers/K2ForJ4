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

        $app = Factory::getApplication();
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
            if (version_compare(JVERSION, '4.0.0-dev', 'ge'))
            {

                $view = new Joomla\Component\Users\Site\View\Registration\HtmlView();
                $view->setModel( new Joomla\Component\Users\Site\Model\RegistrationModel(), true);
                $view->document = Factory::getApplication()->getDocument();
            }
            else
            {
                require_once(JPATH_SITE . '/components/com_users/controller.php');
                $controller = new UsersController;
                $view = $controller->getView($view, 'html');
            }

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
            $view->editor = $editor;

            $lists = array();
            $genderOptions[] = JHTML::_('select.option', 'n', Text::_('K2_NOT_SPECIFIED'));
            $genderOptions[] = JHTML::_('select.option', 'm', Text::_('K2_MALE'));
            $genderOptions[] = JHTML::_('select.option', 'f', Text::_('K2_FEMALE'));
            $lists['gender'] = JHTML::_('select.radiolist', $genderOptions, 'gender', '', 'value', 'text', $K2User->gender);

            $view->lists = $lists;
            $view->K2Params = $params;
            $view->recaptchaClass = $recaptchaClass;

            /* since J4 compatibility */
            $K2Plugins = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
                &$K2User,
                'user'
            ));
            $view->K2Plugins = $K2Plugins;

            $view->K2User = $K2User;
            $view->user = $user;
            $pathway = $app->getPathway();
            $pathway->setPathway(null);

            $nameFieldName = 'jform[name]';
            $view->nameFieldName = $nameFieldName;
            $usernameFieldName = 'jform[username]';
            $view->usernameFieldName = $usernameFieldName;
            $emailFieldName = 'jform[email1]';
            $view->emailFieldName = $emailFieldName;
            $passwordFieldName = 'jform[password1]';
            $view->passwordFieldName = $passwordFieldName;
            $passwordVerifyFieldName = 'jform[password2]';
            $view->passwordVerifyFieldName = $passwordVerifyFieldName;
            $optionValue = 'com_users';
            $view->optionValue = $optionValue;
            $taskValue = 'registration.register';
            $view->taskValue = $taskValue;
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


            if (version_compare(JVERSION, '4.0.0-dev', 'ge'))
            {
                $uri = Uri::getInstance();
                if($uri->getVar('task') == 'method.add'){
                    return;
                }
                $view = new Joomla\Component\Users\Site\View\Profile\HtmlView();
                $view->setModel( new Joomla\Component\Users\Site\Model\ProfileModel(), true);
                $view->document = Factory::getApplication()->getDocument();
            }
            else
            {
                require_once(JPATH_SITE . '/components/com_users/controller.php');
                $controller = new UsersController;
                $view = $controller->getView($view, 'html');
            }

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
                $editor = !empty(Factory::getUser()->getParam('editor')) ? Factory::getUser()->getParam('editor') : Factory::getConfig()->get('editor');
                $wysiwyg = JEditor::getInstance($editor);
                $editor = $wysiwyg->display('description', $K2User->description, '100%', '250px', '', '', false);
            } else {
                $editor = '<textarea id="description" class="k2-plain-text-editor" name="description">'.$K2User->description.'</textarea>';
            }
            $view->editor = $editor;

            $lists = array();
            $genderOptions[] = JHTML::_('select.option', 'n', Text::_('K2_NOT_SPECIFIED'));
            $genderOptions[] = JHTML::_('select.option', 'm', Text::_('K2_MALE'));
            $genderOptions[] = JHTML::_('select.option', 'f', Text::_('K2_FEMALE'));
            $lists['gender'] = JHTML::_('select.radiolist', $genderOptions, 'gender', '', 'value', 'text', $K2User->gender);

            $view->lists = $lists;

            /* since J4 compatibility */
            $K2Plugins = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
                &$K2User,
                'user'
            ));

            $view->K2Plugins = $K2Plugins;

            $view->K2User = $K2User;
            $view->K2Params = $params;

            // Asssign some variables depending on Joomla version
            $nameFieldName = 'jform[name]';
            $view->nameFieldName = $nameFieldName;
            $emailFieldName = 'jform[email1]';
            $view->emailFieldName = $emailFieldName;
            $passwordFieldName = 'jform[password1]';
            $view->passwordFieldName = $passwordFieldName;
            $passwordVerifyFieldName = 'jform[password2]';
            $view->passwordVerifyFieldName = $passwordVerifyFieldName;
            $usernameFieldName = 'jform[username]';
            $view->usernameFieldName = $usernameFieldName;
            $idFieldName = 'jform[id]';
            $view->idFieldName = $idFieldName;
            $optionValue = 'com_users';
            $view->optionValue = $optionValue;
            $taskValue = 'profile.save';
            $view->taskValue = $taskValue;

            ob_start();
            $active = Factory::getApplication()->getMenu()->getActive();
            if (version_compare(JVERSION, '4.0.0-dev', 'ge'))
            {
                if (isset($active->query['layout']) && $active->query['layout'] != 'j4profile') {
                    $active->query['layout'] = 'j4profile';
                }
            }
            else{
                if (isset($active->query['layout']) && $active->query['layout'] != 'profile') {
                    $active->query['layout'] = 'profile';
                }
            }
            $view->user = $user;

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
