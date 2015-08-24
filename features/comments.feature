Feature: Adding Comments
	As a site visitor
	In order to leave a comment
	I need to ensure that commenting works as expected

	Background:
		Given I have a WordPress installation
			| name      | email                     | username      | password |
			| WordPress | administrator@example.com | administrator | password |
		And there are plugins
			| plugin            | status  |
			| babble/babble.php | enabled |
		And I am logged in as "administrator" with password "password"
		And I go to "/wp-admin/"

	Scenario: Redirection after leaving a comment
		When I go to "/en/?p=1"
		Then I should see "Leave a Reply"
		When I fill in "comment" with "Here is my comment"
		And I press "submit"
		Then I should see "Here is my comment"
