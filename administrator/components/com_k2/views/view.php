<?php
/**
 * @version    2.11.x
 * @package    K2
 * @author     JoomlaWorks https://www.joomlaworks.net
 * @copyright  Copyright (c) 2006 - 2022 JoomlaWorks Ltd. All rights reserved.
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;

jimport('joomla.application.component.view');

if (version_compare(JVERSION, '3.0', 'ge')) {
    class K2View extends HtmlView
    {
        public function display($tpl = null)
        {
            // Allow for YOOtheme PRO Integration
            $app = \Joomla\CMS\Factory::getApplication();

            // Only in YOOtheme PRO
            if ($app->isClient('site') && stripos($app->getTemplate(), 'yootheme') === 0) {

                // Trigger the custom YOOtheme Pro event
                $app->triggerEvent('onLoadTemplate', [$this, $tpl]);

                // If the event overrode the output, print that output and don't display anything else
                if ($this->_output) {
                    echo $this->_output;
                    return;
                }
            }

            return parent::display($tpl);
        }
    }
} else {
    class K2View extends JView
    {
    }
}
