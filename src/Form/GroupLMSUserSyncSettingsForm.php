<?php

namespace Drupal\group_lms_user_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GroupLMSUserSyncSettingsForm.
 */
class GroupLMSUserSyncSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'group_lms_user_sync_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['group_lms_user_sync.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['json_content'] = [
      '#title' => $this->t("JSON"),
      '#type' => 'textarea',
      '#description' => $this->t('Paste your JSON here'),
      '#default_value' => "",
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process JSON Content'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('group_lms_user_sync.settings');
    $conf_api_endpoint_info = $config->get('api_endpoint_info');
    $form_api_endpoint_info = $form_state->getValue('api_endpoint_info');
    $conf_api_endpoint_version = $config->get('api_endpoint_version');
    $form_api_endpoint_version = $form_state->getValue('api_endpoint_version');

    // Only rebuild the routes if the api_endpoint_info switch has changed.
    if ($conf_api_endpoint_info != $form_api_endpoint_info) {
      $config->set('api_endpoint_info', $form_api_endpoint_info)->save();
      \Drupal::service('router.builder')->setRebuildNeeded();
    }

    if ($conf_api_endpoint_version != $form_api_endpoint_version) {
      $config->set('api_endpoint_version', $form_api_endpoint_version)->save();
    }

    parent::submitForm($form, $form_state);
  }

}
