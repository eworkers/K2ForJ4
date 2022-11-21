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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

jimport('joomla.application.component.model');

class K2Model extends BaseDatabaseModel
{
    public static function addIncludePath($path = '', $prefix = 'K2Model')
    {
        return parent::addIncludePath($path, $prefix);
    }
}
