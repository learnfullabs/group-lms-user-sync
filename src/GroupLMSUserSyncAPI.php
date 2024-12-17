<?php

namespace Drupal\group_lms_user_sync;

use Drupal\user\Entity\User;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\group_lms_user_sync\Entity\GroupLog;

/**
 * GroupLMSUserSyncAPI.
 *
 * Class that provides methods to parse/process the data (JSON) coming from the API
 */
class GroupLMSUserSyncAPI implements ContainerInjectionInterface
{

  /**
   * Endpoint ID.
   *
   * @var string
   */
  protected $endpoint_id;

  /**
   * LMS Rest Endpoint Data.
   *
   * @var string
   */
  protected $endpoint_data;

  /**
   * Endpoint Public Key.
   *
   * @var string
   */
  protected $endpoint_publickey;

  /**
   * Endpoint Private Key.
   *
   * @var string
   */
  protected $endpoint_privatekey;

  /**
   * Hashed Endpoint Private Key.
   *
   * @var string
   */
  protected $endpoint_hashed_privatekey;

  /**
   * URL of the LMS Endpoint.
   *
   * @var string
   */
  protected $endpoint_url;

  /**
   * API Version.
   *
   * @var string
   */
  protected $api_version = "v1";

  /**
   * The logger channel factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Key repository object.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $repository;

  /**
   * Constructs a new GroupLMSUserSyncAPI object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\key\KeyRepositoryInterface $repository
   *   Key Repository
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger, KeyRepositoryInterface $repository)
  {
    $this->configFactory = $config_factory;
    $this->logger = $logger->get('group_lms_user_sync');
    $this->messenger = $messenger;
    $this->repository = $repository;

    $config = $this->configFactory->getEditable('group_lms_user_sync.settings');
    //$this->endpoint_id = $config->get('api_endpoint_info') ?? "";

    $this->endpoint_id = $config->get('api_base_url') ?? "";
    $this->endpoint_publickey = $config->get('api_public_key') ?? "";
    $this->endpoint_privatekey = $config->get('api_private_key') ?? "";
    $this->endpoint_url = $config->get('api_base_url') ?? "";
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('config'),
      $container->get('logger'),
      $container->get('messenger'),
      $container->get('key.repository')
    );
  }

  /**
   * Sync users/class groups from the LMI AP Endpoint to Drupal Groups.
   *
   * @return int
   *   TRUE on success or FALSE on error
   */
  public function syncUsersToGroups(): int
  {
    if (isset($this->endpoint_id) && !empty($this->endpoint_id)) {

      $this->endpoint_publickey = $this->repository->getKey($this->endpoint_publickey)->getKeyValue();

      $this->endpoint_privatekey = $this->repository->getKey($this->endpoint_privatekey)->getKeyValue();

      $this->endpoint_hashed_privatekey = hash('sha256', $this->endpoint_privatekey . 'json');

      //$this->endpoint_data = $this->repository->getKey($this->endpoint_id)->getKeyValue();

      if (isset($this->endpoint_publickey) && isset($this->endpoint_privatekey)) {
        // Create an httpClient Object that will be used for all the requests.
        $client = \Drupal::httpClient();

        $request_url = $this->endpoint_url . $this->endpoint_publickey . '/hash/' . $this->endpoint_hashed_privatekey . '/format/json/unit_id/';

        //$endpoint_data = json_decode($request_url);
        //$this->endpoint_url = $endpoint_data->url;
        //$auth = 'Basic '. base64_encode($endpoint_data->username . ':' . $endpoint_data->password);

        // Get a list of all the Groups in Drupal

        $group_ids = $this->getGroupIds();
        //$group_ids = $this->getAPIGroupIds();        

        /* Loop through all the Group OUs, make an API call for each Group OU
         * add new members if any, don't add members to the Group if they are
         * members already */
        foreach ($group_ids as $group_id) {

          // Get Group metadata.
          $group = Group::load($group_id);
          $group_name = $group->label();

          // Get all OUs used in Group, if any.
          $group_ous = $this->getGroupOus($group_id);

          if (is_array($group_ous) && (count($group_ous) > 0)) {

            foreach ($group_ous as $ou) {
              /**
               * Loop through all OUs for a Group, make the API call for each OU
               * value.
               * */
              try {
                $request = $client->get($request_url . $ou . '/', [
                  'http_errors' => TRUE,
                  'verify' => FALSE,
                  'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                  ],
                ]);

                if (!empty($request)) {
                  if ($request->getBody()) {

                    

                    $classList = json_decode($request->getBody());

                   // print for debug
                   $this->logger->debug('<pre><code>' . print_r($classList, true) . '</code></pre>');

                    if (is_array($classList) && (count($classList) > 0)) {

                      

                      $this->unEnrollUser($group_id, $classList, $ou);
                      foreach ($classList as $grouping) {
                        foreach ($grouping as $account) {
                          /**
                           * stdClass Object (
                           * [id] => 84774
                           * [user_id] => onlin001
                           * [username] => onlin001
                           * [display_name] => Guest Account
                           * [first_name] => Guest
                           * [last_name] => Account
                           * [role] => stdClass Object(
                           *   [id] => 129
                           * ))
                           */

                          /* First, check if the user (identified by Email or Username) exists, if not, create the user */
                          $id = $account->username;
                          $user_id = $account->user_id;
                          $username = $account->username;
                          $display_name = $account->display_name;
                          $first_name = $account->first_name;
                          $last_name = $account->last_name;
                          $role_id = $account->role->id;

                          // \Drupal::logger('group_lms_user_sync')->info('Username @uname.', [
                          //   '@uname' => $role_id,
                          // ]);

                          /**
                           * ROLE_ID_ADMINISTRATOR                = 102;
                           * ROLE_ID_INSTRUCTOR                   = 106;
                           * ROLE_ID_STUDENT                      = 107;
                           * ROLE_ID_COURSE_MANAGER               = 110;
                           * ROLE_ID_CEL_COURSE_EDITOR            = 111;
                           * ROLE_ID_TA_LEVEL_4                   = 112;
                           * ROLE_ID_STAFF                        = 113;
                           * ROLE_ID_TA_LEVEL_1                   = 114;
                           * ROLE_ID_TA_LEVEL_3                   = 115;
                           * ROLE_ID_TA_LEVEL_2                   = 117;
                           * ROLE_ID_FUTURE_STUDENT               = 124;
                           * ROLE_ID_TA_LEVEL_15                  = 126;
                           * 
                           */

                          // Skip any accounts with roles we will ignore.
                          if ($role_id == '109' || $role_id == '116' || $role_id == '129') {
                            \Drupal::logger('group_lms_user_sync')->info('Skipped: Did not sync @user from @ou to @groupname (@groupid) because LMS Role ID: @role_id.', [
                              '@user' => $username,
                              '@ou' => $ou,
                              '@groupname' => $group_name,
                              '@groupid' => $group_id,
                              '@role_id' => $role_id,
                            ]);
                          } else {
                            $this->enrollUser($group_id, $username, $role_id, $ou);
                          } // end if role
                        } // end $account loop
                      } // end classList loop
                    } // end if ClassList
                  }
                } // end request
              } catch (\Exception $e) {
                watchdog_exception('group_lms_user_sync', $e);
                return FALSE;
              }
            }
          }
        }
      } else {
        // Endpoint URL was not set or is empty
        $this->logger->warning("Failed to set Endpoint URL");
        return FALSE;
      }
    } else {
      // Endpoint ID was not set or is empty
      $this->logger->warning("Failed to set Endpoint URL - Have you set up the Key Authentication Endpoint?");
      return FALSE;
    }

    return TRUE;
  }



  /**
   * Return a list of all the OUs used by a Group stored in Drupal 
   * field field_course_ou.
   * 
   * @param string $gid
   * The Group ID to use when looking for OU values
   * 
   * @return array
   * Array of OUs or an empty array if empty
   */
  private function getGroupOus($gid): array
  {
    $group_ous = [];

    $group = Group::load($gid);
    $api_ou = $group->field_course_ou->getValue();

    foreach ($api_ou as $ou) {
      $group_ous[] = $ou["value"];
    }

    return $group_ous;

  }

  /**
   * Return a list of all the Group IDs.
   * Returns the Group ID for published groups
   * sorted by last modified.
   * 
   * @return array
   *   Array of Group IDs or an empty array if empty
   */
  private function getGroupIds(): array
  {
    $group_ids = [];

    $gids = \Drupal::entityQuery('group')
      ->exists('field_course_ou')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->sort('changed', 'DESC')
      ->execute();

    if (count($gids) > 0) {
      foreach ($gids as $gid) {
        $group_ids[] = $gid;
      }
    }

    return $group_ids;
  }


  /**
   * Return a list of all the Group API IDs stored in the Drupal Group 
   * field field_course_ou.
   * 
   * @return array
   *   Array of Group API IDs or an empty array if empty
   */
  private function getAPIGroupIds(): array
  {
    $group_api_ids = [];

    $gids = \Drupal::entityQuery('group')
      ->exists('field_course_ou')
      ->accessCheck(FALSE)
      ->execute();

    if (count($gids) > 0) {
      foreach ($gids as $gid) {
        $group = Group::load($gid);
        $api_ou = $group->field_course_ou->getValue();

        foreach ($api_ou as $ou) {
          $group_api_ids[] = $ou["value"];
        }
      }
    }

    return $group_api_ids;
  }

  /**
   * Function that creates the Group Membership with
   * the proper role given a Group Id, Username and Role Id.
   * 
   * @param string $group_id
   * The (drupal) Group Id to add the user the user to.
   * 
   * @param string $username
   * The (drupal) username to search for.
   * 
   * @param string $role_id
   * The role id from the LMS to match againsts.
   * 
   * @param string $ou
   * The OU to sync with.
   * 
   */
  public function enrollUser($group_id, $username, $role_id, $ou)
  {

    // Get Group
    $group = Group::load($group_id);
    $group_name = $group->label();

    if (!$group) {
      // Check if Group exists
      $this->logger->error("Failed to load group using gid @gid", ['@gid' => $group_id]);
    } else {
      $user_obj = user_load_by_name($username);

      if ($user_obj) {
        // If User exists in Drupal, process their Group membership.

        /* Check if the user is already a Group Member, if so, continue with the next account */
        $membership = $group->getMember($user_obj);

        if ($membership) {
          /**
           * ToDo:
           * 1. Check Group Role against LMS Role.
           * 2. Sync to LMS Role if needed.
           */
          $this->logger->info("User @username already a member of @group_name", [
            '@username' => $username,
            '@group_name' => $group_name
          ]);


          /////////
          ///////////////////////
          ////////////////////////
          /////////////////////
          ////////////////////// start back here

          $roles = $membership->getRoles();
          foreach ($roles as $role) {
            $this->logger->debug('<pre><code>' . print_r($role->label(), true) . '</code></pre>');
          }

          // pass LMS role snd Group role to syncRole()
          $this->syncRole($username, $role_id, $roles);


        } else {
          switch ($role_id) {
            // CREATOR
            case 110: // ROLE_ID_COURSE_MANAGER
            case 111: // ROLE_ID_CEL_COURSE_EDITOR
              $group->addMember($user_obj, ['group_roles' => ['course_synced-creator']]);
              break;

            // EDITOR
            case 112: // ROLE_ID_TA_LEVEL_4
            case 113: // ROLE_ID_STAFF
              $group->addMember($user_obj, ['group_roles' => ['course_synced-content_editor']]);
              break;

            // STUDENT
            case 107: //ROLE_ID_STUDENT
              $group->addMember($user_obj);
              break;

            // VIEW
            case 102: // ROLE_ID_ADMINISTRATOR
            case 106: // ROLE_ID_INSTRUCTOR
            case 114: // ROLE_ID_TA_LEVEL_1
            case 126: // ROLE_ID_TA_LEVEL_15
            case 117: // ROLE_ID_TA_LEVEL_2
            case 115: // ROLE_ID_TA_LEVEL_3
            case 124: // ROLE_ID_FUTURE_STUDENT
              $group->addMember($user_obj, ['group_roles' => ['course_synced-member']]);
              break;

            default:
              $this->logger->error("Unknown Role ID @roleid for @user", [
                '@roleid' => $role_id,
                '@user' => $username
              ]);
              break;
          } //end switch

          $group_relationship = $group->getMember($user_obj)->getGroupRelationship();
          $group_relationship->field_course_ou->value = $ou;
          $group_relationship->save();



          // Logs Group Activity
          $group_log_event = GroupLog::create(
            array(
              'name' => $group_name . "-" . $group_id . "-" . $user_obj->getAccountName(),
              'group_name' => $group_name,
              'group_ou' => $ou,
              'username' => $user_obj->getAccountName(),
              'enroll_status' => 1,
            )
          );
          $group_log_event->save();

          $this->logger->notice("Added @username to @groupname", ['@username' => $username, '@groupname' => $group_name]);

        } // end if membership

      } else {
        // if User does not exist in Drupal, create the user account first.

        /**
         * ToDo:
         * If Drupal User account doesn't exist, create account first then enroll them in Group.
         */
      }
    }
  }

  /**
   * Unenroll members from a Group if they are no longer in the LMS group.
   *
   * @param int $group_id
   *    The id for the group entity.
   * @param array $roster
   *    An array of LMS user IDs who should remain in the group.
   * @param string $ou
   *    The LEARN OU value.
   */
  public function unEnrollUser($group_id, array $roster, $ou)
  {

    // Get Group
    $group = Group::load($group_id);
    $group_name = $group->label();

    // Get all current group members.
    $current_members = $group->getMembers();
    $members_of_roster = [];

    foreach ($roster as $grouping) {
      foreach ($grouping as $member) {
        $members_of_roster[] = [
          'user_id' => $member->user_id,
          'username' => $member->username,
        ];
      }
    }

    // the column to search for
    $search_column = array_column($members_of_roster, 'username');

    // iterate through each group member to find user who
    // is not matching the roster from the OU.
    foreach ($current_members as $member) {
      $user = $member->getUser();
      $username = $user->getAccountName();
      $group_member_relationship = $group->getMember($user)->getGroupRelationship();
      $group_member_ou_field = $group_member_relationship->field_course_ou->value;

      if ($group_member_ou_field === $ou) {
        if (!in_array($username, $search_column)) {

          // Remove user as member from group
          $group->removeMember($user);

          // Log to Drupal Logs
          \Drupal::logger('group_lms_user_sync')->info('UnEnroll Action: Removing @user (OU: @ou) from  @groupname (@groupid).', [
            '@user' => $username,
            '@ou' => $ou,
            '@groupname' => $group_name,
            '@groupid' => $group_id,
          ]);

          // Logs Group Activity
          $group_log_event = GroupLog::create(
            array(
              'name' => $group_name . "-" . $group_id . "-" . $username,
              'group_name' => $group_name,
              'group_ou' => $ou,
              'username' => $username,
              'enroll_status' => 0,
            )
          );
          $group_log_event->save();
        }
        ;
      }
    }
  }


  public function syncRole($username, $role_id, $group_role)
  {
    // check if $group_role is correct based on $role_id.

    // if mismatched, change user's group role.
  }

  /**
   * Helper function that returns the API Endpoint URL, for debugging purposes
   * 
   * @return string
   *   API Endpoint URL
   */
  public function getAPIEndpoint(): string
  {
    return ($this->endpoint_url . '/' . $this->api_version);
  }

  /**
   * Create a new user with the paramaters given from the API.
   *
   * @param string $username_api
   *   Username from the API.
   * @param string $user_email_api
   *   User email from the API.
   * @param string $language
   *   Language of choice for the user.
   * @param string $group_api_role_id
   *   User Role from the API.
   * 
   * @return int
   *   Drupal\user\Entity\User on success or FALSE on error
   */
  private function createNewUser($username_api, $user_email_api, $language, $group_api_role_id)
  {
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
    $drupal_role_id = $this->getRoleDrupalMapping($group_api_role_id);
    $user_new->addRole($drupal_role_id);

    $res = $user_new->save();

    if (!$res) {
      $this->messenger->addError(t("Failed to register user @username", ['@username' => $username_api]));
      $this->logger->error("Failed to register user @username", ['@username' => $username_api]);
      return FALSE;
    } else {
      return $user_new;
    }
  }

  /**
   * Helper function that maps the Group User Role ID to Drupal Group ID
   *
   * @param int $group_api_role_id
   *   The Group User Role ID read from the API
   * @return drupal_id
   *   Drupal Role ID
   */
  private function getRoleDrupalMapping($group_api_role_id)
  {
    // This is a temporary mapping until we get the real
    // mappings
    // NOTE: "student" role must be created in the Drupal site for now.
    $drupal_role_id = "";

    switch ($group_api_role_id) {
      case 3:
        $drupal_role_id = "student";
        break;

      case 6:
        $drupal_role_id = "student";
        break;

      default:
        $drupal_role_id = "student";
        break;
    }

    return $drupal_role_id;
  }

}