<?php

namespace Drupal\address_usps\Plugin\Field\FieldWidget;

use Drupal\address\Plugin\Field\FieldWidget\AddressDefaultWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'address_usps_simple' field widget.
 *
 * @FieldWidget(
 *   id = "address_usps_simple",
 *   label = @Translation("USPS (Simple)"),
 *   description = @Translation("Converts address to USPS format."),
 *   field_types = {
 *     "address"
 *   },
 * )
 */
class SimpleWidget extends AddressDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#element_validate'][] = [$this, 'validate'];

    return $element;
  }

  /**
   * Element validation.
   *
   * If USPS API will find better address, it will replace address from field.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validate(array $element, FormStateInterface $form_state) {
    // Don't run in case of any validation error.
    if ($form_state->getErrors()) {
      return;
    }

    // USPS API is compatible only with US addresses. Don't run for other
    // countries.
    $field_value = $form_state->getValue($element['#parents'])['address'] ?? [];
    if ($field_value && $field_value['country_code'] === 'US') {
      // @TODO: Move to DI container.
      /** @var \Drupal\address_usps\Convert $converter */
      $converter = \Drupal::service('address_usps.convert');

      // Assuming that if USPS not found better address, current will be kept.
      $usps_address = $converter->convert($field_value);
      $form_state->setValue($element['#parents'], ['address' => $usps_address]);
    }
  }

}
