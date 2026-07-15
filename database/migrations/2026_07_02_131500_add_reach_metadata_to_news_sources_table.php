<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('news_sources', 'source_type')) {
                $table->string('source_type')->nullable()->after('domain');
            }
            if (! Schema::hasColumn('news_sources', 'media_scope')) {
                $table->string('media_scope')->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('news_sources', 'dewan_pers_status')) {
                $table->string('dewan_pers_status')->nullable()->after('media_scope');
            }
            if (! Schema::hasColumn('news_sources', 'local_reach_weight')) {
                $table->decimal('local_reach_weight', 4, 1)->nullable()->after('dewan_pers_status');
            }
            if (! Schema::hasColumn('news_sources', 'scrape_priority')) {
                $table->integer('scrape_priority')->nullable()->after('local_reach_weight');
            }
            if (! Schema::hasColumn('news_sources', 'reach_notes')) {
                $table->text('reach_notes')->nullable()->after('scrape_priority');
            }
        });

        $sources = [
            [
                'name' => 'Tribun Kaltim',
                'domain' => 'kaltim.tribunnews.com',
                'source_type' => 'national_local_channel',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 10.0,
                'scrape_priority' => 2,
                'reach_notes' => 'Channel lokal Tribun untuk Kaltim; prioritas tinggi untuk berita regional.',
            ],
            [
                'name' => 'Kaltim Post / Prokal',
                'domain' => 'prokal.co',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 10.0,
                'scrape_priority' => 1,
                'reach_notes' => 'Prioritas utama media lokal/regional Kaltim.',
            ],
            [
                'name' => 'Kaltimtoday.co',
                'domain' => 'kaltimtoday.co',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 9.0,
                'scrape_priority' => 3,
                'reach_notes' => 'Media lokal Kaltim dengan fokus regional.',
            ],
            [
                'name' => 'Samarinda Pos / Sapos',
                'domain' => 'sapos.co.id',
                'source_type' => 'local_media',
                'media_scope' => 'local_samarinda',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.5,
                'scrape_priority' => 4,
                'reach_notes' => 'Prioritas kota Samarinda.',
            ],
            [
                'name' => 'Kaltimkece.id',
                'domain' => 'kaltimkece.id',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.5,
                'scrape_priority' => 5,
                'reach_notes' => 'Media regional Kaltim dengan bobot tinggi.',
            ],
            [
                'name' => 'Media Kaltim',
                'domain' => 'mediakaltim.com',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.0,
                'scrape_priority' => 6,
                'reach_notes' => 'Media lokal/regional Kaltim.',
            ],
            [
                'name' => 'Koran Kaltim',
                'domain' => 'korankaltim.com',
                'source_type' => 'local_media',
                'media_scope' => 'local_kabupaten',
                'dewan_pers_status' => null,
                'local_reach_weight' => 7.5,
                'scrape_priority' => 7,
                'reach_notes' => 'Media daerah/kabupaten di Kaltim.',
            ],
            [
                'name' => 'Swara Kaltim',
                'domain' => 'swarakaltim.com',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 7.0,
                'scrape_priority' => 8,
                'reach_notes' => 'Media regional Kaltim.',
            ],
            [
                'name' => 'Niaga.Asia',
                'domain' => 'niaga.asia',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 7.0,
                'scrape_priority' => 9,
                'reach_notes' => 'Media regional dan ekonomi untuk Kaltim.',
            ],
            [
                'name' => 'Nomor Satu Kaltim',
                'domain' => 'nomorsatukaltim.disway.id',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 7.0,
                'scrape_priority' => 10,
                'reach_notes' => 'Portal regional Kaltim pada network Disway.',
            ],
            [
                'name' => 'Editorial Kaltim',
                'domain' => 'editorialkaltim.com',
                'source_type' => 'local_media',
                'media_scope' => 'regional_kaltim',
                'dewan_pers_status' => null,
                'local_reach_weight' => 6.5,
                'scrape_priority' => 11,
                'reach_notes' => 'Media regional Kaltim dengan bobot menengah.',
            ],
            [
                'name' => 'Arusbawah.co',
                'domain' => 'arusbawah.co',
                'source_type' => 'local_media',
                'media_scope' => 'local_samarinda',
                'dewan_pers_status' => null,
                'local_reach_weight' => 5.5,
                'scrape_priority' => 12,
                'reach_notes' => 'Media internal/brand lokal Samarinda.',
            ],
        ];

        foreach ($sources as $source) {
            DB::table('news_sources')->updateOrInsert(
                ['domain' => $source['domain']],
                array_merge($source, [
                    'crawling_type' => DB::table('news_sources')->where('domain', $source['domain'])->value('crawling_type') ?: 'html',
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }

        foreach ([
            [
                'name' => 'Busam.id',
                'domain' => 'busam.id',
                'source_type' => 'social_video',
                'media_scope' => 'niche_community',
                'dewan_pers_status' => null,
                'local_reach_weight' => 9.0,
                'scrape_priority' => null,
                'reach_notes' => 'Sumber social/video; jangan diprioritaskan untuk scraping artikel tahap awal.',
                'is_active' => true,
                'crawling_type' => 'api',
            ],
            [
                'name' => 'Samarinda TV',
                'domain' => 'samarindatv.com',
                'source_type' => 'social_video',
                'media_scope' => 'local_samarinda',
                'dewan_pers_status' => null,
                'local_reach_weight' => 8.0,
                'scrape_priority' => null,
                'reach_notes' => 'Sumber social/video; jangan diprioritaskan untuk scraping artikel tahap awal.',
                'is_active' => true,
                'crawling_type' => 'api',
            ],
        ] as $source) {
            DB::table('news_sources')->updateOrInsert(
                ['domain' => $source['domain']],
                array_merge($source, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            foreach (['source_type', 'media_scope', 'dewan_pers_status', 'local_reach_weight', 'scrape_priority', 'reach_notes'] as $column) {
                if (Schema::hasColumn('news_sources', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
