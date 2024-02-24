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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;

jimport('joomla.application.component.view');

class K2ViewItem extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $document = Factory::getDocument();
        $user = Factory::getUser();

        $db = Factory::getDbo();
        $view = Factory::getApplication()->input->getCmd('view');
        $task = Factory::getApplication()->input->getCmd('task');

        $params = ComponentHelper::getParams('com_k2');

        jimport('joomla.filesystem.file');
        jimport('joomla.html.pane');

        HTMLHelper::_('behavior.keepalive');
        if (version_compare(JVERSION, '4.0.0-dev', 'lt')) HTMLHelper::_('behavior.modal');

        K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $model = K2Model::getInstance('Item', 'K2Model', array('table_path' => JPATH_COMPONENT_ADMINISTRATOR . '/tables'));
        $item = $model->getData();
        OutputFilter::objectHTMLSafe($item, ENT_QUOTES, array(
            'video',
            'params',
            'plugins',
            'metadata'
        ));

        // Permissions check for frontend editing
        if ($app->isClient('site')) {
            JLoader::register('K2HelperPermissions', JPATH_COMPONENT . '/helpers/permissions.php');
            if ($task == 'edit' && !K2HelperPermissions::canEditItem($item->created_by, $item->catid)) {
                throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
            }
            if ($task == 'add' && !K2HelperPermissions::canAddItem()) {
                throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
            }

            // Get user permissions
            $K2Permissions = K2Permissions::getInstance();
            $this->permissions = $K2Permissions->permissions;

            // Build permissions message
            $permissionsLabels = array();
            if ($this->permissions->get('add')) {
                $permissionsLabels[] = Text::_('K2_ADD_ITEMS');
            }
            if ($this->permissions->get('editOwn')) {
                $permissionsLabels[] = Text::_('K2_EDIT_OWN_ITEMS');
            }
            if ($this->permissions->get('editAll')) {
                $permissionsLabels[] = Text::_('K2_EDIT_ANY_ITEM');
            }
            if ($this->permissions->get('publish')) {
                $permissionsLabels[] = Text::_('K2_PUBLISH_ITEMS');
            }
            if ($this->permissions->get('editPublished')) {
                $permissionsLabels[] = Text::_('K2_ALLOW_EDITING_OF_ALREADY_PUBLISHED_ITEMS');
            }

            $permissionsMessage = Text::_('K2_YOU_ARE_ALLOWED_TO') . ' ' . implode(', ', $permissionsLabels);

            $this->permissionsMessage = $permissionsMessage;
        }

        if ($item->isCheckedOut($user->get('id'), $item->checked_out)) {
            $message = Text::_('K2_THE_ITEM') . ': ' . $item->title . ' ' . Text::_('K2_IS_CURRENTLY_BEING_EDITED_BY_ANOTHER_ADMINISTRATOR');
            $url = ($app->isClient('site')) ? 'index.php?option=com_k2&view=item&id=' . $item->id . '&tmpl=component' : 'index.php?option=com_k2';
            $app->enqueueMessage($message);
            $app->redirect($url);
        }

        if ($item->id) {
            $item->checkout($user->get('id'));
        } else {
            $item->published = 1;
            $item->publish_down = $db->getNullDate();
            $item->modified = $db->getNullDate();
            $date = Factory::getDate();
            $now = $date->toSql();
            $item->created = $now;
            $item->publish_up = $item->created;
        }

        $lists = array();
        $dateFormat = 'Y-m-d H:i:s';

        // Date/time
        $created = $item->created;
        $publishUp = $item->publish_up;
        $publishDown = $item->publish_down;

        $created = HTMLHelper::_('date', $item->created, $dateFormat);
        $publishUp = HTMLHelper::_('date', $item->publish_up, $dateFormat);
        if ((int)$item->publish_down) {
            $publishDown = HTMLHelper::_('date', $item->publish_down, $dateFormat);
        } else {
            $publishDown = '';
        }

        $lists['createdCalendar'] = $created;
        $lists['publish_up'] = $publishUp;
        $lists['publish_down'] = $publishDown;

        if ($item->id) {
            $lists['created'] = HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC2'));
        } else {
            $lists['created'] = Text::_('K2_NEW_ITEM');
        }

        if ($item->modified == $db->getNullDate() || !$item->id) {
            $lists['modified'] = Text::_('K2_NEVER');
        } else {
            $lists['modified'] = HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC2'));
        }

        // Editors
        /* since J4 compatibility */
	// get user editor
        $editor = !empty(Factory::getUser()->getParam('editor')) ? Factory::getUser()->getParam('editor') : Factory::getConfig()->get('editor');
        $wysiwyg = Editor::getInstance($editor);
        $onSave = '';
        if ($params->get("mergeEditors")) {
            if (isset($item->fulltext) && StringHelper::strlen($item->fulltext) > 1) {
                $textValue = $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext;
            } else {
                $textValue = $item->introtext;
            }
            $text = $wysiwyg->display('text', $textValue, '100%', '400px', '', '');
            $this->text = $text;
        } else {
            $introtext = $wysiwyg->display('introtext', $item->introtext, '100%', '400px', '', '', array('readmore'));
            $this->introtext = $introtext;
            $fulltext = $wysiwyg->display('fulltext', $item->fulltext, '100%', '400px', '', '', array('readmore'));
            $this->fulltext = $fulltext;
        }

        // Publishing
        $lists['published'] = HTMLHelper::_('select.booleanlist', 'published', 'class="inputbox"', $item->published);
        $lists['featured'] = HTMLHelper::_('select.booleanlist', 'featured', 'class="inputbox"', $item->featured);
        $lists['access'] = HTMLHelper::_('access.level', 'access', $item->access, '', false);

        $query = "SELECT ordering AS value, title AS text FROM #__k2_items WHERE catid={$item->catid}";
        $lists['ordering'] = null;

        if (!$item->id) {
            $item->catid = $app->getUserStateFromRequest('com_k2itemsfilter_category', 'catid', 0, 'int');
        }

        require_once JPATH_ADMINISTRATOR . '/components/com_k2/models/categories.php';
        $categoriesModel = K2Model::getInstance('Categories', 'K2Model');
        $categories = $categoriesModel->categoriesTree();
        $lists['catid'] = HTMLHelper::_('select.genericlist', $categories, 'catid', 'class="inputbox"', 'value', 'text', $item->catid);

        $languages = HTMLHelper::_('contentlanguage.existing', true, true);
        $lists['language'] = HTMLHelper::_('select.genericlist', $languages, 'language', '', 'value', 'text', $item->language);

        $lists['checkSIG'] = $model->checkSIG();
        $lists['checkAllVideos'] = $model->checkAllVideos();

        // Media (incl. tab offset)
        $remoteVideo = false;
        $providerVideo = false;
        $embedVideo = false;

        if (!$remoteVideo && !$providerVideo && !$embedVideo) {
            $options['startOffset'] = 0;
        }

        if (!empty($item->video) && stristr($item->video, 'remote}') !== false) {
            $remoteVideo = true;
            $options['startOffset'] = 1;
        }
        $lists['remoteVideo'] = ($remoteVideo) ? preg_replace('%\{[a-z0-9-_]*\}(.*)\{/[a-z0-9-_]*\}%i', '\1', $item->video) : '';
        $lists['remoteVideoType'] = ($remoteVideo) ? preg_replace('%\{([a-z0-9-_]*)\}.*\{/[a-z0-9-_]*\}%i', '\1', $item->video) : '';

        $providers = $model->getVideoProviders();
        $providersOptions = array();
        if (count($providers)) {
            foreach ($providers as $provider) {
                $providersOptions[] = HTMLHelper::_('select.option', $provider, ucfirst($provider));
                if (!empty($item->video) && stristr($item->video, "{{$provider}}") !== false) {
                    $providerVideo = true;
                    $options['startOffset'] = 2;
                }
            }
        }
        $lists['providerVideo'] = ($providerVideo) ? preg_replace('%\{[a-z0-9-_]*\}(.*)\{/[a-z0-9-_]*\}%i', '\1', $item->video) : '';
        $lists['providerVideoType'] = ($providerVideo) ? preg_replace('%\{([a-z0-9-_]*)\}.*\{/[a-z0-9-_]*\}%i', '\1', $item->video) : '';
        if (count($providersOptions)) {
            $lists['providers'] = HTMLHelper::_('select.genericlist', $providersOptions, 'videoProvider', '', 'value', 'text', $lists['providerVideoType']);
        }

        if (!empty($item->video) && StringHelper::substr($item->video, 0, 1) !== '{') {
            $embedVideo = true;
            $options['startOffset'] = 3;
        }
        $lists['embedVideo'] = ($embedVideo) ? $item->video : '';

        $lists['uploadedVideo'] = (!empty($item->video) && !$remoteVideo && !$providerVideo && !$embedVideo) ? $item->video : '';

        // Load plugins
        PluginHelper::importPlugin('content', 'jw_sigpro');
        PluginHelper::importPlugin('content', 'jw_allvideos');

        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        // For SIGPro
        if (isset($item->gallery) && (StringHelper::strpos($item->gallery, 'http://') || StringHelper::strpos($item->gallery, 'https://'))) {
            $item->galleryType = 'flickr';
            $item->galleryValue = StringHelper::substr($item->gallery, 9);
            $item->galleryValue = StringHelper::substr($item->galleryValue, 0, -10);
        } else {
            $item->galleryType = 'server';
            $item->galleryValue = '';
        }
        $params->set('galleries_rootfolder', 'media/k2/galleries');
        $item->text = $item->gallery;
        /* since J4 compatibility */
        Factory::getApplication()->triggerEvent('onContentPrepare', array(
            'com_k2.' . $view,
            &$item,
            &$params,
            null
        ));
        $item->gallery = $item->text;

        // For AllVideos
        if (!$embedVideo) {
            $params->set('vfolder', 'media/k2/videos');
            $params->set('afolder', 'media/k2/audio');
            if (isset($item->video) && StringHelper::strpos($item->video, 'remote}')) {
                preg_match("#}(.*?){/#s", $item->video, $matches);
                if (StringHelper::substr($matches[1], 0, 7) != 'http://' || StringHelper::substr($matches[1], 0, 8) != 'https://') {
                    $item->video = str_replace($matches[1], URI::root() . $matches[1], $item->video);
                }
            }
            $item->text = $item->video;

            /* since J4 compatibility */
            Factory::getApplication()->triggerEvent('onContentPrepare', array(
                'com_k2.' . $view,
                &$item,
                &$params,
                null
            ));

            $item->video = $item->text;
        }

        // Author
        if (isset($item->created_by)) {
            $author = User::getInstance($item->created_by);
            $item->author = $author->name;
        } else {
            $item->author = $user->name;
        }
        if (isset($item->modified_by)) {
            $moderator = User::getInstance($item->modified_by);
            $item->moderator = $moderator->name;
        }
        if ($item->id) {
            $active = $item->created_by;
        } else {
            $active = $user->id;
        }

        // Category
        $categories_option[] = HTMLHelper::_('select.option', 0, Text::_('K2_SELECT_CATEGORY'));
        $categories = $categoriesModel->categoriesTree(null, true, false);
        if ($app->isClient('site')) {
            JLoader::register('K2HelperPermissions', JPATH_SITE . '/components/com_k2/helpers/permissions.php');
            if (($task == 'add' || $task == 'edit') && !K2HelperPermissions::canAddToAll()) {
                for ($i = 0; $i < count($categories); $i++) {
                    if (!K2HelperPermissions::canAddItem($categories[$i]->value) && $task == 'add') {
                        $categories[$i]->disable = true;
                    }
                    if (!K2HelperPermissions::canEditItem($item->created_by, $categories[$i]->value) && $task == 'edit') {
                        $categories[$i]->disable = true;
                    }
                }
            }
        }
        $categories_options = @array_merge($categories_option, $categories);
        $lists['categories'] = HTMLHelper::_('select.genericlist', $categories_options, 'catid', '', 'value', 'text', $item->catid);

        Table::addIncludePath(JPATH_COMPONENT . '/tables');
        $category = Table::getInstance('K2Category', 'Table');
        $category->load($item->catid);

        // Extra fields
        $extraFieldModel = K2Model::getInstance('ExtraField', 'K2Model');
        if ($category->id) {
            $extraFields = $extraFieldModel->getExtraFieldsByGroup($category->extraFieldsGroup);
        } else {
            $extraFields = array();
        }

        for ($i = 0; $i < count($extraFields); $i++) {
            $extraFields[$i]->element = $extraFieldModel->renderExtraField($extraFields[$i], $item->id);
        }

        // Attachments
        if ($item->id) {
            $item->attachments = $model->getAttachments($item->id);
            $rating = $model->getRating();
            if (is_null($rating)) {
                $item->ratingSum = 0;
                $item->ratingCount = 0;
            } else {
                $item->ratingSum = (int)$rating->rating_sum;
                $item->ratingCount = (int)$rating->rating_count;
            }
        } else {
            $item->attachments = null;
            $item->ratingSum = 0;
            $item->ratingCount = 0;
        }

        // Tags
        if ($params->get('taggingSystem') === '0' || $params->get('taggingSystem') === '1') {
            // B/C - Convert old options
            $whichTaggingSystem = ($params->get('taggingSystem')) ? 'free' : 'selection';
            $params->set('taggingSystem', $whichTaggingSystem);
        }
        if ($user->gid < 24 && $params->get('lockTags')) {
            $params->set('taggingSystem', 'selection');
        }

        $tags = $model->getAvailableTags($item->id);
        $lists['tags'] = HTMLHelper::_('select.genericlist', $tags, 'tags', 'multiple="multiple" size="10" ', 'id', 'name');

        if (isset($item->id)) {
            $item->tags = $model->getCurrentTags($item->id);
            $lists['selectedTags'] = HTMLHelper::_('select.genericlist', $item->tags, 'selectedTags[]', 'multiple="multiple" size="10" ', 'id', 'name');
        } else {
            $lists['selectedTags'] = '<select size="10" multiple="multiple" id="selectedTags" name="selectedTags[]"></select>';
        }

        // Metadata
        $lists['metadata'] = class_exists('JParameter') ? new JParameter($item->metadata) : new Registry($item->metadata);
        /*
        // J3.x compatible only
        $metaRobotsOptions = array(
            '' => Text::_('K2_USE_GLOBAL'),
            'index, follow' => Text::_('K2_METADATA_ROBOTS_INDEX_FOLLOW'),
            'index, nofollow' => Text::_('K2_METADATA_ROBOTS_INDEX_NOFOLLOW'),
            'noindex, follow' => Text::_('K2_METADATA_ROBOTS_NOINDEX_FOLLOW'),
            'noindex, nofollow' => Text::_('K2_METADATA_ROBOTS_NOINDEX_NOFOLLOW')
        );
        */
        $metaRobotsOptions = array();
        $metaRobotsOptions[] = HTMLHelper::_('select.option', '', Text::_('K2_USE_GLOBAL'));
        $metaRobotsOptions[] = HTMLHelper::_('select.option', 'index, follow', Text::_('K2_METADATA_ROBOTS_INDEX_FOLLOW'));
        $metaRobotsOptions[] = HTMLHelper::_('select.option', 'index, nofollow', Text::_('K2_METADATA_ROBOTS_INDEX_NOFOLLOW'));
        $metaRobotsOptions[] = HTMLHelper::_('select.option', 'noindex, follow', Text::_('K2_METADATA_ROBOTS_NOINDEX_FOLLOW'));
        $metaRobotsOptions[] = HTMLHelper::_('select.option', 'noindex, nofollow', Text::_('K2_METADATA_ROBOTS_NOINDEX_NOFOLLOW'));
        $lists['metarobots'] = HTMLHelper::_('select.genericlist', $metaRobotsOptions, 'meta[robots]', 'class="inputbox"', 'value', 'text', $lists['metadata']->get('robots'));

        // Image
        $date = Factory::getDate($item->modified);
        $timestamp = '?t=' . $date->toUnix();

        if (File::exists(JPATH_SITE . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_Generic.jpg')) {
            $item->thumb = URI::root() . 'media/k2/items/cache/' . md5("Image" . $item->id) . '_Generic.jpg' . $timestamp;
        }
        if (File::exists(JPATH_SITE . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_XL.jpg')) {
            $item->image = URI::root() . 'media/k2/items/cache/' . md5("Image" . $item->id) . '_XL.jpg' . $timestamp;
        }

        // Plugin Events
        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        /* since J4 compatibility */
        $K2PluginsItemContent = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
            &$item,
            'item',
            'content'
        ));
        $this->K2PluginsItemContent = $K2PluginsItemContent;

        /* since J4 compatibility */
        $K2PluginsItemImage = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
            &$item,
            'item',
            'image'
        ));
        $this->K2PluginsItemImage = $K2PluginsItemImage;

        /* since J4 compatibility */
        $K2PluginsItemGallery = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
            &$item,
            'item',
            'gallery'
        ));
        $this->K2PluginsItemGallery = $K2PluginsItemGallery;

        /* since J4 compatibility */
        $K2PluginsItemVideo = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
            &$item,
            'item',
            'video'
        ));
        $this->K2PluginsItemVideo = $K2PluginsItemVideo;

        /* since J4 compatibility */
        $K2PluginsItemExtraFields = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
            &$item,
            'item',
            'extra-fields'
        ));
        $this->K2PluginsItemExtraFields = $K2PluginsItemExtraFields;

        /* since J4 compatibility */
        $K2PluginsItemAttachments = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
            &$item,
            'item',
            'attachments'
        ));
        $this->K2PluginsItemAttachments = $K2PluginsItemAttachments;

        /* since J4 compatibility */
        $K2PluginsItemOther = Factory::getApplication()->triggerEvent('onRenderAdminForm', array(
            &$item,
            'item',
            'other'
        ));
        $this->K2PluginsItemOther = $K2PluginsItemOther;

        // Parameters
        jimport('joomla.form.form');
        $form = Form::getInstance('itemForm', JPATH_COMPONENT_ADMINISTRATOR . '/models/item.xml');
        $values = array('params' => !empty($item->params) ? json_decode($item->params) : null);
        if (!empty($item->params)) {
            $form->bind($values);
        }

        $this->form = $form;

        $nullDate = $db->getNullDate();
        $this->nullDate = $nullDate;

        $this->extraFields = $extraFields;
        $this->options = $options;
        $this->row = $item;
        $this->lists = $lists;
        $this->params = $params;
        $this->user = $user;
        (Factory::getApplication()->input->getInt('cid')) ? $title = Text::_('K2_EDIT_ITEM') : $title = Text::_('K2_ADD_ITEM');
        $this->title = $title;

        // Disable Joomla menu
        Factory::getApplication()->input->set('hidemainmenu', 1);

        if ($app->isClient('administrator')) {

            // Tabs
            $this->params->set('showImageTab', true);
            $this->params->set('showImageGalleryTab', true);
            $this->params->set('showVideoTab', true);
            $this->params->set('showExtraFieldsTab', true);
            $this->params->set('showAttachmentsTab', true);
            $this->params->set('showK2Plugins', true);
        }

        // JS
        $document->addScriptDeclaration("
            var K2BasePath = '" . URI::base(true) . "/';
            var K2Language = [
                '" . Text::_('K2_REMOVE', true) . "',
                '" . Text::_('K2_LINK_TITLE_OPTIONAL', true) . "',
                '" . Text::_('K2_LINK_TITLE_ATTRIBUTE_OPTIONAL', true) . "',
                '" . Text::_('K2_ARE_YOU_SURE', true) . "',
                '" . Text::_('K2_YOU_ARE_NOT_ALLOWED_TO_POST_TO_THIS_CATEGORY', true) . "',
                '" . Text::_('K2_OR_SELECT_A_FILE_ON_THE_SERVER', true) . "',
                '" . Text::_('K2_ATTACH_FILE', true) . "',
                '" . Text::_('K2_MAX_UPLOAD_SIZE', true) . "',
                '" . ini_get('upload_max_filesize') . "',
                '" . Text::_('K2_OR', true) . "',
                '" . Text::_('K2_BROWSE_SERVER', true) . "'
            ];

            Joomla.submitbutton = function(pressbutton) {
                if (pressbutton == 'cancel') {
                    Joomla.submitform(pressbutton);
                    return;
                }
                if (\$K2.trim(\$K2('#title').val()) == '') {
                    alert('" . Text::_('K2_ITEM_MUST_HAVE_A_TITLE', true) . "');
                } else if (\$K2.trim(\$K2('#catid').val()) == '0') {
                    alert('" . Text::_('K2_PLEASE_SELECT_A_CATEGORY', true) . "');
                } else {
                    syncExtraFieldsEditor();
                    var validation = validateExtraFields();
                    if(validation === true) {
                        \$K2('#selectedTags option').attr('selected', 'selected');
                        Joomla.submitform(pressbutton);
                    }
                }
            };

            /* Tab offset */
            var K2ActiveMediaTab = " . $options['startOffset'] . ";

            /* WYSIWYG Editors */
            function onK2EditorSave() {
                " . $onSave . "
            }
        ");

        // For SIGPro
        if (PluginHelper::isEnabled('k2', 'jw_sigpro')) {
            $sigPro = true;
            $sigProFolder = ($this->row->id) ? $this->row->id : uniqid();
            $this->sigProFolder = $sigProFolder;
        } else {
            $sigPro = false;
        }
        $this->sigPro = $sigPro;

        // For frontend editing
        if ($app->isClient('site')) {
            // Lookup template folders
            $this->_addPath('template', JPATH_COMPONENT . '/templates');
            $this->_addPath('template', JPATH_COMPONENT . '/templates/default');

            $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates');
            $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates/default');

            $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2');
            $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/default');

            $theme = (isset($this->frontendTheme)) ? $this->frontendTheme : $params->get('theme');
            if ($theme) {
                $this->_addPath('template', JPATH_COMPONENT . '/templates/' . $theme);
                $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates/' . $theme);
                $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/' . $theme);
            }

            // Allow temporary template loading with ?template=
            $template = Factory::getApplication()->input->getCmd('template');
            if (isset($template)) {
                // Look for overrides in template folder (new K2 template structure)
                $this->_addPath('template', JPATH_SITE . '/templates/' . $template . '/html/com_k2');
                $this->_addPath('template', JPATH_SITE . '/templates/' . $template . '/html/com_k2/default');
                if ($theme) {
                    $this->_addPath('template', JPATH_SITE . '/templates/' . $template . '/html/com_k2/' . $theme);
                }
            }
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {

        // Get the toolbar object instance
        $toolbar = Toolbar::getInstance('toolbar');
        // Toolbar
        ToolBarHelper::title($this->title, 'k2.png');

        if (version_compare(JVERSION, '4.0.0-dev', 'ge')) {
            $toolbar->apply('apply');
            $toolbar->save('save');
            $toolbar->standardButton('add', 'K2_SAVE_AND_NEW', 'saveAndNew');
            $toolbar->cancel('cancel', 'JTOOLBAR_CANCEL');

            if ($this->row->id) {
                JLoader::register('K2HelperRoute', JPATH_SITE . '/components/com_k2/helpers/route.php');
                $url = urldecode(K2HelperRoute::getItemRoute($this->row->id . ':' . $this->row->alias, $this->row->catid));
                $toolbar->preview(Route::link('site', $url, true, 0, true) . '/?', 'JGLOBAL_PREVIEW')
                    ->bodyHeight(80)
                    ->modalWidth(90);

                if (PluginHelper::isEnabled('system', 'jooa11y')) {
                    $toolbar->jooa11y(Route::link('site', $url . '&jooa11y=1', true, 0, true) . '/?', 'JGLOBAL_JOOA11Y')
                        ->bodyHeight(80)
                        ->modalWidth(90);
                }
            }
        }
        else{

            ToolBarHelper::apply();
            ToolBarHelper::save();
            ToolBarHelper::custom('saveAndNew', 'save-new.png', 'save_f2.png', 'K2_SAVE_AND_NEW', false);
            ToolBarHelper::cancel();
        }
    }
}
