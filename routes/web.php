<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ReportController;

// Public routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth');

    Route::get('/debug_dispatch', function () {
        $stats = \Illuminate\Support\Facades\DB::table('ai_analysis_dispatch_states')
            ->select('status', 'analyzable_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('status', 'analyzable_type')
            ->get();
            
        $inProject = \Illuminate\Support\Facades\DB::table('project_articles')
            ->distinct('article_id')
            ->count('article_id');
            
        return response()->json([
            'dispatch_states' => $stats,
            'distinct_articles_in_project' => $inProject,
            'total_articles' => \App\Models\Article::count()
        ]);
    });

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return auth()->user()?->isAdmin()
            ? redirect()->route('admin.dashboard')
            : view('welcome');
    })->name('home');

    Route::get('/admin', function () {
        $user = auth()->user();

        $projects = \App\Models\Project::query()
            ->withCount('articles')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(function ($project) {
                $articles = $project->articles();
                $socialSources = ['Twitter', 'Twitter/X', 'x.com', 'Instagram', 'Youtube', 'TikTok', 'Facebook', 'Threads'];

                $socialCount = (clone $articles)->whereIn('source_name', $socialSources)->count();
                $lastRisk = (clone $articles)
                    ->join('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
                    ->where('ai.analysis_status', 'success')
                    ->orderByDesc('articles.published_at')
                    ->value('ai.risk_level');

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->articles_count > 0 ? 'Aktif' : 'Belum Aktif',
                    'articles_count' => $project->articles_count,
                    'social_count' => $socialCount,
                    'risk_level' => $lastRisk ? ucfirst($lastRisk) : 'Rendah',
                    'last_risk_score' => $lastRisk,
                ];
            });

        $socialSources = ['Twitter', 'Twitter/X', 'x.com', 'Instagram', 'Youtube', 'TikTok', 'Facebook', 'Threads'];
        $totalProjects = \App\Models\Project::count();
        $totalUsers = \App\Models\User::count();
        $totalArticles = \App\Models\Article::count();
        $totalSocialItems = \App\Models\Article::whereIn('source_name', $socialSources)->count();
        $totalHighRisk = \App\Models\AiAnalysisResult::whereIn('risk_level', ['high', 'critical'])
            ->where('analysis_status', 'success')
            ->count();

        return view('admin.dashboard', compact(
            'user',
            'projects',
            'totalProjects',
            'totalUsers',
            'totalArticles',
            'totalSocialItems',
            'totalHighRisk'
        ));
    })->middleware('admin')->name('admin.dashboard');
    Route::get('/admin/users', function () {
        return view('admin.users');
    })->middleware('admin')->name('admin.users');
    Route::get('/admin/apify', function () {
        return view('admin.apify');
    })->middleware('admin')->name('admin.apify');
    Route::get('/admin/ai-providers', function () {
        return view('admin.ai-providers');
    })->middleware('admin')->name('admin.ai-providers');
    Route::get('/admin/scraping-settings', function () {
        return view('admin.scraping-settings');
    })->middleware('admin')->name('admin.scraping-settings');
    Route::get('/admin/pipeline-monitor', function () {
        return view('admin.pipeline-monitor');
    })->middleware('admin')->name('admin.pipeline-monitor');
    Route::get('/admin/news-sources', function () {
        return view('admin.news-sources');
    })->middleware('admin')->name('admin.news-sources');
    Route::get('/admin/branding', function () {
        return view('admin.branding');
    })->middleware('admin')->name('admin.branding');
    Route::get('/admin/ai-prompt-templates', function () {
        return view('admin.ai-prompt-templates');
    })->middleware('admin')->name('admin.ai-prompt-templates');
    Route::get('/admin/telegram-settings', function () {
        return view('admin.telegram-settings');
    })->middleware('admin')->name('admin.telegram-settings');
    Route::get('/admin/logs', function () {
        return view('admin.logs');
    })->middleware('admin')->name('admin.logs');
    Route::get('/admin/maintenance', function () {
        return view('admin.maintenance');
    })->middleware('admin')->name('admin.maintenance');
    Route::get('/admin/database', function () {
        return view('admin.database');
    })->middleware('admin')->name('admin.database');
    Route::get('/change-password', [LoginController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [LoginController::class, 'updatePassword'])->name('password.update');

    // Report downloads
    Route::get('/report/pdf',   [ReportController::class, 'downloadPdf'])->name('report.pdf');
    Route::get('/report/excel', [ReportController::class, 'downloadExcel'])->name('report.excel');
});
