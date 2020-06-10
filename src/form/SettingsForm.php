<?php

namespace Drupal\address_usps\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Module settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'address_usps.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'address_usps_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('address_usps.settings');

    $form['api_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API username'),
      '#description' => $this->t('USPS Web Tools API username. It can be retrieved <a href=":url" target="_blank">here</a>', [
        ':url' => Url::fromUri('https://www.usps.com/business/web-tools-apis/web-tools-registration.htm')->toString(),
      ]),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('api_username'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('address_usps.settings')
      ->set('api_username', $form_state->getValue('api_username'))
      ->save();
  }

}
