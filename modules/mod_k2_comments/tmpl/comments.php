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
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>

<div id="k2ModuleBox<?php echo $module->id; ?>"
     class="k2LatestCommentsBlock<?php if ($params->get('moduleclass_sfx')) echo ' ' . $params->get('moduleclass_sfx'); ?>">
        <ul>
            <?php foreach ($comments as $key => $comment): ?>
                <li class="<?php echo ($key % 2) ? "odd" : "even";
                if (count($comments) == $key + 1) echo ' lastItem'; ?>">
                    <?php if ($comment->userImage): ?>
                        <a class="k2Avatar lcAvatar" href="<?php echo $comment->link; ?>"
                           title="<?php echo K2HelperUtilities::cleanHtml($comment->commentText); ?>">
                            <img src="<?php echo $comment->userImage; ?>"
                                 alt="<?php echo OutputFilter::cleanText($comment->userName); ?>"
                                 style="width:<?php echo $lcAvatarWidth; ?>px;height:auto;"/>
                        </a>
                    <?php endif; ?>

                    <?php if ($params->get('commentLink')): ?>
                        <a href="<?php echo $comment->link; ?>"><span
                                    class="lcComment"><?php echo HTMLHelper::_('string.truncate', $comment->commentText, 0,true, false );?></span></a>
                    <?php else: ?>
                        <span class="lcComment"><?php echo HTMLHelper::_('string.truncate', $comment->commentText, 0,true, false ); ?></span>
                    <?php endif; ?>

                    <?php if ($params->get('commenterName')): ?>
                        <span class="lcUsername"><?php echo Text::_('K2_WRITTEN_BY'); ?>
                            <?php if (isset($comment->userLink)): ?>
                                <a rel="author"
                                   href="<?php echo $comment->userLink; ?>"><?php echo $comment->userName; ?></a>
                            <?php elseif ($comment->commentURL): ?>
                                <a target="_blank" rel="nofollow"
                                   href="<?php echo $comment->commentURL; ?>"><?php echo $comment->userName; ?></a>
                            <?php else: ?>
                                <?php echo $comment->userName; ?>
                            <?php endif; ?>
            </span>
                    <?php endif; ?>

                    <?php if ($params->get('commentDate')): ?>
                        <span class="lcCommentDate">
                <?php if ($params->get('commentDateFormat') == 'relative'): ?>
                    <?php echo $comment->commentDate; ?>
                <?php else: ?>
                    <?php echo Text::_('K2_ON') .' '. HTMLHelper::_('date', $comment->commentDate, Text::_('K2_DATE_FORMAT_LC2')); ?>
                <?php endif; ?>
            </span>
                    <?php endif; ?>

                    <div class="clr"></div>

                    <?php if ($params->get('itemTitle')): ?>
                        <span class="lcItemTitle"><a
                                    href="<?php echo $comment->itemLink; ?>"><?php echo $comment->title; ?></a></span>
                    <?php endif; ?>

                    <?php if ($params->get('itemCategory')): ?>
                        <span class="lcItemCategory">(<a
                                    href="<?php echo $comment->catLink; ?>"><?php echo $comment->categoryname; ?></a>)</span>
                    <?php endif; ?>

                    <div class="clr"></div>
                </li>
            <?php endforeach; ?>
            <li class="clearList"></li>
        </ul>

    <?php if ($params->get('feed')): ?>
        <div class="k2FeedIcon">
            <a href="<?php echo Route::_('index.php?option=com_k2&view=itemlist&format=feed&moduleID=' . $module->id); ?>"
               title="<?php echo Text::_('K2_SUBSCRIBE_TO_THIS_RSS_FEED'); ?>">
                <span><?php echo Text::_('K2_SUBSCRIBE_TO_THIS_RSS_FEED'); ?></span>
            </a>
            <div class="clr"></div>
        </div>
    <?php endif; ?>
</div>
