<?php

namespace Drupal\address_usps\Element;

use Drupal\address\Element\Address;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an address form element.
 *
 * @FormElement("address_usps_simple")
 *
 * @see \Drupal\address\Element\Address
 */
class AddressUspsSimple extends Address {

  /**
   * {@inheritDoc}
   */
  protected static function addressElements(array $element, array $value) {
    $element = parent::addressElements($element, $value);

    // USPS works only with US addresses. Do nothing for other countries and
    // inherit address module behavior.
    if ($value['country_code'] !== 'US') {
      return $element;
    }

    $element['usps_convert'] = [
      '#type' => 'button',
      '#value' => t('Convert to USPS format'),
      '#ajax' => [
        'callback' => [get_called_class(), 'convertToUspsAddress'],
        'wrapper' => $element['#wrapper_id'],
      ],
    ];

    return $element;
  }

  /**
   * Uses USPS API and replaces element value with retrieved address.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Element markup.
   */
  public static function convertToUspsAddress(array $form, FormStateInterface $form_state) {
    $element = static::ajaxRefresh($form, $form_state);

    // Show status messages in case of validation fail.
    $element['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -200,
    ];

    // Get address field errors.
    $form_errors = $form_state->getErrors();
    $address_errors_key = implode('][', $element['#parents']);
    $address_errors = array_filter($form_errors, function ($key) use ($address_errors_key) {
      return strpos($key, $address_errors_key) === 0;
    }, ARRAY_FILTER_USE_KEY);

    // If any address errors - just return current element and do not convert.
    if ($address_errors) {
      return $element;
    }

    $user_input = &$form_state->getUserInput();

    $current_address_value = NestedArray::getValue($user_input, $element['#parents']);
    $new_address_value = \Drupal::service('address_usps.convert')->convert($current_address_value);
    NestedArray::setValue($user_input, $element['#parents'], $new_address_value);

    $element['#value'] = $new_address_value;

    return $element;
  }

}
