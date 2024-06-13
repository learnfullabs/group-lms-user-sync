<?php

namespace Drupal\group_lms_user_sync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Provides a list controller for group_log entity.
 *
 * @ingroup group_lms_user_sync
 */
class GroupLogListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('Group LMS User Sync implements a GroupLog model. These GroupLog entities are fieldable entities. You can manage the fields on the <a href="@adminlink">GroupLog admin page</a>.', array(
        '@adminlink' => Url::fromRoute('group_lms_user_sync.group_log_settings', [], ['absolute' => 'true'])->toString(),
      )),
    ];

    $build += parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the GroupLog list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['id'] = $this->t('Group Log ID');
    $header['group_name'] = $this->t('Name');
    $header['group_ou'] = $this->t('Group OU');
    $header['username'] = $this->t('Username');
    $header['enroll_status'] = $this->t('Username');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\group_lms_user_sync\Entity\GroupLog */
    $row['id'] = $entity->id();
    $row['group_name'] = $entity->group_name->value;
    $row['group_ou'] = $entity->group_ou->value;
    $row['username'] = $entity->username->value;
    $row['enroll_status'] = $entity->enroll_status->value;

    return $row + parent::buildRow($entity);
  }

}
?>