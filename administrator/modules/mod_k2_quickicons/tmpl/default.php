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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>


<nav class="quick-icons px-3 pb-3" aria-label="Liens rapides System">
    <ul class="nav flex-wrap">
        <li class="quickicon quickicon-single">

            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=item'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"><i class="dashicon item-new"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_ADD_NEW_ITEM'); ?>
                </div>
            </a>
        </li>

        <li class="quickicon quickicon-single">
            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=item'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"><i class="dashicon item-new"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_ADD_NEW_ITEM'); ?>
                </div>
            </a>
        </li>

        <li class="quickicon quickicon-single">
            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=items&amp;filter_featured=-1&amp;filter_trash=0'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"><i class="dashicon items"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_ITEMS'); ?>
                </div>
            </a>
        </li>

        <li class="quickicon quickicon-single">
            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=items&amp;filter_featured=1&amp;filter_trash=0'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"><i class="dashicon items-featured"></i></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_FEATURED_ITEMS'); ?>
                </div>
            </a>
        </li>

        <li class="quickicon quickicon-single">
            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=items&amp;filter_featured=-1&amp;filter_trash=1'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"><i class="dashicon items-trashed"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_TRASHED_ITEMS'); ?>
                </div>
            </a>
        </li>

        <li class="quickicon quickicon-single">
            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=categories&amp;filter_trash=0'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"><i class="dashicon categories"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_CATEGORIES'); ?>
                </div>
            </a>
        </li>

        <li class="quickicon quickicon-single">
            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=categories&amp;filter_trash=1'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"> <i class="dashicon categories-trashed"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_TRASHED_CATEGORIES'); ?>
                </div>
            </a>
        </li>

        <?php if (!$componentParams->get('lockTags') || $user->gid > 23) : ?>
            <li class="quickicon quickicon-single">
                <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=tags'); ?>">
                    <div class="quickicon-info">
                        <div class="quickicon-icon">
                            <div class="" aria-hidden="true"> <i class="dashicon tags"></i></div>
                        </div>
                    </div>
                    <div class="quickicon-name d-flex align-items-end">
                        <?php echo Text::_('K2_TAGS'); ?>
                    </div>
                </a>
            </li>

        <?php endif; ?>

        <li class="quickicon quickicon-single">
            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=comments'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"> <i class="dashicon comments"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_COMMENTS'); ?>
                </div>
            </a>
        </li>


        <?php if ($user->gid > 23) : ?>

            <li class="quickicon quickicon-single">
                <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=extrafields'); ?>">
                    <div class="quickicon-info">
                        <div class="quickicon-icon">
                            <div class="" aria-hidden="true"> <i class="dashicon extra-fields"></i></div>
                        </div>
                    </div>
                    <div class="quickicon-name d-flex align-items-end">
                        <?php echo Text::_('K2_EXTRA_FIELDS'); ?>
                    </div>
                </a>
            </li>

            <li class="quickicon quickicon-single">
                <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=extrafieldsgroups'); ?>">
                    <div class="quickicon-info">
                        <div class="quickicon-icon">
                            <div class="" aria-hidden="true"> <i class="dashicon extra-fields-groups"></i></div>
                        </div>
                    </div>
                    <div class="quickicon-name d-flex align-items-end">
                        <?php echo Text::_('K2_EXTRA_FIELDS_GROUPS'); ?>
                    </div>
                </a>
            </li>

        <?php endif; ?>


        <li class="quickicon quickicon-single">
            <a href="<?php echo Route::_('index.php?option=com_k2&amp;view=media'); ?>">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"> <i class="dashicon mediamanager"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_MEDIA_MANAGER'); ?>
                </div>
            </a>
        </li>


        <li class="quickicon quickicon-single">
            <a data-k2-modal="iframe" target="_blank" href="https://getk2.org/documentation/">
                <div class="quickicon-info">
                    <div class="quickicon-icon">
                        <div class="" aria-hidden="true"> <i class="dashicon documentation"></i></div>
                    </div>
                </div>
                <div class="quickicon-name d-flex align-items-end">
                    <?php echo Text::_('K2_DOCS_AND_TUTORIALS'); ?>
                </div>
            </a>
        </li>


        <?php if ($user->gid > 23) : ?>

            <li class="quickicon quickicon-single">
                <a data-k2-modal="iframe" target="_blank" href="https://getk2.org/extend/">
                    <div class="quickicon-info">
                        <div class="quickicon-icon">
                            <div class="" aria-hidden="true"> <i class="dashicon extend"></i></div>
                        </div>
                    </div>
                    <div class="quickicon-name d-flex align-items-end">
                        <?php echo Text::_('K2_EXTEND'); ?>
                    </div>
                </a>
            </li>

            <li class="quickicon quickicon-single">
                <a data-k2-modal="iframe" target="_blank" href="https://www.joomlaworks.net/forum/k2">
                    <div class="quickicon-info">
                        <div class="quickicon-icon">
                            <div class="" aria-hidden="true"> <i class="dashicon help"></i></div>
                        </div>
                    </div>
                    <div class="quickicon-name d-flex align-items-end">
                        <?php echo Text::_('K2_COMMUNITY'); ?>
                    </div>
                </a>
            </li>

        <?php endif; ?>
    </ul>
</nav>