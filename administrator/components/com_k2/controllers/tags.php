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

class K2ControllerTags extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('view', 'tags');
        parent::display();
    }

    public function publish()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('tags');
        $model->publish();
    }

    public function unpublish()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('tags');
        $model->unpublish();
    }

    public function remove()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('tags');
        $model->remove();
    }

    public function add()
    {
        $app = Factory::getApplication();
        $app->redirect('index.php?option=com_k2&view=tag');
    }

    public function edit()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        $app->redirect('index.php?option=com_k2&view=tag&cid=' . $cid[0]);
    }

    public function removeOrphans()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('tags');
        $model->removeOrphans();
    }
}
