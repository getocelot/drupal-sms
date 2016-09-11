<?php

/**
 * @file
 * Contains \Drupal\sms\Annotation\SmsGateway
 */

namespace Drupal\sms\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines SmsGateway Annotation object.
 *
 * @Annotation
 */
class SmsGateway extends Plugin {

  /**
   * The machine name of the sms gateway.
   *
   * @var string
   */
  protected $id;

  /**
   * Translated user-readable label.
   * @var string
   */
  protected $label;

  /**
   * Maximum number of recipients per outgoing message.
   *
   * Use -1 for no limit.
   *
   * @var int
   */
  protected $outgoing_message_max_recipients;

  /**
   * Whether the gateway is capable of delaying messages until a date.
   *
   * Schedule aware gateways must extract sending time from all message
   * getSendTime() method. Keep in mind this method is only available if the
   * message is a SMS message entity. See the schedule aware gateway
   * implementation in the test modules for an example.
   *
   * @var boolean
   */
  protected $schedule_aware;

  /**
   * Whether the gateway supports queries of current credit balance.
   *
   * @see \Drupal\sms\Entity\SmsGatewayInterface::supportsCreditBalanceQuery()
   *
   * @var boolean
   */
  protected $credit_balance_available;

}
