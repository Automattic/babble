Feature: Resetting a password
  As a WordPress developer
  In order to demonstrate email testing
  I'd like to reset the admin user's password

  @javascript @insulated
  Scenario: Accessing admin initially and being redirected
    Given I am on "/wp-admin/"
    Then the request URI should match "wp-login.php"
#    Then I should see "Log In"

  @javascript @insulated
  Scenario: Receiving a password reset email
    Given I am on "/wp-login.php"
    And I follow "Lost your password?"
    And I fill in "Username or E-mail:" with "testing@example.invalid"
    And I press "Get New Password"
    And the latest email to testing@example.invalid should match "Someone requested that the password be reset for the following account"
    And I follow the second URL in the latest email to testing@example.invalid
    And I fill in "pass1" with "newpassword"
    And I fill in "pass2" with "newpassword"
    And I press "Reset Password"
    And I follow "Log in"
    And I fill in "Username" with "admin"
    And I fill in "Password" with "newpassword"
    And I press "Log In"
    Then I should see "Dashboard"
    And the request URI should match "wp-admin"

  @javascript @insulated
  Scenario: The password reset actually worked
    Given I am logged in to WordPress as "admin" with the password "newpassword" and I am on "/wp-admin/"
    Then I should see "Dashboard"
    And I should see "At a Glance"