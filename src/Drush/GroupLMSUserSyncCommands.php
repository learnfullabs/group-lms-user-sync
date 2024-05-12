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
    $api_version = "v1";

    if (isset($endpoint_id) && !empty($endpoint_id)) {
      // Use the keys API to get the Endpoint URL
      $endpoint_url = \Drupal::service('key.repository')->getKey($endpoint_id)->getKeyValue();
    
      if (isset($endpoint_url) && !empty($endpoint_url)) {
        // Create an httpClient Object that will be used for all the requests.
        $client = \Drupal::httpClient();

        // Pulling the data from the API
        $group_ids = [1, 2, 3];

        foreach ($group_ids as $group_id) {
          try {
            $request = $client->get($endpoint_url . '/' . $api_version . '/' . $group_id . '/classlist/paged', [
              'http_errors' => TRUE,
              'query' => [
                '_format' => 'json'
              ]
            ]);
          
            if (!empty($request)) {
              $this->io()->success('Got data from the Endpoint !' . $request->getBody());
              $classroom = json_decode($request->getBody());

              foreach($classroom as $student) {
                $this->io()->success('Student Identifier: ' . $student->Identifier);
                /* First, check if the user (identified by Email or Username) exists, if not, create the user */
                /* If it exists, enroll the user into the course identified by OrgDefinedId (OU field from the Group field) */
                /* Check for the RoleID field, should map to the Drupal User Role */
              }
            }
          } catch (\Exception $e) {
            watchdog_exception('group_lms_user_sync', $e);
          }
        }
      } else {
        $this->io()->error('Endpoint URL was not set');
      }
    }

    $this->io()->success('Synced users/group from the LMI Endpoint !' . $endpoint_url);
  }

}