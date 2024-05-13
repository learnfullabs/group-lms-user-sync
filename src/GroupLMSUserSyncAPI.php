<?php

namespace Drupal\group_lms_user_sync;

/**
 * GroupLMSUserSyncAPI.
 *
 * Class that provides methods to parse/process the data (JSON) coming from the API
 */
class GroupLMSUserSyncAPI {

  /**
   * Endpoint ID.
   *
   * @var string
   */
  protected $endpoint_id;

  /**
   * URL of the LMS Rest Endpoint.
   *
   * @var string
   */
  protected $endpoint_url;

  /**
   * API Version.
   *
   * @var string
   */
  protected $api_version;

  /**
   * Constructs a new GroupLMSUserSyncAPI object.
   *
   * @param string $vocabulary_name
   *   Vocabulary name where the terms will be created.
   */
  public function __construct(string $endpoint_id, string $endpoint_url, string $api_version) {
    $this->endpoint_id = $endpoint_id;
    $this->endpoint_url = $endpoint_url;
    $this->api_version = $api_version;
  }

  /**
   * Sync users/class groups from the LMI AP Endpoint to the Drupal Groups.
   */
  public function syncUsersToGroups() {
    if (isset($this->endpoint_id) && !empty($this->endpoint_id)) {    
      if (isset($this->endpoint_url) && !empty($this->endpoint_url)) {
        // Create an httpClient Object that will be used for all the requests.
        $client = \Drupal::httpClient();

        // Pulling the data from the API
        $group_ids = [1, 2, 3];

        foreach ($group_ids as $group_id) {
          try {
            $request = $client->get($this->endpoint_url . '/' . $this->api_version . '/' . $group_id . '/classlist/paged', [
              'http_errors' => TRUE,
              'query' => [
                '_format' => 'json'
              ]
            ]);
          
            if (!empty($request)) {
              //$this->io()->success('Got data from the Endpoint !' . $request->getBody());
              $classroom = json_decode($request->getBody());

              foreach($classroom as $student) {
                //$this->io()->success('Student Identifier: ' . $student->Identifier);
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
        // Endpoint URL was not set
        return -1;
      }
    } else {

    }

    return 1;
  }

}