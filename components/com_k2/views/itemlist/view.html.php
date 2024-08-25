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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Document\Feed\FeedItem;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\ModuleHelper;

jimport('joomla.application.component.view');

class K2ViewItemlist extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $document = Factory::getDocument();
        $db = Factory::getDbo();
        $config = Factory::getConfig();
        $user = Factory::getUser();
        $view = Factory::getApplication()->input->getCmd('view');
        $task = Factory::getApplication()->input->getCmd('task');
        $item_id = Factory::getApplication()->input->getCmd('Itemid');
        $format = Factory::getApplication()->input->getCmd('format');
        $limitstart = Factory::getApplication()->input->getInt('limitstart', 0);
        $limit = Factory::getApplication()->input->getInt('limit', 10);
        $moduleID = Factory::getApplication()->input->getInt('moduleID');

        $params = K2HelperUtilities::getParams('com_k2');
        $cache = Factory::getCache('com_k2_extended');
        Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');

        $itemlistModel = $this->getModel('itemlist');
        $itemModel = $this->getModel('item');

        // Menu
        $menu = $app->getMenu();
        $menuDefault = $menu->getDefault();
        $menuActive = $menu->getActive();

        // Important URLs
        $currentAbsoluteUrl = Uri::getInstance()->toString();
        $currentRelativeUrl = Uri::root(true) . str_replace(substr(Uri::root(), 0, -1), '', $currentAbsoluteUrl);
        /*
        $currentMenuItemUrl = '';
        if (!is_null($menuActive) && isset($menuActive->link)) {
            $currentMenuItemUrl = str_replace('&amp;', '&', Route::_($menuActive->link));
        }
        $menuItemMatchesUrl = false;
        if ($currentMenuItemUrl == $currentRelativeUrl) {
            $menuItemMatchesUrl = true;
        }
        */

        // Dates
        $date = Factory::getDate();
        $now = $date->toSql();
        $this->now = $now;

        $nullDate = $db->getNullDate();
        $this->nullDate = $nullDate;

        // Import plugins
        PluginHelper::importPlugin('content');
        PluginHelper::importPlugin('k2');
        /* since J4 compatibility */
        /* JDispatcher removed in J4 */
        /*
                $dispatcher = JDispatcher::getInstance();
        */

        // --- Feed Output [start] ---
        if ($document->getType() == 'feed') {
            if ($moduleID) {
                $result = $itemlistModel->getModuleItems($moduleID);
                $title = $result->title;
                $items = $result->items;
            }
        }
        // --- Feed Output [finish] ---

        // --- JSON Output [start] ---

        if ($document->getType() == 'json') {
            // Prepare JSON output
            $uri = URI::getInstance();
            $response = new stdClass;
            $response->site = new stdClass;
            $response->site->url = $uri->toString(array('scheme', 'host', 'port'));
            $response->site->name = $config->get('sitename');

            // Handle K2 Content (module)
            if ($moduleID) {
                $result = $itemlistModel->getModuleItems($moduleID);
                $items = $result->items;
                $title = $result->title;
                $prefix = 'cat';
            }
        }
        // --- JSON Output [finish] ---

        // Get data based on task
        if (!$moduleID) {
            switch ($task) {
                case 'category':
                    // Get category
                    $id = Factory::getApplication()->input->getInt('id');

                    $category = Table::getInstance('K2Category', 'Table');
                    $category->load($id);

                    // State check
                    if (!$category->published || $category->trash) {
                        $app->setHeader('status', 404, true);
                        throw new \Exception(Text::_('K2_CATEGORY_NOT_FOUND'), 404);
                    }

                    // Access check
                    if (!in_array($category->access, $user->getAuthorisedViewLevels())) {
                        if ($user->guest) {
                            $uri = Uri::getInstance();
                            $url = 'index.php?option=com_users&view=login&return=' . base64_encode($uri->toString());
                            $app->enqueueMessage(Text::_('K2_YOU_NEED_TO_LOGIN_FIRST'), 'notice');
                            $app->redirect(Route::_($url, false));
                        } else {
                            $app->setHeader('status', 403, true);
                            throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
                            return;
                        }
                    }
                    $languageFilter = $app->getLanguageFilter();
                    $languageTag = Factory::getLanguage()->getTag();
                    if ($languageFilter && $category->language != $languageTag && $category->language != '*') { // Test logic
                        return;
                    }

                    // Merge params
                    $cparams = (class_exists('JParameter')) ? new JParameter($category->params) : new Registry($category->params);

                    // Get the meta information before merging params since we do not want them to be inherited
                    $category->metaDescription = $cparams->get('catMetaDesc');
                    $category->metaKeywords = $cparams->get('catMetaKey');
                    $category->metaRobots = $cparams->get('catMetaRobots');
                    $category->metaAuthor = $cparams->get('catMetaAuthor');

                    if ($cparams->get('inheritFrom')) {
                        $masterCategory = Table::getInstance('K2Category', 'Table');
                        $masterCategory->load($cparams->get('inheritFrom'));
                        $cparams = (class_exists('JParameter')) ? new JParameter($masterCategory->params) : new Registry($masterCategory->params);
                    }
                    $params->merge($cparams);

                    // Category link
                    $category->link = urldecode(Route::_(K2HelperRoute::getCategoryRoute($category->id . ':' . urlencode($category->alias))));

                    // Category image
                    $category->image = K2HelperUtilities::getCategoryImage($category->image, $params);

                    // Category plugins
                    $category->text = $category->description;
                    /* since J4 compatibility */
                    Factory::getApplication()->triggerEvent('onContentPrepare', array(
                        'com_k2.category',
                        &$category,
                        &$params,
                        $limitstart
                    ));
                    $category->description = $category->text;

                    // Category K2 plugins
                    $category->event = new stdClass;

                    $category->event->K2CategoryDisplay = '';
                    /* since J4 compatibility */
                    $results = Factory::getApplication()->triggerEvent('onK2CategoryDisplay', array(
                        &$category,
                        &$params,
                        $limitstart
                    ));

                    $category->event->K2CategoryDisplay = trim(implode("\n", $results));
                    $category->text = $category->description;
                    /* since J4 compatibility */
                    Factory::getApplication()->triggerEvent('onK2PrepareContent', array(
                        &$category,
                        &$params,
                        $limitstart
                    ));
                    $category->description = $category->text;

                    $this->category = $category;
                    $this->user = $user;

                    // Category children
                    $subCategories = array();
                    $ordering = $params->get('subCatOrdering');
                    $children = $itemlistModel->getCategoryFirstChildren($id, $ordering);
                    if (count($children)) {
                        foreach ($children as $child) {
                            if ($params->get('subCatTitleItemCounter')) {
                                $child->numOfItems = $itemlistModel->countCategoryItems($child->id);
                            }
                            $child->image = K2HelperUtilities::getCategoryImage($child->image, $params);
                            $child->name = htmlspecialchars($child->name, ENT_QUOTES, 'utf-8');
                            $child->link = urldecode(Route::_(K2HelperRoute::getCategoryRoute($child->id . ':' . urlencode($child->alias))));
                            $subCategories[] = $child;
                        }
                        $this->subCategories = $subCategories;
                    }

                    // Set layout
                    $this->setLayout('category');

                    // Set featured flag
                    Factory::getApplication()->input->set('featured', $params->get('catFeaturedItems'));

                    // Set limit
                    $limit = $params->get('num_leading_items') + $params->get('num_primary_items') + $params->get('num_secondary_items') + $params->get('num_links');

                    // Set ordering
                    if ($params->get('singleCatOrdering')) {
                        $ordering = $params->get('singleCatOrdering');
                    } else {
                        $ordering = $params->get('catOrdering');
                    }

                    // Set title
                    $title = $category->name;
                    $category->name = htmlspecialchars($category->name, ENT_QUOTES, 'utf-8'); // Check this

                    // Set head feed link
                    $addHeadFeedLink = $params->get('catFeedLink');

                    // --- JSON Output [start] ---
                    if ($document->getType() == 'json') {
                        // Set parameters prefix
                        $prefix = 'cat';

                        // Prepare the JSON category object
                        $row = new stdClass;
                        $row->id = $category->id;
                        $row->alias = $category->alias;
                        $row->link = $category->link;
                        $row->name = $category->name;
                        $row->description = $category->description;
                        $row->image = $category->image;
                        $row->extraFieldsGroup = $category->extraFieldsGroup;
                        $row->ordering = $category->ordering;
                        $row->parent = $category->parent;
                        $row->children = $subCategories;
                        $row->events = $category->event;

                        $response->category = $row;
                    }
                    // --- JSON Output [finish] ---

                    break;
                case 'tag':
                    // Prevent spammers from using the tag view
                    $tag = Factory::getApplication()->input->getString('tag');
                    $db->setQuery('SELECT id, name, description FROM #__k2_tags WHERE name = ' . $db->quote($tag));
                    $tag = $db->loadObject();
                    if (!$tag || !$tag->id) {
                        jimport('joomla.filesystem.file');

                        if (File::exists(JPATH_ADMINISTRATOR . '/components/com_falang/falang.php')) {
                            $db->setQuery('SELECT id, value FROM #__falang_content WHERE value = ' . $db->quote($tag));
                            $tag = $db->loadObject();
                        }

                        if (!$tag || !$tag->id) {
                            if ($document->getType() == 'feed' || $document->getType() == 'json') {
                                $app->redirect(Uri::root());
                            } else {
                                $app->setHeader('status', 404, true);
                                throw new \Exception(Text::_('K2_NOT_FOUND'), 404);
                                return false;
                            }
                        }
                    }

                    // Set layout
                    $this->setLayout('tag');

                    // Set limit
                    $limit = $params->get('tagItemCount');

                    // Set ordering
                    $ordering = $params->get('tagOrdering');

                    // Set title
                    $this->name = $tag->name;
                    $title = $tag->name;
                    $page_title = $params->get('page_title');
                    if ($this->menuItemMatchesK2Entity('itemlist', 'tag', $tag->name) && !empty($page_title)) {
                        $title = $params->get('page_title');
                    }
                    $this->title = $title;

		    // set description
	            $description = $tag->description;
                    $this->description = $description;

                    // Link
                    $link = K2HelperRoute::getTagRoute($tag->name);
                    $link = Route::_($link);
                    $this->link = $link;

                    // Set head feed link
                    $addHeadFeedLink = $params->get('tagFeedLink', 1);

                    // --- JSON Output [start] ---
                    if ($document->getType() == 'json') {
                        // Set parameters prefix
                        $prefix = 'tag';

                        $response->tag = $tag->name;
                    }
                    // --- JSON Output [finish] ---

                    break;
                case 'user':
                    // Get user
                    $id = Factory::getApplication()->input->getInt('id');
                    $userObject = Factory::getUser($id);

                    // Check user status
                    if ($userObject->block) {
                        $app->setHeader('status', 404, true);
                        throw new \Exception(Text::_('K2_USER_NOT_FOUND'), 404);
                    }

                    // Get K2 user profile
                    $userObject->profile = $itemlistModel->getUserProfile();

                    // User image
                    $userObject->avatar = K2HelperUtilities::getAvatar($userObject->id, $userObject->email, $params->get('userImageWidth'));

                    // User K2 plugins
                    $userObject->event = new stdClass;

                    $userObject->event->K2UserDisplay = '';
                    if (is_object($userObject->profile) && $userObject->profile->id > 0) {
                        /* since J4 compatibility */
                        $results = Factory::getApplication()->triggerEvent('onK2UserDisplay', array(
                            &$userObject->profile,
                            &$params,
                            $limitstart
                        ));
                        $userObject->event->K2UserDisplay = trim(implode("\n", $results));
                        $userObject->profile->url = isset($userObject->profile->url) ? htmlspecialchars($userObject->profile->url, ENT_QUOTES, 'utf-8') : $userObject->profile->url;
                    }
                    $this->user = $userObject;

                    // Set layout
                    $this->setLayout('user');

                    // Set limit
                    $limit = $params->get('userItemCount');

                    // Set ordering
                    $ordering = $params->get('userOrdering');

                    // Set title
                    $title = $userObject->name;

                    // Link
                    $link = K2HelperRoute::getUserRoute($id);
                    $link = Route::_($link);
                    $this->link = $link;

                    // Set head feed link
                    $addHeadFeedLink = $params->get('userFeedLink', 1);

                    // --- JSON Output [start] ---
                    if ($document->getType() == 'json') {
                        // Set parameters prefix
                        $prefix = 'user';

                        // Prepare the JSON user object
                        $row = new stdClass;
                        $row->name = $userObject->name;
                        $row->avatar = $userObject->avatar;
                        $row->profile = $userObject->profile;
                        if (isset($userObject->profile->plugins)) {
                            unset($userObject->profile->plugins);
                        }
                        $row->events = $userObject->event;

                        $response->user = $row;
                    }
                    // --- JSON Output [finish] ---

                    break;
                case 'date':
                    // Set layout
                    $this->setLayout('generic');

                    // Set limit
                    $limit = $params->get('genericItemCount');

                    // Set ordering
                    $ordering = 'rdate';

                    // Fix wrong timezone
                    if (function_exists('date_default_timezone_get')) {
                        $originalTimezone = date_default_timezone_get();
                    }
                    if (function_exists('date_default_timezone_set')) {
                        date_default_timezone_set('UTC');
                    }

                    // Set title
                    if (Factory::getApplication()->input->getInt('day')) {
                        $dateFromRequest = strtotime(Factory::getApplication()->input->getInt('year') . '-' . Factory::getApplication()->input->getInt('month') . '-' . Factory::getApplication()->input->getInt('day'));
                        $dateFormat = 'l, d F Y';
                    } else {
                        $dateFromRequest = strtotime(Factory::getApplication()->input->getInt('year') . '-' . Factory::getApplication()->input->getInt('month'));
                        $dateFormat = 'F Y';
                    }
                    $title = filter_var(HTMLHelper::_('date', $dateFromRequest, $dateFormat), FILTER_UNSAFE_RAW);
                    $this->title = $title;

                    // Restore the original timezone
                    if (function_exists('date_default_timezone_set') && isset($originalTimezone)) {
                        date_default_timezone_set($originalTimezone);
                    }

                    // Set head feed link
                    $addHeadFeedLink = $params->get('genericFeedLink', 1);

                    // --- JSON Output [start] ---
                    if ($document->getType() == 'json') {
                        // Set parameters prefix
                        $prefix = 'generic';

                        $response->date = $title;
                    }
                    // --- JSON Output [finish] ---

                    break;
                case 'search':
                    // Set layout
                    $this->setLayout('generic');

                    // Set limit
                    $limit = $params->get('genericItemCount');

                    // Set title
                    $title = filter_var(Factory::getApplication()->input->getVar('searchword'), FILTER_UNSAFE_RAW);
                    $this->title = $title;

                    // Set search form data
                    $form = new stdClass;
                    $form->action = Route::_(K2HelperRoute::getSearchRoute());
                    $form->input = ($title) ? $title : Text::_('K2_SEARCH');
                    $form->attributes = '';
                    if (!$app->getCfg('sef')) {
                        $form->attributes .= '
                            <input type="hidden" name="option" value="com_k2" />
                            <input type="hidden" name="view" value="itemlist" />
                            <input type="hidden" name="task" value="search" />
                        ';
                    }
                    if ($params->get('searchMenuItemId', '')) {
                        $form->attributes .= '
                            <input type="hidden" name="Itemid" value="' . $params->get('searchMenuItemId', '') . '" />
                        ';
                    }

                    $this->form = $form;

                    // Set head feed link
                    $addHeadFeedLink = $params->get('genericFeedLink', 1);

                    // --- JSON Output [start] ---
                    if ($document->getType() == 'json') {
                        // Set parameters prefix
                        $prefix = 'generic';

                        $response->search = Factory::getApplication()->input->getVar('searchword');
                    }
                    // --- JSON Output [finish] ---

                    break;
                default:
                    $this->user = $user;

                    // Set layout
                    $this->setLayout('category');

                    // Set featured flag
                    Factory::getApplication()->input->set('featured', $params->get('catFeaturedItems'));

                    // Set limit
                    $limit = $params->get('num_leading_items') + $params->get('num_primary_items') + $params->get('num_secondary_items') + $params->get('num_links');

                    // Set ordering
                    $ordering = $params->get('catOrdering');

                    // Set title
                    $title = $params->get('page_title');

                    // Set head feed link
                    $addHeadFeedLink = $params->get('catFeedLink', 1);

                    // --- JSON Output [start] ---
                    if ($document->getType() == 'json') {
                        // Set parameters prefix
                        $prefix = 'cat';
                    }
                    // --- JSON Output [finish] ---

                    break;
            }

            // --- Feed Output [start] ---
            if ($document->getType() == 'feed') {
                $title = OutputFilter::ampReplace($title);
                $limit = $params->get('feedLimit');
            }
            // --- Feed Output [finish] ---

            // Set a default limit (for the model) if none is found
            if (!$limit) {
                $limit = 10;
            }
            // Allow Feed & JSON outputs to request more items that the preset limit
            if (in_array($document->getType(), ['feed', 'json']) && Factory::getApplication()->input->getInt('limit')) {
                $limit = Factory::getApplication()->input->getInt('limit');
            }
            // Protect from large limit requests
            $siteItemlistLimit = (int)$params->get('siteItemlistLimit', 100);
            if ($siteItemlistLimit && $limit > $siteItemlistLimit) {
                $limit = $siteItemlistLimit;
            }
            Factory::getApplication()->input->set('limit', $limit);

            // Allow for simplified paginated results using "page"
            $page = Factory::getApplication()->input->getInt('page');
            if ($page) {
                $limitstart = $page * $limit;
                Factory::getApplication()->input->set('limitstart', $limitstart);
            }

            // Get items
            if (!isset($ordering)) {
                $items = $itemlistModel->getData();
            } else {
                $items = $itemlistModel->getData($ordering);
            }

            // If a user has no published items, do not display their K2 user page (in the frontend) and redirect to the homepage of the site.
            $userPageDisplay = 0;
            switch ($params->get('profilePageDisplay', 0)) {
                case 1:
                    $userPageDisplay = 1;
                    break;
                case 2:
                    if ($user->id > 0) {
                        $userPageDisplay = 1;
                    }
                    break;
            }
            if ((count($items) == 0 && $task == 'user') && $userPageDisplay == 0) {
                $app->redirect(Uri::root());
            }
        }

        if ($document->getType() != 'json') {
            // Pagination
            jimport('joomla.html.pagination');
            $total = (count($items)) ? $itemlistModel->getTotal() : 0;
            $pagination = new Pagination($total, $limitstart, $limit);
        }

        $rowsForJSON = array();

        for ($i = 0, $iTotal = count($items); $i < $iTotal; $i++) {
            // Ensure that all items have a group. Group-less items get assigned to the leading group
            $items[$i]->itemGroup = 'leading';

            // Item group
            if ($task == "category" || $task == "") {
                if ($i < ($params->get('num_links') + $params->get('num_leading_items') + $params->get('num_primary_items') + $params->get('num_secondary_items'))) {
                    $items[$i]->itemGroup = 'links';
                }
                if ($i < ($params->get('num_secondary_items') + $params->get('num_leading_items') + $params->get('num_primary_items'))) {
                    $items[$i]->itemGroup = 'secondary';
                }
                if ($i < ($params->get('num_primary_items') + $params->get('num_leading_items'))) {
                    $items[$i]->itemGroup = 'primary';
                }
                if ($i < $params->get('num_leading_items')) {
                    $items[$i]->itemGroup = 'leading';
                }
            }

            // --- Feed Output [start] ---
            if ($document->getType() == 'feed') {
                $item = $itemModel->prepareFeedItem($items[$i]);

                // Manipulate tag rendering in the feed URL
                if (Factory::getApplication()->input->getBool('tagsontitle', false) && !empty($item->tags) && count($item->tags)) {

                    // Limit no. of rendered tags in the title (if set)
                    $tagLimit = Factory::getApplication()->input->getInt('taglimit', 0);
                    if ($tagLimit && $tagLimit < count($item->tags)) {
                        $item->tags = array_slice($item->tags, 0, $tagLimit);
                    }

                    // Append tags to the title
                    $item->title = html_entity_decode($this->escape($item->title . ' ' . implode(' ', $item->tags)));
                }

                $feedItem = new FeedItem();
                $feedItem->link = $item->link;
                $feedItem->title = html_entity_decode($this->escape($item->title));
                $feedItem->description = $item->description;
                $feedItem->date = (isset($ordering) && $ordering == 'modified') ? $item->modified : $item->created;
                $feedItem->category = $item->category->name;
                $feedItem->author = $item->author->name;
                if ($params->get('feedBogusEmail')) {
                    $feedItem->authorEmail = $params->get('feedBogusEmail');
                } else {
                    if ($app->getCfg('feed_email') == 'author') {
                        $feedItem->authorEmail = $item->author->email;
                    } else {
                        $feedItem->authorEmail = $app->getCfg('mailfrom');
                    }
                }
                if ($params->get('feedItemImage') && File::exists(JPATH_SITE . '/media/k2/items/cache/' . md5("Image" . $item->id) . '_' . $params->get('feedImgSize') . '.jpg')) {
                    $feedItem->setEnclosure($item->enclosure);
                }

                // Add feed item
                $document->addItem($feedItem);
            }
            // --- Feed Output [finish] ---

            // --- JSON Output [start] ---
            if ($document->getType() == 'json') {
                // Override some display parameters to show a minimum of content elements
                $itemParams = class_exists('JParameter') ? new JParameter($items[$i]->params) : new Registry($items[$i]->params);
                $itemParams->set($prefix . 'ItemIntroText', true);
                $itemParams->set($prefix . 'ItemFullText', true);
                $itemParams->set($prefix . 'ItemTags', true);
                $itemParams->set($prefix . 'ItemExtraFields', true);
                $itemParams->set($prefix . 'ItemAttachments', true);
                $itemParams->set($prefix . 'ItemRating', true);
                $itemParams->set($prefix . 'ItemAuthor', true);
                $itemParams->set($prefix . 'ItemImageGallery', true);
                $itemParams->set($prefix . 'ItemVideo', true);
                $itemParams->set($prefix . 'ItemImage', true);
                $items[$i]->params = $itemParams->toString();
            }
            // --- JSON Output [finish] ---

            // Check if the model should use the cache for preparing the item even if the user is logged in
            if ($user->guest || $task == 'tag' || $task == 'search' || $task == 'date') {
                $cacheFlag = true;
            } else {
                $cacheFlag = true;
                if (K2HelperPermissions::canEditItem($items[$i]->created_by, $items[$i]->catid)) {
                    $cacheFlag = false;
                }
            }

            // Prepare item
	        if ($cacheFlag && $params->get('enableExtendedCache', 0)) {
		        $hits = $items[$i]->hits;
		        $items[$i]->hits = 0;
		        Table::getInstance('K2Category', 'Table');

                // todo: study deeply whether to remove this completely (com_k2_extended cache container)
                // as it appears it has no effect on joomla > 3 except causing problems and increasing cache occupied storage
		        if (version_compare(JVERSION, '4.0.0-dev', 'ge')){
					if($format !=='feed'){
						$task_pre = isset($task) ? '_'.$task : '';
						$format_pre = isset($format) ? '_'.$format : '';
						$view_pre = isset($view) ? '_'.$view : '';
						$tag_name = Factory::getApplication()->input->getString('tag');
						$cLayout = $this->getLayout();
						$layout_pre = isset($cLayout) ? '_'.$cLayout : '';
						$tag_pre = isset($tag_name) ? '_'.$tag_name : '';
						$key = $items[$i]->id .'_'. $items[$i]->alias . $task_pre . $view_pre . $format_pre . $tag_pre . $layout_pre. $item_id;
						if ($cache->contains($key))
						{
							if (is_object($items[$i]))
							{
								$items[$i] = json_decode(json_encode($cache->get($key)));
							}
						}
						else{
							// items are not in cache => prepareItems
							$items[$i] = $itemModel->prepareItem($items[$i], $view, $task);
							$store = $items[$i];
							if (is_object($store)) {
								$store = json_decode(trim (json_encode($store), chr (239). chr (187). chr (191)), true );
							}
							try {
								$cache->store($store, $key);
							} catch (\Exception $e) {
								// for the moment settle to miss caching the query returned object
								// throw new \Exception(Text::_($e), 500);
							}
						}
					}
					else
					{
						$items[$i] = $itemModel->prepareItem($items[$i], $view, $task);
					}

		        }
		        else{
			        $args = array(array(
				        $itemModel,
				        'prepareItem'
			        ), $items[$i], $view, $task);
			        $callback = array_shift($args);
			        $items[$i] = $cache->get($callback, $args);
		        }

		        $items[$i]->hits = $hits;
	        } else {
		        $items[$i] = $itemModel->prepareItem($items[$i], $view, $task);
	        }
            // Plugins
	        $items[$i] = $itemModel->execPlugins($items[$i], $view, $task);

            // Trigger comments counter event if needed
            if (
                $params->get('catItemK2Plugins') &&
                ($params->get('catItemCommentsAnchor') || $params->get('itemCommentsAnchor') || $params->get('itemComments'))
            ) {
                /* since J4 compatibility */
                $results = Factory::getApplication()->triggerEvent('onK2CommentsCounter', array(
                    &$items[$i],
                    &$params,
                    $limitstart
                ));
                $items[$i]->event->K2CommentsCounter = trim(implode("\n", $results));
            }

            // --- JSON Output [start] ---
            if ($document->getType() == 'json') {
                // Set default image
                if ($task == 'date' || $task == 'search' || $task == 'tag' || $task == 'user') {
                    $items[$i]->image = (isset($items[$i]->imageGeneric)) ? $items[$i]->imageGeneric : '';
                } else {
                    if (!$moduleID) {
                        K2HelperUtilities::setDefaultImage($items[$i], $view, $params);
                    }
                }

                $rowsForJSON[] = $itemModel->prepareJSONItem($items[$i]);
            }
            // --- JSON Output [finish] ---
        }

        // --- JSON Output [start] ---
        if ($document->getType() == 'json') {
            $response->items = $rowsForJSON;

            // Output
            $json = json_encode($response);
            $callback = Factory::getApplication()->input->getCmd('callback');
            if ($callback) {
                $document->setMimeEncoding('application/javascript');
                echo $callback . '(' . $json . ')';
            } else {
                echo $json;
            }
        }
        // --- JSON Output [finish] ---

        // Add item link
        if (K2HelperPermissions::canAddItem()) {
            $addLink = Route::_('index.php?option=com_k2&view=item&task=add&tmpl=component&template=system');
            $this->addLink = $addLink;
        }

        // Pathway
        $pathway = $app->getPathWay();
        if (!empty($menuActive)) {
            if (!isset($menuActive->query['task'])) {
                $menuActive->query['task'] = '';
            }
            switch ($task) {
                case 'category':
                    if ($menuActive->query['task'] != 'category' || $menuActive->query['id'] != Factory::getApplication()->input->getInt('id')) {
                        $pathway->addItem($title, '');
                    }
                    break;
                case 'user':
                    if ($menuActive->query['task'] != 'user' || $menuActive->query['id'] != Factory::getApplication()->input->getInt('id')) {
                        $pathway->addItem($title, '');
                    }
                    break;

                case 'tag':
                    if ($menuActive->query['task'] != 'tag' || $menuActive->query['tag'] != Factory::getApplication()->input->getVar('tag')) {
                        $pathway->addItem($title, '');
                    }
                    break;

                case 'search':
                case 'date':
                    $pathway->addItem($title, '');
                    break;
            }
        }

        // --- B/C stuff [start] ---
        // Update the Google Search results container
        if ($task == 'search') {
            $params->set('googleSearch', 0);
            $googleSearchContainerID = trim($params->get('googleSearchContainer', 'k2GoogleSearchContainer'));
            if ($googleSearchContainerID == 'k2Container') {
                $googleSearchContainerID = 'k2GoogleSearchContainer';
            }
            $params->set('googleSearchContainer', $googleSearchContainerID);
        }
        // --- B/C stuff [finish] ---

        // Head Stuff
        if (!in_array($document->getType(), ['feed', 'json', 'raw'])) {
            $menuItemMatch = false;
            $metaTitle = '';

            switch ($task) {
                case 'category':
                    $menuItemMatch = $this->menuItemMatchesK2Entity('itemlist', 'category', $category->id);

                    // Set canonical link
                    $this->setCanonicalUrl($category->link);
                    $link = $category->link;

                    // Set <title>
                    if ($menuItemMatch) {
                        $page_title = $params->get('page_title');
                        if (empty($page_title)) {
                            $params->set('page_title', $title);
                        }
                    } else {
                        $params->set('page_title', $title);
                    }

                    // Prepend/append site name
                    if ($app->getCfg('sitename_pagetitles', 0) == 1) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $app->getCfg('sitename'), $params->get('page_title')));
                    } elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $params->get('page_title'), $app->getCfg('sitename')));
                    }

                    // Override item title with page heading (if set)
                    if ($menuItemMatch) {
                        if ($params->get('page_heading')) {
                            $category->name = $params->get('page_heading');
                        }

                        // B/C assignment so Joomla 2.5+ uses the 'show_page_title' parameter as Joomla 1.5 does
                        $params->set('show_page_title', $params->get('show_page_heading'));
                    }

                    $metaTitle = trim($params->get('page_title'));
                    $document->setTitle($metaTitle);

                    // Set meta description
                    $metaDesc = $document->getMetadata('description');

                    if ($category->metaDescription) {
                        $metaDesc = filter_var($category->metaDescription, FILTER_UNSAFE_RAW);
                    } else {
                        $metaDesc = preg_replace("#{(.*?)}(.*?){/(.*?)}#s", '', $category->description);
                        $metaDesc = filter_var($metaDesc, FILTER_UNSAFE_RAW);
                    }

                    if ($menuItemMatch) {
                        if ($params->get('menu-meta_description')) {
                            $metaDesc = $params->get('menu-meta_description');
                        }
                    }

	            if (!empty($tag->description)) {
		            $metaDesc = $tag->description;
	            }

                    $metaDesc = isset($metaDesc) ? trim($metaDesc) : '';
                    $document->setDescription(K2HelperUtilities::characterLimit($metaDesc, $params->get('metaDescLimit', 150)));

                    // Set meta keywords
                    $metaKeywords = $document->getMetadata('keywords');

                    if ($category->metaKeywords) {
                        $metaKeywords = $category->metaKeywords;
                    }

                    if ($menuItemMatch) {
                        if ($params->get('menu-meta_keywords')) {
                            $metaKeywords = $params->get('menu-meta_keywords');
                        }
                    }

                    $metaKeywords = trim($metaKeywords);
                    $document->setMetadata('keywords', $metaKeywords);

                    // Set meta robots & author
                    $metaRobots = $document->getMetadata('robots');
                    $metaAuthor = '';

                    if (!empty($category->metaRobots)) {
                        $metaRobots = $category->metaRobots;
                    }

                    if (!empty($category->metaAuthor)) {
                        $metaAuthor = $category->metaAuthor;
                    }

                    if ($menuItemMatch) {
                        if ($params->get('robots')) {
                            $metaRobots = $params->get('robots');
                        }
                    }

                    $document->setMetadata('robots', $metaRobots);

                    $metaAuthor = trim($metaAuthor);
                    if ($app->getCfg('MetaAuthor') == '1' && $metaAuthor) {
                        $document->setMetadata('author', $metaAuthor);
                    }

                    // Common for Facebook & Twitter meta tags
                    $metaImage = '';
                    if (!empty($category->image) && strpos($category->image, 'placeholder/category.png') === false) {
                        $metaImage = substr(URI::root(), 0, -1) . str_replace(URI::root(true), '', $category->image);
                    }

                    // Set Facebook meta tags
                    if ($params->get('facebookMetatags', 1)) {
                        $document->setMetaData('og:url', $currentAbsoluteUrl);
                        $document->setMetaData('og:type', 'website');
                        $document->setMetaData('og:title', filter_var($metaTitle, FILTER_UNSAFE_RAW));
                        $document->setMetaData('og:description', K2HelperUtilities::characterLimit($metaDesc, 300)); // 300 chars limit for Facebook post sharing
                        if ($metaImage) {
                            $document->setMetaData('og:image', $metaImage);
                            $document->setMetaData('image', $metaImage); // Generic meta
                        }
                    }

                    // Set Twitter meta tags
                    if ($params->get('twitterMetatags', 1)) {
                        $document->setMetaData('twitter:card', $params->get('twitterCardType', 'summary'));
                        if ($params->get('twitterUsername')) {
                            $document->setMetaData('twitter:site', '@' . $params->get('twitterUsername'));
                        }
                        $document->setMetaData('twitter:title', filter_var($metaTitle, FILTER_UNSAFE_RAW));
                        $document->setMetaData('twitter:description', K2HelperUtilities::characterLimit($metaDesc, 200)); // 200 chars limit for Twitter post sharing
                        if ($metaImage) {
                            $document->setMetaData('twitter:image', $metaImage);
                            $document->setMetaData('twitter:image:alt', filter_var($metaTitle, FILTER_UNSAFE_RAW));
                            if (!$params->get('facebookMetatags')) {
                                $document->setMetaData('image', $metaImage); // Generic meta (if not already set in Facebook meta tags)
                            }
                        }
                    }

                    break;
                case 'tag':
                    $menuItemMatch = $this->menuItemMatchesK2Entity('itemlist', 'tag', $tag->name);

                    // Set canonical link
                    $this->setCanonicalUrl($link);

                    // Set <title>
                    if ($menuItemMatch) {
                        $page_title = $params->get('page_title');
                        if (empty($page_title)) {
                            $params->set('page_title', $tag->name);
                        }
                    } else {
                        $params->set('page_title', $tag->name);
                    }

                    // Prepend/append site name
                    if ($app->getCfg('sitename_pagetitles', 0) == 1) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $app->getCfg('sitename'), $params->get('page_title')));
                    } elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $params->get('page_title'), $app->getCfg('sitename')));
                    }

                    // Override item title with page heading (if set)
                    if ($menuItemMatch) {
                        if ($params->get('page_heading')) {
                            $tag->name = $params->get('page_heading');
                        }

                        // B/C assignment so Joomla 2.5+ uses the 'show_page_title' parameter as Joomla 1.5 does
                        $params->set('show_page_title', $params->get('show_page_heading'));
                    }

                    $metaTitle = trim($params->get('page_title'));
                    $document->setTitle($metaTitle);

                    // Set meta description
                    $metaDesc = Text::_('K2_TAG_VIEW_DEFAULT_METADESC') . ' \'' . $tag->name . '\'';
                    if ($document->getMetadata('description', '')) {
                        $metaDesc .= ' - ' . $document->getMetadata('description');
                    }

                    if ($menuItemMatch) {
                        if ($params->get('menu-meta_description')) {
                            $metaDesc = $params->get('menu-meta_description');
                        }
                    }

                    $metaDesc = isset($metaDesc) ? trim($metaDesc) : '';
                    $document->setDescription(K2HelperUtilities::characterLimit($metaDesc, $params->get('metaDescLimit', 150)));

                    // Set meta keywords
                    $metaKeywords = $tag->name;
                    if ($document->getMetadata('keywords', '')) {
                        $metaKeywords .= ', ' . $document->getMetadata('keywords');
                    }

                    if ($menuItemMatch) {
                        if ($params->get('menu-meta_keywords')) {
                            $metaKeywords = $params->get('menu-meta_keywords');
                        }
                    }

                    $metaKeywords = trim($metaKeywords);
                    $document->setMetadata('keywords', $metaKeywords);

                    // Set meta robots
                    $metaRobots = $document->getMetadata('robots');

                    if ($menuItemMatch) {
                        if ($params->get('robots')) {
                            $metaRobots = $params->get('robots');
                        }
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

                    break;
                case 'user':
                    $menuItemMatch = $this->menuItemMatchesK2Entity('itemlist', 'user', $userObject->name);

                    $filteredUserName = filter_var($userObject->name, FILTER_UNSAFE_RAW);

                    // Set canonical link
                    $this->setCanonicalUrl($link);

                    // Set <title>
                    if ($menuItemMatch) {
                        $page_title = $params->get('page_title');
                        if (empty($page_title)) {
                            $params->set('page_title', $filteredUserName);
                        }
                    } else {
                        $params->set('page_title', $filteredUserName);
                    }

                    // Prepend/append site name
                    if ($app->getCfg('sitename_pagetitles', 0) == 1) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $app->getCfg('sitename'), $params->get('page_title')));
                    } elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $params->get('page_title'), $app->getCfg('sitename')));
                    }

                    // Override item title with page heading (if set)
                    if ($menuItemMatch) {
                        if ($params->get('page_heading')) {
                            $userObject->name = $params->get('page_heading');
                        }

                        // B/C assignment so Joomla 2.5+ uses the 'show_page_title' parameter as Joomla 1.5 does
                        $params->set('show_page_title', $params->get('show_page_heading'));
                    }

                    $metaTitle = trim($params->get('page_title'));
                    $document->setTitle($metaTitle);

                    // Set meta description
                    $metaDesc = Text::_('K2_USER_VIEW_DEFAULT_METADESC') . ' \'' . $filteredUserName . '\'';
                    if ($document->getMetadata('description', '')) {
                        $metaDesc .= ' - ' . $document->getMetadata('description');
                    }

                    if (!empty($userObject->profile->description)) {
                        $metaDesc = filter_var($userObject->profile->description, FILTER_UNSAFE_RAW);
                    }

                    if ($menuItemMatch) {
                        if ($params->get('menu-meta_description')) {
                            $metaDesc = $params->get('menu-meta_description');
                        }
                    }

                    $metaDesc = isset($metaDesc) ? trim($metaDesc) : '';
                    $document->setDescription(K2HelperUtilities::characterLimit($metaDesc, $params->get('metaDescLimit', 150)));

                    // Set meta keywords
                    $metaKeywords = $document->getMetadata('keywords');

                    if ($menuItemMatch) {
                        if ($params->get('menu-meta_keywords')) {
                            $metaKeywords = $params->get('menu-meta_keywords');
                        }
                    }

                    $metaKeywords = trim($metaKeywords);
                    $document->setMetadata('keywords', $metaKeywords);

                    // Set meta robots & author
                    $metaRobots = $document->getMetadata('robots');

                    if ($menuItemMatch) {
                        if ($params->get('robots')) {
                            $metaRobots = $params->get('robots');
                        }
                    }

                    $document->setMetadata('robots', $metaRobots);

                    $metaAuthor = trim($filteredUserName);
                    if ($app->getCfg('MetaAuthor') == '1' && $metaAuthor) {
                        $document->setMetadata('author', $metaAuthor);
                    }

                    // Common for Facebook & Twitter meta tags
                    $metaImage = '';
                    if (!empty($userObject->avatar) && strpos($userObject->avatar, 'placeholder/user.png') === false) {
                        if (strpos($userObject->avatar, 'http://') !== false || strpos($userObject->avatar, 'https://') !== false) {
                            $metaImage = $userObject->avatar;
                        } else {
                            $metaImage = substr(URI::root(), 0, -1) . str_replace(URI::root(true), '', $userObject->avatar);
                        }
                    }

                    // Set Facebook meta tags
                    if ($params->get('facebookMetatags', 1)) {
                        $document->setMetaData('og:url', $link);
                        $document->setMetaData('og:type', 'website');
                        $document->setMetaData('og:title', filter_var($metaTitle, FILTER_UNSAFE_RAW));
                        $document->setMetaData('og:description', K2HelperUtilities::characterLimit($metaDesc, 300)); // 300 chars limit for Facebook post sharing
                        if ($metaImage) {
                            $document->setMetaData('og:image', $metaImage);
                            $document->setMetaData('image', $metaImage); // Generic meta
                        }
                    }

                    // Set Twitter meta tags
                    if ($params->get('twitterMetatags', 1)) {
                        $document->setMetaData('twitter:card', $params->get('twitterCardType', 'summary'));
                        if ($params->get('twitterUsername')) {
                            $document->setMetaData('twitter:site', '@' . $params->get('twitterUsername'));
                        }
                        $document->setMetaData('twitter:title', filter_var($metaTitle, FILTER_UNSAFE_RAW));
                        $document->setMetaData('twitter:description', K2HelperUtilities::characterLimit($metaDesc, 200)); // 200 chars limit for Twitter post sharing
                        if ($metaImage) {
                            $document->setMetaData('twitter:image', $metaImage);
                            $document->setMetaData('twitter:image:alt', filter_var($metaTitle, FILTER_UNSAFE_RAW));
                            if (!$params->get('facebookMetatags')) {
                                $document->setMetaData('image', $metaImage); // Generic meta (if not already set in Facebook meta tags)
                            }
                        }
                    }

                    break;
                case 'date':
                    // Set canonical link
                    $this->setCanonicalUrl($currentRelativeUrl);
                    $link = $currentRelativeUrl;

                    // Set <title>
                    $params->set('page_title', $title);

                    // Prepend/append site name
                    if ($app->getCfg('sitename_pagetitles', 0) == 1) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $app->getCfg('sitename'), $params->get('page_title')));
                    } elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $params->get('page_title'), $app->getCfg('sitename')));
                    }

                    $metaTitle = trim($params->get('page_title'));
                    $document->setTitle($metaTitle);

                    // Set meta description
                    $metaDesc = ($document->getMetadata('description')) ? $document->getMetadata('description') : Text::_('K2_ITEMS_FILTERED_BY_DATE') . ' ' . $metaTitle;
                    $metaDesc = isset($metaDesc) ? trim($metaDesc) : '';
                    $document->setDescription(K2HelperUtilities::characterLimit($metaDesc, $params->get('metaDescLimit', 150)));

                    // Set meta keywords
                    $metaKeywords = trim($document->getMetadata('keywords'));
                    $document->setMetadata('keywords', $metaKeywords);

                    // Set meta robots
                    $metaRobots = $document->getMetadata('robots');
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

                    break;
                case 'search':
                    // Set canonical link
                    $this->setCanonicalUrl($currentRelativeUrl);
                    $link = $currentRelativeUrl;

                    // Set <title>
                    $params->set('page_title', $title);

                    // Prepend/append site name
                    if ($app->getCfg('sitename_pagetitles', 0) == 1) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $app->getCfg('sitename'), $params->get('page_title')));
                    } elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
                        $params->set('page_title', Text::sprintf('JPAGETITLE', $params->get('page_title'), $app->getCfg('sitename')));
                    }

                    $metaTitle = trim(preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', html_entity_decode($params->get('page_title'))));
                    $document->setTitle($metaTitle);

                    // Set meta description
                    $metaDesc = ($document->getMetadata('description')) ? $document->getMetadata('description') : Text::_('K2_SEARCH_RESULTS_FOR') . ' ' . $metaTitle;
                    $metaDesc = isset($metaDesc) ? trim($metaDesc) : '';
                    $document->setDescription(K2HelperUtilities::characterLimit($metaDesc, $params->get('metaDescLimit', 150)));

                    // Set meta keywords
                    $metaKeywords = trim($document->getMetadata('keywords'));
                    $document->setMetadata('keywords', $metaKeywords);

                    // Set meta robots
                    $metaRobots = $document->getMetadata('robots');
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

                    break;
                default:
                    // Set canonical link
                    $this->setCanonicalUrl($currentRelativeUrl);
                    $link = $currentRelativeUrl;

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

                    $metaDesc = isset($metaDesc) ? trim($metaDesc) : '';
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
                    break;
            }

            // Feed URLs (use the $link variable set previously)
            $feedLink = $link;
            $joiner = '?';
            if (strpos($feedLink, '?') !== false) {
                $joiner = '&';
            }
            $feedLink .= $joiner . 'format=feed';

            /*
            if (!is_null($menuActive) && isset($menuActive->id)) {
                $feedLink .= $joiner.'format=feed&Itemid='.$menuActive->id;
            } else {
                $feedLink .= $joiner.'format=feed';
            }
            */

            if ($addHeadFeedLink) {
                if ($metaTitle) {
                    $metaTitle = $metaTitle . ' | ';
                }
                $document->addHeadLink(Route::_($feedLink), 'alternate', 'rel', array(
                    'type' => 'application/rss+xml',
                    'title' => $metaTitle . '' . Text::_('K2_FEED')
                ));
                $document->addHeadLink(Route::_($feedLink . '&type=rss'), 'alternate', 'rel', array(
                    'type' => 'application/rss+xml',
                    'title' => $metaTitle . 'RSS 2.0'
                ));
                $document->addHeadLink(Route::_($feedLink . '&type=atom'), 'alternate', 'rel', array(
                    'type' => 'application/atom+xml',
                    'title' => $metaTitle . 'Atom 1.0'
                ));
            }

            $feedLink = Route::_($feedLink);
            $this->feed = $feedLink;
        }

        if (!in_array($document->getType(), ['feed', 'json'])) {
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
            if ($task == "category" || $task == "") {
                // Leading items
                $offset = 0;
                $length = $params->get('num_leading_items');
                $leading = array_slice($items, $offset, $length);

                // Primary
                $offset = (int)$params->get('num_leading_items');
                $length = (int)$params->get('num_primary_items');
                $primary = array_slice($items, $offset, $length);

                // Secondary
                $offset = (int)($params->get('num_leading_items') + $params->get('num_primary_items'));
                $length = (int)$params->get('num_secondary_items');
                $secondary = array_slice($items, $offset, $length);

                // Links
                $offset = (int)($params->get('num_leading_items') + $params->get('num_primary_items') + $params->get('num_secondary_items'));
                $length = (int)$params->get('num_links');
                $links = array_slice($items, $offset, $length);

                $this->leading = $leading;
                $this->primary = $primary;
                $this->secondary = $secondary;
                $this->links = $links;
            } else {
                $this->items = $items;
            }

            // Set default values to avoid division by zero
            if ($params->get('num_leading_columns') == 0) {
                $params->set('num_leading_columns', 1);
            }
            if ($params->get('num_primary_columns') == 0) {
                $params->set('num_primary_columns', 1);
            }
            if ($params->get('num_secondary_columns') == 0) {
                $params->set('num_secondary_columns', 1);
            }
            if ($params->get('num_links_columns') == 0) {
                $params->set('num_links_columns', 1);
            }

            $this->params = $params;
            $this->pagination = $pagination;
            // temp fix #90
            // Joomla is stripping '2' from option value => 'com_k instead of com_k2'
            $jversion = new JVersion();
            $version = $jversion->getShortVersion();
            if (version_compare($version, '5.1.3', '>=')) {
            	$this->pagination->setAdditionalUrlParam('option', 'com_k2');
            	$this->pagination->setAdditionalUrlParam('task', Factory::getApplication()->input->getCmd('task'));
            	$this->pagination->setAdditionalUrlParam('tag', Factory::getApplication()->input->getString('tag'));
            }

            // K2 Plugins
            /* since J4 compatibility */
            Factory::getApplication()->triggerEvent('onK2BeforeViewDisplay');

            // Display
            parent::display($tpl);
        }
    }

    public function module()
    {
        $app = Factory::getApplication();
        $document = Factory::getDocument();

        if ($document->getType() == 'raw') {
            $componentParams = ComponentHelper::getParams('com_k2');

            $itemlistModel = K2Model::getInstance('Itemlist', 'K2Model');

            jimport('joomla.application.module.helper');
            $moduleID = Factory::getApplication()->input->getInt('moduleID');
            if ($moduleID) {
                $result = $itemlistModel->getModuleItems($moduleID);
                $items = $result->items;

                if (is_string($result->params)) {
                    $params = class_exists('JParameter') ? new JParameter($result->params) : new Registry($result->params);
                } else {
                    $params = $result->params;
                }

                if ($params->get('getTemplate')) {
                    require(ModuleHelper::getLayoutPath('mod_k2_content', $params->get('getTemplate') . '/default'));
                } else {
                    require(ModuleHelper::getLayoutPath($result->module, 'default'));
                }
            }
            $app->close();
        }
    }

    private function setCanonicalUrl($url)
    {
        $document = Factory::getDocument();
        $limitstart = Factory::getApplication()->input->getInt('limitstart', 0);
        $params = K2HelperUtilities::getParams('com_k2');
        $canonicalURL = $params->get('canonicalURL', 'relative');
        if ($canonicalURL) {
            if ($limitstart) {
                $joiner = '?';
                if (strpos($url, '?') !== false) {
                    $joiner = '&';
                }
                $url = $url . '' . $joiner . 'start=' . $limitstart;
            }
            if ($canonicalURL == 'absolute') {
                $url = substr(str_replace(Uri::root(true), '', Uri::root(false)), 0, -1) . $url;
            }
            $document->addHeadLink($url, 'canonical', 'rel');
        }
    }

    private function menuItemMatchesK2Entity($view, $task, $identifier)
    {
        $app = Factory::getApplication();

        // Menu
        $menu = $app->getMenu();
        $menuActive = $menu->getActive();

        // Match
        $matched = false;

        if (isset($task)) {
            if ($task == 'tag') {
                if (is_object($menuActive) && isset($menuActive->query['view']) && $menuActive->query['view'] == $view && isset($menuActive->query['task']) && $menuActive->query['task'] == $task && isset($menuActive->query['tag']) && $menuActive->query['tag'] == $identifier) {
                    $matched = true;
                }
            } else {
                if (is_object($menuActive) && isset($menuActive->query['view']) && $menuActive->query['view'] == $view && isset($menuActive->query['task']) && $menuActive->query['task'] == $task && isset($menuActive->query['id']) && $menuActive->query['id'] == $identifier) {
                    $matched = true;
                }
            }
        } else {
            if (is_object($menuActive) && isset($menuActive->query['view']) && $menuActive->query['view'] == $view && isset($menuActive->query['id']) && $menuActive->query['id'] == $identifier) {
                $matched = true;
            }
        }

        return $matched;
    }
}
