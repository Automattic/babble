Feature: Setting up Babble
  As a WordPress site admin
  In order to have Babble on my site
  I need to activate and set up the plugin

  @javascript @insulated
  Scenario: Activating the plugin
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/plugins.php?s=Multilingual"
    Then I should see "Babble"
    When I follow "Activate"
    Then I should see "Plugin activated."
    And I should see "Babble setup: Please visit the Available Languages settings and setup your available languages and the default language."
    When I follow "Available Languages settings"
    Then I should see "Please select the languages you wish to translate this site into."

