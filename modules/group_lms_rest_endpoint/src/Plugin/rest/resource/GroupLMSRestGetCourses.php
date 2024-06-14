<?php

namespace Drupal\group_lms_rest_endpoint\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableResponseInterface;

/**
 * Provides a Get Courses Endpoint
 * 
 * Returns a list of available courses identified by the OU ID
 *
 * @RestResource(
 *   id = "group_lms_rest_get_courses",
 *   label = @Translation("Group LMS Rest Get Courses"),
 *   uri_paths = {
 *     "canonical" = "/api/le/{version}/courses/paged"
 *   }
 * )
 */
class GroupLMSRestGetCourses extends ResourceBase {
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
  public function get($version = "v1") {
    $path_assets = DRUPAL_ROOT . "/" . \Drupal::service('extension.list.module')->getPath('group_lms_rest_endpoint');
    $basenames = [];

    $assets = glob($path_assets  . "/assets/groups/*.json");

    foreach ($assets as $asset) {
      $basenames[] = basename($asset, ".json");
    }

    $response = $basenames;

    // TODO: Return error code when required
    return new ResourceResponse($response);
  }
}