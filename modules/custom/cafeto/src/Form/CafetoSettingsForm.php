<?php

namespace Drupal\cafeto\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CafetoSettingsForm extends ConfigFormBase {

  const SETTINGS = 'cafeto.settings';

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return "cafeto_config_form";
  }

  /**
   * @inheritdoc
   */
  protected function getEditableConfigNames() {
    return [static::SETTINGS];
  }

  /**
   * @inheritdoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $form['tmdb_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The movie database API key'),
      '#default_value' => $config->get('tmdb_api_key'),
      '#description' => $this->t('Insert your <i><a href="https://www.themoviedb.org" target="_blank">The Movie Database API (v3)</a></i> key.'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * @inheritdoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('tmdb_api_key');
    $api_key = trim($api_key);
    $form_state->setValue('tmdb_api_key', $api_key);
    parent::validateForm($form, $form_state);
  }

  /**
   * @inheritdoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('tmdb_api_key', $form_state->getValue('tmdb_api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
