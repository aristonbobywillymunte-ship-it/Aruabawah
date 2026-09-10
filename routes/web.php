<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ReportController;
use App\Models\Project;
use App\Services\ContentMatchingService;

// Public routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth');

    Route::get('/debug_dispatch', function () {
        $stats = \Illuminate\Support\Facades\DB::table('ai_analysis_dispatch_states')
            ->select('status', 'analyzable_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('status', 'analyzable_type')
            ->get();

        $inProject = Project::query()
            ->where('is_active', true)
            ->get()
            ->sum(function ($project) {
                return app(ContentMatchingService::class)->countMatchingContentForProject($project)['articles'] ?? 0;
            });

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

    Route::get('/projects/create', App\Livewire\ProjectCreate::class)->name('projects.create');

    Route::get('/admin', function () {
        return view('admin.dashboard');
    })->middleware('admin')->name('admin.dashboard');
    Route::get('/admin/users', function () {
        return view('admin.users');
    })->middleware('admin')->name('admin.users');
    Route::get('/admin/apify', function () {
        return view('admin.apify');
    })->middleware('admin')->name('admin.apify');
    Route::get('/admin/apify-financials', function () {
        return view('admin.apify-financial-report');
    })->middleware('admin')->name('admin.apify-financials');
    Route::get('/admin/packages', function () {
        return view('admin.packages');
    })->middleware('admin')->name('admin.packages');
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

    // ─── Client Management ───
    Route::get('/admin/clients', function () {
        return view('admin.clients');
    })->name('admin.clients');
    Route::get('/admin/clients/create', function () {
        return view('admin.clients-create');
    })->name('admin.clients.create');
    Route::get('/admin/clients/{user}/settings', function (\App\Models\User $user) {
        return view('admin.clients-settings', ['user' => $user]);
    })->name('admin.clients.settings');



    Route::get('/change-password', [LoginController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [LoginController::class, 'updatePassword'])->name('password.update');

    // Report downloads
    Route::get('/report/pdf',   [ReportController::class, 'downloadPdf'])->name('report.pdf');
    Route::get('/report/excel', [ReportController::class, 'downloadExcel'])->name('report.excel');
});
