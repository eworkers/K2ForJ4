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
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Form\FormField;

require_once(JPATH_ADMINISTRATOR . '/components/com_k2/elements/base.php');

class K2ElementK2Category extends K2Element
{
    public function fetchElement($name, $value, &$node, $control_name)
    {
        $db = Factory::getDbo();
        $query = 'SELECT m.* FROM #__k2_categories m WHERE trash = 0 ORDER BY parent, ordering';
        $db->setQuery($query);
        $mitems = $db->loadObjectList();
        $children = array();
        if ($mitems) {
            foreach ($mitems as $v) {
                $v->title = $v->name;
                $v->parent_id = $v->parent;
                $pt = $v->parent;
                $list = @$children[$pt] ? $children[$pt] : array();
                array_push($list, $v);
                $children[$pt] = $list;
            }
        }

        $list = JHTML::_('menu.treerecurse', 0, '', array(), $children, 9999, 0, 0);
        $mitems = array();
        $option = Factory::getApplication()->input->getCmd('option');
        if ($name == 'categories' || $name == 'jform[params][categories]') {
            if (version_compare(JVERSION, '4.0.0-dev', 'lt')){
                JHtml::_('behavior.framework');
                $js = "
                /* Mootools Snippet */
				function disableParams(){
					$('jform_params_num_leading_items').setProperty('disabled','disabled');
					$('jform_params_num_leading_columns').setProperty('disabled','disabled');
					$('jform_params_leadingImgSize').setProperty('disabled','disabled');
					$('jform_params_num_primary_items').setProperty('disabled','disabled');
					$('jform_params_num_primary_columns').setProperty('disabled','disabled');
					$('jform_params_primaryImgSize').setProperty('disabled','disabled');
					$('jform_params_num_secondary_items').setProperty('disabled','disabled');
					$('jform_params_num_secondary_columns').setProperty('disabled','disabled');
					$('jform_params_secondaryImgSize').setProperty('disabled','disabled');
					$('jform_params_num_links').setProperty('disabled','disabled');
					$('jform_params_num_links_columns').setProperty('disabled','disabled');
					$('jform_params_linksImgSize').setProperty('disabled','disabled');
					$('jform_params_catCatalogMode').setProperty('disabled','disabled');
					$('jform_params_catFeaturedItems').setProperty('disabled','disabled');
					$('jform_params_catOrdering').setProperty('disabled','disabled');
					$('jform_params_catPagination').setProperty('disabled','disabled');
					$('jform_params_catPaginationResults0').setProperty('disabled','disabled');
					$('jform_params_catPaginationResults1').setProperty('disabled','disabled');
					$('jform_params_catFeedLink0').setProperty('disabled','disabled');
					$('jform_params_catFeedLink1').setProperty('disabled','disabled');
					$('jform_params_catFeedIcon0').setProperty('disabled','disabled');
					$('jform_params_catFeedIcon1').setProperty('disabled','disabled');
					$('jformparamstheme').setProperty('disabled','disabled');
				}

				function enableParams(){
					$('jform_params_num_leading_items').removeProperty('disabled');
					$('jform_params_num_leading_columns').removeProperty('disabled');
					$('jform_params_leadingImgSize').removeProperty('disabled');
					$('jform_params_num_primary_items').removeProperty('disabled');
					$('jform_params_num_primary_columns').removeProperty('disabled');
					$('jform_params_primaryImgSize').removeProperty('disabled');
					$('jform_params_num_secondary_items').removeProperty('disabled');
					$('jform_params_num_secondary_columns').removeProperty('disabled');
					$('jform_params_secondaryImgSize').removeProperty('disabled');
					$('jform_params_num_links').removeProperty('disabled');
					$('jform_params_num_links_columns').removeProperty('disabled');
					$('jform_params_linksImgSize').removeProperty('disabled');
					$('jform_params_catCatalogMode').removeProperty('disabled');
					$('jform_params_catFeaturedItems').removeProperty('disabled');
					$('jform_params_catOrdering').removeProperty('disabled');
					$('jform_params_catPagination').removeProperty('disabled');
					$('jform_params_catPaginationResults0').removeProperty('disabled');
					$('jform_params_catPaginationResults1').removeProperty('disabled');
					$('jform_params_catFeedLink0').removeProperty('disabled');
					$('jform_params_catFeedLink1').removeProperty('disabled');
					$('jform_params_catFeedIcon0').removeProperty('disabled');
					$('jform_params_catFeedIcon1').removeProperty('disabled');
					$('jformparamstheme').removeProperty('disabled');
				}

				function setTask() {
					var counter=0;
					$$('#jformparamscategories option').each(function(el) {
						if (el.selected){
							value=el.value;
							counter++;
						}
					});
					if (counter>1 || counter==0){
						$('jform_request_id').setProperty('value','');
						$('jform_request_task').setProperty('value','');
						$('jform_params_singleCatOrdering').setProperty('disabled', 'disabled');
						enableParams();
					}
					if (counter==1){
						$('jform_request_id').setProperty('value',value);
						$('jform_request_task').setProperty('value','category');
						$('jform_params_singleCatOrdering').removeProperty('disabled');
						disableParams();
					}
				}

				window.addEvent('domready', function(){
					if($('request-options')) {
						$$('.panel')[0].setStyle('display', 'none');
					}
					setTask();
				});
				";
            }
            else{
                HTMLHelper::_('jquery.framework');
                $js = "
                var $ = jQuery.noConflict();
                var targetParams = [				
                    '#jform_params_num_leading_items',
					'#jform_params_num_leading_columns',
					'#jform_params_leadingImgSize',
					'#jform_params_num_primary_items',
					'#jform_params_num_primary_columns',
					'#jform_params_primaryImgSize',
					'#jform_params_num_secondary_items',
					'#jform_params_num_secondary_columns',
					'#jform_params_secondaryImgSize',
					'#jform_params_num_links',
					'#jform_params_num_links_columns',
					'#jform_params_linksImgSize',
					'#jform_params_catCatalogMode',
					'#jform_params_catFeaturedItems',
					'#jform_params_catOrdering',
					'#jform_params_catPagination',
					'#jform_params_catPaginationResults0',
					'#jform_params_catPaginationResults1',
					'#jform_params_catFeedLink0',
					'#jform_params_catFeedLink1',
					'#jform_params_catFeedIcon0',
					'#jform_params_catFeedIcon1',
					'#jformparamstheme'
					].join(', ');
					
				function disableParams(){
                    $(targetParams).prop('disabled', true);
                    $('.jwHeaderContainer').hide();
                    $(targetParams).parents('.control-group').hide();
				}

				function enableParams(){
                    $(targetParams).prop('disabled', false);
                    $('.jwHeaderContainer').hide();
                    $(targetParams).parents('.control-group').show();
				}

				function setTask() {
					var counter=0;
					var counter = $('#jformparamscategories :selected').length;
					if (counter>1 || counter==0){
						$('#jform_request_id').val('');
						$('#jform_request_task').val('');
						$('jform_params_singleCatOrdering').prop('disabled', true);
						enableParams();
					}
					if (counter==1){
						$('jform_request_id').val($('#jformparamscategories :selected').val());
						$('jform_request_task').val('category');
						$('jform_params_singleCatOrdering').prop('disabled', false);
						disableParams();
					}
				}

				$(document).ready(function($){
                    if($('request-options')) {
                        if($('.panel')[0])
                            {
                                $('.panel')[0].css('display', 'none');
                            }
                    }
                    setTask();
				});
				";
            }
            $doc = Factory::getDocument();

            $doc->addScriptDeclaration($js);
        }

        foreach ($list as $item) {
            $item->treename = StringHelper::str_ireplace('&#160;', '- ', $item->treename);
            @$mitems[] = JHTML::_('select.option', $item->id, $item->treename);
        }

        $fieldName = $name . '[]';

        if ($name == 'categories' || $name == 'jform[params][categories]') {
            $onChange = 'onchange="setTask();"';
        } else {
            $onChange = '';
        }

        return JHTML::_('select.genericlist', $mitems, $fieldName, $onChange . ' class="inputbox" multiple="multiple" size="15"', 'value', 'text', $value);
    }
}

class JFormFieldK2Category extends K2ElementK2Category
{
    public $type = 'k2category';
}

class JElementK2Category extends K2ElementK2Category
{
    public $_name = 'k2category';
}
