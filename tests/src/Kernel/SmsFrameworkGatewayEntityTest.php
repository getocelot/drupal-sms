<?php

/**
 * @file
 * Contains \Drupal\Tests\sms\Kernel\SmsFrameworkGatewayEntity.
 */

namespace Drupal\Tests\sms\Kernel;

use Drupal\sms\Entity\SmsGateway;
use Drupal\sms\Entity\SmsMessageInterface;
use Drupal\sms\Direction;

/**
 * Tests SMS Framework gateway entity.
 *
 * @group SMS Framework
 */
class SmsFrameworkGatewayEntityTest extends SmsFrameworkKernelBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['sms', 'sms_test_gateway', 'telephone', 'dynamic_entity_reference'];

  /**
   * Tests skip queue.
   */
  public function testSkipQueue() {
    $gateway = $this->createGateway();
    $this->assertFalse($gateway->getSkipQueue(), 'Default value does not skip queue.');

    $gateway->setSkipQueue(TRUE);
    $this->assertTrue($gateway->getSkipQueue());
  }

  /**
   * Tests incoming retention setting.
   */
  public function testIncomingRetentionDuration() {
    $gateway = $this->createGateway();

    // Default value.
    $this->assertEquals(0, $gateway->getRetentionDuration(Direction::INCOMING));

    $gateway->setRetentionDuration(Direction::INCOMING, 444);
    $this->assertEquals(444, $gateway->getRetentionDuration(Direction::INCOMING));
  }

  /**
   * Tests outgoing retention setting.
   */
  public function testOutgoingRetentionDuration() {
    $gateway = $this->createGateway();

    // Default value.
    $this->assertEquals(0, $gateway->getRetentionDuration(Direction::INCOMING));

    $gateway->setRetentionDuration(Direction::OUTGOING, 999);
    $this->assertEquals(999, $gateway->getRetentionDuration(Direction::OUTGOING));
  }

  /**
   * Tests a bad retention direction.
   */
  public function testGetRetentionDurationInvalidDirection() {
    $gateway = $this->createGateway();
    $this->setExpectedException(\Exception::class);
    $gateway->getRetentionDuration(0);
  }

  /**
   * Tests 'max outgoing recipients' annotation custom value.
   */
  public function testGetMaxRecipientsOutgoingCustom() {
    $gateway = $this->createGateway([
      'plugin' => 'memory',
    ]);
    $this->assertEquals(-1, $gateway->getMaxRecipientsOutgoing());
  }

  /**
   * Tests 'max outgoing recipients' annotation default value.
   */
  public function testGetMaxRecipientsOutgoingDefault() {
    $gateway = $this->createGateway([
      'plugin' => 'capabilities_default',
    ]);
    $this->assertEquals(1, $gateway->getMaxRecipientsOutgoing());
  }

  /**
   * Tests 'schedule aware annotation' custom value.
   */
  public function testIsScheduleAwareCustom() {
    $gateway = $this->createGateway([
      'plugin' => 'memory_schedule_aware',
    ]);
    $this->assertTrue($gateway->isScheduleAware());
  }

  /**
   * Tests 'schedule aware annotation' default value.
   */
  public function testIsScheduleAwareDefault() {
    $gateway = $this->createGateway([
      'plugin' => 'capabilities_default',
    ]);
    $this->assertFalse($gateway->isScheduleAware());
  }

  /**
   * Tests 'supports credit balance' annotation custom value.
   */
  public function testSupportsCreditBalanceQueryCustom() {
    $gateway = $this->createGateway([
      'plugin' => 'memory',
    ]);
    $this->assertTrue($gateway->supportsCreditBalanceQuery());
  }

  /**
   * Tests 'supports credit balance' annotation default value.
   */
  public function testSupportsCreditBalanceQueryDefault() {
    $gateway = $this->createGateway([
      'plugin' => 'capabilities_default',
    ]);
    $this->assertFalse($gateway->supportsCreditBalanceQuery());
  }

  /**
   * Create a new gateway.
   *
   * @param array $values
   *   Custom values to pass to the gateway.
   *
   * @return \Drupal\sms\Entity\SmsGatewayInterface
   *   An unsaved gateway config entity.
   */
  protected function createGateway($values = []) {
    return SmsGateway::create($values + [
      'plugin' => 'memory',
    ]);
  }

}
