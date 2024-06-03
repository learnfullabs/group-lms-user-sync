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

/**
 * GroupLMSUserSyncAPI.
 *
 * Class that provides methods to parse/process the data (JSON) coming from the API
 */
class GroupLMSUserSyncAPI implements ContainerInjectionInterface {

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
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger, KeyRepositoryInterface $repository) {
    $this->configFactory = $config_factory;
    $this->logger = $logger->get('group_lms_user_sync');
    $this->messenger = $messenger;
    $this->repository = $repository;

    $config = $this->configFactory->getEditable('group_lms_user_sync.settings');
    $endpoint_id = $config->get('api_endpoint_info') ?? "";

    $this->endpoint_id = $endpoint_id;
    $this->endpoint_data = $this->repository->getKey($endpoint_id)->getKeyValue();
  }

    /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
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
   * @param bool $print_debug_messenger_info
   *   TRUE for printing extra $this->messenger->addStatus() calls for each action
   *   FALSE otherwise
   *
   * @return int
   *   TRUE on success or FALSE on error
   */
  public function syncUsersToGroups($print_debug_messenger_info = FALSE): int {
    if (isset($this->endpoint_id) && !empty($this->endpoint_id)) {    
      if (isset($this->endpoint_data) && !empty($this->endpoint_data)) {
        // Create an httpClient Object that will be used for all the requests.
        $client = \Drupal::httpClient();

        $endpoint_data = json_decode($this->endpoint_data);
        $this->endpoint_url = $endpoint_data->url;
        $auth = 'Basic '. base64_encode($endpoint_data->username . ':' . $endpoint_data->password);

        /* Get a list of all the Group API IDs stored in the Drupal groups */
        $group_ids = $this->getAPIGroupIds();

        foreach ($group_ids as $group_id) {
          try {
            $request = $client->get($this->endpoint_url . '/' . $this->api_version . '/' . $group_id . '/classlist/paged', [
              'http_errors' => TRUE,
              'query' => [
                '_format' => 'json'
              ],
              'headers' => [
                'Authorization' => $auth,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
             ],
            ]);
          
            if (!empty($request)) {
              if ($request->getBody()) {
                $classroom = json_decode($request->getBody());

                if (is_array($classroom)) {
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
                    $language = "en";
    
                    if ($user_obj) {
                      /* If it exists, enroll the user into the course identified by OrgDefinedId (OU field from the Group field) */
                      $gids = \Drupal::entityQuery('group')
                      ->condition('field_course_ou', $group_id_api)
                      ->accessCheck(FALSE)
                      ->execute();
    
                      if (count($gids) > 0) {
                        foreach ($gids as $gid) {
                          $group = Group::load($gid);
    
                          if (!$group) {
                            $this->logger('group_lms_user_sync')->error("Failed to load group identified by Group API ID @groupname", ['@groupname' => $group_id_api ]);
                            continue;
                          }
    
                          $group->addMember($user_obj);
                          $group_relationship = $group->getMember($user_obj)->getGroupRelationship();
                          $group_relationship->field_course_ou->value = $group_id_api;
                          $group_relationship->save();
    
                          $count_updated_groups[$user_id_api] = $group->id();
                          $group_name = $group->label();

                          if ($print_debug_messenger_info) {
                            $this->messenger->addStatus(t("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]));
                          }

                          $this->logger->notice("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]);
                        }
                      } else {
                        if ($print_debug_messenger_info) {
                          $this->messenger-addWarning(t("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]));
                        }

                        $this->logger->notice("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]);
                      }
                    } else {
                      try {
                        $user_new = $this->createNewUser($username_api, $user_email_api, $language, $group_role_api);
    
                        $gids = \Drupal::entityQuery('group')
                        ->condition('field_course_ou', $group_id_api)
                        ->accessCheck(FALSE)
                        ->execute();
    
                        // Let's add the newly created user in the group
                        if (count($gids) > 0) {
                          foreach ($gids as $gid) {
                            $group = Group::load($gid);
    
                            if (!$group) {
                              $this->logger->error("Failed to load group identified by Group API ID @groupname", ['@groupname' => $group_id_api ]);
                              continue;
                            }
    
                            $group->addMember($user_new);
                            $group_relationship = $group->getMember($user_new)->getGroupRelationship();
                            $group_relationship->field_course_ou->value = $group_id_api;
                            $group_relationship->save();
    
                            $count_updated_groups[$user_id_api] = $group->id();
                            $group_name = $group->label();

                            if ($print_debug_messenger_info) {
                              $this->messenger->addStatus(t("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]));
                            }

                            $this->logger->notice("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]);
                          }
                        } else {
                          if ($print_debug_messenger_info) {
                            $this->messenger-addWarning(t("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]));
                          }
                          
                          $this->logger->notice("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]);
                        }
    
                      } catch (\Exception $e) {
                        if ($print_debug_messenger_info) {
                          $this->messenger-addError(t("Error when creating new user: @username", ['@username' => $username_api]));
                        }
                        
                        $this->logger->error("Error when creating new user: @username", ['@username' => $username_api]);
                        watchdog_exception('group_lms_user_sync', $e);
                      }
    
                    }
                  }
                }
              }
            }
          } catch (\Exception $e) {
            watchdog_exception('group_lms_user_sync', $e);
            return FALSE;
          }
        }
      } else {
        // Endpoint URL was not set or is empty
        $this->logger->warning("Failed to set Endpoint URL");
        return FALSE;
      }
    } else {
      // Endpoint ID was not set or is empty
      $this->logger->warning("Failed to set Endpoint URL");
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sync users/class groups from JSON Field to Drupal Groups.
   * 
   * @param string $jsonContent
   *   The JSON Snippet that will be processed.
   *
   * @return int
   *   TRUE on success or FALSE on error
   */
  public function syncFromTextField($jsonContent): int {
    $classroom = json_decode($jsonContent);

    if (!$classroom) {
      $this->messenger->addError(t("Error when trying to decode the JSON data, please check."));
      return FALSE;
    }

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

        if (count($gids) > 0) {
          foreach ($gids as $gid) {
            $group = Group::load($gid);

            if (!$group) {
              $this->logger('group_lms_user_sync')->error("Failed to load group identified by Group API ID @groupname", ['@groupname' => $group_id_api ]);
              continue;
            }

            $group->addMember($user_obj);
            $group_relationship = $group->getMember($user_obj)->getGroupRelationship();
            $group_relationship->field_course_ou->value = $group_id_api;
            $group_relationship->save();

            $count_updated_groups[$user_id_api] = $group->id();
            $group_name = $group->label();

            $this->messenger->addStatus(t("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]));
            $this->logger->notice("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]);
          }
        } else {
          $this->messenger->addWarning(t("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]));
          $this->logger->notice("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]);
        }
      } else {
        try {
          $user_new = $this->createNewUser($username_api, $user_email_api, $language, $group_role_api);

          $gids = \Drupal::entityQuery('group')
          ->condition('field_course_ou', $group_id_api)
          ->accessCheck(FALSE)
          ->execute();

          // Let's add the newly created user in the group
          if (count($gids) > 0) {
            foreach ($gids as $gid) {
              $group = Group::load($gid);

              if (!$group) {
                $this->messenger->addError(t("Failed to load group identified by Group API ID @groupname", ['@groupname' => $group_id_api ]));
                $this->logger->error("Failed to load group identified by Group API ID @groupname", ['@groupname' => $group_id_api ]);
                continue;
              }

              $group->addMember($user_new);
              $group_relationship = $group->getMember($user_new)->getGroupRelationship();
              $group_relationship->field_course_ou->value = $group_id_api;
              $group_relationship->save();

              $count_updated_groups[$user_id_api] = $group->id();
              $group_name = $group->label();

              $this->messenger->addStatus(t("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]));
              $this->logger->notice("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]);
            }
          } else {
            $this->messenger->addWarning(t("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]));
            $this->logger->notice("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]);
          }

        } catch (\Exception $e) {
          $this->messenger-addError(t("Error when creating new user: @username", ['@username' => $username_api]));
          $this->logger->error("Error when creating new user: @username", ['@username' => $username_api]);
          watchdog_exception('group_lms_user_sync', $e);
        }

      }
    }
    
    return TRUE;
  }

  /**
   * Return a list of all the Group API IDs from the Group field field_course_ou.
   * 
   * @return array
   *   Array of Group API IDs or an empty array if empty
   */
  private function getAPIGroupIds(): array {
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
   * Helper function that returns the API Endpoint URL, for debugging purposes
   * 
   * @return string
   *   API Endpoint URL
   */
  public function getAPIEndpoint() {
    return ($this->endpoint_url . '/' . $this->api_version);
  }

  /**
   * Add an user to a set of Drupal Groups.
   *
   * @param Drupal\user\Entity\User $user
   *   User object entity.
   * @param array $groups
   *   Array of Drupal Group IDs (NOT Group OU IDs) where the user will be added
   * 
   * @return int
   *   TRUE on success or FALSE on error
   */
  private function addUserToGroups(User $user, $groups) {
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
  private function createNewUser($username_api, $user_email_api, $language, $group_api_role_id) {
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
      $this->messenger->addError(t("Failed to register user @username", ['@username' => $username_api ]));
      $this->logger->error("Failed to register user @username", ['@username' => $username_api ]);
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
  private function getRoleDrupalMapping($group_api_role_id) {
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