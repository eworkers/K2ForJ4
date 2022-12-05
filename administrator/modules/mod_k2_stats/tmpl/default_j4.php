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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Quick and dirty fix for Joomla 3.0 missing CSS tabs when creating tabs using the API.
// Should be removed when Joomla fixes that...
$document = Factory::getDocument();
$document->addStyleDeclaration('
		dl.tabs {float:left;margin:10px 0 -1px 0;z-index:50;}
		dl.tabs dt {float:left;padding:4px 10px;border:1px solid #ccc;margin-left:3px;background:#e9e9e9;color:#666;}
		dl.tabs dt.open {background:#f9f9f9;border-bottom:1px solid #f9f9f9;z-index:100;color:#000;}
		div.current {clear:both;border:1px solid #ccc;padding:10px 10px;background:#f9f9f9;}
		dl.tabs h3 {font-size:12px;line-height:12px;margin:4px;}
	');

// Import Joomla tabs
jimport('joomla.html.pane');

$selector = 'k2StatsTabs'.$module->id;
?>

<div class="clr"></div>


<?php echo HTMLHelper::_('uitab.startTabSet', $selector, ['active' => 'latestItemsTab', 'recall' => true]); ?>
<?php if($params->get('latestItems', 1)): ?>
    <?php echo HTMLHelper::_('uitab.addTab', $selector, 'latestItemsTab'.$module->id, Text::_('K2_LATEST_ITEMS')); ?>
    <table class="adminlist table table-striped">
        <thead>
        <tr>
            <td class="title"><?php echo Text::_('K2_TITLE'); ?></td>
            <td class="title"><?php echo Text::_('K2_CREATED'); ?></td>
            <td class="title"><?php echo Text::_('K2_AUTHOR'); ?></td>
        </tr>
        </thead>
        <tbody>
        <?php foreach($latestItems as $latest): ?>
            <tr>
                <td><a href="<?php echo Route::_('index.php?option=com_k2&view=item&cid='.$latest->id); ?>"><?php echo $latest->title; ?></a></td>
                <td><?php echo JHTML::_('date', $latest->created , Text::_('K2_DATE_FORMAT')); ?></td>
                <td><?php echo $latest->author; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endif; ?>

<?php if($params->get('popularItems', 1)): ?>
    <?php echo HTMLHelper::_('uitab.addTab', $selector, 'popularItemsTab'.$module->id, Text::_('K2_POPULAR_ITEMS')); ?>
    <table class="adminlist table table-striped">
        <thead>
        <tr>
            <td class="title"><?php echo Text::_('K2_TITLE'); ?></td>
            <td class="title"><?php echo Text::_('K2_HITS'); ?></td>
            <td class="title"><?php echo Text::_('K2_CREATED'); ?></td>
            <td class="title"><?php echo Text::_('K2_AUTHOR'); ?></td>
        </tr>
        </thead>
        <tbody>
        <?php foreach($popularItems as $popular): ?>
            <tr>
                <td><a href="<?php echo Route::_('index.php?option=com_k2&view=item&cid='.$popular->id); ?>"><?php echo $popular->title; ?></a></td>
                <td><?php echo $popular->hits; ?></td>
                <td><?php echo JHTML::_('date', $popular->created , Text::_('K2_DATE_FORMAT')); ?></td>
                <td><?php echo $popular->author; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endif; ?>

<?php if($params->get('mostCommentedItems', 1)): ?>
    <?php echo HTMLHelper::_('uitab.addTab', $selector, 'mostCommentedItemsTab'.$module->id, Text::_('K2_MOST_COMMENTED_ITEMS')); ?>
    <table class="adminlist table table-striped">
        <thead>
        <tr>
            <td class="title"><?php echo Text::_('K2_TITLE'); ?></td>
            <td class="title"><?php echo Text::_('K2_COMMENTS'); ?></td>
            <td class="title"><?php echo Text::_('K2_CREATED'); ?></td>
            <td class="title"><?php echo Text::_('K2_AUTHOR'); ?></td>
        </tr>
        </thead>
        <tbody>
        <?php foreach($mostCommentedItems as $mostCommented): ?>
            <tr>
                <td><a href="<?php echo Route::_('index.php?option=com_k2&view=item&cid='.$mostCommented->id); ?>"><?php echo $mostCommented->title; ?></a></td>
                <td><?php echo $mostCommented->numOfComments; ?></td>
                <td><?php echo JHTML::_('date', $mostCommented->created , Text::_('K2_DATE_FORMAT')); ?></td>
                <td><?php echo $mostCommented->author; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endif; ?>

<?php if($params->get('latestComments', 1)): ?>
    <?php echo HTMLHelper::_('uitab.addTab', $selector, 'latestCommentsTab'.$module->id, Text::_('K2_LATEST_COMMENTS')); ?>
    <table class="adminlist table table-striped">
        <thead>
        <tr>
            <td class="title"><?php echo Text::_('K2_COMMENT'); ?></td>
            <td class="title"><?php echo Text::_('K2_ADDED_ON'); ?></td>
            <td class="title"><?php echo Text::_('K2_POSTED_BY'); ?></td>
        </tr>
        </thead>
        <tbody>
        <?php foreach($latestComments as $latest): ?>
            <tr>
                <td><?php echo $latest->commentText; ?></td>
                <td><?php echo JHTML::_('date', $latest->commentDate , Text::_('K2_DATE_FORMAT')); ?></td>
                <td><?php echo $latest->userName; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endif; ?>

<?php if($params->get('statistics', 1)): ?>
    <?php echo HTMLHelper::_('uitab.addTab', $selector, 'statsTab'.$module->id, Text::_('K2_STATISTICS')); ?>
    <table class="adminlist table table-striped">
        <thead>
        <tr>
            <td class="title"><?php echo Text::_('K2_TYPE'); ?></td>
            <td class="title"><?php echo Text::_('K2_COUNT'); ?></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td><?php echo Text::_('K2_ITEMS'); ?></td>
            <td><?php echo $statistics->numOfItems; ?> (<?php echo $statistics->numOfFeaturedItems.' '.Text::_('K2_FEATURED').' - '.$statistics->numOfTrashedItems.' '.Text::_('K2_TRASHED'); ?>)</td>
        </tr>
        <tr>
            <td><?php echo Text::_('K2_CATEGORIES'); ?></td>
            <td><?php echo $statistics->numOfCategories; ?> (<?php echo $statistics->numOfTrashedCategories.' '.Text::_('K2_TRASHED'); ?>)</td>
        </tr>
        <tr>
            <td><?php echo Text::_('K2_TAGS'); ?></td>
            <td><?php echo $statistics->numOfTags; ?></td>
        </tr>
        <tr>
            <td><?php echo Text::_('K2_COMMENTS'); ?></td>
            <td><?php echo $statistics->numOfComments; ?></td>
        </tr>
        <tr>
            <td><?php echo Text::_('K2_USERS'); ?></td>
            <td><?php echo $statistics->numOfUsers; ?></td>
        </tr>
        <tr>
            <td><?php echo Text::_('K2_USER_GROUPS'); ?></td>
            <td><?php echo $statistics->numOfUserGroups; ?></td>
        </tr>
        </tbody>
    </table>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
<?php endif; ?>
<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
