<?php

/**
 * @file
 * Contains \Drupal\sms_user\Tests\SmsUserWebTest.
 */

namespace Drupal\sms_user\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\user\Entity\User;

/**
 * Integration tests for SMS User module.
 *
 * @group SMS Framework
 *
 * @todo Add tests for creation of users via sms.
 * @todo Add tests for integration with rules and actions modules.
 */
class SmsUserWebTest extends WebTestBase {

  public static $modules = ['sms', 'sms_test_gateway', 'sms_user', 'syslog', 'sms_devel'];

  /**
   * Returns the config() object.
   *
   * @return \Drupal\Core\Config\Config
   */
  protected function config($name) {
    return $this->container->get('config.factory')->get($name);
  }

  /**
   * Tests user adding phone number.
   */
  public function testNumberConfirmationAndSmsUserSend() {
    // Set up test default gateway.
    $this->config('sms.settings')->set('default_gateway', 'test')->save();
    $user = $this->drupalCreateUser(array('receive sms', 'edit own sms number'));
    $this->drupalLogin($user);

    // Post user confirmation request.
    $edit = array('number' => '1234567890');
    $this->drupalPostForm('user/' . $user->id() . '/mobile', $edit, t('Confirm number'));
    $this->assertResponse(200);
    // Get the code that was sent.
    $gw_result = sms_test_gateway_result();
    preg_match('/\b([0-9]{4})\b/', $gw_result['message'], $matches);
    $code = $matches[1];
    // Post the code for confirmation.
    $this->drupalPostForm('user/' . $user->id() . '/mobile', array('confirm_code' => $code), t('Confirm number'));
    $this->assertResponse(200);
    // Confirm user's number is verified.
    $user = User::load($user->id());
    $this->assertTrue($user->sms_user['number'] == $edit['number'] && $user->sms_user['status'] == SMS_USER_CONFIRMED, 'Successfully confirmed user phone number ' . $edit['number']);

    // Send sms to user with registered number.
    $message = 'Test user message';
    $this->assertTrue(sms_user_send($user->id(), $message), 'Successfully sent message to user with permission');
    $this->assertEqual(sms_test_gateway_result(),
      array('number' => $user->sms_user['number'], 'message' => $message, 'options' => $user->sms_user['gateway']), 'Message sent through the correct gateway.');

    // Test sms_user_authenticate() on this user.
    $account = sms_user_authenticate($user->sms_user['number']);
    $this->assertEqual($user->id(), $account->id(), 'Correctly authenticated user by phone number.');

    // Get a user with no permissions and test failed sending.
    $user1 = $this->drupalCreateUser(array());
    $this->assertFalse(sms_user_send($user1->id(), $message), 'Failed sending to user without permission');

    // Clear user confirmed number.
    $this->drupalPostForm('user/' . $user->id() . '/mobile', array(), t('Delete & start over'));
    $this->assertResponse(200);
    $this->resetAll();
    $user = User::load($user->id());
    $this->assertTrue($user->sms_user['number'] == '' && $user->sms_user['status'] == 0, 'Successfully deleted user confirmed number');

    // Test that sending fails when confirmed number is deleted.
    $this->assertFalse(sms_user_send($user->id(), $message), 'Failed sending to user without confirmed number');

    // Test failure to authenticate a non-existent number.
    $this->assertFalse(sms_user_authenticate(rand(23400000000, 23499999999)), 'Failed to authenticate non-existent number.');
  }

  /**
   * Tests sms_user admin options.
   */
  public function testSmsUserOptions() {
    $user = $this->drupalCreateUser(array('administer smsframework', 'receive sms', 'edit own sms number'));
    $this->drupalLogin($user);

    // Set the sms_user admin options.
    $edit = array(
      'registration_form' => 0,
      'confirmation_message' => $this->randomString(20),
      'enable_sleep' => 1,
      'sleep_start_time' => 1,
      'sleep_end_time' => 1,
      'registration_enabled' => 1,
      'allow_password' => 1,
      'new_account_message' => $this->randomString(30),
      'max_chars' => 160,
    );
    $this->drupalPostForm('admin/config/smsframework/sms_user_options', $edit, t('Save configuration'));
    $this->assertResponse(200);

    // Verify that the variables are set.
    foreach ($edit as $name => $value) {
      $this->assertEqual($value, $this->config('sms_user.settings')->get($name), sprintf('Variable %s has been set.', $name));
    }

    // Check that the user registration page honors the mobile number field
    // visibility settings.
    $this->drupalLogout();
    $this->config('sms_user.settings')->set('registration_form', 0)->save();
    $this->drupalGet('user/register');
    $this->assertNoField('sms_user[number]', 'No number field in registration form.');

    $this->config('sms_user.settings')->set('registration_form', 1)->save();
    $this->drupalGet('user/register');
    $this->assertField('sms_user[number]', 'Number field in registration form.');

    $this->config('sms_user.settings')->set('registration_form', 2)->save();
    $this->drupalGet('user/register');
    $this->assertField('sms_user[number]', 'Number field in registration form.');

    $required_phone_field = $this->xpath('//input[@name="sms_user[number]" and @required="required"]');
    $this->assertEqual(count($required_phone_field), 1, 'Number field marked as required.');
  }

  /**
   * Tests whether a user can opt out and in for sms messages from the site.
   */
  public function testSmsUserOptOut() {

    // Create Excluded User
    $excluded_user = $this->drupalCreateUser(array('administer smsframework', 'receive sms', 'edit own sms number'));
    $this->drupalLogin($excluded_user);

    // Set up test default gateway.
    $this->config('sms.settings')->set('default_gateway', 'test')->save();

    $sms_user_settings = array(
      'registration_enabled' => TRUE,
      'allow_password' => TRUE,
      'allow_opt_out' => TRUE,
    );
    $this->drupalPostForm('admin/config/smsframework/sms_user_options', $sms_user_settings, t('Save configuration'));

    // Confirm excluded_user number.
    $edit = array('number' => '1234567890');
    $this->drupalPostForm('user/' . $excluded_user->id() . '/mobile', $edit, t('Confirm number'));
    $this->drupalPostForm(NULL, NULL, t('Confirm without code'));
    $this->assertText('Your mobile phone number has been confirmed.', 'Authors number is confirmed');

    // Set the Opt Out checkbox.
    $opt_out = array('opted_out' => TRUE );
    $this->drupalPostForm('user/' . $excluded_user->id() . '/mobile', $opt_out, t('Set'));
    $this->assertText(t('The changes have been saved.'), 'Excluded user has chosen to opt out of messages from the site.');

    $test_message1 = array(
      'number' => '1234567890',
      'message' => 'Test opting out of messages',
    );

    sms_test_gateway_result(TRUE);
    $this->drupalPostForm('admin/config/smsframework/devel', $test_message1, t('Send Message'));
    $this->assertResponse(200);
    // Test if the message was not sent by checking the cached sms_test message
    // result.
    $this->assertFalse(sms_test_gateway_result(),  t('Message was not sent to user that opted out.'));

    // Create Normal User
    $normal_user = $this->drupalCreateUser(array('administer smsframework', 'receive sms', 'edit own sms number'));
    $this->drupalLogin($normal_user);

    // Confirm normal_user number.
    $edit = array('number' => '0987654321');
    $this->drupalPostForm('user/' . $normal_user->id() . '/mobile', $edit, t('Confirm number'));
    $this->drupalPostForm(NULL, NULL, t('Confirm without code'));
    $this->assertText('Your mobile phone number has been confirmed.', 'Authors number is confirmed');

    // Set the Opt Out checkbox.
    $setting = array('opted_out' => FALSE );
    $this->drupalPostForm('user/' . $normal_user->id() . '/mobile', $setting, t('Set'));
    $this->assertText(t('The changes have been saved.'), t('Author has chosen opt in for messages from the site.'));

    $test_message2 = array(
      'number' => '0987654321',
      'message' => 'Test opting in for messages.',
    );

    sms_test_gateway_result(TRUE);
    $this->drupalPostForm('admin/config/smsframework/devel', $test_message2, t('Send Message'));
    $this->assertResponse(200);
    $this->assertText('Form submitted ok for number ' . $test_message2['number'] . ' and message: ' . $test_message2['message'], 'Successfully sent message to recipient with registered number');

    // Test if the message was not sent by checking the cached sms_test message
    // result.
    $gw_result = sms_test_gateway_result();
    $this->assertTrue(in_array($test_message2['number'], explode(',', $gw_result['number'])),  t('Message was sent to user that did not opt out.'));

    // Disable Opt Out for this site.
    $this->drupalLogin($excluded_user);
    $sms_user_settings['allow_opt_out'] = FALSE;
    $this->drupalPostForm('admin/config/smsframework/sms_user_options', $sms_user_settings, t('Save configuration'));
    $this->assertFalse($this->config('sms_user.settings')->get('allow_opt_out'), 'Opt out globally disabled.');

    // Confirm that the opt-out button is not available to users.
    $this->drupalGet('user/' . $excluded_user->id() . '/mobile');
    $this->assertNoText(t('Opt out of sms messages from this site.'), t('Opt out checkbox not visible in UI.'));

    // Ensure opt out doesn't work when message is sent.
    sms_test_gateway_result(TRUE);
    $this->drupalPostForm('admin/config/smsframework/devel', $test_message1, t('Send Message'));
    $this->assertResponse(200);
    $this->assertText('Form submitted ok for number ' . $test_message1['number'] . ' and message: ' . $test_message1['message'], 'Successfully sent message to recipient with registered number');
    $gw_result = sms_test_gateway_result();
    $this->assertTrue(in_array($test_message1['number'], explode(',', $gw_result['number'])),  t('Message was sent to user who opted out due to global override.'));
  }

}
