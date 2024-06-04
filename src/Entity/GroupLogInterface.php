<?php

namespace Drupal\group_lms_user_sync;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Group Log entity.
 * @ingroup group_lms_user_sync
 */
interface GroupLogInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {
  
  /**
   * Gets the Group Log creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Group Log.
   */
  public function getCreatedTime();

  /**
   * Sets the Group Log creation timestamp.
   *
   * @param int $timestamp
   *   The Group Log creation timestamp.
   *
   * @return \Drupal\group_lms_user_sync\Entity\GroupLogInterface
   *   The called Group Log entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Group OU ID recorded on this Group Log entity.
   *
   * @return string
   *   OU ID of the Group Log.
   */
  public function getGroupOU();

  /**
   * Sets the Group OU ID.
   *
   * @param string $group_ou
   *   The Group OU ID.
   *
   * @return \Drupal\group_lms_user_sync\Entity\GroupLogInterface
   *   The called Group Log entity.
   */
  public function setGroupOU($group_ou);

  /**
   * Gets the Group Name recorded on this Group Log entity.
   *
   * @return string
   *   Group Name of the Group Log.
   */
  public function getGroupName();

  /**
   * Sets the Group Name.
   *
   * @param string $group_name
   *   The Group Name.
   *
   * @return \Drupal\group_lms_user_sync\Entity\GroupLogInterface
   *   The called Group Log entity.
   */
  public function setGroupName($group_name);

  /**
   * Gets the Username recorded on this Group Log entity.
   * This will be the username who enrolled/unenrolled in/from the group name
   * referenced in this entity
   *
   * @return string
   *   Username recorded on this Group Log entity.
   */
  public function getUserName();

  /**
   * Sets the Username recorded on this Group Log entity.
   * This will be the username who enrolled/unenrolled in/from the group name
   * referenced in this entity
   *
   * @param string $username
   *   Username recorded on this Group Log entity.
   *
   * @return \Drupal\group_lms_user_sync\Entity\GroupLogInterface
   *   The called Group Log entity.
   */
  public function setUserName($username);

  /**
   * Gets the Group User Enroll Status
   *
   * @return bool
   *   TRUE if the user was enrolled in the Group, FALSE if the user was removed
   *   from the group.
   */
  public function getEnrollStatus();

  /**
   * Sets the Group Log creation timestamp.
   *
   * @param bool $enroll_status
   *   TRUE to record that the user was enrolled in the Group, FALSE if the
   *   user was removed from the Group.
   *
   * @return \Drupal\group_lms_user_sync\Entity\GroupLogInterface
   *   The called Group Log entity.
   */
  public function setEnrollStatus($enroll_status);

}

?>