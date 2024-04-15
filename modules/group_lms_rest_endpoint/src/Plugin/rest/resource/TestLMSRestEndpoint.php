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
 *     "canonical" = "/group-lms/test_lms_rest_endpoint"
 *   }
 * )
 */
class TestLMSRestEndpoint extends ResourceBase {

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    $response = ['message' => 'Hello, this is a rest service'];
    return new ResourceResponse($response);
  }
}