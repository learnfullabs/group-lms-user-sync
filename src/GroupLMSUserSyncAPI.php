<?php

namespace Drupal\group_lms_user_sync;

use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;

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
              $classroom = json_decode($request->getBody());

              foreach ($classroom as $student) {                
                /* First, check if the user (identified by Email or Username) exists, if not, create the user */
                $user_email_api = $student->Email;
                /* Returns an \Drupal\user\UserInterface object */
                $user_obj = user_load_by_mail($user_email_api);
                $group_id_api = $student->OrgDefinedId;
                $user_id_api = $student->Identifier;
                $username_api = $student->Username;
                /* Check for the RoleID field, should map to the Drupal User Role */
                $group_role_api = $student->RoleId;
                $count_updated_groups = [];

                if ($user_obj) {
                  /* If it exists, enroll the user into the course identified by OrgDefinedId (OU field from the Group field) */
                  $gids = \Drupal::entityQuery('group')
                  ->condition('field_course_ou', $group_id_api)
                  ->accessCheck(FALSE)
                  ->execute();

                  if (count($gids)) {
                    foreach ($gids as $gid) {
                      $group = Group::load($gid);

                      if (!$group) {
                        \Drupal::logger('group_lms_user_sync')->error("Failed to load group identified by Group API ID @groupname", ['@groupname' => $group_id_api ]);
                        continue;
                      }

                      $group->addMember($user_obj);
                      $count_updated_groups[$user_id_api] = $group->id();
                      $group_name = $group->label();
                      \Drupal::logger('group_lms_user_sync')->notice("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]);
                    }
                  } else {
                    \Drupal::logger('group_lms_user_sync')->notice("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]);
                  }
                } else {
                  /* User doesn't exist, create it for now */
                  $user_new = User::create();

                  // This username must be unique and accept only a-Z,0-9, - _ @ .
                  $user_new->setUsername($username_api);

                  // Mandatory settings
                  $user_new->setPassword(NULL);
                  $user_new->enforceIsNew();
                  $user_new->setEmail($user_email_api);

                  // Optional settings
                  $language = 'en';
                  $user_new->set("langcode", $language);
                  $user_new->set("preferred_langcode", $language);

                  $user_new->activate();

                  try {
                    $res = $user_new->save();

                    $gids = \Drupal::entityQuery('group')
                    ->condition('field_course_ou', $group_id_api)
                    ->accessCheck(FALSE)
                    ->execute();

                    // Let's add the newly created user in the group
                    if (count($gids)) {
                      foreach ($gids as $gid) {
                        $group = Group::load($gid);

                        if (!$group) {
                          \Drupal::logger('group_lms_user_sync')->error("Failed to load group identified by Group API ID @groupname", ['@groupname' => $group_id_api ]);
                          continue;
                        }

                        $group->addMember($user_new);
                        $count_updated_groups[$user_id_api] = $group->id();
                        $group_name = $group->label();
                        \Drupal::logger('group_lms_user_sync')->notice("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]);
                      }
                    } else {
                      \Drupal::logger('group_lms_user_sync')->notice("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]);
                    }

                  } catch (\Exception $e) {
                    \Drupal::logger('group_lms_user_sync')->error("Failed to register user @username", ['@username' => $username_api ]);
                    watchdog_exception('group_lms_user_sync', $e);
                  }

                }
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