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

jimport('joomla.application.component.view');

class K2ViewSettings extends K2View
{
    public function display($tpl = null)
    {
        JHTML::_('bootstrap.tooltip');

        jimport('joomla.html.pane');

        $model = $this->getModel();

        $params = $model->getParams();
        $this->params = $params;

        $pane = JPane::getInstance('Tabs');
        $this->pane = $pane;

        parent::display($tpl);
    }
}
