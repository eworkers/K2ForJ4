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

?>

<form action="index.php" method="post" name="adminForm">
    <fieldset>
        <div style="float:right;">
            <button onclick="submitbutton('save');window.top.setTimeout('window.parent.document.getElementById(\'sbox-window\').close()', 700);"
                    type="button">
                <?php echo Text::_('K2_SAVE'); ?>
            </button>
            <button onclick="window.parent.document.getElementById('sbox-window').close();" type="button">
                <?php echo Text::_('K2_CANCEL'); ?>
            </button>
        </div>
        <div class="configuration">
            <?php echo Text::_('K2_SETTINGS') ?>
        </div>
        <div class="clr"></div>
    </fieldset>

    <?php echo $this->pane->startPane('settings'); ?>
    <?php foreach ($this->params->getGroups() as $group => $value): ?>
        <?php echo $this->pane->startPanel(Text::_($group), $group . '-tab'); ?>
        <?php echo $this->params->render('params', $group); ?>
        <?php echo $this->pane->endPanel(); ?>
    <?php endforeach; ?>
    <?php echo $this->pane->endPane(); ?>

    <input type="hidden" name="option" value="com_k2"/>
    <input type="hidden" name="view" value="settings"/>
    <input type="hidden" id="task" name="task" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
