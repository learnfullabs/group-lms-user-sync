<?php

namespace Drupal\group_lms_user_sync\Drush;

use Drupal\group_lms_user_sync\GroupLMSUserSyncAPI;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drush\Commands\DrushCommands;

/**
 * Class GroupLMSUserSyncCommands.
 *
 * @package Drupal\group_lms_user_sync\Drush
 */
class GroupLMSUserSyncCommands extends DrushCommands {

  /**
   * The GroupLMSUserSyncAPI wrapper.
   *
   * @var \Drupal\group_lms_user_sync\GroupLMSUserSyncAPI
   */
  protected $api;

  /**
   * The admin controller constructor.
   *
   * @param \Drupal\group_lms_user_sync\GroupLMSUserSyncAPI $api
   *   The GroupLMSUserSyncAPI wrapper.
   */
  public function __construct(GroupLMSUserSyncAPI $api) {
    $this->api = $api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->api = $container->get('group_lms_user_sync.api');
    
    return $instance;
  }

  /**
   * Sync users/class groups from the LMI endpoint.
   *
   * @command gl:user-sync
   * 
   * @aliases gl-us
   * 
   * @usage gl-us
   */
  public function syncUsersGroups() {

    $res = $this->api->syncUsersToGroups();

    if ($res == 1) {
      $this->io()->success('Synced users/group from the LMI Endpoint ! ' . $endpoint_url);
    } else if ($res == -1) {
      $this->io()->error('Endpoint URL was not set');
    } else {
      $this->io()->error('Unknown Error !');
    }
  }

}
