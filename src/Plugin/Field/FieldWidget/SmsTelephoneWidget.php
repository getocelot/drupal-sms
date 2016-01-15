<?php

/**
 * @file
 * Contains \Drupal\sms\Plugin\Field\FieldWidget\SmsTelephoneWidget.
 */

namespace Drupal\sms\Plugin\Field\FieldWidget;

use Drupal\telephone\Plugin\Field\FieldWidget\TelephoneDefaultWidget;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'sms_telephone' widget.
 *
 * @FieldWidget(
 *   id = "sms_telephone",
 *   label = @Translation("SMS Framework Telephone"),
 *   field_types = {
 *     "telephone"
 *   }
 * )
 */
class SmsTelephoneWidget extends TelephoneDefaultWidget {

  use UrlGeneratorTrait;

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var \Drupal\sms\PhoneNumberProviderInterface $phone_number_provider */
    $phone_number_provider = \Drupal::service('sms.phone_number');
    /** @var \Drupal\Core\Datetime\DateFormatter $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');

    $t_args['@url'] = $this->url('sms.phone.verify');
    $config = $phone_number_provider->getPhoneNumberSettingsForEntity($items->getEntity());
    $lifetime = $config->get('duration_verification_code_expire') ?: 0;

    if (isset($items[$delta]->value)) {
      $phone_verification = $phone_number_provider
        ->getPhoneVerification($items->getEntity(), $items[$delta]->value);

      if ($phone_verification) {
        if ($phone_verification->getStatus()) {
          $element['value']['#description'] = $this->t('This phone number is verified. <strong>Warning:</strong> Modifying this phone number will remove verification.');
        }
        else {
          $element['value']['#disabled'] = TRUE;
          $expiration_date = $phone_verification->getCreatedTime() + $lifetime;

          if (time() < $expiration_date) {
            $t_args['@time'] = $date_formatter->formatTimeDiffUntil($expiration_date, [
              'granularity' => 2,
            ]);
            $element['value']['#description'] = $this->t('A verification code has been sent to this phone number. Go to the <a href="@url">verification form</a> and enter the code. The code will expire if it is not verified in @time.', $t_args);
          }
          else {
            // This message displays if we are waiting for cron to delete
            // expired verification codes.
            $element['value']['#description'] = $this->t('Verification code expired. Try again later.');
          }
        }
      }
      else {
        // This message will display if there is a field value, but the
        // verification expired.
        $t_args['@time'] = $date_formatter->formatInterval($lifetime, 2);
        $element['value']['#description'] = $this->t('Save this form to send a new verification code as an SMS message, you must enter the code into the <a href="@url">verification form</a> within @time.', $t_args);
      }
    }
    else {
      $t_args['@time'] = $date_formatter->formatInterval($lifetime, 2);
      $element['value']['#description'] = $this->t('Enter a phone number. A verification code will be sent as an SMS message, you must enter the code into the <a href="@url">verification form</a> within @time.', $t_args);
    }

    return $element;
  }

}
