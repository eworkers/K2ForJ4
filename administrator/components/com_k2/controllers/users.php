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

class K2ControllerUsers extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('view', 'users');
        parent::display();
    }

    public function edit()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        $app->redirect('index.php?option=com_k2&view=user&cid=' . $cid[0]);
    }

    public function remove()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('users');
        $model->remove();
    }

    public function enable()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('users');
        $model->enable();
    }

    public function disable()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('users');
        $model->disable();
    }

    public function delete()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('users');
        $model->delete();
    }

    public function move()
    {
        $view = $this->getView('users', 'html');
        $view->setLayout('move');
        $model = $this->getModel('users');
        $view->setModel($model);
        $view->move();
    }

    public function saveMove()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('users');
        $model->saveMove();
    }

    public function cancelMove()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $app = Factory::getApplication();
        $app->redirect('index.php?option=com_k2&view=users');
    }

    public function import()
    {
        $model = $this->getModel('users');
        $model->import();
    }

    public function search()
    {
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $word = Factory::getApplication()->input->getString('q', null);
        $word = $db->Quote($db->escape($word, true) . '%', false);
        $query = "SELECT id,name FROM #__users WHERE name LIKE " . $word . " OR username LIKE " . $word . " OR email LIKE " . $word;
        $db->setQuery($query);
        $result = $db->loadObjectList();
        echo json_encode($result);
        $app->close();
    }
}
