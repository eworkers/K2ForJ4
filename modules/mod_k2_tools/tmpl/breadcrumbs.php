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
use Joomla\CMS\Uri\Uri;

$output = '';
if ($params->get('home')) {
    $output .= '<span class="bcTitle">' . Text::_('K2_YOU_ARE_HERE') . '</span><a href="' . URI::root() . '">' . $params->get('home', Text::_('K2_HOME')) . '</a>';
    if (count($path)) {
        foreach ($path as $link) {
            $output .= '<span class="bcSeparator">' . $params->get('seperator', '&raquo;') . '</span>' . $link;
        }
    }
    if ($title) {
        $output .= '<span class="bcSeparator">' . $params->get('seperator', '&raquo;') . '</span>' . $title;
    }
} else {
    if ($title) {
        $output .= '<span class="bcTitle">' . Text::_('K2_YOU_ARE_HERE') . '</span>';
    }
    if (count($path)) {
        foreach ($path as $link) {
            $output .= $link . '<span class="bcSeparator">' . $params->get('seperator', '&raquo;') . '</span>';
        }
    }
    $output .= $title;
}

?>

<div id="k2ModuleBox<?php echo $module->id; ?>"
     class="k2BreadcrumbsBlock<?php if ($params->get('moduleclass_sfx')) echo ' ' . $params->get('moduleclass_sfx'); ?>">
    <?php echo $output; ?>
</div>
