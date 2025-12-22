<?php

namespace Drupal\group_lms_user_sync\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\group_lms_user_sync\GroupLMSUserSyncAPI;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes group LMS sync batches.
 *
 * @QueueWorker(
 *   id = "group_lms_user_sync_batch",
 *   title = @Translation("Group LMS User Sync Batch"),
 *   cron = {"time" = 120}
 * )
 */
class GroupLmsSyncBatchWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The sync API service.
   *
   * @var \Drupal\group_lms_user_sync\GroupLMSUserSyncAPI
   */
  protected $syncApi;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new GroupLmsSyncBatchWorker.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\group_lms_user_sync\GroupLMSUserSyncAPI $sync_api
   *   The sync API service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GroupLMSUserSyncAPI $sync_api, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->syncApi = $sync_api;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('group_lms_user_sync.api'),
      $container->get('logger.factory')->get('group_lms_user_sync')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $offset = $data['offset'] ?? 0;
    $limit = $data['limit'] ?? 5;

    $this->logger->info('Processing batch: offset @offset, limit @limit', [
      '@offset' => $offset,
      '@limit' => $limit,
    ]);

    $result = $this->syncApi->syncUsersToGroups($offset, $limit);

    $this->logger->info('Batch completed: offset @offset. Result: @result', [
      '@offset' => $offset,
      '@result' => $result,
    ]);
  }

}
