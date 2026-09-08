<?php

namespace Drupal\group_lms_user_sync\Drush;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Class GroupLMSReportCommands.
 *
 * Reporting commands for auditing the results of the LMS sync.
 *
 * @package Drupal\group_lms_user_sync\Drush
 */
class GroupLMSReportCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs the report commands.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database, DateFormatterInterface $date_formatter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Report users with the site-wide Student role who hold a non-Student group role.
   *
   * Only individually assigned (individual scope) group roles are considered.
   * Insider/outsider roles are automatic - every member of a synced course
   * carries the insider "Student" role, so counting those would match nearly
   * every membership on the site. Roles labelled "Student" are excluded, which
   * drops the individually assigned sandbox_course-student role.
   *
   * One row is emitted per user/group/role combination.
   *
   * @command gl:student-group-roles
   *
   * @aliases gl-sgr
   *
   * @option file
   *   Write the CSV to this path instead of stdout.
   * @option group-types
   *   Comma separated group type IDs to restrict the report to. Defaults to
   *   all group types.
   * @option drupal-role
   *   The site-wide user role to report on. Defaults to "student".
   * @option base-url
   *   Base URL used to build the group_url column. Defaults to the production
   *   site, https://hive.uwaterloo.ca.
   *
   * @usage drush gl-sgr
   *   Print the CSV to stdout.
   * @usage drush gl-sgr > /tmp/student-group-roles.csv
   *   Redirect the CSV to a file.
   * @usage drush gl-sgr --file=/tmp/student-group-roles.csv
   *   Write the CSV to a file, printing a summary instead.
   * @usage drush gl-sgr --group-types=course_synced,sandbox_course
   *   Restrict the report to course group types.
   */
  public function studentGroupRoles(array $options = [
    'file' => NULL,
    'group-types' => NULL,
    'drupal-role' => 'student',
    'base-url' => 'https://hive.uwaterloo.ca',
  ]) {
    $drupal_role_id = $options['drupal-role'];
    $base_url = rtrim($options['base-url'], '/');

    $drupal_role = $this->entityTypeManager->getStorage('user_role')->load($drupal_role_id);
    if (!$drupal_role) {
      throw new \Exception(sprintf('The user role "%s" does not exist.', $drupal_role_id));
    }

    $group_types = [];
    if (!empty($options['group-types'])) {
      $group_types = array_filter(array_map('trim', explode(',', $options['group-types'])));
    }

    // Individually assigned group roles that are not a "Student" role. Insider
    // and outsider roles are never stored on the membership, so restricting to
    // the individual scope is what makes "has a role beyond plain member"
    // meaningful.
    $reportable_roles = [];
    foreach ($this->entityTypeManager->getStorage('group_role')->loadMultiple() as $group_role_id => $group_role) {
      if ($group_role->getScope() !== 'individual') {
        continue;
      }
      if (strcasecmp($group_role->label(), 'Student') === 0) {
        continue;
      }
      if ($group_types && !in_array($group_role->getGroupTypeId(), $group_types, TRUE)) {
        continue;
      }
      $reportable_roles[$group_role_id] = $group_role->label();
    }

    if (!$reportable_roles) {
      throw new \Exception('No individually assigned non-Student group roles were found for the requested group types.');
    }

    $query = $this->database->select('group_relationship_field_data', 'gr');
    $query->join('group_relationship__group_roles', 'grr', 'grr.entity_id = gr.id AND grr.deleted = 0');
    $query->join('groups_field_data', 'g', 'g.id = gr.gid AND g.default_langcode = 1');
    $query->join('users_field_data', 'u', 'u.uid = gr.entity_id AND u.default_langcode = 1');
    $query->join('user__roles', 'ur', 'ur.entity_id = gr.entity_id AND ur.roles_target_id = :drupal_role', [
      ':drupal_role' => $drupal_role_id,
    ]);
    // field_course_ou only exists on the course_synced-group_membership
    // bundle, so this has to be a left join - sandbox and open course
    // memberships have no OU and report an empty value.
    $query->leftJoin('group_relationship__field_course_ou', 'ou', 'ou.entity_id = gr.id AND ou.deleted = 0 AND ou.delta = 0');

    $query->condition('gr.plugin_id', 'group_membership');
    $query->condition('gr.default_langcode', 1);
    $query->condition('grr.group_roles_target_id', array_keys($reportable_roles), 'IN');

    if ($group_types) {
      $query->condition('g.type', $group_types, 'IN');
    }

    $query->addField('u', 'uid', 'user_id');
    $query->addField('u', 'name', 'user_name');
    $query->addField('u', 'mail', 'user_email');
    $query->addField('grr', 'group_roles_target_id', 'group_role_id');
    $query->addField('g', 'label', 'group_name');
    $query->addField('g', 'id', 'group_id');
    $query->addField('ou', 'field_course_ou_value', 'course_ou');
    $query->addField('gr', 'created', 'relationship_created');

    $query->orderBy('u.name');
    $query->orderBy('g.label');
    $query->orderBy('grr.group_roles_target_id');

    $rows = $query->execute()->fetchAll();

    $handle = $options['file']
      ? fopen($options['file'], 'w')
      : fopen('php://output', 'w');

    if (!$handle) {
      throw new \Exception(sprintf('Could not open "%s" for writing.', $options['file']));
    }

    fputcsv($handle, [
      'user_id',
      'user_name',
      'user_email',
      'drupal_role',
      'group_role',
      'group_name',
      'group_id',
      'group_url',
      'course_ou',
      'relationship_created',
    ]);

    foreach ($rows as $row) {
      fputcsv($handle, [
        $row->user_id,
        $row->user_name,
        $row->user_email,
        $drupal_role->label(),
        $reportable_roles[$row->group_role_id],
        $row->group_name,
        $row->group_id,
        $base_url . '/group/' . $row->group_id,
        $row->course_ou,
        $row->relationship_created
          ? $this->dateFormatter->format($row->relationship_created, 'custom', 'Y-m-d H:i:s')
          : '',
      ]);
    }

    fclose($handle);

    if ($options['file']) {
      $this->io()->success(sprintf('Wrote %d row(s) to %s', count($rows), $options['file']));
    }
  }

}
