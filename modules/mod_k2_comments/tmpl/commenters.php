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
use Joomla\CMS\Language\Text;

?>

<div id="k2ModuleBox<?php echo $module->id; ?>"
     class="k2TopCommentersBlock<?php if ($params->get('moduleclass_sfx')) echo ' ' . $params->get('moduleclass_sfx'); ?>">
        <ul>
            <?php foreach ($commenters as $key => $commenter): ?>
                <li class="<?php echo ($key % 2) ? "odd" : "even";
                if (count($commenters) == $key + 1) echo ' lastItem'; ?>">
                    <?php if ($commenter->userImage): ?>
                        <a class="k2Avatar tcAvatar" rel="author" href="<?php echo $commenter->link; ?>">
                            <img src="<?php echo $commenter->userImage; ?>"
                                 alt="<?php echo OutputFilter::cleanText($commenter->userName); ?>"
                                 style="width:<?php echo $tcAvatarWidth; ?>px;height:auto;"/>
                        </a>
                    <?php endif; ?>

                    <?php if ($params->get('commenterLink')): ?>
                    <a class="tcLink" rel="author" href="<?php echo $commenter->link; ?>">
                        <?php endif; ?>
                        <span class="tcUsername"><?php echo $commenter->userName; ?></span>

                        <?php if ($params->get('commenterCommentsCounter')): ?>
                            <span class="tcCommentsCounter">(<?php echo $commenter->counter; ?>)</span>
                        <?php endif; ?>
                        <?php if ($params->get('commenterLink')): ?>
                    </a>
                <?php endif; ?>

                    <?php if ($params->get('commenterLatestComment')): ?>
                        <a class="tcLatestComment" href="<?php echo $commenter->latestCommentLink; ?>">
                            <?php echo $commenter->latestCommentText; ?>
                        </a>
                        <span class="tcLatestCommentDate"><?php echo Text::_('K2_POSTED_ON'); ?><?php echo JHTML::_('date', $commenter->latestCommentDate, Text::_('K2_DATE_FORMAT_LC2')); ?></span>
                    <?php endif; ?>

                    <div class="clr"></div>
                </li>
            <?php endforeach; ?>
            <li class="clearList"></li>
        </ul>
</div>
