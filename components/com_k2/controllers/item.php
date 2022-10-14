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
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;

jimport('joomla.application.component.controller');

class K2ControllerItem extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        $model = $this->getModel('itemlist');
        $document = Factory::getDocument();
        $viewType = $document->getType();
        $view = $this->getView('item', $viewType);
        $view->setModel($model);
        Factory::getApplication()->input->set('view', 'item');
        $user = Factory::getUser();
        if ($user->guest) {
            $cache = true;
        } else {
            $cache = true;
            Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
            $row = Table::getInstance('K2Item', 'Table');
            $row->load(Factory::getApplication()->input->getInt('id'));
            if (K2HelperPermissions::canEditItem($row->created_by, $row->catid)) {
                $cache = false;
            }
            $params = K2HelperUtilities::getParams('com_k2');
            if ($row->created_by == $user->id && $params->get('inlineCommentsModeration')) {
                $cache = false;
            }
            if ($row->access > 0) {
                $cache = false;
            }
            $category = Table::getInstance('K2Category', 'Table');
            $category->load($row->catid);
            if ($category->access > 0) {
                $cache = false;
            }
            if ($params->get('comments') && $document->getType() == 'html') {
                $itemListModel = K2Model::getInstance('Itemlist', 'K2Model');
                $profile = $itemListModel->getUserProfile($user->id);
                $script = "
                    \$K2(document).ready(function() {
                        \$K2('#userName').val(" . json_encode($user->name) . ").attr('disabled', 'disabled');
                        \$K2('#commentEmail').val('" . $user->email . "').attr('disabled', 'disabled');
                ";
                if (is_object($profile) && $profile->url) {
                    $script .= "
                        \$K2('#commentURL').val('" . htmlspecialchars($profile->url, ENT_QUOTES, 'UTF-8') . "').attr('disabled', 'disabled');
                    ";
                }
                $script .= "
                    });
                ";
                $document->addScriptDeclaration($script);
            }
        }

        $urlparams['id'] = 'INT';
        $urlparams['print'] = 'INT';
        $urlparams['lang'] = 'CMD';
        $urlparams['Itemid'] = 'INT';
        $urlparams['m'] = 'INT';
        $urlparams['amp'] = 'INT';
        $urlparams['tmpl'] = 'CMD';
        $urlparams['template'] = 'CMD';
        parent::display($cache, $urlparams);
    }

    public function edit()
    {
        Factory::getApplication()->input->set('tmpl', 'component');
        $app = Factory::getApplication();
        $document = Factory::getDocument();
        $params = K2HelperUtilities::getParams('com_k2');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);

        K2HelperHTML::loadHeadIncludes(true, true, true);

        // CSS
        $document->addStyleSheet(JURI::root(true) . '/templates/system/css/general.css');
        $document->addStyleSheet(JURI::root(true) . '/templates/system/css/system.css');

        $this->addModelPath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $this->addViewPath(JPATH_COMPONENT_ADMINISTRATOR . '/views');
        $view = $this->getView('item', 'html');
        $view->frontendTheme = $params->get('theme');
        $view->setLayout('itemform');

        if ($params->get('category')) {
            Factory::getApplication()->input->set('catid', $params->get('category'));
        }

        $view->display();
    }

    public function add()
    {
        $this->edit();
    }

    public function cancel()
    {
        $this->setRedirect(JURI::root(true));
        return false;
    }

    public function save()
    {
        $app = Factory::getApplication();/* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        Factory::getApplication()->input->set('tmpl', 'component');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/item.php');
        $model = new K2ModelItem;
        $model->save(true);
        $app->close();
    }

    public function deleteAttachment()
    {
        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/item.php');
        $model = new K2ModelItem;
        $model->deleteAttachment();
    }

    public function tag()
    {
        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/tag.php');
        $model = new K2ModelTag;
        $model->addTag();
    }

    public function tags()
    {
        $user = Factory::getUser();
        if ($user->guest) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/tag.php');
        $model = new K2ModelTag;
        $model->tags();
    }

    public function download()
    {
        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/item.php');
        $model = new K2ModelItem;
        $model->download();
    }

    public function extraFields()
    {
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);

        $app = Factory::getApplication();
        $id = Factory::getApplication()->input->getInt('id', null);

        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/category.php');
        $categoryModel = new K2ModelCategory;
        $category = $categoryModel->getData();

        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/extrafield.php');
        $extraFieldModel = new K2ModelExtraField;
        $extraFields = $extraFieldModel->getExtraFieldsByGroup($category->extraFieldsGroup);

        if (!empty($extraFields) && count($extraFields)) {
            $output = '<div id="extraFields">';
            foreach ($extraFields as $extraField) {
                if ($extraField->type == 'header') {
                    $output .= '
                    <div class="itemAdditionalField fieldIs' . ucfirst($extraField->type) . '">
                        <h4>' . $extraField->name . '</h4>
                    </div>
                    ';
                } else {
                    $output .= '
                    <div class="itemAdditionalField fieldIs' . ucfirst($extraField->type) . '">
                        <div class="itemAdditionalValue">
                            <label for="K2ExtraField_' . $extraField->id . '">' . $extraField->name . '</label>
                        </div>
                        <div class="itemAdditionalData">
                            ' . $extraFieldModel->renderExtraField($extraField, $id) . '
                        </div>
                    </div>
                    ';
                }
            }
            $output .= '</div>';
        } else {
            $output = '
                <div class="k2-generic-message">
                    <h3>' . Text::_('K2_NOTICE') . '</h3>
                    <p>' . Text::_('K2_THIS_CATEGORY_DOESNT_HAVE_ASSIGNED_EXTRA_FIELDS') . '</p>
                </div>
            ';
        }

        echo $output;

        $app->close();
    }

    public function checkin()
    {
        $model = $this->getModel('item');
        $model->checkin();
    }

    public function vote()
    {
        $model = $this->getModel('item');
        $model->vote();
    }

    public function getVotesNum()
    {
        $model = $this->getModel('item');
        $model->getVotesNum();
    }

    public function getVotesPercentage()
    {
        $model = $this->getModel('item');
        $model->getVotesPercentage();
    }

    public function comment()
    {
        $model = $this->getModel('item');
        $model->comment();
    }

    public function resetHits()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        Factory::getApplication()->input->set('tmpl', 'component');
        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/item.php');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        $model = new K2ModelItem;
        $model->resetHits();
    }

    public function resetRating()
    {
        /* since J4 compatibility */;
        JSession::checkToken() or jexit('Invalid Token');
        Factory::getApplication()->input->set('tmpl', 'component');
        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/item.php');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        $model = new K2ModelItem;
        $model->resetRating();
    }

    public function media()
    {
        Factory::getApplication()->input->set('tmpl', 'component');
        $params = K2HelperUtilities::getParams('com_k2');
        $document = Factory::getDocument();
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);
        $user = Factory::getUser();
        if ($user->guest) {
            $uri = Uri::getInstance();
            $url = 'index.php?option=com_users&view=login&return=' . base64_encode($uri->toString());
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('K2_YOU_NEED_TO_LOGIN_FIRST'), 'notice');
            $app->redirect(Route::_($url, false));
        }

        K2HelperHTML::loadHeadIncludes(false, true, true);

        $this->addViewPath(JPATH_COMPONENT_ADMINISTRATOR . '/views');
        $view = $this->getView('media', 'html');
        $view->addTemplatePath(JPATH_COMPONENT_ADMINISTRATOR . '/views/media/tmpl');
        $view->setLayout('default');
        $view->display();
    }

    public function connector()
    {
        Factory::getApplication()->input->set('tmpl', 'component');
        $user = Factory::getUser();
        if ($user->guest) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        require_once(JPATH_COMPONENT_ADMINISTRATOR . '/controllers/media.php');
        $controller = new K2ControllerMedia();
        $controller->connector();
    }

    public function users()
    {
        $itemID = Factory::getApplication()->input->getInt('itemID');
        Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
        $item = Table::getInstance('K2Item', 'Table');
        $item->load($itemID);
        if (!K2HelperPermissions::canAddItem() && !K2HelperPermissions::canEditItem($item->created_by, $item->catid)) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        $K2Permissions = K2Permissions::getInstance();
        if (!$K2Permissions->permissions->get('editAll')) {
            JFactory::getApplication()->enqueueMessage(Text::_('K2_ALERTNOTAUTH'), 'ERROR');
        }
        Factory::getApplication()->input->set('tmpl', 'component');
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_k2');
        $language = Factory::getLanguage();
        $language->load('com_k2', JPATH_ADMINISTRATOR);

        $document = Factory::getDocument();

        K2HelperHTML::loadHeadIncludes(true, true, true);

        $this->addViewPath(JPATH_COMPONENT_ADMINISTRATOR . '/views');
        $this->addModelPath(JPATH_COMPONENT_ADMINISTRATOR . '/models');
        $view = $this->getView('users', 'html');
        $view->addTemplatePath(JPATH_COMPONENT_ADMINISTRATOR . '/views/users/tmpl');
        $view->display();
    }
}
