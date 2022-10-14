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

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

jimport('joomla.application.component.controller');

class K2ControllerComments extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        $document = Factory::getDocument();
        $user = Factory::getUser();

        $params = ComponentHelper::getParams('com_k2');

        K2HelperHTML::loadHeadIncludes(true, true, true);

        // Message for guests
        if ($user->guest) {
            $uri = Uri::getInstance();
            $url = 'index.php?option=com_users&view=login&return=' . base64_encode($uri->toString());
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('K2_YOU_NEED_TO_LOGIN_FIRST'), 'notice');
            $app->redirect(Route::_($url, false));
        }

        Factory::getApplication()->input->set('tmpl', 'component');

        // Language
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);

        $this->addViewPath(JPATH_COMPONENT_ADMINISTRATOR . '/views');
        $this->addModelPath(JPATH_COMPONENT_ADMINISTRATOR . '/models');

        $view = $this->getView('comments', 'html');
        $view->addTemplatePath(JPATH_COMPONENT_ADMINISTRATOR . '/views/comments/tmpl');
        $view->addHelperPath(JPATH_COMPONENT_ADMINISTRATOR . '/helpers');
        $view->display();
    }

    public function publish()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        $user = Factory::getUser();
        if ($user->guest) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $model = K2Model::getInstance('Comments', 'K2Model');
        $model->publish();
    }

    public function unpublish()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        $user = Factory::getUser();
        if ($user->guest) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $model = K2Model::getInstance('Comments', 'K2Model');
        $model->unpublish();
    }

    public function remove()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        $user = Factory::getUser();
        if ($user->guest) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $model = K2Model::getInstance('Comments', 'K2Model');
        $model->remove();
    }

    public function deleteUnpublished()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        $user = Factory::getUser();
        if ($user->guest) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $model = K2Model::getInstance('Comments', 'K2Model');
        $model->deleteUnpublished();
    }

    public function saveComment()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        $user = Factory::getUser();
        if ($user->guest) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $model = K2Model::getInstance('Comments', 'K2Model');
        $model->save();
        $app->close();
    }

    public function report()
    {
        Factory::getApplication()->input->set('tmpl', 'component');
        $view = $this->getView('comments', 'html');
        $view->setLayout('report');
        $view->report();
    }

    public function sendReport()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        $params = K2HelperUtilities::getParams('com_k2');
        $user = Factory::getUser();
        if (!$params->get('comments') || !$params->get('commentsReporting') || ($params->get('commentsReporting') == '2' && $user->guest)) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $model = K2Model::getInstance('Comments', 'K2Model');
        $model->setState('id', Factory::getApplication()->input->getInt('id'));
        $model->setState('name', Factory::getApplication()->input->getString('name'));
        $model->setState('reportReason', Factory::getApplication()->input->getString('reportReason'));
        if (!$model->report()) {
            echo $model->getError();
        } else {
            echo Text::_('K2_REPORT_SUBMITTED');
        }
        $app = Factory::getApplication();
        $app->close();
    }

    public function reportSpammer()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $format = Factory::getApplication()->input->getVar('format');
        $errors = array();
        if (!$user->authorise('core.admin', 'com_k2')) {
            $format == 'raw' ? die(Text::_('K2_ALERTNOTAUTH')) : JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        K2Model::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_k2/models');
        $model = K2Model::getInstance('User', 'K2Model');
        $model->setState('id', Factory::getApplication()->input->getInt('id'));
        $model->reportSpammer();
        if ($format == 'raw') {
            $response = '';
            $messages = $app->getMessageQueue();
            foreach ($messages as $message) {
                $response .= $message['message'] . "\n";
            }
            die($response);
        }
        $this->setRedirect('index.php?option=com_k2&view=comments&tmpl=component');
    }
}
