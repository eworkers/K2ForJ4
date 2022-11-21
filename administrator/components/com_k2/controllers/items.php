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

jimport('joomla.application.component.controller');

class K2ControllerItems extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('view', 'items');
        parent::display();
    }

    public function publish()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->publish();
    }

    public function unpublish()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->unpublish();
    }

    public function saveorder()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $result = $model->saveorder();
        $document = Factory::getDocument();
        if ($document->getType() == 'raw') {
            echo '1';
            return $this;
        } else {
            $this->setRedirect('index.php?option=com_k2&view=items', Text::_('K2_NEW_ORDERING_SAVED'));
        }
    }

    public function orderup()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->orderup();
    }

    public function orderdown()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->orderdown();
    }

    public function savefeaturedorder()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $result = $model->savefeaturedorder();
        $document = Factory::getDocument();
        if ($document->getType() == 'raw') {
            echo '1';
            return $this;
        } else {
            $this->setRedirect('index.php?option=com_k2&view=items', Text::_('K2_NEW_FEATURED_ORDERING_SAVED'));
        }
    }

    public function featuredorderup()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->featuredorderup();
    }

    public function featuredorderdown()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->featuredorderdown();
    }

    public function accessregistered()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->accessregistered();
    }

    public function accessspecial()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->accessspecial();
    }

    public function accesspublic()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->accesspublic();
    }

    public function featured()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->featured();
    }

    public function trash()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->trash();
    }

    public function restore()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->restore();
    }

    public function remove()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->remove();
    }

    public function add()
    {
        $app = Factory::getApplication();
        $app->redirect('index.php?option=com_k2&view=item');
    }

    public function edit()
    {
        $app = Factory::getApplication();
        $cid = Factory::getApplication()->input->getVar('cid');
        $app->redirect('index.php?option=com_k2&view=item&cid=' . $cid[0]);
    }

    public function copy()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->copy();
    }

    public function import()
    {
        $model = $this->getModel('items');
        $model->importJ16();
    }

    public function saveBatch()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('items');
        $model->saveBatch();
    }

    public function logStats()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $status = Factory::getApplication()->input->getInt('status');
        $response = Factory::getApplication()->input->getString('response');
        $date = Factory::getDate();
        $now = $date->toSql();
        $db = Factory::getDbo();

        $query = 'DELETE FROM #__k2_log';
        $db->setQuery($query);
        $db->execute();

        $query = 'INSERT INTO #__k2_log VALUES(' . $status . ', ' . $db->quote($response) . ', ' . $db->quote($now) . ')';
        $db->setQuery($query);
        $db->execute();

        exit;
    }
}
