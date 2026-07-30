<?php

use App\Models\AiPromptTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_prompt_templates')
            ->where('source_type', 'article')
            ->where('is_default', true)
            ->update([
                'system_prompt' => AiPromptTemplate::articleAiSystemPrompt(),
                'user_prompt_template' => AiPromptTemplate::articleAiUserPromptTemplate(),
                'output_schema' => AiPromptTemplate::articleAiOutputSchema(),
                'updated_at' => now(),
            ]);

        DB::table('ai_prompt_templates')
            ->where('source_type', 'social')
            ->where('is_default', true)
            ->update([
                'system_prompt' => AiPromptTemplate::socialAiSystemPrompt(),
                'user_prompt_template' => AiPromptTemplate::socialAiUserPromptTemplate(),
                'output_schema' => AiPromptTemplate::socialAiOutputSchema(),
                'updated_at' => now(),
            ]);

        DB::table('ai_prompt_templates')
            ->where('source_type', 'report')
            ->where('is_default', true)
            ->update([
                'system_prompt' => AiPromptTemplate::reportAiSystemPrompt(),
                'user_prompt_template' => AiPromptTemplate::reportAiUserPromptTemplate(),
                'output_schema' => AiPromptTemplate::reportAiOutputSchema(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('ai_prompt_templates')
            ->where('source_type', 'article')
            ->where('is_default', true)
            ->update([
                'system_prompt' => 'Anda adalah AI analis berita senior. Analisis berita yang diberikan dan berikan respon dalam format JSON yang valid. Gunakan estimasi pembaca yang natural, spesifik, dan tidak dipaksa ke angka bulat generik tanpa alasan kuat.',
                'user_prompt_template' => 'Analisis berita berikut:\nJudul: {title}\nKonten: {content}\nPastikan estimasi pembaca bersifat natural dan realistis, bukan pembulatan mekanis.',
                'output_schema' => '{"type": "object", "properties": {"summary": {"type": "string"}, "sentiment": {"type": "string"}, "sentiment_score": {"type": "number"}, "main_issue": {"type": "string"}, "entities": {"type": "array"}, "risk_level": {"type": "string"}, "risk_reason": {"type": "string"}, "reach_estimate": {"type": "integer"}, "reach_score_10": {"type": "integer"}, "reach_level": {"type": "string"}, "reach_trend": {"type": "string"}, "reach_source": {"type": "string"}, "reach_confidence": {"type": "string"}, "reach_reason": {"type": "string"}, "recommendation": {"type": "string"}}}',
                'updated_at' => now(),
            ]);

        DB::table('ai_prompt_templates')
            ->where('source_type', 'social')
            ->where('is_default', true)
            ->update([
                'system_prompt' => 'Anda adalah AI analis media sosial. Analisis postingan medsos yang diberikan dan berikan respon dalam format JSON yang valid. Prioritaskan link, jenis media, caption, dan engagement untuk menentukan nilai konten.',
                'user_prompt_template' => 'Analisis postingan medsos berikut:\nPlatform: {platform}\nURL: {url}\nMedia Type: {media_type}\nMedia URL: {media_url}\nThumbnail URL: {thumbnail_url}\nAuthor: {author_name}\nKonten: {content}\nEngagement: {engagement_context}\nMedia Context: {media_context}\nKonteks Project: {project_context}',
                'output_schema' => '{"type": "object", "properties": {"summary": {"type": "string"}, "sentiment": {"type": "string"}, "sentiment_score": {"type": "number"}, "main_issue": {"type": "string"}, "entities": {"type": "array"}, "risk_level": {"type": "string"}, "risk_reason": {"type": "string"}, "reach_estimate": {"type": "integer"}, "reach_score_10": {"type": "integer"}, "reach_level": {"type": "string"}, "reach_trend": {"type": "string"}, "reach_source": {"type": "string"}, "reach_confidence": {"type": "string"}, "reach_reason": {"type": "string"}, "content_type": {"type": "string"}, "media_type": {"type": "string"}, "media_link_used": {"type": "string"}, "media_signal": {"type": "string"}, "local_relevance_score": {"type": "integer"}, "confidence_score": {"type": "integer"}, "confidence_level": {"type": "string"}, "signals_used": {"type": "array"}, "reasoning_summary": {"type": "string"}, "limitations": {"type": "string"}, "recommendation": {"type": "string"}}}',
                'updated_at' => now(),
            ]);

        DB::table('ai_prompt_templates')
            ->where('source_type', 'report')
            ->where('is_default', true)
            ->update([
                'system_prompt' => AiPromptTemplate::reportAiSystemPrompt(),
                'user_prompt_template' => AiPromptTemplate::reportAiUserPromptTemplate(),
                'output_schema' => AiPromptTemplate::reportAiOutputSchema(),
                'updated_at' => now(),
            ]);
    }
};
