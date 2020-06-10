<?php

namespace Drupal\address_usps;

use Drupal\Core\Config\ConfigFactoryInterface;
use USPS\Address;
use USPS\AddressVerify;

/**
 * Address USPS Verify service.
 */
class Convert {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Third party USPS address verifier.
   *
   * @var \USPS\AddressVerify
   */
  protected $verifier;

  const CONVERT_MAPPING = [
    'organization' => 'FirmName',
    'address_line1' => 'Address1',
    'address_line2' => 'Address2',
    'locality' => 'City',
    'administrative_area' => 'State',
    // Zip code processed separately based on its length.
  ];

  /**
   * Constructs a Verify object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   * @TODO: Add logger.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;

    $settings = $this->configFactory->get('address_usps.settings');
    $api_username = $settings->get('api_username');

    // @TODO: Inherit Drupal's web client cURL options.
    $this->verifier = new AddressVerify($api_username);
  }

  /**
   * Verifies address in USPS service and returns verified value.
   *
   * If no address was found or any error occurred - just returns the original
   * address.
   *
   * @param array $address
   *   Address information array in field value format.
   */
  public function convert(array $address): array {
    // Create address object compatible with USPS library.
    $address_object = new Address();

    // FIll address object.
    foreach (static::CONVERT_MAPPING as $field_property => $api_parameter) {
      $address_object->setField($api_parameter, $address[$field_property]);
    }

    // Add empty zip codes as they are required to be in request even empty.
    $address_object->setField('Zip5', '');
    $address_object->setField('Zip4', '');

    // Fill zip code according to its length.
    $zip_length = strlen($address['postal_code']);
    if (in_array($zip_length, [4, 5])) {
      $address_object->setField('Zip' . $zip_length, $address['postal_code']);
    }

    // Run validation.
    $this->verifier->addAddress($address_object);
    $this->verifier->verify();

    $verified_address = $this->verifier->getArrayResponse()['AddressValidateResponse']['Address'] ?? [];

    // Return original address in case of errors or missing new address.
    if (!$verified_address || $this->verifier->isError()) {
      // @TODO: Add logger.
      return $address;
    }

    // Collect new address data in field value format.
    $new_address = $address;
    // Fill back data from response.
    foreach (self::CONVERT_MAPPING as $field_property => $api_parameter) {
      $new_address[$field_property] = $verified_address[$api_parameter] ?? $new_address[$field_property];
    }
    $new_address['postal_code'] = implode('-', array_filter([
      $verified_address['Zip5'] ?? NULL,
      $verified_address['Zip4'] ?? NULL,
    ]));

    return $new_address;
  }

}
