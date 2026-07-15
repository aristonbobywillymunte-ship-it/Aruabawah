# TikTok Flow Audit

## Status
- TIKTOK FLOW READY

## Actor Aktif
- `paul_44/tiktok-search`
- Status: active
- Default keyword: `walikota samarinda`
- Default limit: `50`
- Range mode: `7d`
- Memory limit: `2048`
- Interval: `5 menit`

## Payload Final
```json
{
  "dateRange": "7days",
  "includeSearchKeywords": true,
  "keywords": ["{keyword}"],
  "location": "ID",
  "maxItems": "{limit}",
  "mirrorVideos": true,
  "proxyConfiguration": {
    "useApifyProxy": true,
    "apifyProxyGroups": ["RESIDENTIAL"],
    "apifyProxyCountry": "ID"
  },
  "sortType": "RELEVANCE",
  "strictKeywordMatch": false,
  "useProxy": true,
  "minPlayCount": 0,
  "mirrorVideoBytes": 262144,
  "minDurationSec": 0,
  "maxConcurrentKeywords": 1
}
```

## QA Test
- Project: `Gubernur Kaltim` (ID 2)
- Command: `scraping:run-apify --platform=TikTok --project-id=2 --limit=3 --keyword="gubernur kaltim" --no-telegram`
- Result: success
- Dataset items saved: 3
- Article mirror created: 2
- TikTok items stored in DB: 3

## Queue Status
- `ai-analysis = 0`
- `ai-backfill = 0`
- `apify = 0`
- `scraping = 0`
- `failed_jobs_recent = 0`

## Notes
- TikTok data masuk ke `social_media_items` dan tidak tercampur sebagai artikel portal.
- `posted_at` aman dipakai, dan data valid tetap tersimpan meski author name kosong menjadi fallback `Unknown Author`.
- Tidak ada failed job baru dari test ini.
