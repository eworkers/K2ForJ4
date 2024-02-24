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
use Joomla\CMS\Session\Session;

jimport('joomla.application.component.controller');

class K2ControllerSettings extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        $app = Factory::getApplication();
        $app->redirect('index.php?option=com_config&view=component&component=com_k2&path=&tmpl=component');
    }

    public function save()
    {
        $app = Factory::getApplication();/* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('settings');
        $model->save();
        $app->redirect('index.php?option=com_k2&view=settings');
    }
}
