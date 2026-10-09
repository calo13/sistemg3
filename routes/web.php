<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliverablesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', config('jetstream.auth_session')])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('/memoria/configuracion', 'memory.configuration')
        ->middleware('can:memory.view')->name('memory.configuration');
    Route::view('/procesos', 'processes.index')
        ->middleware('can:memory.view')->name('processes.index');
    Route::view('/paginacion', 'paging.index')
        ->middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view'])->name('paging.index');
    Route::view('/traduccion', 'translation.index')
        ->middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view', 'can:results.view'])->name('translation.index');
    Route::view('/segmentacion', 'segmentation.index')
        ->middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view'])->name('segmentation.index');
    Route::view('/comparacion', 'comparison.index')
        ->middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view', 'can:results.view'])->name('comparison.index');
    Route::view('/demanda', 'stress.index')
        ->middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view', 'can:results.view'])->name('stress.index');
    Route::view('/demostracion', 'demo.index')
        ->middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view'])->name('demo.index');
    Route::view('/historial', 'history.index')->middleware('can:history.view')->name('history.index');
    Route::view('/terminal', 'terminal.index')
        ->middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view', 'can:results.view'])->name('terminal.index');
    Route::view('/presentation', 'presentation.index')
        ->middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view', 'can:results.view'])->name('presentation.index');
    Route::middleware(['can:memory.view', 'can:tables.view', 'can:simulations.view', 'can:results.view'])->group(function () {
        Route::get('/entregables', [DeliverablesController::class, 'index'])->name('deliverables.index');
        Route::get('/entregables/video', [DeliverablesController::class, 'video'])->name('deliverables.video');
        Route::get('/entregables/descargar/{artifact}', [DeliverablesController::class, 'download'])
            ->whereIn('artifact', ['manual', 'informe', 'presentacion', 'video'])->name('deliverables.download');
    });
    Route::view('/administracion/usuarios', 'admin.user-roles')
        ->middleware('can:users.manage')->name('admin.users');
});
