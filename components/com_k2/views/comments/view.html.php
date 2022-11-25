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
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;

jimport('joomla.application.component.view');

class K2ViewComments extends K2View
{
    public function report($tpl = null)
    {
        $params = K2HelperUtilities::getParams('com_k2');
        $document = Factory::getDocument();
        $user = Factory::getUser();

        Table::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
        $row = Table::getInstance('K2Comment', 'Table');
        $row->load(Factory::getApplication()->input->getInt('commentID'));
        if (!$row->published) {
            throw new \Exception(Text::_('K2_NOT_FOUND'), 404);
        }

        if (!$params->get('comments') || !$params->get('commentsReporting') || ($params->get('commentsReporting') == '2' && $user->guest)) {
            throw new \Exception(Text::_('K2_ALERTNOTAUTH'), 403);
        }

        // B/C code for reCAPTCHA
        if ($params->get('antispam') == 'recaptcha' || $params->get('antispam') == 'both') {
            $params->set('recaptcha', true);
        } else {
            $params->set('recaptcha', false);
        }
        $params->set('recaptchaV2', true);

        // Load reCAPTCHA
        if ($params->get('recaptcha') && ($user->guest || $params->get('recaptchaForRegistered', 1))) {
            $document->addScript('https://www.google.com/recaptcha/api.js?onload=onK2RecaptchaLoaded&render=explicit');
            $document->addScriptDeclaration('
                function onK2RecaptchaLoaded() {
                    grecaptcha.render("recaptcha", {
                        "sitekey": "' . $item->params->get('recaptcha_public_key') . '",
                        "theme": "' . $item->params->get('recaptcha_theme', 'light') . '"
                    });
                }
            ');
            $this->recaptchaClass = 'k2-recaptcha-v2';
        }

        $this->row = $row;
        $this->user = $user;
        $this->params = $params;

        parent::display($tpl);
    }
}
