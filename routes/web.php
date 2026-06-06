<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Osiset\ShopifyApp\Util;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\TaskController;

// Home — Dashboard
Route::get('/', function () {
    return view('welcome');
})->middleware(['verify.shopify'])->name('home');

// GraphQL Proxy
Route::post('/api/graphql', function (Request $request) {
    $shop = Auth::user();
    $query = $request->input('query');
    $response = $shop->api()->graph($query);
    return response()->json($response);
})->middleware(['verify.shopify']);

// Editor Routes
Route::prefix('editor')->middleware(['verify.shopify'])->group(function () {
    Route::get('/price', [EditorController::class, 'price']);
    Route::post('/price', [EditorController::class, 'submitPrice']);
    Route::post('/price/preview', [EditorController::class, 'previewPrice']);
    Route::get('/inventory', [EditorController::class, 'inventory']);
    Route::post('/inventory', [EditorController::class, 'submitInventory']);
    Route::post('/inventory/preview', [EditorController::class, 'previewInventory']);
    Route::get('/tags', [EditorController::class, 'tags']);
    Route::post('/tags', [EditorController::class, 'submitTags']);
    Route::post('/tags/preview', [EditorController::class, 'previewTags']);
});

// Task History
Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks/{task}/copy', [TaskController::class, 'copy']);
    Route::post('/tasks/{task}/revert', [TaskController::class, 'revert']);
});