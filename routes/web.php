<?php

use Illuminate\Support\Facades\Route;
use Metribay\Core\Http\Controllers\AnnotationController;
use Metribay\Core\Http\Controllers\DashboardController;
use Metribay\Core\Http\Controllers\SettingsController;
use Metribay\Core\Http\Middleware\UseWorkspace;

Route::middleware(['web', 'auth', 'auth.session', 'verified'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'home'])->name('metribay.home');
    Route::get('/account', [DashboardController::class, 'account'])->name('metribay.account');
    Route::get('/workspaces', [DashboardController::class, 'workspaces'])->name('metribay.workspaces');
    Route::prefix('w/{workspace}')->middleware(UseWorkspace::class)->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('metribay.dashboard');
        Route::get('/projects/{project}', [DashboardController::class, 'project'])->name('metribay.projects.show');
        Route::get('/settings', [SettingsController::class, 'index'])->name('metribay.settings');
        Route::post('/projects', [SettingsController::class, 'projectStore'])->name('metribay.projects.store');
        Route::patch('/projects/{project}', [SettingsController::class, 'projectUpdate'])->name('metribay.projects.update');
        Route::post('/groups', [SettingsController::class, 'groupStore'])->name('metribay.groups.store');
        Route::post('/connections', [SettingsController::class, 'connectionStore'])->name('metribay.connections.store');
        Route::patch('/connections/{connection}', [SettingsController::class, 'connectionUpdate'])->name('metribay.connections.update');
        Route::post('/sources', [SettingsController::class, 'sourceStore'])->name('metribay.sources.store');
        Route::patch('/sources/{source}', [SettingsController::class, 'sourceUpdate'])->name('metribay.sources.update');
        Route::post('/sources/{source}/sync', [SettingsController::class, 'sync'])->name('metribay.sources.sync');
        Route::post('/sources/{source}/check', [SettingsController::class, 'check'])->name('metribay.sources.check');
        Route::get('/export', [SettingsController::class, 'export'])->name('metribay.settings.export');
        Route::post('/import', [SettingsController::class, 'import'])->name('metribay.settings.import');
        Route::post('/projects/{project}/annotations', [AnnotationController::class, 'store'])->name('metribay.annotations.store');
        Route::patch('/projects/{project}/annotations/{annotation}', [AnnotationController::class, 'update'])->name('metribay.annotations.update');
        Route::delete('/projects/{project}/annotations/{annotation}', [AnnotationController::class, 'destroy'])->name('metribay.annotations.destroy');
    });
});
