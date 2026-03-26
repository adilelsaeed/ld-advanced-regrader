# Advanced Regrader for LearnDash

An advanced, high-performance administrative tool for LearnDash LMS that allows administrators to safely and efficiently recalculate quiz grades for all previous attempts after modifying quiz questions, answer correct-states, or point values.

## 🤔 Why use this plugin? (The Problem)

By default, LearnDash LMS permanently records a student's score the moment they complete a quiz. If you later discover a mistake in a quiz (e.g., a wrong answer was marked as correct, a question's points value needs to be changed, or new questions are added), **the scores of students who have already taken the quiz are NOT updated automatically**.

Administrators are left with two bad choices:
1. Leave the old incorrect scores as they are.
2. Manually delete all attempts and ask the students to retake the quiz.

Furthermore, attempting to run a custom script to update thousands of historical quiz attempts usually results in catastrophic server crashes, PHP `max_execution_time` timeouts, and Memory Exhaustion errors.

**This plugin completely solves this problem.**

## ✨ Features

- **Simulation & Preview**: Before committing any changes, view a paginated table showing the student's current score alongside the *simulated new score* based on the latest quiz question settings.
- **Comprehensive Regrading**: Accurately recalculates scores and statistics for *all* previous attempts, strictly matching the answers the student provided at the time, not just their latest attempt.
- **AJAX Background Bulk Regrading**: Safely process tens of thousands of attempts securely in the background. The plugin handles updates in manageable batches (50 at a time) with a live progress bar, completely neutralizing server timeout and memory limit crashes.
- **Smart Filtering**: Narrow down the attempts by a specific Quiz, a LearnDash Group, or a specific User (Search by Name or Email).
- **Manual Selective Updating**: Check specific boxes to selectively regrade only a handful of students if you don't wish to run the full bulk auto-regrader.
- **Course Progress Sync**: Includes an option to safely update the student's overall Course Completion status if their newly regraded score turns a "Failed" quiz into a "Passed" quiz.

## 🚀 How to Use It

1. Navigate to your WordPress Admin Dashboard.
2. Go to **LearnDash LMS** > **Regrade Scores**.
3. Use the first dropdown to **Select Quiz**.
4. *(Optional)* Select a **Group** or enter a **Search User** string to narrow down exactly who you want to regrade.
5. Click **Show Results & Simulation**. 
   - *The plugin will now compare the students' old answers against the CURRENT question models and present a simulated "Difference".*
6. **To update specific attempts**: Check the boxes next to the target students, optionally check the "Update Course Completion" box, and click the primary **Update Selected Students Grades** button.
7. **To safely update ALL attempts (Recommended for large datasets)**: Click the secondary **Auto Regrade All Matching (Batch)** button. 
   - A progress bar will appear. Keep your browser tab open until the progress bar reaches 100% and the page reloads.

## ⚙️ Requirements
- WordPress 5.0+
- PHP 7.4+
- LearnDash LMS
