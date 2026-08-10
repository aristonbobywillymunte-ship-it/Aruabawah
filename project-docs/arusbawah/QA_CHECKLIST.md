# QA Checklist

## APIFY-EFFECTIVE-SCHEDULE-8A
- [x] `php -l app/Console/Commands/RunApifyScraping.php`
- [x] `php -l app/Services/Scraping/ProjectScheduleResolver.php`
- [x] `php -l tests/Feature/ApifyEffectiveScheduleRuntimeTest.php`
- [x] `php artisan route:list`
- [x] `php artisan schedule:list`
- [x] `php artisan view:clear`
- [x] `git diff --check`
- [x] `php artisan test --filter=ApifyEffectiveScheduleRuntimeTest`
- [ ] Real scraping run
- [ ] Production migration

## ACTOR-INTERVAL-REMOVAL-7B-RUNTIME
- [x] `php -l config/services.php`
- [x] `php -l app/Console/Commands/RunApifyScraping.php`
- [x] `php -l app/Jobs/ApifyScrapingJob.php`
- [x] `php -l tests/Feature/ApifyActorIntervalRuntimeRemovalTest.php`
- [x] `php artisan route:list`
- [x] `php artisan schedule:list`
- [x] `php artisan view:clear`
- [x] `git diff --check`
- [x] `php artisan test --filter=ApifyActorIntervalRuntimeRemovalTest`
- [ ] Real scraping run
- [ ] Production migration

## ACTOR-INTERVAL-REMOVAL-7A-UI
- [x] `php -l app/Livewire/Admin/ApifyConfiguration.php`
- [x] `php -l app/Models/ApifyActor.php`
- [x] `php -l tests/Feature/AdminApifyConfigurationTest.php`
- [x] `php artisan route:list`
- [x] `php artisan view:clear`
- [x] `git diff --check`
- [ ] `php artisan test --filter=AdminApifyConfigurationTest` final rerun after assertion fix
- [ ] Browser QA
- [ ] Runtime scraping
- [ ] Production migration

## PORTAL-SLOT-RECOVERY-6C
- [x] `php -l config/services.php`
- [x] `php -l app/Console/Commands/RunNewsPortalScraping.php`
- [x] `php -l tests/Feature/PortalScheduleRecoveryTest.php`
- [x] `php artisan route:list`
- [x] `php artisan schedule:list`
- [x] `php artisan view:clear`
- [x] `git diff --check`
- [x] `php artisan test --filter=PortalScheduleRecoveryTest`
- [x] `php artisan test --filter=PortalScheduleFulfillmentTest`
- [x] `php artisan test --filter=PortalEffectiveScheduleRuntimeTest`
- [x] `php artisan test --filter=ProjectScheduleResolverTest`
- [ ] Real scraping run
- [ ] Production migration

## PORTAL-SLOT-FULFILLMENT-6B
- [x] `php -l app/Console/Commands/RunNewsPortalScraping.php`
- [x] `php -l app/Models/Project.php`
- [x] `php -l database/migrations/2026_08_11_000000_add_portal_last_scheduled_success_at_to_projects_table.php`
- [x] `php -l tests/Feature/PortalScheduleFulfillmentTest.php`
- [x] `php artisan route:list`
- [x] `php artisan schedule:list`
- [x] `php artisan view:clear`
- [x] `git diff --check`
- [x] `php artisan test --filter=PortalScheduleFulfillmentTest`
- [x] `php artisan test --filter=PortalEffectiveScheduleRuntimeTest`
- [x] `php artisan test --filter=ProjectScheduleResolverTest`
- [ ] Real scraping run
- [ ] Production migration

## PORTAL-EFFECTIVE-SCHEDULE-6A
- [x] `php artisan test --filter=PortalEffectiveScheduleRuntimeTest`
- [x] `php artisan route:list`
- [x] `php artisan view:clear`
- [x] `git diff --check`
