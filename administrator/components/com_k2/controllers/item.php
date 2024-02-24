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

class K2ControllerItem extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('view', 'item');
        parent::display();
    }

    public function save()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('item');
        $model->save();
    }

    public function apply()
    {
        $this->save();
    }

    public function cancel()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('item');
        $model->cancel();
    }

    public function deleteAttachment()
    {
        $model = $this->getModel('item');
        $model->deleteAttachment();
    }

    public function tag()
    {
        $model = $this->getModel('tag');
        $model->addTag();
    }

    public function tags()
    {
        $user = Factory::getUser();
        if ($user->guest) {
            throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
        }
        $model = $this->getModel('tag');
        $model->tags();
    }

    public function download()
    {
        $model = $this->getModel('item');
        $model->download();
    }

    public function extraFields()
    {
        $app = Factory::getApplication();
        $id = Factory::getApplication()->input->getInt('id', null);

        $categoryModel = $this->getModel('category');
        $category = $categoryModel->getData();

        $extraFieldModel = $this->getModel('extraField');
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

    public function resetHits()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('item');
        $model->resetHits();
    }

    public function resetRating()
    {
        /* since J4 compatibility */;
        Session::checkToken() or jexit('Invalid Token');
        $model = $this->getModel('item');
        $model->resetRating();
    }
}
