# Project Run Scraping Feedback Fix

## Scope
Fix only the project-card **Jalankan Scraping Sekarang** action feedback. Stable scraping engines, scheduler, social comment dispatch, Apify actors, package rules, and worker behavior are intentionally untouched.

## Root Cause
The effective class-based Livewire component dispatched `BootstrapNewProjectScrapingJob` correctly, but called `notifyProjectAction()` with its arguments reversed. The human message was treated as the toast type, so the user could press the action and receive broken/no visible feedback.

## Source Fix
- Branch: `fix/project-run-scraping-feedback`
- `App\Livewire\ProjectsList` overrides only `runScraping()`.
- Existing project access check, bootstrap job dispatch, cache invalidation, redirect, and `news` queue behavior are preserved.
- Toast feedback is sent as a normal `success` notification with a readable background-processing message.
- `ProjectsListRunScrapingTest` verifies confirmation -> queue dispatch -> success feedback without executing scraping.

## Safety
- No scheduler changes.
- No scraping command changes.
- No Apify/comment scraper changes.
- No package/entitlement changes.
- No migration or database schema changes.
- Production not modified.

## QA Status
Local runtime QA completed on isolated PostgreSQL `media_intelligent_testing`.

- `ProjectsListRunScrapingTest`: PASS — 1 test, 9 assertions.
- Job dispatch: PASS; `BootstrapNewProjectScrapingJob` is queued on `news`.
- Toast: PASS; type `success` and expected background-running message.
- `ProjectsListDefaultViewTest`: same baseline and branch failure; no new regression.
- `ProjectsListArticleReachTest`: same baseline and branch failure (`total_articles_found` undefined); no new regression.
- `NEW REGRESSIONS = 0`.
- `php-l`, `route:list`, `view:clear`, and `git diff --check`: PASS.
- Real scraping: NO.
- Apify called: NO.
- Production DB used: NO.
- Production modified: NO.

GitHub source audit is required once more before merge to `main`.
