Feature: Viewing Translation Jobs
	As a site administrator
	In order to manage translation jobs
	I need to ensure the correct access is available to users

	Background:
		Given I have a WordPress installation
			| name      | email                     | username      | password |
			| WordPress | administrator@example.com | administrator | password |
		And there are plugins
			| plugin            | status  |
			| babble/babble.php | enabled |
		And I am logged in as "administrator" with password "password"
		And I go to "/wp-admin/"
		And there are users
			| user_login    | display_name | user_email                | user_pass | role          |
			| translator    | Translator   | translator@example.com    | password  | translator    |
			| editor        | Editor       | editor@example.com        | password  | editor        |
			| author        | Author       | author@example.com        | password  | author        |
			| contributor   | Contributor  | contributor@example.com   | password  | contributor   |
			| subscriber    | Subscriber   | subscriber@example.com    | password  | subscriber    |

	Scenario: Administrator access to the Translation Jobs screen
		Given I am logged in as "administrator" with password "password"
		When I go to "/wp-admin/"
		Then I should see "Howdy, administrator"
		Then I should see "Translations"
		When I follow "Translations"
		Then I should see "Translation Jobs"

	Scenario: Translator access to the Translation Jobs screen
		Given I am logged in as "translator" with password "password"
		When I go to "/wp-admin/"
		Then I should see "Howdy, translator"
		Then I should see "Translations"
		When I follow "Translations"
		Then I should see "Translation Jobs"

	Scenario: Editor access to the Translation Jobs screen
		Given I am logged in as "editor" with password "password"
		When I go to "/wp-admin/"
		Then I should see "Howdy, editor"
		Then I should see "Translations"
		When I follow "Translations"
		Then I should see "Translation Jobs"

	Scenario: Author access to the Translation Jobs screen
		Given I am logged in as "author" with password "password"
		When I go to "/wp-admin/"
		Then I should see "Howdy, author"
		Then I should see "Translations"
		When I follow "Translations"
		Then I should see "Translation Jobs"

	Scenario: Contributor access to the Translation Jobs screen
		Given I am logged in as "contributor" with password "password"
		When I go to "/wp-admin/"
		Then I should see "Howdy, contributor"
		Then I should see "Translations"
		When I follow "Translations"
		Then I should see "Translation Jobs"

	Scenario: Subscriber access to the Translation Jobs screen
		Given I am logged in as "subscriber" with password "password"
		When I go to "/wp-admin/"
		Then I should see "Howdy, subscriber"
		Then I should not see "Translations"
