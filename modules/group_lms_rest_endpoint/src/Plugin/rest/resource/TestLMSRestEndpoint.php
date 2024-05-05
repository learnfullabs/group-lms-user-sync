<?php

namespace Drupal\group_lms_rest_endpoint\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a Test LMS Rest Endpoint
 *
 * @RestResource(
 *   id = "test_lms_rest_endpoint",
 *   label = @Translation("Test LMS Rest Endpoint"),
 *   uri_paths = {
 *     "canonical" = "/api/le/{version}/{orgUnitId}/classlist/paged"
 *   }
 * )
 */
class TestLMSRestEndpoint extends ResourceBase {

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get($version = "v1", $orgUnitId = 4) {
    $response = ['message' => 'Hello, this is a rest service'];
    return new ResourceResponse($response);
  }
}