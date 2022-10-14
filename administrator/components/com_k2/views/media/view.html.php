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
use Joomla\CMS\Utility\Utility;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.view');

class K2ViewMedia extends K2View
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $document = Factory::getDocument();
        $type = Factory::getApplication()->input->getCmd('type');
        $fieldID = Factory::getApplication()->input->getCmd('fieldID');
        if ($type == 'video') {
            $mimes = "'video','audio'";
        } elseif ($type == 'image') {
            $mimes = "'image'";
        } else {
            $mimes = '';
        }
        $token = Session::getFormToken();

        $this->mimes = $mimes;
        $this->type = $type;
        $this->fieldID = $fieldID;
        $this->token = $token;

        if ($app->isClient('administrator')) {
            // Toolbar
            JToolBarHelper::title(Text::_('K2_MEDIA_MANAGER'), 'k2.png');
            JToolBarHelper::preferences('com_k2', '(window.innerHeight) * 0.9', '(window.innerWidth) * 0.7', 'K2_SETTINGS');

            $this->loadHelper('html');
            K2HelperHTML::subMenu();
        }

        parent::display($tpl);
    }
}
