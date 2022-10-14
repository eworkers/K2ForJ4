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

class K2ControllerCategory extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('view', 'category');
        parent::display();
    }

    public function save()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('category');
        $model->save();
    }

    public function saveAndNew()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('category');
        $model->save();
    }

    public function apply()
    {
        $this->save();
    }

    public function cancel()
    {
        $app = Factory::getApplication();
        $app->redirect('index.php?option=com_k2&view=categories');
    }
}
