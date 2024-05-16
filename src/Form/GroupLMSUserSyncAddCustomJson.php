<?php

namespace Drupal\group_lms_user_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\group_lms_user_sync\GroupLMSUserSyncAPI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GroupLMSUserSyncAddCustomJson.
 */
class GroupLMSUserSyncAddCustomJson extends FormBase {

  /**
   * The GroupLMSUserSyncAPI wrapper.
   *
   * @var \Drupal\group_lms_user_sync\GroupLMSUserSyncAPI
   */
  protected $api;

  /**
   * Provides messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GroupLMSUserSyncAddCustomJson constructor.
   *
   * @param \Drupal\group_lms_user_sync\GroupLMSUserSyncAPI $api
   *   The GroupLMSUserSyncAPI wrapper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(GroupLMSUserSyncAPI $api, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->api = $api;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('group_lms_user_sync.api'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'group_lms_user_sync_custom_json';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['json_data'] = [
      '#title' => $this->t("JSON Snippet"),
      '#type' => 'textarea',
      '#description' => $this->t('Paste the JSON Content here for updating/adding new users to the groups. Refer to the README.md for more information on the structure of the JSON Snippet.'),
      '#default_value' => "",
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process JSON File'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $json_data = $form_state->getValue('json_data');

    if (!$json_data) {
      $this->messenger->AddError("JSON Data field is empty.");
    }

    try {
      $res = $this->api->syncFromTextField($json_data);

      if ($res) {
        $this->messenger->addMessage($this->t('Processed the JSON Data and updated groups.'));
      } else {
        $this->messenger->AddError("Error when updating the groups from the JSON Data. Check database logs for more info.");
      }
    } catch (\Exception $e) {
      $this->messenger->AddError("Error when updating the groups from the JSON Data. Check database logs for more info.");
    }
  }

}