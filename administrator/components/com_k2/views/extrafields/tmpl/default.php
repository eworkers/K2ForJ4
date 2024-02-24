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
use Joomla\String\StringHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

?>

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
                <?php echo $this->lists['type']; ?>
                <?php echo $this->lists['group']; ?>
                <?php echo $this->lists['state']; ?>
            </td>
        </tr>
    </table>
    <div class="k2AdminTableData">
        <table class="adminlist table table-striped<?php if (isset($this->rows) && count($this->rows) == 0): ?> nocontent<?php endif; ?>"
               id="k2ExtraFieldsList">
            <thead>
            <tr>
                <th width="1%" class="k2ui-center k2ui-hide-on-mobile">
                    <?php echo HTMLHelper::_('grid.sort', '<i class="icon-menu-2"></i>', 'ordering', @$this->lists['order_Dir'], @$this->lists['order'], null, 'asc', 'K2_ORDER'); ?>
                </th>
                <th class="k2ui-center"><input
                            id="k2<?php echo $this->params->get('backendListToggler', 'TogglerStandard'); ?>"
                            type="checkbox" name="toggle" value=""/></th>
                <th class="k2ui-left"><?php echo HTMLHelper::_('grid.sort', 'K2_NAME', 'name', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                <th class="k2ui-center k2ui-hide-on-mobile"><?php echo HTMLHelper::_('grid.sort', 'K2_GROUP', 'groupname', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                <th><?php echo HTMLHelper::_('grid.sort', 'K2_ORDER', 'ordering', @$this->lists['order_Dir'], @$this->lists['order']); ?><?php if ($this->ordering) echo HTMLHelper::_('grid.order', $this->rows); ?></th>
                <th class="k2ui-center k2ui-hide-on-mobile"><?php echo HTMLHelper::_('grid.sort', 'K2_TYPE', 'type', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                <th class="k2ui-center"><?php echo HTMLHelper::_('grid.sort', 'K2_PUBLISHED', 'published', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
                <th class="k2ui-center k2ui-hide-on-mobile"><?php echo HTMLHelper::_('grid.sort', 'K2_ID', 'exf.id', @$this->lists['order_Dir'], @$this->lists['order']); ?></th>
            </tr>
            </thead>
            <?php
            $tfootColspan = 7;
            ?>
            <tfoot>
            <tr>
                <td colspan="<?php echo $tfootColspan; ?>">
                    <div class="k2LimitBox">
                        <?php echo $this->page->getLimitBox(); ?>
                    </div>
                    <?php echo $this->page->getListFooter(); ?>
                </td>
            </tr>
            </tfoot>
            <tbody>
            <?php if (isset($this->rows) && count($this->rows) > 0): ?>
                <?php foreach ($this->rows as $key => $row): ?>
                    <tr class="row<?php echo($key % 2); ?>" sortable-group-id="<?php echo $row->group; ?>">
                        <td class="k2ui-order k2ui-center k2ui-hide-on-mobile">
                            <span class="sortable-handler<?php echo ($this->ordering) ? '' : ' inactive tip-top'; ?>"
                                  title="<?php echo ($this->ordering) ? '' : Text::_('JORDERINGDISABLED'); ?>"
                                  rel="tooltip"><i class="icon-menu"></i></span>
                            <input type="text" style="display:none" name="order[]" size="5"
                                   value="<?php echo $row->ordering; ?>" class="width-20 text-area-order"/>
                        </td>
                        <td class="k2ui-center"><?php $row->checked_out = 0;
                            echo @HTMLHelper::_('grid.checkedout', $row, $key); ?></td>
                        <td>
                            <a href="<?php echo Route::_('index.php?option=com_k2&view=extrafield&cid=' . $row->id); ?>"><?php echo $row->name; ?></a>
                            <span class="k2AliasValue"><?php echo Text::_('K2_ALIAS'); ?>: <?php echo $row->alias; ?></span>
                        </td>
                        <td class="k2ui-center k2ui-hide-on-mobile"><?php echo $row->groupname; ?></td>
                        <td class="k2ui-center k2ui-hide-on-mobile"><?php echo Text::_('K2_EXTRA_FIELD_' . StringHelper::strtoupper($row->type)); ?></td>
                        <td class="k2ui-center"><?php echo $row->status; ?></td>
                        <td class="k2ui-center k2ui-hide-on-mobile"><?php echo $row->id; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo $tfootColspan; ?>" class="k2ui-nocontent">
                        <div class="k2ui-nocontent-message">
                            <i class="fa fa-list"
                               aria-hidden="true"></i><?php echo Text::_('K2_BE_NO_EXTRA_FIELDS_FOUND'); ?>
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
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
