Feature: Accessing WordPress site
  As a WordPress developer
  In order to know this Apache is serving static HTML
  I'd like to check the WordPress readme.html is visible

  @javascript @insulated
  Scenario: Visiting the homepage
    Given I am on "/readme.html"
#    And I wait for 10 seconds
#    And echo debugging information
    Then I should see "WordPress is a very special project to me"
