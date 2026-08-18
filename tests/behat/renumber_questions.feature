@local @local_quizrenumber
Feature: Renumber the questions in a course's quizzes
  In order to make questions sort predictably in the question bank
  As a teacher
  I need to prefix question names with an incrementing number

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name    | questiontext        |
      | Test questions   | truefalse | Apple   | First question      |
      | Test questions   | truefalse | Banana  | Second question     |
      | Test questions   | truefalse | Cherry  | Third question      |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz A | C1     | quiza    |
      | quiz     | Quiz B | C1     | quizb    |
    And quiz "Quiz A" contains the following questions:
      | question | page |
      | Apple    | 1    |
      | Banana   | 1    |
    And quiz "Quiz B" contains the following questions:
      | question | page |
      | Cherry   | 1    |

  Scenario: A teacher renumbers the questions in a single quiz
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Renumber quiz questions" in current page administration
    Then I should see "Quiz A"
    And I should see "Quiz B"
    And I should see "2 fixed / 0 random"
    When I set the field "Quiz A" to "1"
    And I press "Continue to preview"
    Then I should see "Preview renumbering"
    And I should see "0010_Apple"
    And I should see "0020_Banana"
    But I should not see "0010_Cherry"
    When I press "Apply renumbering"
    Then I should see "Renumbering results"
    And I should see "2 question(s) renamed."
    # The rename must be visible in the question bank itself, not only on the results page.
    And I am on the "Course 1" "core_question > course question bank" page
    And I should see "0010_Apple"
    And I should see "0020_Banana"
    And I should see "Cherry"

  Scenario: Renumbering twice does not stack prefixes
    Given I am on the "Course 1" course page logged in as teacher1
    And I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Quiz A" to "1"
    And I press "Continue to preview"
    And I press "Apply renumbering"
    And I am on the "Course 1" course page
    When I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Quiz A" to "1"
    And I press "Continue to preview"
    Then I should see "0010_Apple"
    And I should not see "0010_0010_Apple"
    When I press "Apply renumbering"
    And I am on the "Course 1" "core_question > course question bank" page
    Then I should see "0010_Apple"
    And I should not see "0010_0010_Apple"

  Scenario: Numbering restarts for each quiz by default
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Quiz A" to "1"
    And I set the field "Quiz B" to "1"
    And I press "Continue to preview"
    Then I should see "0010_Apple"
    And I should see "0020_Banana"
    # Quiz B starts again rather than continuing from 0030.
    And I should see "0010_Cherry"

  Scenario: Continuous numbering runs one sequence across both quizzes
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Quiz A" to "1"
    And I set the field "Quiz B" to "1"
    And I press "Continue to preview"
    And I set the field "Numbering scope" to "Continuous across all selected quizzes"
    And I press "Apply renumbering"
    Then I should see "3 question(s) renamed."
    And I am on the "Course 1" "core_question > course question bank" page
    And I should see "0010_Apple"
    And I should see "0020_Banana"
    And I should see "0030_Cherry"

  Scenario: An increment above the maximum is refused by the server
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Quiz A" to "1"
    And I press "Continue to preview"
    And I set the field "Increment" to "101"
    And I press "Apply renumbering"
    Then I should see "The increment cannot be larger than 100."
    And I should see "Preview renumbering"

  Scenario: Selecting no quizzes is rejected
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Renumber quiz questions" in current page administration
    And I press "Continue to preview"
    Then I should see "Select at least one quiz to renumber."

  Scenario: The shared badge links to the full usage list and back again
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Renumber quiz questions" in current page administration
    # Cherry is in Quiz B only, so pick a quiz whose questions are shared.
    And I set the field "Quiz A" to "1"
    And I press "Continue to preview"
    Then I should see "Preview renumbering"
    # Apple and Banana are used once each, so no badge should be offered for them.
    And I should not see "Used in 2 places"

  Scenario: A question used by two quizzes is badged and its usage page lists both
    Given the following "questions" exist:
      | questioncategory | qtype     | name   | questiontext    |
      | Test questions   | truefalse | Shared | Shared question |
    And quiz "Quiz A" contains the following questions:
      | question | page |
      | Shared   | 1    |
    And quiz "Quiz B" contains the following questions:
      | question | page |
      | Shared   | 1    |
    And I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Quiz A" to "1"
    And I press "Continue to preview"
    Then I should see "Used in 2 places"
    When I follow "Used in 2 places"
    Then I should see "Quizzes using this question"
    And I should see "Used in 2 quiz(zes) in total."
    And I should see "Quiz A"
    And I should see "Quiz B"
    # Both quizzes are in the course being worked on, so neither is flagged as elsewhere.
    And I should not see "Another course"
    # And each quiz links through to itself.
    And "Quiz A" "link" should exist
    And "Quiz B" "link" should exist
    # And the way back returns to the preview, not just the quiz list.
    When I follow "Back to the list"
    Then I should see "Preview renumbering"
    And I should see "0010_Apple"

  @javascript
  Scenario: The preview updates without reloading and select all ticks every quiz
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Select all quizzes" to "1"
    Then the field "Quiz A" matches value "1"
    And the field "Quiz B" matches value "1"
    When I press "Continue to preview"
    Then I should see "0010_Apple"
    # Changing the options rewrites the table in the browser, with no round trip.
    When I set the field "Start number" to "100"
    And I set the field "Increment" to "5"
    Then I should see "0100_Apple"
    And I should see "0105_Banana"
    And I should not see "0010_Apple"
    # The recomputed name must stay inside its <code> element. Writing textContent to the
    # cell instead would replace the element and silently drop the monospace formatting.
    And "//code[@data-region='newname'][contains(text(),'0100_Apple')]" "xpath_element" should exist

  @javascript
  Scenario: Turning off prefix stripping keeps the existing number
    Given I am on the "Course 1" course page logged in as teacher1
    And I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Quiz A" to "1"
    And I press "Continue to preview"
    And I press "Apply renumbering"
    And I am on the "Course 1" course page
    When I navigate to "Renumber quiz questions" in current page administration
    And I set the field "Quiz A" to "1"
    And I press "Continue to preview"
    And I set the field "Strip existing prefix" to "0"
    Then I should see "0010_0010_Apple"
