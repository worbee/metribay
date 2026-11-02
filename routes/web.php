<?php

use Illuminate\Support\Facades\Route;
use Metribay\Core\Http\Controllers\DashboardController;
use Metribay\Core\Http\Controllers\HiddenChartController;
use Metribay\Core\Http\Controllers\LocaleController;
use Metribay\Core\Http\Controllers\ProjectController;
use Metribay\Core\Http\Controllers\ProjectEventController;
use Metribay\Core\Http\Controllers\SettingsController;
use Metribay\Core\Http\Controllers\SourceSetupController;
use Metribay\Core\Http\Middleware\UseWorkspace;

Route::middleware(['web', 'throttle:20,1'])->post('/locale', LocaleController::class)->name('metribay.locale');

Route::middleware(['web', 'auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'home'])->name('metribay.home');
    Route::get('/integrations/google/callback', [SourceSetupController::class, 'googleReturn'])->name('metribay.google.callback');
    Route::get('/profile', [DashboardController::class, 'profile'])->name('metribay.profile');
    Route::redirect('/account', '/profile', 301)->name('metribay.account');
    Route::prefix('w/{workspace}')->middleware(UseWorkspace::class)->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('metribay.dashboard');
        Route::get('/projects/new', [ProjectController::class, 'create'])->name('metribay.projects.create');
        Route::get('/projects/{project}', [DashboardController::class, 'project'])->name('metribay.projects.show');
        Route::get('/projects/{project}/settings', [ProjectController::class, 'edit'])->name('metribay.projects.edit');
        Route::get('/sources/google', [SourceSetupController::class, 'google'])->name('metribay.sources.google');
        Route::get('/sources/google/callback', [SourceSetupController::class, 'googleCallback'])->name('metribay.sources.google.callback');
        Route::get('/sources/new', [SourceSetupController::class, 'show'])->name('metribay.sources.create');
        Route::post('/sources/setup', [SourceSetupController::class, 'update'])->middleware('throttle:30,1')->block(10, 10)->name('metribay.sources.setup');
        Route::get('/settings', [SettingsController::class, 'index'])->name('metribay.settings');
        Route::post('/projects', [ProjectController::class, 'store'])->name('metribay.projects.store');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('metribay.projects.update');
        Route::post('/groups', [SettingsController::class, 'groupStore'])->name('metribay.groups.store');
        Route::post('/connections', [SettingsController::class, 'connectionStore'])->name('metribay.connections.store');
        Route::patch('/connections/{connection}', [SettingsController::class, 'connectionUpdate'])->name('metribay.connections.update');
        Route::delete('/connections/{connection}', [SettingsController::class, 'connectionDestroy'])->name('metribay.connections.destroy');
        Route::post('/sources', [SettingsController::class, 'sourceStore'])->name('metribay.sources.store');
        Route::patch('/sources/{source}', [SettingsController::class, 'sourceUpdate'])->name('metribay.sources.update');
        Route::post('/sources/{source}/check', [SettingsController::class, 'check'])->name('metribay.sources.check');
        Route::post('/sources/{source}/environments', [SettingsController::class, 'environments'])->middleware('throttle:30,1')->name('metribay.sources.environments');
        Route::get('/export', [SettingsController::class, 'export'])->name('metribay.settings.export');
        Route::post('/import', [SettingsController::class, 'import'])->name('metribay.settings.import');
        Route::post('/projects/{project}/events', [ProjectEventController::class, 'store'])->name('metribay.events.store');
        Route::patch('/projects/{project}/events/{event}', [ProjectEventController::class, 'update'])->name('metribay.events.update');
        Route::delete('/projects/{project}/events/{event}', [ProjectEventController::class, 'destroy'])->name('metribay.events.destroy');
        Route::post('/projects/{project}/hidden-charts', [HiddenChartController::class, 'store'])->name('metribay.charts.hide');
        Route::delete('/projects/{project}/hidden-charts/{chart}', [HiddenChartController::class, 'destroy'])->name('metribay.charts.show');
    });
});
