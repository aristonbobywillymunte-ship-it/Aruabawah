<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\RunApifyScraping::class,
        \App\Console\Commands\RunNewsPortalScraping::class,
        \App\Console\Commands\RunNeedsRescrape::class,
        \App\Console\Commands\TestSourceDiscovery::class,
        \App\Console\Commands\SaveArticleReachAssessment::class,
        \App\Console\Commands\BackfillDisplayReach::class,
        \App\Console\Commands\SyncSQLiteToPostgres::class,
        \App\Console\Commands\TestArticleAiAnalysis::class,
        \App\Console\Commands\TestArticleReachScoring::class,
        \App\Console\Commands\TestPortalUrlScraping::class,
        \App\Console\Commands\TestSmallScrapingPipeline::class,
        \App\Console\Commands\ReconcileDispatchStates::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
