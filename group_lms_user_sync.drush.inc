<?php

/**
 * @file
 * The group_lms_user_sync drush commands.
 */

use Drupal\group_lms_user_sync\Drush\DrushCommands;

/**
 * Implements hook_drush_command().
 */
function group_lms_user_sync_drush_command() {
  $items['group-lms-sync-users-groups'] = [
    'description' => 'Sync users/class groups from the LMI endpoint.',
    'aliases' => [
      'gl:user-sync'
    ],
  ];

  return $items;
}

/**
 * Check the donations paid with a wire transfer to see if the payment was collected.
 */
function drush_group_lms_user_sync_group_lms_sync_users_groups() {
  $drush = new DrushCommands();
  $drush->syncUsersGroups();
}