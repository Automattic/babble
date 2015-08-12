Feature: Setting up Babble
  As a WordPress site admin
  In order to have Babble on my site
  I need to activate and set up the plugin

  Background:
    Given I have a WordPress installation
    | name      | email                     | username      | password |
    | WordPress | administrator@example.com | administrator | password |
    And there are plugins
    | plugin            | status  |
    | babble/babble.php | enabled |

  Scenario: Activating the plugin
    Given I am logged in as "administrator" with password "password"
    When I go to "/wp-admin"
    Then I should see "Babble setup: Please visit the Available Languages settings and setup your available languages and the default language."
    When I follow "Available Languages settings"
    Then I should see "Please select the languages you wish to translate this site into."

