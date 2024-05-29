<?php

namespace Drupal\group_lms_user_sync;

use Drupal\user\Entity\User;
use Drupal\Core\Messenger\MessengerInterface;
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
   * Constructs a new GroupLMSUserSyncAPI object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger->get('group_lms_user_sync');
    $this->messenger = $messenger;

    $config = $this->configFactory->getEditable('group_lms_user_sync.settings');
    $endpoint_id = $config->get('api_endpoint_info') ?? "";
    $api_version = "v1";
    $endpoint_url = \Drupal::service('key.repository')->getKey($endpoint_id)->getKeyValue();
    
    $this->endpoint_id = $endpoint_id;
    $this->endpoint_url = $endpoint_url;
    $this->api_version = $api_version;
  }

    /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config'),
      $container->get('logger'),
      $container->get('messenger'),
    );
  }

  /**
   * Sync users/class groups from the LMI AP Endpoint to Drupal Groups.
   *
   * @return int
   *   TRUE on success or FALSE on error
   */
  public function syncUsersToGroups(): int {
    if (isset($this->endpoint_id) && !empty($this->endpoint_id)) {    
      if (isset($this->endpoint_url) && !empty($this->endpoint_url)) {
        // Create an httpClient Object that will be used for all the requests.
        $client = \Drupal::httpClient();

        /* Get a list of all the Group API IDs stored in the Drupal groups */
        $group_ids = $this->getAPIGroupIds();

        foreach ($group_ids as $group_id) {
          try {
            $request = $client->get($this->endpoint_url . '/' . $this->api_version . '/' . $group_id . '/classlist/paged', [
              'http_errors' => TRUE,
              'query' => [
                '_format' => 'json'
              ]
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
                          $this->logger->notice("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]);
                        }
                      } else {
                        $this->logger->notice("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]);
                      }
                    } else {
                      try {
                        $user_new = $this->createNewUser($username_api, $user_email_api, $language, "");
    
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
                            $group_relationship = $group->getMember($user_obj)->getGroupRelationship();
                            $group_relationship->field_course_ou->value = $group_id_api;
                            $group_relationship->save();
    
                            $count_updated_groups[$user_id_api] = $group->id();
                            $group_name = $group->label();
                            $this->messenger->addStatus(t("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]));
                            $this->logger->notice("Added user @username to group @groupname", ['@username' => $username_api, '@groupname' => $group_id_api]);
                          }
                        } else {
                          $this->logger->notice("There is no Drupal group with that Group API ID: @groupname", ['@groupname' => $group_id_api]);
                        }
    
                      } catch (\Exception $e) {
                        watchdog_exception('group_lms_user_sync', $e);
                      }
    
                    }
                  }
                }
              }
            }
          } catch (\Exception $e) {
            watchdog_exception('group_lms_user_sync', $e);
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
          $user_new = $this->createNewUser($username_api, $user_email_api, $language, "");

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

        } catch (\Exception $e) {
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
   * @param string $user_role
   *   Role for the user.
   * 
   * @return int
   *   Drupal\user\Entity\User on success or FALSE on error
   */
  private function createNewUser($username_api, $user_email_api, $language, $user_role = "") {
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

    $res = $user_new->save();

    if (!$res) {
      $this->messenger->addError(t("Failed to register user @username", ['@username' => $username_api ]));
      $this->logger->error("Failed to register user @username", ['@username' => $username_api ]);
      return FALSE;
    } else {
      return $user_new;
    }
  }

}