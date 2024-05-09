<?php

namespace Drupal\group_lms_user_sync\Drush;

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
    $endpoint_id = \Drupal::config('group_lms_user_sync.settings')->get('api_endpoint_info') ?? "";

    if (isset($endpoint_id) && !empty($endpoint_id)) {
      // Use the keys API to get the Endpoint URL
      $endpoint_url = \Drupal::service('key.repository')->getKey($endpoint_id)->getKeyValue();
    }

    $this->io()->success('Synced users/group from the LMI Endpoint !' . $endpoint_url);
  }

}
