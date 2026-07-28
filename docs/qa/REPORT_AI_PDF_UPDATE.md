# Report AI PDF Update

Date: 2026-07-28

## Scope
- Keep the existing PDF layout.
- Reuse the current `Kesimpulan AI` and `Rekomendasi AI` sections only.
- Pull values from project-level AI insight fields before rendering the PDF.

## Implementation Notes
- `ReportController::downloadPdf()` now resolves project AI insight data first.
- If the project does not have AI insight data yet, the existing `GenerateProjectAiInsightJob` is executed synchronously.
- `Project` now casts `ai_insight_recommendations` as an array and `ai_insight_updated_at` as a datetime.
- The Laporan PDF button in `MediaDashboard` now prepares AI insight first, shows a loading overlay, and opens the PDF only after the AI report is ready.
- `GenerateProjectAiInsightJob` now forces persistence of `ai_insight_summary`, `ai_insight_recommendations`, and `ai_insight_updated_at` so the AI result is not blocked by mass-assignment rules.
- The AI prompt for report insight was rewritten to emphasize berita/isu, framing media, reputational risk, and issue-specific recommendations.

## Verification
- PHP syntax check passed for the modified files.
