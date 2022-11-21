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

use Joomla\CMS\Utility\Utility;
use Joomla\CMS\Session\Session;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

class K2HelperStats
{
    public static function getScripts()
    {
        $data = self::getData();
        $token = Session::getFormToken();

        HTMLHelper::_('jquery.framework');

        $document = Factory::getDocument();

        // For IE8/9 only (to be removed in K2 v3.x)
        $document->addScript('https://cdnjs.cloudflare.com/ajax/libs/jquery-ajaxtransport-xdomainrequest/1.0.4/jquery.xdomainrequest.min.js');

        $document->addScriptDeclaration("
	    	/* K2 - Metrics */
	        (function(\$){
				function K2LogResult(xhr) {
					\$.ajax({
						type: 'POST',
						url: 'index.php',
						data: {
							'option': 'com_k2',
							'view': 'items',
							'task': 'logStats',
							'" . $token . "': '1',
							'status': xhr.status,
							'response': xhr.responseText
						}
					});
				}
		        \$(document).ready(function(){
					\$.ajax({
						crossDomain: true,
						type: 'POST',
						url: 'https://metrics.getk2.org/gather.php',
						data: " . $data . "
					}).done(function(response, result, xhr) {
						K2LogResult(xhr);
					}).fail(function(xhr, result, response) {
						K2LogResult(xhr);
					});
				});
			})(jQuery);
		");
    }

    public static function getData()
    {
        $data = new stdClass;
        $data->identifier = self::getIdentifier();
        $data->php = self::getPhpVersion();
        $data->databaseType = self::getDbType();
        $data->databaseVersion = self::getDbVersion();
        $data->server = self::getServer();
        $data->serverInterface = self::getServerInterface();
        $data->cms = self::getCmsVersion();
        $data->extensionName = 'K2';
        $data->extensionVersion = self::getExtensionVersion();
        $data->caching = self::getCaching();
        $data->cachingDriver = self::getCachingDriver();
        return json_encode($data);
    }

    public static function getIdentifier()
    {
        $configuration = Factory::getConfig();
        $secret = $configuration->get('secret');
        return md5($secret . $_SERVER['SERVER_ADDR']);
    }

    public static function getPhpVersion()
    {
        return phpversion();
    }

    public static function getDbType()
    {
        $configuration = Factory::getConfig();
        $type = $configuration->get('dbtype');
        if ($type == 'mysql' || $type == 'mysqli' || $type == 'pdomysql') {
            $db = Factory::getDbo();
            $query = 'SELECT version();';
            $db->setQuery($query);
            $result = $db->loadResult();
            $result = strtolower($result);
            if (strpos($result, 'mariadb') !== false) {
                $type = 'mariadb';
            }
        }
        return $type;
    }

    public static function getDbVersion()
    {
        $db = Factory::getDbo();
        return $db->getVersion();
    }

    public static function getServer()
    {
        return isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : getenv('SERVER_SOFTWARE');
    }

    public static function getServerInterface()
    {
        return php_sapi_name();
    }

    public static function getCmsVersion()
    {
        return JVERSION;
    }

    public static function getExtensionVersion()
    {
        return K2_CURRENT_VERSION;
    }

    public static function getCaching()
    {
        $configuration = Factory::getConfig();
        return $configuration->get('caching');
    }

    public static function getCachingDriver()
    {
        $configuration = Factory::getConfig();
        return $configuration->get('cache_handler');
    }

    public static function shouldLog()
    {
        $db = Factory::getDbo();
        $query = 'SELECT * FROM #__k2_log';
        $db->setQuery($query, 0, 1);
        $result = $db->loadObject();
        if (!$result) {
            return true;
        }
        $now = Factory::getDate()->toUnix();
        $days = floor(($now - strtotime($result->timestamp)) / (60 * 60 * 24));
        if ($days >= 30 || $result->status != 200) {
            return true;
        }
        return false;
    }
}
