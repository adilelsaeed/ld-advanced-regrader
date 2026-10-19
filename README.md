# Advanced Regrader for LearnDash

[![CI](https://github.com/adilelsaeed/ld-advanced-regrader/actions/workflows/ci.yml/badge.svg)](https://github.com/adilelsaeed/ld-advanced-regrader/actions/workflows/ci.yml)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![WordPress 6.2+](https://img.shields.io/badge/WordPress-6.2%2B-21759b)
![License GPL-2.0+](https://img.shields.io/badge/license-GPL--2.0%2B-blue)

Recalculate LearnDash quiz scores for past attempts after you change questions, correct answers or points, with a preview before anything is saved.

## The problem

LearnDash saves a student's score the moment they finish a quiz. Fix a mistake in the quiz later, and everyone who already took it keeps the old score. The usual options are to leave wrong scores in place or ask every student to retake the quiz, and one-off scripts over thousands of attempts tend to hit PHP time and memory limits.

This plugin recalculates each past attempt from the answers the student actually gave, against the quiz as it is now.

## Features

- **Preview first**: saved score, recalculated score, difference and any pass/fail change per attempt. Nothing is written until you confirm.
- **Regrade selected attempts, or all matching attempts** in short sequential batches with a progress bar and a Stop button.
- **Filter** by quiz, LearnDash group, or student name, email or username.
- **Consistent updates** across the attempt record, its pass/fail status, the student's quiz history (`_sfwd-quizzes`) and the per-question ProQuiz statistics.
- **Never removes points it cannot check**: single and multiple choice are re-scored; other question types keep their original points.
- **Optional course progress**: marks the quiz step complete for attempts that now pass.
- **RTL ready** (layout uses logical CSS properties; an RTL stylesheet is generated at build time).

## Architecture

```
ld-advanced-regrader.php      Bootstrap: constants, autoloader, boot on plugins_loaded
src/
  Plugin.php                  Wires services; capability and batch-size filters
  Scoring/                    Pure PHP, no WordPress: easy to unit test
    Question.php              Question value object (decoupled from WpProQuiz)
    AnswerEvaluator.php       Is a stored answer correct under current settings?
    ScoreCalculator.php       Re-scores an attempt; keeps points for unsupported types
    ScoreResult.php           Score, total, percentage, pass check
  Regrade/
    Regrader.php              Preview, apply, batch; always recalculates server-side
    UserQuizMetaUpdater.php   Matches the attempt inside _sfwd-quizzes (pure)
  Data/
    AttemptRepository.php     SQL for attempts, meta and ProQuiz statistics
    QuizRepository.php        The only class that touches WpProQuiz models
  Rest/RegradeController.php  REST API: /options, /attempts, /regrade, /regrade/batch
  Admin/AdminPage.php         LearnDash submenu page; mounts the React app
src/js/                       React admin app (@wordpress/components, api-fetch)
tests/php/                    PHPUnit + Brain Monkey unit tests
src/js/**/test/               Jest + Testing Library tests
```

Design decisions worth calling out:

- **The client never sends scores.** The admin app sends attempt IDs only; the server recalculates every score before saving and checks each attempt belongs to the requested quiz.
- **Filtering and paging happen in SQL.** Group membership uses LearnDash's `learndash_group_users_{id}` user meta, so a quiz with 50,000 attempts is never loaded into PHP at once.
- **Batches are stable.** Rows are ordered by completion time and ID, and regrading never adds or removes attempts, so offset paging is safe across batch requests.
- **No guessing when matching history.** If an attempt can't be matched in `_sfwd-quizzes` by statistic reference or completion time (and the quiz has more than one attempt), that history entry is left alone rather than overwriting the wrong one.

## Extending

```php
// Let group leaders use the regrader.
add_filter( 'ldar_capability', fn() => 'group_leader' );

// Smaller batches on a slow host.
add_filter( 'ldar_batch_size', fn() => 20 );

// React to a regrade (e.g. log it or notify the student).
add_action( 'ldar_attempt_regraded', function ( $activity_id, $result, $old_meta ) {
	// $result->score, $result->total_points, $result->percentage()
}, 10, 3 );
```

## Development

Requirements: PHP 7.4+, Composer, Node 20+, Docker (for the local WordPress environment).

```bash
composer install
npm install

npm run build        # build the admin app into build/
npm start            # rebuild on change

composer test        # PHPUnit
composer lint        # PHPCS (WordPress coding standards + PHP 7.4 compatibility)
composer analyse     # PHPStan
npm run test:unit    # Jest
npm run lint:js
npm run lint:css
```

### Local WordPress with Docker

The project ships a [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) config. LearnDash is commercial, so point wp-env at your own copy:

```bash
cp .wp-env.override.json.example .wp-env.override.json   # edit the LearnDash path
npm run env start                                        # http://localhost:8888 (admin / password)
```

### Continuous integration

GitHub Actions runs on every push and pull request:

- PHPUnit on PHP 7.4, 8.0, 8.1, 8.2 and 8.3
- PHPCS (WordPress-Extra, WordPress-Docs, PHPCompatibilityWP) and PHPStan
- ESLint, Stylelint, Jest, production build, and an installable plugin zip as a build artifact

### Release

```bash
npm run build && npm run plugin-zip   # creates ld-advanced-regrader.zip
```

## Requirements

- WordPress 6.2+
- PHP 7.4+
- LearnDash LMS

## License

GPL-2.0-or-later. © Adil Elsaeed.
