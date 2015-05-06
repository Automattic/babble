Feature: Accessing WordPress site
  As a WordPress developer
  In order to know this Travis thing is working
  I'd like to check the WordPress homepage is visible

  @javascript @insulated
  Scenario: Visiting the homepage
    Given I am on "/"
#    And I wait for 10 seconds
#    And echo debugging information
    Then I should see "Proudly powered by WordPress"
