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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Users\Administrator\Helper\Mfa;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');
$user = $this->getCurrentUser();
?>

<!-- K2 user profile form -->
<form id="member-profile" action="<?php echo Route::_('index.php?option=com_users'); ?>" method="post"
      class="form-validate" enctype="multipart/form-data">
    <?php if ($this->params->def('show_page_title', 1)): ?>
        <div class="componentheading<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
            <?php echo $this->escape($this->params->get('page_title')); ?>
        </div>
    <?php endif; ?>
    <div id="k2Container" class="k2AccountPage">
        <fieldset>
            <legend>
                <?php echo Text::_('K2_ACCOUNT_DETAILS'); ?>
            </legend>


            <div class="control-group">
                <div class="control-label"><label for="username"><?php echo Text::_('K2_USER_NAME'); ?></label></div>


                <span><b><?php echo $this->user->get('username'); ?></b></span>
            </div>


            <div class="control-group">
                <div class="control-label">
                    <label id="namemsg" for="name"><?php echo Text::_('K2_NAME'); ?></label>
                </div>
                <div class="controls">
                    <input class="form-control" type="text" name="<?php echo $this->nameFieldName; ?>"
                           id="name" size="40"
                           value="<?php echo $this->escape($this->user->get('name')); ?>"
                           class="inputbox required"
                           maxlength="50"/>
                </div>
            </div>


            <div class="control-group">
                <div class="control-label">
                    <label id="emailmsg" for="email"><?php echo Text::_('K2_EMAIL'); ?></label>
                </div>
                <div class="controls">
                    <input class="form-control" type="text" id="email"
                           name="<?php echo $this->emailFieldName; ?>" size="40"
                           value="<?php echo $this->escape($this->user->get('email')); ?>"
                           class="inputbox required validate-email" maxlength="100"/>
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="email2msg"
                           for="email2"><?php echo Text::_('K2_CONFIRM_EMAIL'); ?></label>
                </div>
                <div class="controls">
                    <input class="form-control" type="text" id="email2" name="jform[email2]" size="40"
                           value="<?php echo $this->escape($this->user->get('email')); ?>"
                           class="inputbox required validate-email" maxlength="100"/>
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="pwmsg" for="password"><?php echo Text::_('K2_PASSWORD'); ?></label>
                </div>
                <div class="controls">
                    <input class="form-control" class="inputbox validate-password" type="password" id="password"
                           name="<?php echo $this->passwordFieldName; ?>" size="40" value=""/>
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="pw2msg" for="password2"><?php echo Text::_('K2_VERIFY_PASSWORD'); ?></label>
                </div>
                <div class="controls">
                    <input class="form-control" class="inputbox validate-passverify" type="password" id="password2"
                           name="<?php echo $this->passwordVerifyFieldName; ?>" size="40" value=""/>
                </div>
            </div>
            <legend>
                <?php echo Text::_('K2_PERSONAL_DETAILS'); ?>
            </legend>
            <!-- K2 attached fields -->

            <div class="control-group">
                <div class="control-label">
                    <label id="gendermsg" for="gender"><?php echo Text::_('K2_GENDER'); ?></label>
                </div>
                <?php echo $this->lists['gender']; ?>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="descriptionmsg" for="description"><?php echo Text::_('K2_DESCRIPTION'); ?></label>
                </div>
                <?php echo $this->editor; ?>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="imagemsg" for="image"><?php echo Text::_('K2_USER_IMAGE_AVATAR'); ?></label>
                </div>
                <div class="controls">
                    <input class="form-control" type="file" id="image" name="image" accept="image/*"/>
                </div>
                <?php if ($this->K2User->image): ?>
                    <div class="control-group">
                        <img class="k2AccountPageImage"
                             src="<?php echo URI::root(true) . '/media/k2/users/' . $this->K2User->image; ?>"
                             alt="<?php echo $this->user->name; ?>"/>
                        <div class="controls">
                            <input type="checkbox" name="del_image" id="del_image"/>
                        </div>
                        <div class="control-label">
                            <label for="del_image"><?php echo Text::_('K2_CHECK_THIS_BOX_TO_DELETE_CURRENT_IMAGE_OR_JUST_UPLOAD_A_NEW_IMAGE_TO_REPLACE_THE_EXISTING_ONE'); ?></label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="urlmsg" for="url"><?php echo Text::_('K2_URL'); ?></label>
                </div>
                <div class="controls">
                    <input class="form-control" type="text" size="50" value="<?php echo $this->K2User->url; ?>"
                           name="url" id="url"/>
                </div>
            </div>

            <?php if (count(array_filter($this->K2Plugins))): ?>
                <!-- K2 Plugin attached fields -->
                <legend>
                    <?php echo Text::_('K2_ADDITIONAL_DETAILS'); ?>
                </legend>

                <?php foreach ($this->K2Plugins as $K2Plugin): ?>
                    <?php if (!is_null($K2Plugin)): ?>

                        <?php echo $K2Plugin->fields; ?>


                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (isset($this->form)): ?>
                <?php foreach ($this->form->getFieldsets() as $fieldset): // Iterate through the form fieldsets and display each one.?>
                    <?php if ($fieldset->name !== 'core'): ?>
                        <?php $fields = $this->form->getFieldset($fieldset->name); ?>
                        <?php if (isset($fields) && count($fields)): ?>
                            <?php if (isset($fieldset->label)): // If the fieldset has a label set, display it as the legend.?>
                                <legend>
                                    <?php echo Text::_($fieldset->label); ?>
                                </legend>
                            <?php endif; ?>
                            <?php foreach ($fields as $field): // Iterate through the fields in the set and display them.?>
                                <?php if ($field->hidden): // If the field is hidden, just display the input.?>
                                    <div class="controls">
                                        <?php echo $field->input; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="control-group">
                                        <div class="control-label">
                                            <?php echo $field->label; ?>
                                        </div>
                                        <div class="controls">
                                            <?php echo $field->input; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php try {
                if (Mfa::getConfigurationInterface($user)) : ?>
                    <fieldset class="com-users-profile__multifactor">
                        <legend><?php echo Text::_('COM_USERS_PROFILE_MULTIFACTOR_AUTH'); ?></legend>
                        <?php echo $this->mfaConfigurationUI ?>
                    </fieldset>
                <?php endif;
            } catch (Exception $e) {
            } ?>
            <div class="k2AccountPageUpdate">
                <button class="button validate" type="submit" onclick="submitbutton( this.form );return false;">
                    <?php echo Text::_('K2_SAVE'); ?>
                </button>
            </div>
    </div>
    <input class="form-control" type="hidden" name="<?php echo $this->usernameFieldName; ?>"
           value="<?php echo $this->user->get('username'); ?>"/>
    <input class="form-control" type="hidden" name="<?php echo $this->idFieldName; ?>"
           value="<?php echo $this->user->get('id'); ?>"/>
    <input class="form-control" type="hidden" name="gid" value="<?php echo $this->user->get('gid'); ?>"/>
    <input class="form-control" type="hidden" name="option" value="<?php echo $this->optionValue; ?>"/>
    <input class="form-control" type="hidden" name="task" value="<?php echo $this->taskValue; ?>"/>
    <input class="form-control" type="hidden" name="K2UserForm" value="1"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
