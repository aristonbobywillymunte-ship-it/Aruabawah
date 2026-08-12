# Project Run Scraping Feedback Fix

## Scope
Fix only the project-card **Jalankan Scraping Sekarang** action feedback. Stable scraping engines, scheduler, social comment dispatch, Apify actors, package rules, and worker behavior are intentionally untouched.

## Root Cause
The effective class-based Livewire component dispatched `BootstrapNewProjectScrapingJob` correctly, but called `notifyProjectAction()` with its arguments reversed. The human message was treated as the toast type, so the user could press the action and receive broken/no visible feedback.

## Source Fix
- Branch: `fix/project-run-scraping-feedback`
- `App\Livewire\ProjectsList` now overrides only `runScraping()`.
- Existing project access check, bootstrap job dispatch, cache invalidation, redirect, and `news` queue behavior are preserved.
- Toast feedback is now sent as a normal `success` notification with a readable background-processing message.
- A focused `ProjectsListRunScrapingTest` verifies confirmation -> queue dispatch -> success feedback without executing scraping.

## Safety
- No scheduler changes.
- No scraping command changes.
- No Apify/comment scraper changes.
- No package/entitlement changes.
- No migration or database schema changes.
- Production not modified.

## QA Status
Source audit completed on GitHub. Local runtime QA on isolated PostgreSQL `media_intelligent_testing` is still required before merge.
