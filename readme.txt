=== Advanced Regrader for LearnDash ===
Contributors: adilelsaeed
Tags: learndash, quiz, regrade, lms, scores
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recalculate LearnDash quiz scores for past attempts after you change questions, correct answers or points, with a preview before anything is saved.

== Description ==

LearnDash saves a student's score the moment they finish a quiz. If you later fix a quiz (a wrong answer was marked correct, a question's points change, a question is added), students who already took it keep their old scores.

Your options are usually to leave the wrong scores, or ask everyone to retake the quiz. Custom scripts that update thousands of attempts tend to hit PHP time and memory limits.

**Advanced Regrader for LearnDash** recalculates every past attempt from the answers the student actually gave, against the quiz as it is now.

= Features =

* **Preview first.** See each attempt's saved score next to its recalculated score, the difference, and any pass/fail change. Nothing is saved until you confirm.
* **Regrade selected attempts or all matching attempts.** "All" runs in short batches (50 by default) with a progress bar and a Stop button, so large quizzes never hit server limits.
* **Filter** by quiz, LearnDash group, or student name, email or username.
* **Keeps everything consistent.** Updates the attempt, its pass/fail status, the student's quiz history and the per-question quiz statistics.
* **Safe with every question type.** Single and multiple choice questions are re-scored. Other types (free text, sorting, essay, ...) keep the points the student was originally awarded, and the preview says how many were kept.
* **Optional course progress.** Mark the quiz complete for students who now pass; LearnDash then updates course completion itself.
* **Original score kept** in the attempt's data, even across repeated regrades.
* **Works in RTL** languages such as Arabic.

= For developers =

* REST API under `ld-advanced-regrader/v1` (`/options`, `/attempts`, `/regrade`, `/regrade/batch`).
* Filters: `ldar_capability` (default `manage_options`), `ldar_batch_size` (default 50).
* Action: `ldar_attempt_regraded( $activity_id, $result, $old_meta )`.
* Source, tests and CI: https://github.com/adilelsaeed/ld-advanced-regrader

== Installation ==

1. Upload the `ld-advanced-regrader` folder to `/wp-content/plugins/`, or install the release zip from the Plugins screen.
2. Activate the plugin.
3. Go to **LearnDash LMS > Regrade Scores**.

LearnDash LMS must be installed and active.

== Frequently Asked Questions ==

= Does it change student answers? =

No. It reads the answers the student submitted and recalculates the score. Answers are never modified.

= Is it safe on thousands of attempts? =

Yes. Filtering and paging run in the database, and "Save new scores for all" processes attempts in short batches. You can stop at any time. Take a database backup before large regrades, as with any bulk change.

= Which question types are re-scored? =

Single choice and multiple choice. Other types keep their original points, so a regrade never takes away points it cannot re-check.

= Does it update course completion? =

Only if you tick "Mark the quiz complete for students who now pass". It marks the quiz step complete for attempts that pass after regrading, and LearnDash re-evaluates course completion from there.

= Who can use it? =

Administrators (`manage_options`) by default. Developers can change this with the `ldar_capability` filter.

== Screenshots ==

1. Filters and the preview table with current vs. new scores.
2. Confirmation before saving.
3. Batch progress with a Stop button.

== Changelog ==

= 2.0.0 =
* New admin screen built with React and WordPress components, with a confirmation dialog before saving.
* New REST API replaces admin-ajax.
* Security: scores are now always recalculated on the server when saving. Previously the page submitted the new scores itself.
* Fix: "Update course completion" no longer marks a course complete for students who still fail.
* Fix: question types other than single and multiple choice keep their original points instead of scoring zero.
* Fix: a missing single-choice answer no longer counts as choosing the first option.
* Fix: when an attempt cannot be matched in the student's quiz history, it is left unchanged instead of overwriting another attempt.
* Filtering, counting and paging now run in SQL, so large quizzes load quickly.
* Batch regrading can be stopped mid-way; batch size is filterable.
* The first original score is kept across repeated regrades.
* New `ldar_capability` and `ldar_batch_size` filters and `ldar_attempt_regraded` action.
* Requires WordPress 6.2+.
* Unit tests (PHPUnit, Jest), coding standards, static analysis and GitHub Actions CI.

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

== Upgrade Notice ==

= 2.0.0 =
Security and correctness release: scores are recalculated on the server when saving, and course completion is only updated for passing attempts. Requires WordPress 6.2+.
