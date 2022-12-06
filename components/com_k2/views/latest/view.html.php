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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.view');

class K2ViewLatest extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $document = Factory::getDocument();
        $user = Factory::getUser();

        $params = K2HelperUtilities::getParams('com_k2');

        $cache = Factory::getCache('com_k2_extended');

        $limit = $params->get('latestItemsLimit');
        $limitstart = Factory::getApplication()->input->getInt('limitstart');

        $model = $this->getModel('itemlist');
        $itemModel = $this->getModel('item');

        // Menu
        $menu = $app->getMenu();
        $menuDefault = $menu->getDefault();
        $menuActive = $menu->getActive();

        // Important URLs
        $currentAbsoluteUrl = Uri::getInstance()->toString();
        $currentRelativeUrl = Uri::root(true) . str_replace(substr(Uri::root(), 0, -1), '', $currentAbsoluteUrl);

        // Set layout
        $this->setLayout('latest');

        // Import plugins
        PluginHelper::importPlugin('content');
        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        if ($params->get('source')) {
            // Categories
            $categoryIDs = $params->get('categoryIDs');
            if (is_string($categoryIDs) && !empty($categoryIDs)) {
                $categoryIDs = array();
                $categoryIDs[] = $params->get('categoryIDs');
            }
            $categories = array();
            Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/tables');
            if (is_array($categoryIDs)) {
                foreach ($categoryIDs as $categoryID) {
                    $category = Table::getInstance('K2Category', 'Table');
                    $category->load($categoryID);
                    $category->event = new stdClass;
                    $languageCheck = true;
                    $accessCheck = in_array($category->access, $user->getAuthorisedViewLevels());
                    if ($app->getLanguageFilter()) {
                        $languageTag = Factory::getLanguage()->getTag();
                        $languageCheck = in_array($category->language, array($languageTag, '*'));
                    }

                    if ($category->published && $accessCheck && $languageCheck) {
                        // Merge params
                        $cparams = class_exists('JParameter') ? new JParameter($category->params) : new Registry($category->params);
                        if ($cparams->get('inheritFrom')) {
                            $masterCategory = Table::getInstance('K2Category', 'Table');
                            $masterCategory->load($cparams->get('inheritFrom'));
                            $cparams = class_exists('JParameter') ? new JParameter($masterCategory->params) : new Registry($masterCategory->params);
                        }
                        $params->merge($cparams);

                        // Category image
                        $category->image = K2HelperUtilities::getCategoryImage($category->image, $params);

                        // Category plugins
                        $category->text = $category->description;

                        /* since J4 compatibility */
                        Factory::getApplication()->triggerEvent('onContentPrepare', array('com_k2.category', &$category, &$params, $limitstart));
                        $category->description = $category->text;

                        // Category K2 plugins
                        $category->event->K2CategoryDisplay = '';
                        /* since J4 compatibility */
                        $results = Factory::getApplication()->triggerEvent('onK2CategoryDisplay', array(&$category, &$params, $limitstart));
                        $category->event->K2CategoryDisplay = trim(implode("\n", $results));
                        $category->text = $category->description;
                        /* since J4 compatibility */
                        Factory::getApplication()->triggerEvent('onK2PrepareContent', array(&$category, &$params, $limitstart));
                        $category->description = $category->text;

                        // Category link
                        $link = urldecode(K2HelperRoute::getCategoryRoute($category->id . ':' . urlencode($category->alias)));
                        $category->link = Route::_($link);
                        $category->feed = Route::_($link . '&format=feed');

                        Factory::getApplication()->input->set('view', 'itemlist');
                        Factory::getApplication()->input->set('task', 'category');
                        Factory::getApplication()->input->set('id', $category->id);
                        Factory::getApplication()->input->set('featured', 1);
                        Factory::getApplication()->input->set('limit', $limit);
                        Factory::getApplication()->input->set('clearFlag', true);

                        $category->name = htmlspecialchars($category->name, ENT_QUOTES, 'utf-8');
                        if ($limit) {
                            $category->items = $model->getData('rdate');

                            Factory::getApplication()->input->set('view', 'latest');
                            Factory::getApplication()->input->set('task', '');

                            for ($i = 0; $i < count($category->items); $i++) {
                                $hits = $category->items[$i]->hits;
                                $category->items[$i]->hits = 0;
                                if (version_compare(JVERSION, '4.0.0-dev', 'ge')){
                                    $key = ('k2_item_CatLatest' . $category->items[$i]->id . $category->items[$i]->alias);
                                    if ($cache->contains($key))
                                    {
                                        $category->items[$i]= $cache->get($key, 'com_k2_extended');
                                    }
                                    else{
                                        $category->items[$i] = $itemModel->prepareItem($category->items[$i], 'latest', '');
                                        $store = $category->items[$i];
                                        if (is_object($store)) {
                                           $store = json_encode($store);
                                           $store = json_decode(trim ($store, chr (239). chr (187). chr (191)), true );
                                        }
                                        try{
                                            $cache->store($store, $key);
                                        } catch (\Exception $e) {
                                            // for the moment settle to miss caching the query returned object
                                            // throw new \Exception(Text::_($e), 500);
                                        }
                                    }
                                }
                                else{
                                    $args = array(array($itemModel, 'prepareItem'), $category->items[$i], 'latest', '');
                                    $callback = array_shift($args);
                                    $category->items[$i] = $cache->get($callback, $args);
                                }

                                $category->items[$i]->hits = $hits;
                                $category->items[$i] = $itemModel->execPlugins($category->items[$i], 'latest', '');

                                // Trigger comments counter event
                                /* since J4 compatibility */
                                $results = Factory::getApplication()->triggerEvent('onK2CommentsCounter', array(&$category->items[$i], &$params, $limitstart));
                                $category->items[$i]->event->K2CommentsCounter = trim(implode("\n", $results));
                            }
                        } else {
                            $category->items = array();
                        }
                        $categories[] = $category;
                    }
                }
            }
            $source = 'categories';
            $this->blocks = $categories;
        } else {
            // Users
            $usersIDs = $params->get('userIDs');
            if (is_string($usersIDs) && !empty($usersIDs)) {
                $usersIDs = array();
                $usersIDs[] = $params->get('userIDs');
            }

            $users = array();
            if (is_array($usersIDs)) {
                foreach ($usersIDs as $userID) {
                    $userObject = Factory::getUser($userID);
                    if (!$userObject->block) {
                        $userObject->event = new stdClass;

                        // User profile
                        $userObject->profile = $model->getUserProfile($userID);

                        // User image
                        $userObject->avatar = K2HelperUtilities::getAvatar($userObject->id, $userObject->email, $params->get('userImageWidth'));

                        // User K2 plugins
                        $userObject->event->K2UserDisplay = '';
                        if (is_object($userObject->profile) && $userObject->profile->id > 0) {
                            /* since J4 compatibility */
                            $results = Factory::getApplication()->triggerEvent('onK2UserDisplay', array(&$userObject->profile, &$params, $limitstart));
                            $userObject->event->K2UserDisplay = trim(implode("\n", $results));
                            $userObject->profile->url = htmlspecialchars($userObject->profile->url, ENT_QUOTES, 'utf-8');
                        }

                        $link = K2HelperRoute::getUserRoute($userObject->id);
                        $userObject->link = Route::_($link);
                        $userObject->feed = Route::_($link . '&format=feed');
                        $userObject->name = htmlspecialchars($userObject->name, ENT_QUOTES, 'utf-8');
                        if ($limit) {
                            $userObject->items = $model->getAuthorLatest(0, $limit, $userID);

                            for ($i = 0; $i < count($userObject->items); $i++) {
                                $hits = $userObject->items[$i]->hits;
                                $userObject->items[$i]->hits = 0;

                                if (version_compare(JVERSION, '4.0.0-dev', 'ge')){
                                    $key = ('k2_item_UserLatest' . $userObject->items[$i]->id . $userObject->items[$i]->alias);
                                    if ($cache->contains($key))
                                    {
                                        $userObject->items[$i]= $cache->get($key, 'com_k2_extended');
                                    }
                                    else{
                                        $userObject->items[$i] = $itemModel->prepareItem($userObject->items[$i], 'latest', '');
                                        $UserStore = $userObject->items[$i];
                                        if (is_object($UserStore)) {
                                            $UserStore = json_encode($UserStore);
                                            if(!empty($UserStore)){
                                                $UserStore = json_decode(trim ($UserStore, chr (239). chr (187). chr (191)), true );
                                                try{
                                                    $cache->store($UserStore, $key);
                                                } catch (\Exception $e) {
                                                    // for the moment settle to miss caching the query returned object
                                                    throw new \Exception(Text::_($e), 500);
                                                }
                                            }
                                        }

                                    }
                                }
                                else{
                                    $args = array(array($itemModel, 'prepareItem'), $userObject->items[$i], 'latest', '');
                                    $callback = array_shift($args);
                                    $userObject->items[$i] = $cache->get($callback, $args);
                                }
                                $userObject->items[$i]->hits = $hits;

                                // Plugins
                                $userObject->items[$i] = $itemModel->execPlugins($userObject->items[$i], 'latest', '');

                                // Trigger comments counter event
                                /* since J4 compatibility */
                                $results = Factory::getApplication()->triggerEvent('onK2CommentsCounter', array(&$userObject->items[$i], &$params, $limitstart));
                                $userObject->items[$i]->event->K2CommentsCounter = trim(implode("\n", $results));
                            }
                        } else {
                            $userObject->items = array();
                        }
                        $users[] = $userObject;
                    }
                }
            }
            $source = 'users';
            $this->blocks = $users;
        }

        // Head Stuff
        if (!in_array($document->getType(), ['raw', 'json'])) {
            // Set canonical link
            $this->setCanonicalUrl($currentAbsoluteUrl);

            // Set <title>
            // Prepend/append site name
            if ($app->getCfg('sitename_pagetitles', 0) == 1) {
                $params->set('page_title', Text::sprintf('JPAGETITLE', $app->getCfg('sitename'), $params->get('page_title')));
            } elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
                $params->set('page_title', Text::sprintf('JPAGETITLE', $params->get('page_title'), $app->getCfg('sitename')));
            }

            // B/C assignment so Joomla 2.5+ uses the 'show_page_title' parameter as Joomla 1.5 does
            $params->set('show_page_title', $params->get('show_page_heading'));

            $metaTitle = trim($params->get('page_title'));
            $document->setTitle($metaTitle);

            // Set meta description
            $metaDesc = $document->getMetadata('description');

            if ($params->get('menu-meta_description')) {
                $metaDesc = $params->get('menu-meta_description');
            }

            $metaDesc = trim($metaDesc);
            $document->setDescription(K2HelperUtilities::characterLimit($metaDesc, $params->get('metaDescLimit', 150)));

            // Set meta keywords
            $metaKeywords = $document->getMetadata('keywords');

            if ($params->get('menu-meta_keywords')) {
                $metaKeywords = $params->get('menu-meta_keywords');
            }

            $metaKeywords = trim($metaKeywords);
            $document->setMetadata('keywords', $metaKeywords);

            // Set meta robots
            $metaRobots = $document->getMetadata('robots');

            if ($params->get('robots')) {
                $metaRobots = $params->get('robots');
            }

            $document->setMetadata('robots', $metaRobots);

            // Set Facebook meta tags
            if ($params->get('facebookMetatags', 1)) {
                $document->setMetaData('og:url', $currentAbsoluteUrl);
                $document->setMetaData('og:type', 'website');
                $document->setMetaData('og:title', filter_var($metaTitle, FILTER_UNSAFE_RAW));
                $document->setMetaData('og:description', K2HelperUtilities::characterLimit($metaDesc, 300)); // 300 chars limit for Facebook post sharing
            }

            // Set Twitter meta tags
            if ($params->get('twitterMetatags', 1)) {
                $document->setMetaData('twitter:card', 'summary');
                if ($params->get('twitterUsername')) {
                    $document->setMetaData('twitter:site', '@' . $params->get('twitterUsername'));
                }
                $document->setMetaData('twitter:title', filter_var($metaTitle, FILTER_UNSAFE_RAW));
                $document->setMetaData('twitter:description', K2HelperUtilities::characterLimit($metaDesc, 200)); // 200 chars limit for Twitter post sharing
            }
        }

        // Lookup template folders
        $this->_addPath('template', JPATH_COMPONENT . '/templates');
        $this->_addPath('template', JPATH_COMPONENT . '/templates/default');

        $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates');
        $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates/default');

        $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2');
        $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/default');

        $theme = $params->get('theme');
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

        // Assign data
        $this->params = $params;
        $this->source = $source;

        // Display
        parent::display($tpl);
    }

    private function setCanonicalUrl($url)
    {
        $document = Factory::getDocument();
        $params = K2HelperUtilities::getParams('com_k2');
        $canonicalURL = $params->get('canonicalURL', 'relative');
        if ($canonicalURL == 'absolute') {
            $url = substr(str_replace(Uri::root(true), '', Uri::root(false)), 0, -1) . $url;
        }
        $document->addHeadLink($url, 'canonical', 'rel');
    }
}
