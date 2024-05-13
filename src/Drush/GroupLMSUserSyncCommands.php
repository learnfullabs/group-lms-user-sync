<?php

namespace Drupal\group_lms_user_sync\Drush;

use Drupal\group_lms_user_sync\GroupLMSUserSyncAPI;
use Drush\Commands\DrushCommands;

/**
 * Class GroupLMSUserSyncCommands.
 *
 * @package Drupal\group_lms_user_sync\Drush
 */
class GroupLMSUserSyncCommands extends DrushCommands {

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
    $drushHandler = new GroupLMSUSerSyncAPI();
    $endpoint_id = \Drupal::config('group_lms_user_sync.settings')->get('api_endpoint_info') ?? "";
    $api_version = "v1";
    $endpoint_url = \Drupal::service('key.repository')->getKey($endpoint_id)->getKeyValue();

    $res = $drushHandler->syncUsersToGroups($endpoint_id, $api_version, $endpoint_url);

    if ($res == 1) {
      $this->io()->success('Synced users/group from the LMI Endpoint !' . $endpoint_url);
    } else if ($res == -1) {
      $this->io()->error('Endpoint URL was not set');
    }
  }

}
