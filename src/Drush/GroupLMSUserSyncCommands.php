<?php

namespace Drupal\group_lms_user_sync\Drush;

use Drupal\group_lms_user_sync\GroupLMSUserSyncAPI;

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

    $drushHandler->syncUsersToGroups();
  }

}
