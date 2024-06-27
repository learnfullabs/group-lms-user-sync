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

    $config = $this->config('group_lms_user_sync.settings');

    $form['api_public_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Public key'),
      '#default_value' => $config->get('api_public_key'),
    ];

    $form['api_private_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Private key'),
      '#default_value' => $config->get('api_private_key'),
    ];

    $form['api_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint Base URL'),
      '#default_value' => $config->get('api_base_url'),
      '#size' => 255,
      '#description' => $this->t('The URL to the endpoint'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('group_lms_user_sync.settings');
    $conf_api_public_key = $config->get('api_public_key');
    $form_api_public_key = $form_state->getValue('api_public_key');
    $conf_api_private_key = $config->get('api_private_key');
    $form_api_private_key = $form_state->getValue('api_private_key');
    $conf_api_base_url = $config->get('api_base_url');
    $form_api_base_url = $form_state->getValue('api_base_url');

    if ($conf_api_private_key != $form_api_private_key) {
      $config->set('api_private_key', $form_api_private_key)->save();
    }

    if ($conf_api_public_key != $form_api_public_key) {
      $config->set('api_public_key', $form_api_public_key)->save();
    }

    // Only rebuild the routes if the api_endpoint_info switch has changed.
    if ($conf_api_base_url != $form_api_base_url) {
      $config->set('api_base_url', $form_api_base_url)->save();
      \Drupal::service('router.builder')->setRebuildNeeded();
    }

    parent::submitForm($form, $form_state);
  }

}
