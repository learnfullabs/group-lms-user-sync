<?php

namespace Drupal\group_lms_rest_endpoint\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableResponseInterface;

/**
 * Provides a Class Get Membership Endpoint
 * 
 * Returns an user object if the user identified by username
 * belongs to the group identified by orgUnitId, or returns an error message/empty array
 * otherwise.
 *
 * @RestResource(
 *   id = "group_lms_rest_get_membership",
 *   label = @Translation("Group LMS Rest Get Membership"),
 *   uri_paths = {
 *     "canonical" = "/api/le/{version}/{orgUnitId}/{username}"
 *   }
 * )
 */
class GroupLMSRestGetMembership extends ResourceBase {
  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->getParameter('serializer.formats'),
        $container->get('logger.factory')->get('custom_rest'),
        $container->get('current_user')
    );
  }

  /**
   * Responds to entity GET requests.
   * 
   * @return \Drupal\rest\ResourceResponse
   */
  public function get($version, $orgUnitId, $username) {
    $path_assets = DRUPAL_ROOT . "/" . \Drupal::service('extension.list.module')->getPath('group_lms_rest_endpoint');
    $jsonContents = [];

    if (isset($orgUnitId) && !empty($orgUnitId)) {
      if (file_exists($path_assets . "/assets/groups/" . $orgUnitId . ".json")) {
        if (isset($username) && !empty($username)) {
          $course_list = json_decode(file_get_contents($path_assets . "/assets/groups/" . $orgUnitId . ".json"), true);
          $user_in_course = FALSE;

          foreach ($course_list as $student) {
            // Student is in the course, stop the loop
            if ($student["Username"] == $username) {
              $user_in_course = TRUE;
              break;
            }
          }

          if ($user_in_course) {
            $jsonContents = $student;
          } else {
            $jsonContents = [];
          }
        }      
      } else {
        $jsonContents = "Group ID does not exist";
      }
    }

    $response = $jsonContents;

    // TODO: Return error code when required
    return new ResourceResponse($response);
  }
}