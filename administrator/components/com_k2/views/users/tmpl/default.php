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
use Joomla\CMS\Uri\Uri;

$app = Factory::getApplication();
$context = Factory::getApplication()->input->getCmd('context');

?>

<?php if ($app->isClient('site') || $context == "modalselector"): ?>
    <!-- Modal View -->
<div id="k2ModalContainer">
    <div id="k2ModalHeader">
        <h2 id="k2ModalLogo"><span><?php echo Text::_('K2_USERS'); ?></span></h2>
        <table id="k2ModalToolbar" cellpadding="2" cellspacing="4">
            <tr>
                <td id="toolbar-close" class="button">
                    <a href="#" id="k2CloseMfp">
                        <i class="fa fa-times-circle" aria-hidden="true"></i> <?php echo Text::_('K2_CLOSE'); ?>
                    </a>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <form action="index.php" method="post" name="adminForm" id="adminForm">
        <table class="k2AdminTableFilters table">
            <tr>
                <td class="k2AdminTableFiltersSearch">
                    <label class="k2ui-not-visible"><?php echo Text::_('K2_FILTER'); ?></label>
                    <div class="btn-wrapper input-append">
                        <input type="text" name="search"
                               value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>"
                               class="text_area" title="<?php echo Text::_('K2_FILTER_BY_TITLE'); ?>"
                               placeholder="<?php echo Text::_('K2_FILTER'); ?>"/>
                        <button id="k2SubmitButton" class="btn"><?php echo Text::_('K2_GO'); ?></button>
                        <button id="k2ResetButton" class="btn"><?php echo Text::_('K2_RESET'); ?></button>
                    </div>
                </td>
                <td class="k2AdminTableFiltersSelects k2ui-hide-on-mobile">
                    <?php echo $this->lists['status']; ?>
                    <?php echo $this->lists['filter_group_k2']; ?>
                    <?php echo $this->lists['filter_group']; ?>
                </td>
            </tr>
        </table>
        <div class="k2AdminTableData">
            <table class="adminlist table table-striped<?php if (isset($this->rows) && count($this->rows) == 0): ?> nocontent<?php endif; ?>"
                   id="k2UsersList">
                <thead>
                <tr>
                    <th class="k2ui-hide-on-mobile">#</th>
                    <th<?php echo ($context == "modalselector") ? ' class="k2ui-not-visible"' : ' class="k2ui-center"'; ?>>
                        <input id="k2<?php echo $this->params->get('backendListToggler', 'TogglerStandard'); ?>"
                               type="checkbox" name="toggle" value=""/></th>
                    <th><?php echo JHTML::_('grid.sort', 'K2_NAME', 'juser.name', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                    <th class="k2ui-hide-on-mobile"><?php echo JHTML::_('grid.sort', 'K2_USERNAME', 'juser.username', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                    <th class="k2ui-center"><?php echo Text::_('K2_LOGGED_IN'); ?></th>
                    <th class="k2ui-center"><?php echo JHTML::_('grid.sort', 'K2_ENABLED', 'juser.block', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                    <th class="k2ui-hide-on-mobile">
                        <?php echo Text::_('K2_JOOMLA_GROUP'); ?>
                    </th>
                    <th class="k2ui-hide-on-mobile"><?php echo JHTML::_('grid.sort', 'K2_GROUP', 'groupname', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                    <th class="k2ui-hide-on-mobile"><?php echo JHTML::_('grid.sort', 'K2_EMAIL', 'juser.email', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                    <th class="k2ui-center k2ui-hide-on-mobile"><?php echo JHTML::_('grid.sort', 'K2_LAST_VISIT', 'juser.lastvisitDate', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                    <th class="k2ui-center k2ui-hide-on-mobile">IP</th>
                    <th class="k2ui-center k2ui-hide-on-mobile"><?php echo Text::_('K2_IMAGE'); ?></th>
                    <th class="k2ui-center k2ui-hide-on-mobile"><?php echo Text::_('K2_FLAG_AS_SPAMMER'); ?></th>
                    <th class="k2ui-center k2ui-hide-on-mobile"><?php echo JHTML::_('grid.sort', 'K2_ID', 'juser.id', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                </tr>
                </thead>
                <?php
                $tfootColspan = 14;
                /*
                if ($context == "modalselector") {
                    $tfootColspan--;
                }
                */
                ?>
                <tfoot>
                <tr>
                    <td colspan="<?php echo $tfootColspan; ?>">
                        <div class="k2CommentsPagination">
                            <div class="k2LimitBox">
                                <?php echo $this->page->getLimitBox(); ?>
                            </div>
                            <?php echo $this->page->getListFooter(); ?>
                        </div>
                    </td>
                </tr>
                </tfoot>
                <tbody>
                <?php if (isset($this->rows) && count($this->rows) > 0): ?>
                    <?php foreach ($this->rows as $key => $row): ?>
                        <tr class="row<?php echo($key % 2); ?>">
                            <td class="k2ui-hide-on-mobile"><?php echo $key + 1; ?></td>
                            <td class="k2ui-center<?php echo ($context == "modalselector") ? ' k2ui-not-visible' : ''; ?>">
                                <?php $row->checked_out = 0;
                                echo JHTML::_('grid.id', $key, $row->id); ?>
                            </td>
                            <td>
                                <?php if ($context == "modalselector"): ?>
                                    <?php
                                    if (Factory::getApplication()->input->getCmd('output') == 'list') {
                                        $onClick = 'window.parent.k2ModalSelector(\'' . $row->id . '\', \'' . str_replace(array("'", "\""), array("\\'", ""), $row->name) . '\', \'' . Factory::getApplication()->input->getCmd('fid') . '\', \'' . Factory::getApplication()->input->getVar('fname') . '\', \'' . Factory::getApplication()->input->getCmd('output') . '\'); return false;';
                                    } else {
                                        $onClick = 'window.parent.k2ModalSelector(\'' . $row->id . '\', \'' . str_replace(array("'", "\""), array("\\'", ""), $row->name) . '\', \'' . Factory::getApplication()->input->getCmd('fid') . '\', \'' . Factory::getApplication()->input->getVar('fname') . '\'); return false;';
                                    }
                                    ?>
                                    <a class="k2ListItemDisabled"
                                       title="<?php echo Text::_('K2_CLICK_TO_ADD_THIS_ENTRY'); ?>" href="#"
                                       onclick="<?php echo $onClick; ?>"><?php echo $row->name; ?></a>
                                <?php else: ?>
                                    <a href="<?php echo $row->link; ?>"><?php echo $row->name; ?></a>
                                <?php endif; ?>

                                <div class="k2ui-show-on-mobile">
                                    <div class="k2ui-list-mobile-attribute">
                                        <?php echo Text::_('K2_USERNAME'); ?>: <?php echo $row->username; ?>
                                    </div>
                                    <div class="k2ui-list-mobile-attribute">
                                        <?php echo Text::_('K2_EMAIL'); ?>: <?php echo $row->email; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="k2ui-hide-on-mobile"><?php echo $row->username; ?></td>
                            <td class="k2ui-center"><?php echo $row->loggedInStatus; ?></td>
                            <td class="k2ui-center"><?php echo $row->blockStatus; ?></td>
                            <td class="k2ui-hide-on-mobile"><?php echo $row->usertype; ?></td>
                            <td class="k2ui-hide-on-mobile"><?php echo $row->groupname; ?></td>
                            <td class="k2ui-hide-on-mobile"><?php echo $row->email; ?></td>
                            <td class="k2ui-center k2ui-nowrap k2ui-hide-on-mobile">
                                <?php echo ($row->lvisit) ? JHTML::_('date', $row->lvisit, $this->dateFormat) : Text::_('K2_NEVER'); ?>
                            </td>
                            <td class="k2ui-center k2ui-hide-on-mobile">
                                <?php if ($row->ip): ?>
                                    <a target="_blank"
                                       href="https://ipalyzer.com/<?php echo $row->ip; ?>"><?php echo $row->ip; ?></a>
                                <?php endif; ?>
                            </td>
                            <td class="k2ui-center k2ui-hide-on-mobile">
                                <?php if ($row->image):
                                    $avatarTimestamp = '';
                                    $avatarFile = JPATH_SITE . '/media/k2/users/' . $row->image;
                                    if (file_exists($avatarFile) && filemtime($avatarFile)) {
                                        $avatarTimestamp = '?t=' . date("Ymd_Hi", filemtime($avatarFile));
                                    }
                                    $avatar = URI::root(true) . '/media/k2/users/' . $row->image . $avatarTimestamp;
                                    ?>
                                    <a href="<?php echo $avatar; ?>" title="<?php echo Text::_('K2_PREVIEW_IMAGE'); ?>"
                                       data-fancybox="gallery" data-caption="<?php echo $row->name; ?>">
                                        <i class="fa fa-picture-o" aria-hidden="true"
                                           title="<?php echo Text::_('K2_PREVIEW_IMAGE'); ?>"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="k2ui-center k2ui-hide-on-mobile">
                                <?php if (!$row->block): ?>
                                    <?php if ($context == "modalselector"): ?>
                                        <a id="k2ReportUserButtonBackend" class="k2ReportUserButton k2ReportUserButtonBackend k2IsIcon"
                                           href="<?php echo Route::_('index.php?option=com_k2&view=user&task=report&tmpl=component&context=modalselector&id=' . $row->id); ?>">
                                            <i class="fa fa-ban" aria-hidden="true"></i>
                                        </a>
                                    <?php else: ?>
                                        <a id="k2ReportUserButtonBackend" class="k2ReportUserButton k2ReportUserButtonBackend k2IsIcon"
                                           href="<?php echo Route::_('index.php?option=com_k2&view=user&task=report&id=' . $row->id); ?>">
                                            <i class="fa fa-ban" aria-hidden="true"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="k2ui-center k2ui-hide-on-mobile"><?php echo $row->id; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $tfootColspan; ?>" class="k2ui-nocontent">
                            <div class="k2ui-nocontent-message">
                                <i class="fa fa-list"
                                   aria-hidden="true"></i><?php echo Text::_('K2_BE_NO_USERS_FOUND'); ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <input type="hidden" name="option" value="com_k2"/>
        <input type="hidden" name="view" value="<?php echo Factory::getApplication()->input->getVar('view'); ?>"/>
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>"/>
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>"/>
        <input type="hidden" name="boxchecked" value="0"/>
        <?php if ($context == "modalselector"): ?>
            <input type="hidden" name="context" value="modalselector"/>
            <input type="hidden" name="tmpl" value="component"/>
            <input type="hidden" name="fid" value="<?php echo Factory::getApplication()->input->getCmd('fid'); ?>"/>
            <input type="hidden" name="fname" value="<?php echo Factory::getApplication()->input->getVar('fname'); ?>"/>
            <input type="hidden" name="output"
                   value="<?php echo Factory::getApplication()->input->getCmd('output'); ?>"/>
        <?php endif; ?>
        <?php echo JHTML::_('form.token'); ?>
    </form>

    <?php if ($app->isClient('site') || $context == "modalselector"): ?>
</div>
<?php endif; ?>
