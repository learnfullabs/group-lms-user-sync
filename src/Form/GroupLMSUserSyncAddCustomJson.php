<?php

namespace Drupal\group_lms_user_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GroupLMSUserSyncAddCustomJson.
 */
class GroupLMSUserSyncAddCustomJson extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'group_lms_user_sync_custom_json';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['group_lms_user_sync.custom_json_form'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['api_endpoint_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Version'),
      '#default_value' => $config->get('api_endpoint_version'),
      '#size' => 30,
      '#description' => $this->t('Endpoint API Version: v1 or v2'),
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