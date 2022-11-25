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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Filesystem\File;

jimport('joomla.application.component.view');

class K2ViewItem extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $document = Factory::getDocument();
        $params = K2HelperUtilities::getParams('com_k2');
        $limitstart = Factory::getApplication()->input->getInt('limitstart', 0);
        $view = Factory::getApplication()->input->getWord('view');
        $task = Factory::getApplication()->input->getWord('task');

        $config = Factory::getConfig();

        $db = Factory::getDbo();
        $jnow = Factory::getDate();
        $now = $jnow->toSql();
        $nullDate = $db->getNullDate();

        $this->setLayout('item');

        /* since J4 compatibility */
// init $addLink to avoid warning
        $addLink = '';
        // Add link
        if (K2HelperPermissions::canAddItem()) {
            $addLink = Route::_('index.php?option=com_k2&view=item&task=add&tmpl=component&template=system');
        }
        $this->addLink = $addLink;

        // Get item model
        $model = $this->getModel();
        $item = $model->getData();

        // Menu
        $menu = $app->getMenu();
        $menuDefault = $menu->getDefault();
        $menuActive = $menu->getActive();

        // Important URLs
        $currentAbsoluteUrl = Uri::getInstance()->toString();
        $currentRelativeUrl = Uri::root(true) . str_replace(substr(Uri::root(), 0, -1), '', $currentAbsoluteUrl);

        // Check if item exists
        if (!is_object($item) || !$item->id) {
            $app->setHeader('status', 404, true);
            throw new \Exception(Text::_('K2_ITEM_NOT_FOUND'), 404);
        }

        // --- JSON Output [start] ---
        // Set the document type in Joomla 1.5
        if (Factory::getApplication()->input->getCmd('format') == 'json') {
            $document->setMimeEncoding('application/json');
            $document->setType('json');
        }
        if ($document->getType() == 'json') {
            // Override some display parameters to show a minimum of content elements
            $itemParams = class_exists('JParameter') ? new JParameter($item->params) : new Registry($item->params);
            $itemParams->set('itemIntroText', true);
            $itemParams->set('itemFullText', true);
            $itemParams->set('itemTags', true);
            $itemParams->set('itemExtraFields', true);
            $itemParams->set('itemAttachments', true);
            $itemParams->set('itemRating', true);
            $itemParams->set('itemAuthor', true);
            $itemParams->set('itemImageGallery', true);
            $itemParams->set('itemVideo', true);
            $item->params = $itemParams->toString();
        }
        // --- JSON Output [finish] ---

        // Prepare item
        $item = $model->prepareItem($item, $view, $task);
        $itemTextBeforePlugins = $item->introtext . ' ' . $item->fulltext;

        // Plugins
        $item = $model->execPlugins($item, $view, $task);

        // User K2 plugins
        $item->event->K2UserDisplay = '';
        if (isset($item->author) && is_object($item->author->profile) && isset($item->author->profile->id)) {
            PluginHelper::importPlugin('k2');
            /* since J4 compatibility */
            /* JDispatcher removed in J4 */
            /*
                        $dispatcher = JDispatcher::getInstance();
            */
            /* since J4 compatibility */
            $results = Factory::getApplication()->triggerEvent('onK2UserDisplay', array(
                &$item->author->profile,
                &$params,
                $limitstart
            ));
            $item->event->K2UserDisplay = trim(implode("\n", $results));
            $item->author->profile->url = htmlspecialchars($item->author->profile->url, ENT_QUOTES, 'UTF-8');
        }

        // Access check
        if ($this->getLayout() == 'form') {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        if (!in_array($item->access, $user->getAuthorisedViewLevels()) || !in_array($item->category->access, $user->getAuthorisedViewLevels())) {
            if ($user->guest) {
                $uri = Uri::getInstance();
                $url = 'index.php?option=com_users&view=login&return=' . base64_encode($uri->toString());
                $app->enqueueMessage(Text::_('K2_YOU_NEED_TO_LOGIN_FIRST'), 'notice');
                $app->redirect(Route::_($url, false));
            } else {
                JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
                return;
            }
        }

        // Published check
        if (
            !$item->published ||
            $item->trash ||
            ($item->publish_up != $nullDate && $item->publish_up > $now) ||
            ($item->publish_down != $nullDate && $item->publish_down < $now)
        ) {
            $app->setHeader('status', 404, true);
            throw new \Exception(Text::_('K2_ITEM_NOT_FOUND'), 404);
        }

        if (!$item->category->published || $item->category->trash) {
            $app->setHeader('status', 404, true);
            throw new \Exception(Text::_('K2_CATEGORY_NOT_FOUND'), 404);
        }

        // Increase item hits counter
        if ($params->get('siteItemHits', 1)) {
            $model->hit($item->id);
        }

        // Set default image
        K2HelperUtilities::setDefaultImage($item, $view);

        // B/C code for reCaptcha
        if ($params->get('antispam') == 'recaptcha' || $params->get('antispam') == 'both') {
            $params->set('recaptcha', true);
            $item->params->set('recaptcha', true);
        } else {
            $params->set('recaptcha', false);
            $item->params->set('recaptcha', false);
        }
        $params->set('recaptchaV2', true);
        $item->params->set('recaptchaV2', true);

        // Comments
        if ($document->getType() != 'json') {
            $item->event->K2CommentsCounter = '';
            $item->event->K2CommentsBlock = '';
            if ($item->params->get('itemComments')) {
                // Trigger comments events
                PluginHelper::importPlugin('k2');
                /* since J4 compatibility */
                /* JDispatcher removed in J4 */
                /*
                                $dispatcher = JDispatcher::getInstance();
                */
                /* since J4 compatibility */
                $results = Factory::getApplication()->triggerEvent('onK2CommentsCounter', array(
                    &$item,
                    &$params,
                    $limitstart
                ));
                $item->event->K2CommentsCounter = trim(implode("\n", $results));
                /* since J4 compatibility */
                $results = Factory::getApplication()->triggerEvent('onK2CommentsBlock', array(
                    &$item,
                    &$params,
                    $limitstart
                ));
                $item->event->K2CommentsBlock = trim(implode("\n", $results));

                // Load K2 native comments system only if there are no plugins overriding it
                if (empty($item->event->K2CommentsCounter) && empty($item->event->K2CommentsBlock)) {

                    // Load reCaptcha
                    if (!Factory::getApplication()->input->getInt('print') && ($item->params->get('comments') == '1' || ($item->params->get('comments') == '2' && K2HelperPermissions::canAddComment($item->catid)))) {
                        if ($params->get('recaptcha') && ($user->guest || $params->get('recaptchaForRegistered', 1))) {
                            $document->addScript('https://www.google.com/recaptcha/api.js?onload=onK2RecaptchaLoaded&render=explicit');
                            $document->addScriptDeclaration('
                                function onK2RecaptchaLoaded() {
                                    grecaptcha.render("recaptcha", {
                                        "sitekey": "' . $item->params->get('recaptcha_public_key') . '",
                                        "theme": "' . $item->params->get('recaptcha_theme', 'light') . '"
                                    });
                                }
                            ');
                            $this->recaptchaClass = 'k2-recaptcha-v2';
                        }
                    }

                    // Check for inline comment moderation
                    if (!$user->guest && $user->id == $item->created_by && $params->get('inlineCommentsModeration')) {
                        $inlineCommentsModeration = true;
                        $commentsPublished = false;
                    } else {
                        $inlineCommentsModeration = false;
                        $commentsPublished = true;
                    }
                    $this->inlineCommentsModeration = $inlineCommentsModeration;

                    // Flag spammer link
                    $reportSpammerFlag = false;
                    if ($user->authorise('core.admin', 'com_k2')) {
                        $reportSpammerFlag = true;
                        $document->addScriptDeclaration('var K2Language = ["' . Text::_('K2_REPORT_USER_WARNING', true) . '"];');
                    }

                    $limit = $params->get('commentsLimit');
                    $comments = $model->getItemComments($item->id, $limitstart, $limit, $commentsPublished);

                    for ($i = 0; $i < count($comments); $i++) {
                        $comments[$i]->commentText = nl2br($comments[$i]->commentText);

                        // Convert URLs to links properly
                        $comments[$i]->commentText = preg_replace("/([^\w\/])(www\.[a-z0-9\-]+\.[a-z0-9\-]+)/i", "$1http://$2", $comments[$i]->commentText);
                        $comments[$i]->commentText = preg_replace("/([\w]+:\/\/[\w\-?&;#~=\.\/\@]+[\w\/])/i", "<a target=\"_blank\" rel=\"nofollow\" href=\"$1\">$1</A>", $comments[$i]->commentText);
                        $comments[$i]->commentText = preg_replace("/([\w\-?&;#~=\.\/]+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,3}|[0-9]{1,3})(\]?))/i", "<a href=\"mailto:$1\">$1</A>", $comments[$i]->commentText);

                        $comments[$i]->userImage = K2HelperUtilities::getAvatar($comments[$i]->userID, $comments[$i]->commentEmail, $params->get('commenterImgWidth'));
                        if ($comments[$i]->userID > 0) {
                            $comments[$i]->userLink = K2HelperRoute::getUserRoute($comments[$i]->userID);
                        } else {
                            $comments[$i]->userLink = $comments[$i]->commentURL;
                        }
                        if ($reportSpammerFlag && $comments[$i]->userID > 0) {
                            $comments[$i]->reportUserLink = Route::_('index.php?option=com_k2&view=comments&task=reportSpammer&id=' . $comments[$i]->userID . '&format=raw');
                        } else {
                            $comments[$i]->reportUserLink = false;
                        }
                    }

                    $item->comments = $comments;

                    if (!isset($item->numOfComments)) {
                        $item->numOfComments = 0;
                    }

                    jimport('joomla.html.pagination');
                    $total = $item->numOfComments;
                    $pagination = new Pagination($total, $limitstart, $limit);
                }
            }
        }

        // Author's latest items
        if ($item->params->get('itemAuthorLatest') && $item->created_by_alias == '') {
            $itemlistModel = $this->getModel('itemlist');
            $authorLatestItems = $itemlistModel->getAuthorLatest($item->id, $item->params->get('itemAuthorLatestLimit'), $item->created_by);
            if (count($authorLatestItems)) {
                for ($i = 0; $i < count($authorLatestItems); $i++) {
                    $authorLatestItems[$i]->link = urldecode(Route::_(K2HelperRoute::getItemRoute($authorLatestItems[$i]->id . ':' . urlencode($authorLatestItems[$i]->alias), $authorLatestItems[$i]->catid . ':' . urlencode($authorLatestItems[$i]->categoryalias))));
                }
                $this->authorLatestItems = $authorLatestItems;
            }
        }

        // Related items
        if ($item->params->get('itemRelated') && isset($item->tags) && count($item->tags)) {
            $itemlistModel = $this->getModel('itemlist');
            $relatedItems = $itemlistModel->getRelatedItems($item->id, $item->tags, $item->params);
            if (count($relatedItems)) {
                for ($i = 0; $i < count($relatedItems); $i++) {
                    $relatedItems[$i]->link = urldecode(Route::_(K2HelperRoute::getItemRoute($relatedItems[$i]->id . ':' . urlencode($relatedItems[$i]->alias), $relatedItems[$i]->catid . ':' . urlencode($relatedItems[$i]->categoryalias))));
                }
                $this->relatedItems = $relatedItems;
            }
        }

        // Navigation (previous and next item)
        if ($item->params->get('itemNavigation')) {
            // Previous Item
            $previousItem = $model->getPreviousItem($item->id, $item->catid, $item->ordering, $item->params->get('catOrdering'));
            if (!is_null($previousItem)) {
                $item->previous = $model->prepareItem($previousItem, 'item', '');
                $item->previous = $model->execPlugins($item->previous, 'item', '');

                // B/C
                $item->previousLink = $item->previous->link;
                $item->previousTitle = $item->previous->title;

                // Image
                $item->previousImageXSmall = '';
                $item->previousImageSmall = '';
                $item->previousImageMedium = '';
                $item->previousImageLarge = '';
                $item->previousImageXLarge = '';

                $imageTimestamp = '';
                $dateModified = ((int)$previousItem->modified) ? $previousItem->modified : '';
                if ($params->get('imageTimestamp', 1) && $dateModified) {
                    $imageTimestamp = '?t=' . strftime("%Y%m%d_%H%M%S", strtotime($dateModified));
                }

                $imageFilenamePrefix = md5("Image" . $previousItem->id);
                $imagePathPrefix = Uri::base(true) . '/media/k2/items/cache/' . $imageFilenamePrefix;

                // Check if the "generic" variant exists
                if (File::exists(JPATH_SITE . '/media/k2/items/cache/' . $imageFilenamePrefix . '_Generic.jpg')) {
                    $item->previousImageGeneric = $imagePathPrefix . '_Generic.jpg' . $imageTimestamp;
                    $item->previousImageXSmall = $imagePathPrefix . '_XS.jpg' . $imageTimestamp;
                    $item->previousImageSmall = $imagePathPrefix . '_S.jpg' . $imageTimestamp;
                    $item->previousImageMedium = $imagePathPrefix . '_M.jpg' . $imageTimestamp;
                    $item->previousImageLarge = $imagePathPrefix . '_L.jpg' . $imageTimestamp;
                    $item->previousImageXLarge = $imagePathPrefix . '_XL.jpg' . $imageTimestamp;

                    $item->previousImageProperties = new stdClass;
                    $item->previousImageProperties->filenamePrefix = $imageFilenamePrefix;
                    $item->previousImageProperties->pathPrefix = $imagePathPrefix;
                }
            }

            // Next Item
            $nextItem = $model->getNextItem($item->id, $item->catid, $item->ordering, $item->params->get('catOrdering'));
            if (!is_null($nextItem)) {
                $item->next = $model->prepareItem($nextItem, 'item', '');
                $item->next = $model->execPlugins($item->next, 'item', '');

                // B/C
                $item->nextLink = $item->next->link;
                $item->nextTitle = $item->next->title;

                // Image
                $item->nextImageXSmall = '';
                $item->nextImageSmall = '';
                $item->nextImageMedium = '';
                $item->nextImageLarge = '';
                $item->nextImageXLarge = '';

                $imageTimestamp = '';
                $dateModified = ((int)$nextItem->modified) ? $nextItem->modified : '';
                if ($params->get('imageTimestamp', 1) && $dateModified) {
                    $imageTimestamp = '?t=' . strftime("%Y%m%d_%H%M%S", strtotime($dateModified));
                }

                $imageFilenamePrefix = md5("Image" . $nextItem->id);
                $imagePathPrefix = Uri::base(true) . '/media/k2/items/cache/' . $imageFilenamePrefix;

                // Check if the "generic" variant exists
                if (File::exists(JPATH_SITE . '/media/k2/items/cache/' . $imageFilenamePrefix . '_Generic.jpg')) {
                    $item->nextImageGeneric = $imagePathPrefix . '_Generic.jpg' . $imageTimestamp;
                    $item->nextImageXSmall = $imagePathPrefix . '_XS.jpg' . $imageTimestamp;
                    $item->nextImageSmall = $imagePathPrefix . '_S.jpg' . $imageTimestamp;
                    $item->nextImageMedium = $imagePathPrefix . '_M.jpg' . $imageTimestamp;
                    $item->nextImageLarge = $imagePathPrefix . '_L.jpg' . $imageTimestamp;
                    $item->nextImageXLarge = $imagePathPrefix . '_XL.jpg' . $imageTimestamp;

                    $item->nextImageProperties = new stdClass;
                    $item->nextImageProperties->filenamePrefix = $imageFilenamePrefix;
                    $item->nextImageProperties->pathPrefix = $imagePathPrefix;
                }
            }
        }

        // Absolute URL
        $uri = JURI::getInstance();
        $item->absoluteURL = $uri->toString();

        // Get the frontend's language for use in social media buttons - use explicit variable references for future update flexibility
        $getSiteLanguage = Factory::getLanguage();
        $languageTag = $getSiteLanguage->getTag();
        $item->langTagForFB = str_replace('-', '_', $languageTag);
        $item->langTagForTW = strtolower($languageTag);
        $item->langTagForLI = str_replace('-', '_', $languageTag);

        // Set the link for sharing
        $item->sharinglink = $item->absoluteURL;

        // --- B/C stuff [start] ---
        // Social Share URL
        $item->socialLink = urlencode($item->absoluteURL);

        // Twitter link (legacy code)
        if ($params->get('twitterUsername')) {
            $item->twitterURL = 'https://twitter.com/intent/tweet?text=' . urlencode($item->title) . '&amp;url=' . urlencode($item->absoluteURL) . '&amp;via=' . $params->get('twitterUsername');
        } else {
            $item->twitterURL = 'https://twitter.com/intent/tweet?text=' . urlencode($item->title) . '&amp;url=' . urlencode($item->absoluteURL);
        }

        // Deprecate Google+ sharing
        $params->set('itemGooglePlusOneButton', 0);
        $item->params->set('itemGooglePlusOneButton', 0);
        $item->langTagForGP = '';
        // --- B/C stuff [end] ---

        // Email link
        /* since J4 compatibility */
        // com_mailto is removed in joomla4 without replacement
        /*
            require_once(JPATH_SITE.'/components/com_mailto/helpers/mailto.php');
            $item->emailLink = Route::_('index.php?option=com_mailto&tmpl=component&link='.MailToHelper::addLink($item->absoluteURL));
        */

        // Set pathway
        $pathway = $app->getPathWay();
        if ($menuActive) {
            if (isset($menuActive->query['view']) && ($menuActive->query['view'] != 'item' || $menuActive->query['id'] != $item->id)) {
                if (!isset($menuActive->query['task']) || $menuActive->query['task'] != 'category' || $menuActive->query['id'] != $item->catid) {
                    $pathway->addItem($item->category->name, $item->category->link);
                }
                $pathway->addItem($item->rawTitle, '');
            }
        }

        // --- JSON Output [start] ---
        if ($document->getType() == 'json') {
            $uri = JURI::getInstance();

            // Build the output object
            $row = $model->prepareJSONItem($item);

            // Output
            $response = new stdClass;

            // Site
            $response->site = new stdClass;
            $response->site->url = $uri->toString(array('scheme', 'host', 'port'));
            $response->site->name = $config->get('sitename');
            $response->item = $row;

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

        // --- Insert additional HTTP headers [start] ---
        JFactory::getApplication()->allowCache(true);

        $itemCreatedOrModifiedDate = ((int)$item->modified) ? $item->modified : $item->created;
        $itemCreatedOrModifiedDate = strftime("%a, %d %b %Y %H:%M:%S GMT", strtotime($itemCreatedOrModifiedDate));

        // Last-Modified HTTP header
        JFactory::getApplication()->setHeader('Last-Modified', $itemCreatedOrModifiedDate);

        // Etag HTTP header
        JFactory::getApplication()->setHeader('ETag', md5($item->id . '_' . $itemCreatedOrModifiedDate));

        // Append as custom script tag to bypass Joomla cache shortcomings
        $caching = $config->get('caching');
        if ($caching) {
            $document->addScriptDeclaration('{"Last-Modified": "' . $itemCreatedOrModifiedDate . '", "ETag": "' . md5($item->id . '_' . $itemCreatedOrModifiedDate) . '"}', 'application/x-k2-headers');
        }

        // --- Insert additional HTTP headers [finish] ---

        // Head Stuff
        if (!in_array($document->getType(), ['json', 'raw'])) {
            $menuItemMatch = $this->menuItemMatchesK2Entity('item', null, $item->id);

            // Set canonical link
            $this->setCanonicalUrl($item->link);

            // Set <title>
            if ($menuItemMatch) {
                $page_title = $params->get('page_title');
                if (empty($page_title)) {
                    $params->set('page_title', $item->rawTitle);
                }
            } else {
                $params->set('page_title', $item->rawTitle);
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
                    $item->title = $params->get('page_heading');
                }

                // B/C assignment so Joomla 2.5+ uses the 'show_page_title' parameter as Joomla 1.5 does
                $params->set('show_page_title', $params->get('show_page_heading'));
            }

            $metaTitle = trim($params->get('page_title'));
            $document->setTitle($metaTitle);

            // Set meta description
            $metaDesc = $document->getMetadata('description');

            if ($item->metadesc) {
                $metaDesc = filter_var($item->metadesc, FILTER_SANITIZE_STRING);
            } else {
                $metaDesc = preg_replace("#{(.*?)}(.*?){/(.*?)}#s", '', $itemTextBeforePlugins);
                $metaDesc = filter_var($metaDesc, FILTER_SANITIZE_STRING);
            }

            if ($params->get('menu-meta_description')) {
                $metaDesc = $params->get('menu-meta_description');
            }

            $metaDesc = trim($metaDesc);
            $document->setDescription(K2HelperUtilities::characterLimit($metaDesc, $params->get('metaDescLimit', 150)));

            // Set meta keywords
            $metaKeywords = trim($document->getMetadata('keywords'));

            if ($item->metakey) {
                $metaKeywords = $item->metakey;
            } else {
                if (isset($item->tags) && count($item->tags)) {
                    $tmp = array();
                    foreach ($item->tags as $tag) {
                        $tmp[] = $tag->name;
                    }
                    $metaKeywords = implode(',', $tmp);
                }
            }

            if ($params->get('menu-meta_keywords')) {
                $metaKeywords = $params->get('menu-meta_keywords');
            }

            $metaKeywords = trim($metaKeywords);
            $document->setMetadata('keywords', $metaKeywords);

            // Set meta robots & author
            $metaRobots = $document->getMetadata('robots');
            $metaAuthor = '';

            if (!empty($item->author->name)) {
                $metaAuthor = $item->author->name;
            }

            $itemMetaData = (class_exists('JParameter')) ? new JParameter($item->metadata) : new Registry($item->metadata);
            $itemMetaData = $itemMetaData->toArray();
            if (!empty($itemMetaData['robots'])) {
                $metaRobots = $itemMetaData['robots'];
            }
            if (!empty($itemMetaData['author'])) {
                $metaAuthor = $itemMetaData['author'];
            }

            if ($params->get('robots')) {
                $metaRobots = $params->get('robots');
            }

            // Use a large image preview for Google Discover
            if ($metaRobots == '') {
                $metaRobots = 'max-image-preview:large';
            } else {
                $metaRobots .= ', max-image-preview:large';
            }

            $document->setMetadata('robots', $metaRobots);

            $metaAuthor = trim($metaAuthor);
            if ($app->getCfg('MetaAuthor') == '1' && $metaAuthor) {
                $document->setMetadata('author', $metaAuthor);
            }

            // Set Facebook meta tags
            if ($params->get('facebookMetatags', 1)) {
                $document->setMetaData('og:url', $item->absoluteURL);
                $document->setMetaData('og:type', 'article');
                $document->setMetaData('og:title', filter_var($metaTitle, FILTER_SANITIZE_STRING));
                $document->setMetaData('og:description', K2HelperUtilities::characterLimit($metaDesc, 300)); // 300 chars limit for Facebook post sharing
                $facebookImage = 'image' . $params->get('facebookImage', 'Medium');
                if ($item->$facebookImage) {
                    $basename = basename($item->$facebookImage);
                    if (strpos($basename, '?t=') !== false) {
                        $tmpBasename = explode('?t=', $basename);
                        $basenameWithNoTimestamp = $tmpBasename[0];
                    } else {
                        $basenameWithNoTimestamp = $basename;
                    }
                    if (File::exists(JPATH_SITE . '/media/k2/items/cache/' . $basenameWithNoTimestamp)) {
                        $image = JURI::root() . 'media/k2/items/cache/' . $basename;
                        $document->setMetaData('og:image', $image);
                        $document->setMetaData('image', $image); // Generic meta
                    }
                }
            }

            // Set Twitter meta tags
            if ($params->get('twitterMetatags', 1)) {
                $document->setMetaData('twitter:card', $params->get('twitterCardType', 'summary'));
                if ($params->get('twitterUsername')) {
                    $document->setMetaData('twitter:site', '@' . $params->get('twitterUsername'));
                }
                $document->setMetaData('twitter:title', filter_var($metaTitle, FILTER_SANITIZE_STRING));
                $document->setMetaData('twitter:description', K2HelperUtilities::characterLimit($metaDesc, 200)); // 200 chars limit for Twitter post sharing
                $twitterImage = 'image' . $params->get('twitterImage', 'Medium');
                if ($item->$twitterImage) {
                    $basename = basename($item->$twitterImage);
                    if (strpos($basename, '?t=') !== false) {
                        $tmpBasename = explode('?t=', $basename);
                        $basenameWithNoTimestamp = $tmpBasename[0];
                    } else {
                        $basenameWithNoTimestamp = $basename;
                    }
                    if (File::exists(JPATH_SITE . '/media/k2/items/cache/' . $basenameWithNoTimestamp)) {
                        $image = JURI::root() . 'media/k2/items/cache/' . $basename;
                        $document->setMetaData('twitter:image', $image);
                        $document->setMetaData('twitter:image:alt', (!empty($item->image_caption)) ? filter_var($item->image_caption, FILTER_SANITIZE_STRING) : filter_var($item->title, FILTER_SANITIZE_STRING));
                        if (!$params->get('facebookMetatags')) {
                            $document->setMetaData('image', $image); // Generic meta (if not already set in Facebook meta tags)
                        }
                    }
                }
            }

            // --- Google Structured Data ---
            if ($item->params->get('k2SeoGsdState', 1)) {
                $itemSD_Type = $item->params->get('k2SeoGsdType', 'Article');

                // Cleanups
                $sdStrSearch = ['&amp;', '&nbsp;', '&quot;', '&#039;', '&apos;', '&lt;', '&gt;', '{K2Splitter}', '\\'];
                $sdStrReplace = ['&', ' ', '"', '\'', '\'', '<', '>', ' ', ''];
                $sdPregSearch = ["#<script(.*?)</script>#is", "/\r|\n|\t/", "/\s\s+/"];
                $sdPregReplace = [' ', ' ', ' '];
                $allowedTags = '<script>';

                // Prepare content snippets
                $itemSD_SiteName = $config->get('sitename');
                $itemSD_SiteName = ($params->get('k2SeoGsdOrgName')) ? $params->get('k2SeoGsdOrgName') : $itemSD_SiteName;
                $itemSD_SiteLogo = JURI::root() . trim($params->get('k2SeoGsdOrgLogo'));

                $itemSD_Description = str_replace($sdStrSearch, $sdStrReplace, strip_tags($item->introtext, $allowedTags));
                $itemSD_ArticleBody = str_replace($sdStrSearch, $sdStrReplace, strip_tags($item->text, $allowedTags));

                $itemSD_Description = preg_replace($sdPregSearch, $sdPregReplace, $itemSD_Description);
                $itemSD_ArticleBody = preg_replace($sdPregSearch, $sdPregReplace, $itemSD_ArticleBody);

                $itemSD_AuthorName = (!empty($item->created_by_alias)) ? $item->created_by_alias : $item->author->name;
                $itemSD_AuthorURL = (!empty($item->created_by_alias)) ? JURI::root() : $this->absUrl($item->author->link);

                $itemSD_Modified = ((int)$item->modified) ? $item->modified : $item->created;

                $itemSD_Images = '';
                if (!empty($item->image)) {
                    $itemSD_Images = '
                    "image": [
                        "' . $this->absUrl($item->imageXLarge) . '",
                        "' . $this->absUrl($item->imageLarge) . '",
                        "' . $this->absUrl($item->imageMedium) . '",
                        "' . $this->absUrl($item->imageSmall) . '",
                        "' . $this->absUrl($item->imageXSmall) . '",
                        "' . $this->absUrl($item->imageGeneric) . '"
                    ],';
                }

                // Output
                $itemSD_LDJSON = '
                {
                    "@context": "https://schema.org",
                    "@type": "' . $itemSD_Type . '",
                    "mainEntityOfPage": {
                        "@type": "WebPage",
                        "@id": "' . $this->absUrl($item->link) . '"
                    },
                    "url": "' . $this->absUrl($item->link) . '",
                    "headline": "' . $this->filterHTML($metaTitle) . '",' . $itemSD_Images . '
                    "datePublished": "' . $item->created . '",
                    "dateModified": "' . $itemSD_Modified . '",
                    "author": {
                        "@type": "Person",
                        "name": "' . $itemSD_AuthorName . '",
                        "url": "' . $itemSD_AuthorURL . '"
                    },
                    "publisher": {
                        "@type": "Organization",
                        "name": "' . $itemSD_SiteName . '",
                        "url": "' . JURI::root() . '",
                        "logo": {
                            "@type": "ImageObject",
                            "name": "' . $itemSD_SiteName . '",
                            "width": "' . $params->get('k2SeoGsdOrgLogoWidth') . '",
                            "height": "' . $params->get('k2SeoGsdOrgLogoHeight') . '",
                            "url": "' . $itemSD_SiteLogo . '"
                        }
                    },
                    "articleSection": "' . $this->absUrl($item->category->link) . '",
                    "keywords": "' . $this->filterHTML($metaKeywords) . '",
                    "description": "' . $this->filterHTML($itemSD_Description) . '",
                    "articleBody": "' . $this->filterHTML($itemSD_ArticleBody) . '"
                }
                ';

                // Assign
                $item->params->set('itemGoogleStructuredData', $itemSD_LDJSON);

                $document->addScriptDeclaration($itemSD_LDJSON, 'application/ld+json');
            } else {
                $item->params->set('itemGoogleStructuredData', null);
            }
        }

        if ($document->getType() != 'json') {
            // Lookup template folders
            $this->_addPath('template', JPATH_COMPONENT . '/templates');
            $this->_addPath('template', JPATH_COMPONENT . '/templates/default');

            $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates');
            $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates/default');

            $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2');
            $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/default');

            if ($item->params->get('theme')) {
                $this->_addPath('template', JPATH_COMPONENT . '/templates/' . $item->params->get('theme'));
                $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/templates/' . $item->params->get('theme'));
                $this->_addPath('template', JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/com_k2/' . $item->params->get('theme'));
            }

            // Allow temporary template loading with ?template=
            $template = Factory::getApplication()->input->getCmd('template');
            if (isset($template)) {
                // Look for overrides in template folder (new K2 template structure)
                $this->_addPath('template', JPATH_SITE . '/templates/' . $template . '/html/com_k2');
                $this->_addPath('template', JPATH_SITE . '/templates/' . $template . '/html/com_k2/default');
                if ($item->params->get('theme')) {
                    $this->_addPath('template', JPATH_SITE . '/templates/' . $template . '/html/com_k2/' . $item->params->get('theme'));
                }
            }

            // Assign data
            $this->item = $item;
            $this->user = $user;
            $this->params = $item->params;
            $this->pagination = $pagination;

            // Display
            parent::display($tpl);
        }
    }

    private function absUrl($relUrl)
    {
        if (substr($relUrl, 0, 4) != 'http') {
            return substr(JURI::root(), 0, -1) . str_replace(JURI::root(true), '', $relUrl);
        } else {
            return $relUrl;
        }
    }

    private function filterHTML($str)
    {
        return htmlspecialchars(trim($str), ENT_QUOTES, 'utf-8');
    }

    private function setCanonicalUrl($url)
    {
        $document = Factory::getDocument();
        $params = K2HelperUtilities::getParams('com_k2');
        $canonicalURL = $params->get('canonicalURL', 'relative');
        if ($canonicalURL) {
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
