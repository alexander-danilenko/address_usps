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

    // IMPORTANT! Keep items ordering as by some unknown reason XML elements
    // ordering is important for USPS items and may lead to false errors.
    $address_object
      ->setApt($address['address_line2'])
      ->setAddress($address['address_line1'])
      ->setCity($address['locality'])
      ->setState($address['administrative_area'])
      // Add empty zip codes as they are required to be in request even empty.
      ->setZip5('')
      ->setZip4('');

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
    $new_address = array_merge($address, [
      'locality' => $verified_address['City'],
      'administrative_area' => $verified_address['State'],
      // USPS merges address lines to single line.
      'address_line1' => $verified_address['Address2'] ?? NULL,
      // Leave 2nd address line blank.
      'address_line2' => '',
      'postal_code' => implode('-', array_filter([
        $verified_address['Zip5'] ?? NULL,
        $verified_address['Zip4'] ?? NULL,
      ])),
    ]);

    return $new_address;
  }

}
