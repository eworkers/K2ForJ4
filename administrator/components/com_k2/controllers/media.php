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

use Joomla\String\StringHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Filesystem\File;

jimport('joomla.application.component.controller');
jimport('joomla.filesystem.file');

class K2ControllerMedia extends K2Controller
{
    public function display($cachable = false, $urlparams = array())
    {
        Factory::getApplication()->input->set('view', 'media');
        parent::display();
    }

    public function connector()
    {
        // Check token
        $method = ($_POST) ? 'post' : 'get';
        Session::checkToken($method) or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_media');
        $root = $params->get('file_path', 'media');
        $folder = Factory::getApplication()->input->getVar('folder', $root, 'default', 'path');
        $type = Factory::getApplication()->input->getCmd('type', 'video');

        if (StringHelper::trim($folder) == "") {
            $folder = $root;
        } else {
            // Ensure that we are always below the root directory
            if (strpos($folder, $root) !== 0) {
                $folder = $root;
            }
        }

        // Disable debug
        Factory::getApplication()->input->set('debug', false);

        $url = JURI::root(true) . '/' . $folder;
        $path = JPATH_SITE . '/' . Path::clean($folder);

        Path::check($path);

        // Disallow force downloading sensitive file types
        $disallowedFileTypes = array('php', 'ini', 'sql', 'htaccess');
        $target = Factory::getApplication()->input->getCmd('target');
        $download = Factory::getApplication()->input->getCmd('download');
        if ($target && $download) {
            $filePath = base64_decode(substr($target, 2));
            $fileExtension = strtolower(pathinfo(basename($filePath), PATHINFO_EXTENSION));
            if (in_array($fileExtension, $disallowedFileTypes)) {
                return;
            }
        }

        require_once(JPATH_SITE . '/media/k2/assets/vendors/studio-42/elfinder/php/autoload.php');

        function access($attr, $path, $data, $volume)
        {
            $app = Factory::getApplication();

            // Hide PHP files
            $ext = strtolower(File::getExt(basename($path)));

            if ($ext == 'php') {
                return true;
            }

            // Hide files and folders starting with .
            if (strpos(basename($path), '.') === 0 && $attr == 'hidden') {
                return true;
            }

            // Read only access for front-end. Full access for administration section.
            switch ($attr) {
                case 'read':
                    return true;
                    break;
                case 'write':
                    return ($app->isClient('site')) ? false : true;
                    break;
                case 'locked':
                    return ($app->isClient('site')) ? true : false;
                    break;
                case 'hidden':
                    return false;
                    break;
            }
        }

        if ($app->isClient('administrator')) {
            $permissions = array('read' => true, 'write' => true);
        } else {
            $permissions = array('read' => true, 'write' => false);
        }

        $options = array(
            'debug' => false,
            'roots' => array(
                array(
                    'driver' => 'LocalFileSystem',
                    'path' => $path,
                    'URL' => $url,
                    'accessControl' => 'access',
                    'defaults' => $permissions,
                    'mimeDetect' => 'internal',
                    'mimefile' => JPATH_SITE . '/media/k2/assets/vendors/studio-42/elfinder/php/mime.types',
                    'uploadDeny' => array('all'),
                    'uploadAllow' => array('image', 'video', 'audio', 'text/plain', 'text/html', 'application/json', 'application/pdf', 'application/zip', 'application/x-7z-compressed', 'application/x-bzip', 'application/x-bzip2', 'text/css', 'application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
                    'uploadOrder' => array('deny', 'allow')
                )
            )
        );
        $connector = new elFinderConnector(new elFinder($options));
        $connector->run();
    }
}
