<?php

namespace Drupal\group_lms_user_sync\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the group_lms_user_sync entity edit forms.
 *
 * @ingroup group_lms_user_sync
 */
class GroupLogForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\group_lms_user_sync\Entity\GroupLog */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    $form['langcode'] = array(
      '#title' => $this->t('Language'),
      '#type' => 'language_select',
      '#default_value' => $entity->getUntranslated()->language()->getId(),
      '#languages' => LanguageInterface::STATE_ALL,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    $entity = $this->entity;
    if ($status == SAVED_UPDATED) {
      $this->messenger()
        ->addMessage($this->t('The Group Log %feed has been updated.', ['%feed' => $entity->toLink()->toString()]));
    } else {
      $this->messenger()
        ->addMessage($this->t('The Group Log %feed has been added.', ['%feed' => $entity->toLink()->toString()]));
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }
}

?>