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

class K2ControllerUser extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('view', 'user');
        parent::display();
    }

    public function save()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('user');
        $model->save();
    }

    public function apply()
    {
        $this->save();
    }

    public function cancel()
    {
        $app = Factory::getApplication();
        $app->redirect('index.php?option=com_k2&view=users');
    }

    public function report()
    {
        $app = Factory::getApplication();
        $model = K2Model::getInstance('User', 'K2Model');
        $model->setState('id', Factory::getApplication()->input->getInt('id'));
        $model->reportSpammer();
        if (Factory::getApplication()->input->getCmd('context') == "modalselector") {
            $app->redirect('index.php?option=com_k2&view=users&tmpl=component&template=system&context=modalselector');
        } else {
            $app->redirect('index.php?option=com_k2&view=users');
        }
    }
}
