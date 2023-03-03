@mod @mod_roleplay
Feature: Add roleplay activity
  In order to ask questions as a roleplay of multiple responses
  As a teacher
  I need to add roleplay activities to courses

  Scenario: Add a roleplay activity and complete the activity as a student
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Roleplay" to section "1" and I fill the form with:
      | Roleplay name | Roleplay name |
      | Description | Roleplay Description |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I choose "Option 1" from "Roleplay name" roleplay activity
    Then I should see "Your selection: Option 1"
    And I should see "Your roleplay has been saved"
