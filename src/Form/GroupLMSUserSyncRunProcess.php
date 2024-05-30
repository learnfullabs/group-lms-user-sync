<?php

namespace Drupal\group_lms_user_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\group_lms_user_sync\GroupLMSUserSyncAPI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GroupLMSUserSyncRunProcess.
 */
class GroupLMSUserSyncRunProcess extends FormBase {

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
    return 'group_lms_user_sync_run_process';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['form_description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Click here to run the Sync Process from the LMS API to the Drupal Groups. Check database logs to look for issues after the sync has been executed.'),
      '#weight' => 1,            
    ];
    
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run the Sync Process'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $res = $this->api->syncUsersToGroups(TRUE);

    if ($res) {
      $this->messenger->addMessage($this->t('Synced users/group from the LMI Endpoint ! ' . $this->api->getAPIEndpoint()));
    } else {
      $this->messenger->AddError($this->t('Unknown Error - Please check the Database Log table for more information !'));
    }
  }

}