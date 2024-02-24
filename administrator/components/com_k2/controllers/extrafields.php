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
use Joomla\CMS\Session\Session;

jimport('joomla.application.component.controller');

class K2ControllerExtraFields extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('view', 'extrafields');
        parent::display();
    }

    public function publish()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('extraFields');
        $model->publish();
    }

    public function unpublish()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('extraFields');
        $model->unpublish();
    }

    public function saveorder()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('extraFields');
        $model->saveorder();
        $document = Factory::getDocument();
        if ($document->getType() == 'raw') {
            echo '1';
            return $this;
        } else {
            $this->setRedirect('index.php?option=com_k2&view=extrafields', Text::_('K2_NEW_ORDERING_SAVED'));
        }
    }

    public function orderup()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('extraFields');
        $model->orderup();
    }

    public function orderdown()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('extraFields');
        $model->orderdown();
    }

    public function remove()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('extraFields');
        $model->remove();
    }

    public function add()
    {
        $app = Factory::getApplication();
        $app->redirect('index.php?option=com_k2&view=extrafield');
    }

    public function edit()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        $app->redirect('index.php?option=com_k2&view=extrafield&cid=' . $cid[0]);
    }
}
