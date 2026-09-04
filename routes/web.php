<?php

use App\Http\Controllers\Api\PlaceSearchController;
use App\Http\Controllers\Api\TripEstimateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\InternalTaskController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\RefuelController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::view('/offline', 'offline')->name('offline');

// La portada es pública y va fuera del grupo «guest» a propósito: quien ya tiene
// la sesión abierta también puede volver a ella sin que se le eche al panel.
Route::view('/', 'landing')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store']);
    Route::get('/registro', [RegisterController::class, 'create'])->name('register');
    Route::post('/registro', [RegisterController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/panel', DashboardController::class)->name('dashboard');

    // ─── Grupos ────────────────────────────────────────────────────────────
    Route::get('/grupos/crear', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/grupos', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/grupos/unirse', [GroupController::class, 'joinForm'])->name('groups.join');
    Route::post('/grupos/unirse', [GroupController::class, 'join']);

    Route::middleware('group.member')->prefix('g/{group}')->group(function () {
        Route::get('/', [GroupController::class, 'show'])->name('groups.show');
        Route::patch('/', [GroupController::class, 'update'])->name('groups.update');

        Route::get('/viajes/nuevo', [TripController::class, 'create'])->name('trips.create');
        Route::post('/viajes', [TripController::class, 'store'])->name('trips.store');
        Route::get('/viajes/{trip}', [TripController::class, 'show'])->name('trips.show');
        Route::post('/viajes/{trip}/anular', [TripController::class, 'cancel'])->name('trips.cancel');

        Route::get('/libro', [LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/liquidar', [SettlementController::class, 'index'])->name('settlements.index');
        Route::post('/liquidar', [SettlementController::class, 'store'])->name('settlements.store');
    });

    // ─── Vehículos ─────────────────────────────────────────────────────────
    Route::get('/coches', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('/coches/nuevo', [VehicleController::class, 'create'])->name('vehicles.create');
    Route::post('/coches', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::get('/coches/{vehicle}/editar', [VehicleController::class, 'edit'])->name('vehicles.edit');
    Route::put('/coches/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::post('/coches/{vehicle}/repostajes', [RefuelController::class, 'store'])->name('refuels.store');

    // ─── Endpoints internos de la interfaz ─────────────────────────────────
    Route::get('/api/lugares', PlaceSearchController::class)->name('api.places');
    Route::post('/api/estimacion', TripEstimateController::class)->name('api.estimate');
});

// Tareas disparadas por el cron externo (GitHub Actions)
Route::middleware('internal.token')->prefix('internal')->group(function () {
    Route::post('/sync-prices', [InternalTaskController::class, 'syncPrices'])->name('internal.sync-prices');
    Route::post('/calibrate', [InternalTaskController::class, 'calibrate'])->name('internal.calibrate');
});
