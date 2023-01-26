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
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;

class K2Parameter
{
	public $namespace = null;
    public function __construct($data, $path, $namespace)
    {
	    if ($namespace) {
		    $this->namespace = $namespace;
	    }
        $this->values = new Registry($data);
    }

    public function get($path, $default = null)
    {
        return $this->values->get($this->namespace . $path, $default);
    }
}
