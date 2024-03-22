<?php

/**
 * @version    2.11.x
 * @package    K2
 * @license    GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$icons = [
	['image'   => 'icon-file-alt',
	 'link'    => Route::_('index.php?option=com_k2&view=items'),
	 'linkadd' => Route::_('index.php?option=com_k2&view=item'),
	 'name'    => 'K2_ITEMS',
	 'access'  => ['core.manage', 'com_k2', 'core.edit', 'com_k2'],
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_items', 1)
	],
	['image'   => 'icon-star',
	 'link'    => Route::_('index.php?option=com_k2&view=items&filter_featured=1&filter_trash=0'),
	 'name'    => 'K2_FEATURED_ITEMS',
	 'access'  => ['core.manage', 'com_k2'],
	 'group'   => 'MOD_QUICKICON_SITE',
	 'param'   => (int)$params->get('show_featured', 1)
	],
	['image'   => 'icon-trash',
	 'link'    => Route::_('index.php?option=com_k2&view=items&filter_trash=0'),
	 'name'    => 'K2_TRASHED_ITEMS',
	 'access'  => ['core.manage', 'com_k2'],
	 'group'   => 'MOD_QUICKICON_SITE',
	 'param'   => (int)$params->get('show_trashed', 1)
	],
	['image'   => 'icon-list',
	 'link'    => Route::_('index.php?option=com_k2&view=categories'),
	 'linkadd' => Route::_('index.php?option=com_k2&view=category'),
	 'name'    => 'K2_CATEGORIES',
	 'access'  => ['core.manage', 'com_k2', 'core.create', 'com_k2'],
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_categories', 1)
	],
	['image'   => 'icon-tags',
	 'link'    => Route::_('index.php?option=com_k2&view=tags'),
	 'linkadd' => Route::_('index.php?option=com_k2&view=tag'),
	 'name'    => 'K2_TAGS',
	 'access'  => ['core.manage', 'com_k2', 'core.create', 'com_k2'],
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_tags', 1)
	],
	['image'   => 'icon-comments',
	 'link'    => Route::_('index.php?option=com_k2&view=comments'),
	 'name'    => 'K2_COMMENTS',
	 'access'  => ['core.manage', 'com_k2'],
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_comments', 1)
	],
	['image'   => 'icon-cube',
	 'link'    => Route::_('index.php?option=com_k2&view=extrafields'),
	 'linkadd' => Route::_('index.php?option=com_k2&view=extrafield'),
	 'name'    => 'K2_EXTRA_FIELDS',
	 'access'  => ['core.manage', 'com_k2', 'core.create', 'com_k2'],
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_extra_fields', 1)
	],
	['image'   => 'icon-cubes',
	 'link'    => Route::_('index.php?option=com_k2&view=extrafieldsgroups'),
	 'linkadd' => Route::_('index.php?option=com_k2&view=extrafieldsgroup'),
	 'name'    => 'K2_EXTRA_FIELD_GROUPS',
	 'access'  => ['core.manage', 'com_k2', 'core.create', 'com_k2'],
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_extra_field_groups', 1)
	],
	['image'   => 'icon-picture',
	 'link'    => Route::_('index.php?option=com_k2&view=media'),
	 'name'    => 'K2_MEDIA_MANAGER',
	 'access'  => ['core.manage', 'com_k2'],
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_media_manager', 1)
	],
	['image'   => 'icon-book',
	 'link'    => 'https://www.joomlaworks.net/documentation/',
	 'name'    => 'K2_DOCS_AND_TUTORIALS',
	 'target'  => 'k2Popup',
	 'onclick' => 'K2Popup(\'https://www.joomlaworks.net/documentation/\');',
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_documentation', 1)
	],
	['image'   => 'icon-puzzle-piece',
	 'link'    => 'https://www.joomlaworks.net/extensions/',
	 'name'    => 'K2_EXTEND',
	 'target'  => 'k2Popup',
	 'onclick' => 'K2Popup(\'https://www.joomlaworks.net/extensions/\');',
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_extension', 1)
	],
	['image'   => 'icon-users',
	 'link'    => 'https://www.joomlaworks.net/forum/k2/',
	 'name'    => 'K2_COMMUNITY',
	 'target'  => 'k2Popup',
	 'onclick' => 'K2Popup(\'https://www.joomlaworks.net/forum/k2/\');',
	 'group'   => 'MOD_K2_QUICKICON',
	 'param'   => (int)$params->get('show_community', 1)
	]
];
$buttons = [];
foreach ($icons as $icon){
	if($icon['param']){
		$buttons[] = $icon;
	}
}
$html = HTMLHelper::_('icons.buttons', $buttons);
?>
<?php if (!empty($html)) : ?>
    <nav class="quick-icons px-3 pb-3" aria-label="<?php echo Text::_('MOD_K2_QUICKICON_NAV_LABEL') . ' ' . $module->title; ?>">
        <ul class="nav flex-wrap">
			<?php echo $html; ?>
        </ul>
    </nav>
<?php endif; ?>