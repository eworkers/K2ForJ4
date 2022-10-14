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

jimport('joomla.application.component.controller');

class K2ControllerLatest extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        $view = $this->getView('latest', 'html');
        $model = $this->getModel('itemlist');
        $view->setModel($model);
        $itemModel = $this->getModel('item');
        $view->setModel($itemModel);
        $user = Factory::getUser();
        if ($user->guest) {
            $cache = true;
        } else {
            $cache = false;
        }
        $urlparams['Itemid'] = 'INT';
        $urlparams['m'] = 'INT';
        $urlparams['amp'] = 'INT';
        $urlparams['tmpl'] = 'CMD';
        $urlparams['template'] = 'CMD';
        parent::display($cache, $urlparams);
    }
}
