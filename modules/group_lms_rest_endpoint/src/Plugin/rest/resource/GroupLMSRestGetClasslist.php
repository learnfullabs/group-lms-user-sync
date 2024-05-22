<?php

namespace Drupal\group_lms_rest_endpoint\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableResponseInterface;

/**
 * Provides a Class List Endpoint
 *
 * @RestResource(
 *   id = "group_lms_rest_get_classlist",
 *   label = @Translation("Group LMS Rest Get Class list"),
 *   uri_paths = {
 *     "canonical" = "/api/le/{version}/{orgUnitId}/classlist/paged"
 *   }
 * )
 */
class GroupLMSRestGetClasslist extends ResourceBase {
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
   * @return \Drupal\rest\ResourceResponse
   */
  public function get($version = "v1", $orgUnitId = 100101) {
    $path = DRUPAL_ROOT . "/" . \Drupal::service('extension.list.module')->getPath('group_lms_rest_endpoint');

    if (isset($orgUnitId) && !empty($orgUnitId)) {
      if (file_exists($path . "/assets/groups/" . $orgUnitId . ".json")) {
        $jsonContents = json_decode(file_get_contents($path . "/assets/groups/" . $orgUnitId . ".json"), true);
      } else {
        $jsonContents = "Group ID does not exist";
      }
    }

    $response = $jsonContents;

    return new ResourceResponse($response);
  }
}