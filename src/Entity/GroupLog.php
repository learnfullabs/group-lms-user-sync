<?php

namespace Drupal\group_lms_user_sync\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\user\UserInterface;

/**
 * Defines the GroupLog entity.
 *
 * @ingroup group_lms_user_sync
 *
 * This is the main definition of the entity type. From it, an entityType is
 * derived. The most important properties in this example are listed below.
 *
 * id: The unique identifier of this entityType. It follows the pattern
 * 'moduleName_xyz' to avoid naming conflicts.
 *
 * label: Human readable name of the entity type.
 *
 * handlers: Handler classes are used for different tasks. You can use
 * standard handlers provided by D8 or build your own, most probably derived
 * from the standard class. In detail:
 *
 * - view_builder: we use the standard controller to view an instance. It is
 *   called when a route lists an '_entity_view' default for the entityType
 *   (see routing.yml for details. The view can be manipulated by using the
 *   standard drupal tools in the settings.
 *
 * - list_builder: We derive our own list builder class from the
 *   entityListBuilder to control the presentation.
 *   If there is a view available for this entity from the views module, it
 *   overrides the list builder. @todo: any view? naming convention?
 *
 * - form: We derive our own forms to add functionality like additional fields,
 *   redirects etc. These forms are called when the routing list an
 *   '_entity_form' default for the entityType. Depending on the suffix
 *   (.add/.edit/.delete) in the route, the correct form is called.
 *
 * - access: Our own accessController where we determine access rights based on
 *   permissions.
 *
 * More properties:
 *
 *  - base_table: Define the name of the table used to store the data. Make sure
 *    it is unique. The schema is automatically determined from the
 *    BaseFieldDefinitions below. The table is automatically created during
 *    installation.
 *
 *  - fieldable: Can additional fields be added to the entity via the GUI?
 *    Analog to content types.
 *
 *  - entity_keys: How to access the fields. Analog to 'nid' or 'uid'.
 *
 *  - links: Provide links to do standard tasks. The 'edit-form' and
 *    'delete-form' links are added to the list built by the
 *    entityListController. They will show up as action buttons in an additional
 *    column.
 *
 * There are many more properties to be used in an entity type definition. For
 * a complete overview, please refer to the '\Drupal\Core\Entity\EntityType'
 * class definition.
 *
 * The following construct is the actual definition of the entity type which
 * is read and cached. Don't forget to clear cache after changes.
 *
 * @ContentEntityType(
 *   id = "group_log",
 *   label = @Translation("Group Log"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\group_lms_user_sync\GroupLogListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\group_lms_user_sync\Form\GroupLogForm",
 *       "edit" = "Drupal\group_lms_user_sync\Form\GroupLogForm",
 *       "delete" = "Drupal\group_lms_user_sync\Form\GroupLogDeleteForm",
 *     },
 *     "access" = "Drupal\group_lms_user_sync\GroupLogAccessControlHandler",
 *   },
 *   base_table = "group_log",
 *   admin_permission = "administer group_log entity",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/admin/group/reports/group_log/{group_log}",
 *     "edit-form" = "/admin/group/reports/group_log/{group_log}/edit",
 *     "delete-form" = "/admin/group/reports/group_log/{group_log}/delete",
 *     "collection" = "/admin/group/reports/group_log/list",
 *   },
 *   field_ui_base_route = "group_lms_user_sync.group_log_settings",
 * )
 *
 * The 'links' above are defined by their path. For core to find the corresponding
 * route, the route name must follow the correct pattern:
 *
 * entity.<entity-name>.<link-name> (replace dashes with underscores)
 * Example: 'entity.group_log.canonical'
 *
 * See routing file above for the corresponding implementation
 *
 * The 'GroupLog' class defines methods and fields for the group_log entity.
 *
 * Being derived from the ContentEntityBase class, we can override the methods
 * we want. In our case we want to provide access to the standard fields about
 * creation and changed time stamps.
 *
 * Our interface (see GroupLogInterface) also exposes the EntityOwnerInterface.
 * This allows us to provide methods for setting and providing ownership
 * information.
 *
 * The most important part is the definitions of the field properties for this
 * entity type. These are of the same type as fields added through the GUI, but
 * they can by changed in code. In the definition we can define if the user with
 * the rights privileges can influence the presentation (view, edit) of each
 * field.
 */
class GroupLog extends ContentEntityBase implements GroupLogInterface {

  use EntityChangedTrait; // Implements methods defined by EntityChangedInterface.

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }


  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupOU() {
    return $this->get('group_ou')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setGroupOU($group_ou) {
    $this->set('group_ou', $group_ou);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupName() {
    return $this->get('group_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setGroupName($group_name) {
    $this->set('group_name', $group_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserName() {
    return $this->get('username')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserName($username) {
    $this->set('username', $username);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnrollStatus() {
    return $this->get('enroll_status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnrollStatus($enroll_status) {
    $this->set('enroll_status', $enroll_status);
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Group Log entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Group Log entity.'))
      ->setReadOnly(TRUE);

      $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Group Log Name (Entity Label)'))
      ->setDescription(t(
        'Group Log Name: Can be used to set a title to this Group Log Event.'
      ))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Group name of the entity
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.
    $fields['group_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Group Name'))
      ->setDescription(t('The Group Name logged by the Group Log Event.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Group OU ID of the Group API
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.
    $fields['group_ou'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Group OU'))
      ->setDescription(t('The Group OU logged by the Group Log Event.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -4,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // User Name of the Group Log event
    // We set display options for the view as well as the form.
    // Users with correct privileges can change the view and edit configuration.
    $fields['username'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Username Group Log Event'))
      ->setDescription(t('The Username logged by the Group Log Event.'))
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -3,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -3,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Enroll Status of the Group Log event
    // TRUE if the user was enrolled in the Group, FALSE if the user was removed
    // from the Group
    $fields['enroll_status'] = BaseFieldDefinition::create('boolean')
    ->setLabel(t('Enroll Status'))
    ->setDescription(t('A boolean indicating that user was enrolled (TRUE) or not (FALSE)'))
    ->setDefaultValue(TRUE)
    ->setSettings(['on_label' => 'Enrolled', 'off_label' => 'Unenrolled'])
    ->setDisplayOptions('view', [
      'label' => 'visible',
      'type' => 'boolean',
      'weight' => -2,
    ])
    ->setDisplayOptions('form', [
      'type' => 'boolean_checkbox',
      'weight' => -2,
    ])
    ->setDisplayConfigurable('view', TRUE)
    ->setDisplayConfigurable('form', TRUE);

    
      $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code of Group Log entity.'));
    
      $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }
}

?>