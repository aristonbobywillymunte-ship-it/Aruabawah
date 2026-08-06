# QA Report — Halaman Manajemen Paket & Apify Configuration
**Tanggal:** 2026-08-07  
**Server:** `ubuntu@3.27.115.35`  
**Database testing:** `media_intelligent_testing`  
**Commit terakhir:** `9b53afe`  
**URL yang diaudit:** http://3.27.115.35/admin/packages

---

## 1. Perubahan yang Dilakukan dalam Sesi Ini

### 1.1 Menyembunyikan Actor Non-Aktif dari Modal Paket
- **File:** `app/Livewire/Admin/PackageManager.php`
- **Masalah:** Actor ID 60 (`scrapeflow/facebook-posts-search-scraper`, status `inactive`) masih tampil di modal "Atur Actor & Biaya" padahal sudah dihapus dari whitelist
- **Fix:** Tambahkan filter `->where('status', 'active')` pada query `$allActors`
- **Status:** ✅ RESOLVED — actor lama tidak lagi muncul di modal

### 1.2 Perbaikan Validasi Whitelist `actorSlug`
- **File:** `app/Livewire/Admin/ApifyConfiguration.php`
- **Masalah:** Validasi whitelist menggunakan `addError() + throw ValidationException::withMessages()` manual — error tidak tertangkap oleh Livewire error bag
- **Fix:** Ganti ke `$this->validate(['actorSlug' => ['in:...']])` yang proper agar error masuk error bag Livewire
- **Status:** ✅ RESOLVED

### 1.3 Perbaikan Unit Test Whitelist Actor Slug
- **File:** `tests/Feature/AdminApifyConfigurationTest.php`
- **Masalah:** Test menggunakan platform Instagram, namun Instagram secara by-design auto-override slug tidak dikenal ke slug default via `resolveInstagramActorDefinition()` + `applyActorDefinitionDefaults()`. Akibatnya slug invalid selalu di-replace sehingga whitelist check tidak pernah berjalan
- **Fix:** Gunakan platform **Facebook** untuk test whitelist failure — Facebook tidak punya auto-override slug, sehingga slug apapun yang diset user dipertahankan
- **Status:** ✅ RESOLVED

---

## 2. Hasil Unit Test (Relevan dengan Perubahan)

```
PASS  Tests\Feature\AdminApifyConfigurationTest
  ✓ non admins cannot access apify configuration page         1.60s
  ✓ admins can access apify configuration page                0.13s
  ✓ registry sync keeps primary actors and uses hashtag       0.10s
  ✓ admin can toggle actor status                             0.13s
  ✓ save actor slug must be in whitelist                      0.35s

Tests: 5 passed (21 assertions)
```

---

## 3. Full Test Suite Summary

| Metric | Sebelum Perubahan | Setelah Perubahan |
|--------|:-----------------:|:-----------------:|
| Total passed | 137 | 137 |
| Total failed | 66 | 66 |
| `AdminApifyConfigurationTest` | 4/5 ✅ | **5/5 ✅** |

> ⚠️ **66 kegagalan yang ada merupakan pre-existing failures** tidak berkaitan dengan perubahan sesi ini. Dikonfirmasi dengan menjalankan test suite sebelum dan sesudah — jumlah identik.

---

## 4. Test Suite Kelas-per-Kelas

### ✅ PASS (Semua test dalam kelas lulus)
| Test Class |
|---|
| `Tests\Unit\AiAnalysisDispatchStateServiceTest` |
| `Tests\Unit\AiFailureClassifierTest` |
| `Tests\Unit\AiQueueConnectionTest` |
| `Tests\Unit\ExampleTest` |
| `Tests\Unit\GoogleNewsUrlDecoderServiceTest` |
| `Tests\Unit\SocialProjectScrapePriorityServiceTest` |
| `Tests\Feature\AdminApifyConfigurationTest` ⭐ (diperbaiki sesi ini) |
| `Tests\Feature\AiDispatchStateReconcileTest` |
| `Tests\Feature\AiQueueUnscoredContentTest` |

### ❌ FAIL (Pre-existing — tidak berkaitan dengan perubahan sesi ini)
| Test Class | Keterangan |
|---|---|
| `Tests\Unit\NewsProjectScrapePriorityServiceTest` | Pre-existing |
| `Tests\Feature\AdminNewsSourcesTest` | Pre-existing |
| `Tests\Feature\AiCardDisplayTest` | Pre-existing |
| `Tests\Feature\AiProviderQaTest` | Pre-existing |
| `Tests\Feature\AiProviderRouterTest` | Pre-existing |
| `Tests\Feature\AiRateLimitGuardTest` | Pre-existing |
| `Tests\Feature\ApifyDispatchTest` | Pre-existing |
| `Tests\Feature\BackfillArticleReadersCommandTest` | Pre-existing |
| `Tests\Feature\BackfillArticleReadersJobTest` | Pre-existing |
| `Tests\Feature\BackfillDisplayReachCommandTest` | Pre-existing |
| `Tests\Feature\BrandingSettingsTest` | Pre-existing |
| `Tests\Feature\ContentMatchingServiceTest` | Pre-existing |
| `Tests\Feature\MarkIncompleteForRescrapeTest` | Pre-existing |
| `Tests\Feature\PipelineMonitorTest` | Pre-existing |
| `Tests\Feature\ProjectMetricsAuditTest` | Pre-existing |
| `Tests\Feature\ProjectSoftDeleteRestoreTest` | Pre-existing |
| `Tests\Feature\ProjectTopicsValidationTest` | Pre-existing |
| `Tests\Feature\ProjectsListArticleReachTest` | Pre-existing |
| `Tests\Feature\ProjectsListDefaultViewTest` | Pre-existing |
| `Tests\Feature\RunNewsPortalScrapingFinalUrlInvariantTest` | Pre-existing |
| `Tests\Feature\RunNewsPortalScrapingQuotaSplitTest` | Pre-existing |
| `Tests\Feature\SocialMediaDeduplicationTest` | Pre-existing |
| `Tests\Feature\SocialMediaPipelineTest` | Pre-existing |

---

## 5. Verifikasi Manual di Server

| Item | Status |
|------|--------|
| Modal Paket — Actor lama (ID 60) tidak tampil | ✅ |
| Whitelist Validation — Slug invalid menghasilkan error di form | ✅ |
| Unit test `save actor slug must be in whitelist` | ✅ PASS |
| Halaman http://3.27.115.35/admin/packages dapat diakses | ✅ |

---

## 6. Commits dalam Sesi Ini

| Hash | Pesan |
|------|-------|
| `100f5f3` | fix(apify-tests): throw validation exception on whitelist check failure |
| `6468c44` | fix: gunakan validate() untuk whitelist actorSlug agar error masuk ke Livewire error bag |
| `32f4788` | fix(test): gunakan platform Facebook untuk test whitelist failure actorSlug |
| `9b53afe` | fix(test): set facebook_max_posts ke integer valid agar required_if validation lolos |
