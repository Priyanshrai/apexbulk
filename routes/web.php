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
})->middleware(['verify.shopify'])->name('api.graphql');

// Editor Routes
Route::prefix('editor')->middleware(['verify.shopify'])->group(function () {
    Route::get('/price', [EditorController::class, 'price'])->name('editor.price');
    Route::post('/price', [EditorController::class, 'submitPrice'])->name('editor.price.submit');
    Route::post('/price/preview', [EditorController::class, 'previewPrice'])->name('editor.price.preview');
    Route::get('/inventory', [EditorController::class, 'inventory'])->name('editor.inventory');
    Route::post('/inventory', [EditorController::class, 'submitInventory'])->name('editor.inventory.submit');
    Route::post('/inventory/preview', [EditorController::class, 'previewInventory'])->name('editor.inventory.preview');
    Route::get('/tags', [EditorController::class, 'tags'])->name('editor.tags');
    Route::post('/tags', [EditorController::class, 'submitTags'])->name('editor.tags.submit');
    Route::post('/tags/preview', [EditorController::class, 'previewTags'])->name('editor.tags.preview');
});

// Task History
Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks/{task}/copy', [TaskController::class, 'copy'])->name('tasks.copy');
    Route::post('/tasks/{task}/revert', [TaskController::class, 'revert'])->name('tasks.revert');
});

// Billing plans page
Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/billing/plans', function () {
        return view('billing.plans');
    })->name('billing.plans');
});

/*
|--------------------------------------------------------------------------
| GDPR Compliance Webhooks (Mandatory for App Store)
|--------------------------------------------------------------------------
| Uses the package's auth.webhook middleware for HMAC verification.
| Kyon147 package /webhook/{type} route cannot handle slashes in topic
| names (shop/redact, customers/redact, etc.), so we define explicit routes.
*/
Route::prefix('webhook/gdpr')->middleware(['auth.webhook'])->group(function () {
    Route::post('/shop-redact', function (Request $request) {
        \App\Jobs\GdprShopRedactJob::dispatch(
            $request->header('x-shopify-shop-domain'),
            json_decode($request->getContent())
        );
        return response('', 201);
    });

    Route::post('/customers-redact', function (Request $request) {
        \App\Jobs\GdprCustomerRedactJob::dispatch(
            $request->header('x-shopify-shop-domain'),
            json_decode($request->getContent())
        );
        return response('', 201);
    });

    Route::post('/customers-data-request', function (Request $request) {
        \App\Jobs\GdprCustomerDataRequestJob::dispatch(
            $request->header('x-shopify-shop-domain'),
            json_decode($request->getContent())
        );
        return response('', 201);
    });
});