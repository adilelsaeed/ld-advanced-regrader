=== Advanced Regrader for LearnDash ===
Contributors: adilelsaeed
Tags: learndash, quiz, regrade, lms, scores
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely recalculate and update quiz grades for all previous LearnDash quiz attempts after modifying questions, answers, or point values.

== Description ==

**Advanced Regrader for LearnDash** is an administrative tool that allows you to safely and efficiently recalculate quiz grades for all previous attempts after modifying quiz questions, answer correct-states, or point values.

= The Problem =

By default, LearnDash LMS permanently records a student's score the moment they complete a quiz. If you later discover a mistake in a quiz (e.g., a wrong answer was marked as correct, a question's points value needs to be changed, or new questions are added), the scores of students who have already taken the quiz are NOT updated automatically.

Administrators are left with two bad choices:

1. Leave the old incorrect scores as they are.
2. Manually delete all attempts and ask the students to retake the quiz.

Furthermore, attempting to run a custom script to update thousands of historical quiz attempts usually results in server crashes, PHP timeouts, and memory exhaustion errors.

**This plugin completely solves this problem.**

= Key Features =

* **Simulation & Preview** — Before committing any changes, view a paginated table showing the student's current score alongside the simulated new score based on the latest quiz question settings.
* **Comprehensive Regrading** — Accurately recalculates scores and statistics for all previous attempts, strictly matching the answers the student provided at the time.
* **AJAX Background Bulk Regrading** — Safely process tens of thousands of attempts in the background. The plugin handles updates in manageable batches (50 at a time) with a live progress bar, completely preventing server timeouts and memory limit crashes.
* **Smart Filtering** — Narrow down the attempts by a specific Quiz, a LearnDash Group, or a specific User (search by Name or Email).
* **Manual Selective Updating** — Selectively regrade only specific students instead of running the full bulk auto-regrader.
* **Course Progress Sync** — Optionally update the student's overall Course Completion status if their newly regraded score turns a "Failed" quiz into a "Passed" quiz.

== Installation ==

1. Upload the `ld-advanced-regrader` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **LearnDash LMS > Regrade Scores** in your WordPress admin dashboard.

== Frequently Asked Questions ==

= Does this plugin require LearnDash LMS? =

Yes. LearnDash LMS must be installed and active for this plugin to function.

= Will this plugin modify student answers? =

No. The plugin only recalculates scores based on the current question settings. Student answers are never modified.

= Is it safe to use on a large number of attempts? =

Yes. The batch processing feature handles updates in chunks of 50, preventing server timeouts and memory issues. You can safely regrade tens of thousands of attempts.

= Can I preview the changes before saving? =

Yes. The simulation table shows you the current score vs. the new calculated score for every attempt before you commit any changes.

= Does it update course completion? =

Only if you check the "Update Course Completion" option. If a student's regraded score changes a failed quiz to a passing grade, the course progress will be updated accordingly.

== Screenshots ==

1. Main interface showing quiz selection and filtering options.
2. Simulation table with current vs. new scores and difference highlighting.
3. Batch processing progress bar for bulk regrading.

== Changelog ==

= 1.0.2 =
* Added AJAX-based batch processing for bulk regrading with progress bar.
* Added table pagination for large datasets (50 items per page).
* Performance improvements for handling thousands of quiz attempts.

= 1.0.1 =
* Added quiz statistics update (wp_learndash_pro_quiz_statistic table).
* Added user quiz meta update (_sfwd-quizzes) for accurate LearnDash statistics.
* Improved score matching logic with statistic_ref_id support.

= 1.0.0 =
* Initial release.
* Score simulation and preview table.
* Selective and bulk regrading.
* Group and user search filtering.
* Course progress sync option.

== Upgrade Notice ==

= 1.0.2 =
Adds batch processing for safely handling large datasets without server timeouts.
