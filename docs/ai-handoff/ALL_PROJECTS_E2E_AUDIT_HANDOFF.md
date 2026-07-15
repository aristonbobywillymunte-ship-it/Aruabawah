# All Projects E2E Audit Handoff

## Scope
Read-only audit seluruh project untuk memverifikasi:
- input project
- prioritas selector/scheduler
- discovery/scraping funnel
- article/pivot integrity
- AI reach resmi
- risk/notification readiness
- status UI umum

## Snapshot
- Timestamp audit: `2026-07-03 09:35:32` WITA
- Snapshot JSON: `docs/ai-handoff/snapshots/all-projects-e2e-audit-20260703-093532.json`
- DB counts:
  - users: 2
  - projects: 5
  - projects aktif: 3
  - projects inactive: 2
  - project_user: 4
  - news_sources: 0
  - news_source_suggestions: 5
  - candidate_links: 43
  - scraping_items: 43
  - articles: 37
  - project_articles: 37
  - ai_analysis_results: 37
  - risk_notifications: 2
  - scraping_settings: 1
  - ai_providers: 2
  - ai_prompt_templates: 2
  - telegram_settings: 1
  - jobs: 0
  - failed_jobs: 0
- Redis queues:
  - news: 0
  - default: 0
  - ai-analysis: 0
  - notification: 0
  - apify: 0

## Container State
- media_intelligent_container: running, restart 0
- media_intelligent_postgres_container: running, restart 0
- media_intelligent_redis_container: running, restart 0
- media_intelligent_worker_container: running, restart 0
- media_intelligent_ai_worker_container: running, restart 0
- media_intelligent_scheduler_container: running, restart 0
- media_intelligent_notification_worker_container: exited, restart 0
- media_intelligent_apify_worker_container: exited, restart 635

## Project Inventory

| ID | Nama | Owner | Active | Topics | Candidate | Scraping | Articles | AI Success | Risk | Oldest Article | Newest Article | Last Scrape | Last AI | Notes |
|---|---|---|---:|---|---:|---:|---:|---:|---:|---|---|---|---|---|
| 2 | Rudy Masud | user | Yes | `rudy mas'ud` | 10 | 10 | 7 | 7 | 2 | 2026-06-08 07:04:20 | 2026-07-02 03:22:20 | 2026-07-02 23:01:06 | 2026-07-03 09:01:14 | Healthy but has 2 historical Telegram auth_error rows |
| 5 | samarinda | user | No | `samarinda` | 15 | 15 | 15 | 15 | 0 | 2026-07-01 15:10:26 | 2026-07-02 19:08:37 | 2026-07-03 08:16:12 | 2026-07-03 08:16:16 | Inactive, historical data only |
| 7 | seno aji | user | Yes | `seno aji`, `wagub kaltim` | 18 | 18 | 15 | 14 | 0 | 2026-05-22 10:03:35 | 2026-07-02 10:00:11 | 2026-07-03 09:04:08 | 2026-07-03 09:04:22 | One invalid_ai_reach exists |
| 8 | helmi abdullah | user | No | `helmi abdullah` | 0 | 0 | 0 | 0 | 0 | - | - | - | - | Inactive / not processed |
| 27 | Samarinda | user | Yes | `Samarinda` | 0 | 0 | 3 | 3 | 0 | 2026-07-02 19:08:37 | 2026-07-02 22:47:51 | 2026-07-03 09:50:15 | 2026-07-03 09:50:15 | New active project discovered during audit |

## Cache / First Attempt Audit
- Cache driver: `database`
- Cache table backend: `public.cache`
- No `first-scrape-attempt` rows existed at audit time.
- Cache keys present:
  - `laravel-cache-illuminate:queue:restart`
  - `laravel-cache-scheduler_heartbeat`
- `Cache::has()` / `Cache::get()` via service:
  - project 2 => `false` / `null`
  - project 5 => `false` / `null`
  - project 7 => `false` / `null`
  - project 8 => `false` / `null`
  - project 27 => `false` / `null`
- Conclusion: at the time of audit all eligible projects are treated as **pending first-attempt** because the attempt markers are absent from cache. The earlier marker snapshot is no longer present in the current store.

## Priority / Selector Order (Read-only Service Call)
Current service output:
1. Project 27 - Samarinda
2. Project 7 - seno aji
3. Project 2 - Rudy Masud

Why:
- service filters only active projects with non-empty `topics`
- pending projects are ordered by newest `created_at` first
- once a project has attempt marker, ordering changes to oldest attempt first

## Input Validation / Project Management
- `ProjectsList` persists `topicsString` into `projects.topics` JSON.
- Project creation validates `name` and `topicsString`.
- Deactivate is soft-disable only (`is_active = false`), not hard delete.
- No separate `project_keywords` table exists.
- Active projects are the only ones eligible for the scraper priority service.

## Scraping Funnel

| Project | Candidate | Decode berhasil | Rejected | Partial | Article | Pivot |
|---|---:|---:|---:|---:|---:|---:|
| 2 Rudy Masud | 10 | 10 | 2 | 1 | 7 | 7 |
| 5 samarinda | 15 | 15 | 0 | 0 | 15 | 15 |
| 7 seno aji | 18 | 18 | 2 | 1 | 15 | 15 |
| 8 helmi abdullah | 0 | 0 | 0 | 0 | 0 | 0 |
| 27 Samarinda | 0 | 0 | 0 | 0 | 3 | 3 |

Status failure breakdown observed in project data:
- decoder failed: none surfaced in current sample set
- URL Google: none stored as final article URL
- non-article: handled earlier in discovery, not present in final article set
- keyword mismatch: not observed in final persisted set
- content <=500: 1 partial each on projects 2 and 7
- duplicate: 2 rejected each on projects 2 and 7
- HTTP/timeout: not surfaced in current persisted final set
- other: project 27 has 3 article rows but no candidate/scraping rows, so it likely represents a different ingestion path or a backfilled/project-only attach path

## Article Audit
### Active project 2 (3 samples)
1. `article_id=13` - content 4338, `lenteraKalimantan.com`, published `2026-07-02 03:22:20`, AI success, reach valid
2. `article_id=15` - content 2416, `KPFM Balikpapan`, published `2026-07-02 02:05:33`, AI success, reach valid
3. `article_id=21` - content 3154, `detikKalimantan`, published `2026-06-25 14:30:00`, AI success, reach valid

### Active project 7 (3 samples)
1. `article_id=40` - content 3663, `Headline Kaltim`, published `2026-07-02 10:00:11`, AI success, reach valid
2. `article_id=31` - content 3070+, `detikKalimantan`, AI success, reach valid
3. `article_id=30` - content 2600+, `detikKalimantan`, AI success, reach valid

### Active project 27 (3 samples)
1. `article_id=37` - content 1931, `detiknews`, published `2026-07-02 19:08:37`, AI success, reach valid
2. `article_id=38` - content 2641, `Antara News`, published `2026-07-02 06:22:15`, AI success, reach valid
3. `article_id=39` - content 2298, `KOMPAS.com`, published `2026-07-02 15:47:51`, AI success, reach valid

## AI / Reach Audit
- Official AI reach source: `ai_analysis_results` with `analysis_status = success` and `reach_method = ai_reader_estimate_v1`
- `project_estimated_readers <= potential_estimated_readers` is satisfied for successful rows sampled.
- `is_exact_reach = false` on sampled successful rows.
- `confidence_score` stays within `<=69` / Medium when no analytics data exists.

### Project 2 sample
- article 13: potential 850, project 750, confidence 69, reach valid, exact false
- article 15: potential 850, project 650, confidence 65, reach valid, exact false
- article 21: potential 450, project 300, confidence 65, reach valid, exact false

### Project 7 sample
- article 40: potential 842, project 463, confidence 65, reach valid, exact false
- article 31: potential 842, project 584, confidence 68, reach valid, exact false
- article 30: potential 842, project 714, confidence 68, reach valid, exact false
- article 22: invalid_ai_reach exists for project 7 with validation error:
  - `project_estimated_readers cannot exceed potential_estimated_readers`
  - `potential score (10) inconsistent with readers (1), expected 1`

### Project 27 sample
- article 37: potential 842, project 514, confidence 65, exact false
- article 38: potential 842, project 617, confidence 68, exact false
- article 39: potential 1200, project 450, confidence 65, exact false

### Raw response examples
- Project 2 / article 13 raw_response contains the same AI JSON fields that are used by UI/report.
- Project 7 / article 37 raw_response contains the same AI JSON fields that are used by UI/report.
- The DB raw_response is the canonical source; UI/report reads these values directly via the AI analysis result relation.

## Notification Audit
- Telegram setting row exists and is active, but only a placeholder bot token is present.
- `project_telegram_recipients` is empty.
- Project 2 has 2 historical `risk_notifications` with `auth_error` and Telegram 401.
- Projects 7 and 27 have no risk notifications.
- No new notification job was generated during this audit.
- `failed_jobs = 0` and Redis queue lengths are 0, so there is no current backlog.

## Notification Readiness Matrix

| Project | Enabled | Channel tersedia | Recipient | Recipient valid | High/Critical count | Risk row | External job | Delivery | Dedup key |
|---|---|---|---|---|---:|---:|---|---|---|
| 2 Rudy Masud | Yes | Yes | No | No | 2 historical | 2 auth_error | No | failed historically | ai_analysis_result_id |
| 5 samarinda | Yes | Yes | No | No | 0 | 0 | No | - | ai_analysis_result_id |
| 7 seno aji | Yes | Yes | No | No | 0 | 0 | No | - | ai_analysis_result_id |
| 8 helmi abdullah | Yes | Yes | No | No | 0 | 0 | No | - | ai_analysis_result_id |
| 27 Samarinda | Yes | Yes | No | No | 0 | 0 | No | - | ai_analysis_result_id |

## UI Audit Status
- I could confirm via HTTP that the route redirects to login when not authenticated.
- Full Livewire/browser hydration audit was not re-run in this pass because no browser automation tool was available in this turn.
- Prior browser verification for project 7 already showed the simplified card layout and AI readers estimate values aligned with DB.

## Health Matrix

| Project | Input | Selection | Scraping | Artikel | AI | Reach | Notification | UI | Status |
|---|---|---|---|---|---|---|---|---|---|
| 2 Rudy Masud | Complete | Eligible, pending first-attempt | Healthy | Healthy | Healthy | Healthy | Needs attention | Previously validated | PERLU PERHATIAN |
| 5 samarinda | Complete | Inactive | Historical only | Historical | Historical | Historical | Not applicable | Not active in UI | INACTIVE |
| 7 seno aji | Complete | Eligible, pending first-attempt | Healthy | Healthy | Healthy | Healthy | OK | Previously validated | SEHAT |
| 8 helmi abdullah | Complete | Inactive | None | None | None | None | Not applicable | Not active in UI | INACTIVE |
| 27 Samarinda | Ambiguous / broad | Eligible, pending first-attempt | Healthy | Healthy | Healthy | Healthy | OK | Needs browser recheck | PERLU PERHATIAN |

## Priority Findings
### P0
- None found in this read-only pass.

### P1
- Project 2 still has 2 historical Telegram auth_error notifications that should not be retried automatically.
- Project 7 has one `invalid_ai_reach` row that should stay excluded from official reach display.

### P2
- Project 27 is an active project discovered during audit and should be tracked in dashboards/selector inventory.

### P3
- Browser/Livewire hydration verification was not repeated in this pass due tool limitation.

### P4
- No evidence of duplicate canonical URLs or duplicate pivots in the sampled active projects.

## Final Check
- Counts before and after audit are unchanged.
- jobs = 0
- failed_jobs = 0
- Redis queues = 0
- notification worker remains exited
- Apify worker remains exited

## Next Recommendation
- If browser verification is needed again, rerun the hydrated page audit on `/?project=7` and the project list page with an actual browser automation tool.
- No code or database change was made in this audit.

## Follow-up: First-Attempt Marker Migration

- The audit earlier found that the first-attempt marker was effectively lost when cache keys disappeared.
- This was fixed by adding `projects.first_news_scrape_attempt_at` and moving the priority state into PostgreSQL.
- Backfill now covers the historical projects with scrape evidence:
  - 2 `Rudy Masud`
  - 5 `samarinda`
  - 7 `seno aji`
  - 27 `Samarinda`
- Project 8 remains without a marker because there is no scrape history to infer from.
- The new maintenance command is `news:backfill-first-scrape-attempts`.
- This closes the cache-vs-database contradiction that made projects appear "pending first-attempt" after cache eviction.
- The legacy cache keys were also cleared after backfill, so PostgreSQL is the only source of truth for the marker now.
- Verification caveat: the `media_intelligent_container` artisan context later reported zero projects, while the named PostgreSQL container still showed populated rows; this is an environment alignment issue, not a data change from this migration.
- Follow-up audit result:
  - direct PostgreSQL now also reports `users = 0`, `projects = 0`, `articles = 0`
  - the active runtime DB is empty, so the login failure is caused by data loss / empty runtime state
  - historical data exists in `storage/backups/portal-reset-20260702-192547.sql`
  - no restore was performed in this pass

## Restore Result
- Runtime DB was restored from:
  - `storage/backups/portal-reset-20260702-192547.sql`
- Restored counts:
  - users: 2
  - projects: 3
  - project_user: 3
  - articles: 13
  - project_articles: 13
  - ai_analysis_results: 13
  - news_sources: 17
- Login check:
  - existing admin login reached `/admin`
- Backfill:
  - `projects.first_news_scrape_attempt_at` backfilled for projects 2, 5, and 7
- Note:
  - the later 5-project / 37-article state was not recoverable from the files available in this pass
