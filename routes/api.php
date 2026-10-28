<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\ShopSettingsController;
use App\Http\Controllers\AdminConversationController;
use App\Http\Controllers\AdminCustomRequestController;
use App\Http\Controllers\CustomRequestController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Vérification d'e-mail : limite plus large (le front peut rappeler la route)
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('verification.verify');

Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1');
Route::get('/shop-settings', ShopSettingsController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);
    Route::delete('/profile', [AuthController::class, 'deleteAccount']);
    Route::get('/me', [AuthController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| Catégories
|--------------------------------------------------------------------------
*/
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Produits
|--------------------------------------------------------------------------
*/
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Écriture sur les produits : nécessite un compte vérifié (email confirmé)
Route::middleware(['auth:sanctum', 'verified', 'role:admin|vendeur'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    // Images produits
    Route::post('/products/{product}/images', [ProductImageController::class, 'store']);
    Route::put('/product-images/{productImage}', [ProductImageController::class, 'update']);
    Route::delete('/product-images/{productImage}', [ProductImageController::class, 'destroy']);

    // Variantes produits
    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::put('/product-variants/{productVariant}', [ProductVariantController::class, 'update']);
    Route::delete('/product-variants/{productVariant}', [ProductVariantController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Panier
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::put('/cart/items/{itemId}', [CartController::class, 'update']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
});

/*
|--------------------------------------------------------------------------
| Commandes, checkout et adresses
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/counts', [OrderController::class, 'counts']); // avant /orders/{order}
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:30,1');

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
});

// Créer une commande : nécessite un compte vérifié (email confirmé)
Route::middleware(['auth:sanctum', 'verified', 'throttle:10,1'])->group(function () {
    Route::post('/orders', [CheckoutController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Avis produits
|--------------------------------------------------------------------------
*/
Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Wishlist
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::get('/wishlist/ids', [WishlistController::class, 'ids']); // avant /wishlist/{productId}
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Coupons
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/coupons/check', [CouponController::class, 'check']);
});

Route::middleware(['throttle:10,1', 'auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::post('/coupons', [CouponController::class, 'store']);
    Route::put('/coupons/{coupon}', [CouponController::class, 'update']);
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/stats', [AdminDashboardController::class, 'stats']);
    Route::get('/admin/orders', [AdminDashboardController::class, 'orders']);
    Route::get('/admin/products', [AdminDashboardController::class, 'products']);
    Route::get('/admin/users', [AdminDashboardController::class, 'users']);
    Route::get('/admin/reviews', [AdminDashboardController::class, 'reviews']); 
    Route::get('/admin/users/{user}', [AdminDashboardController::class, 'showUser']);
    Route::get('/admin/custom-requests', [AdminCustomRequestController::class, 'index']);
    Route::get('/admin/custom-requests/{customRequest}', [AdminCustomRequestController::class, 'show']);
    Route::put('/admin/custom-requests/{customRequest}/quote', [AdminCustomRequestController::class, 'quote']);
    Route::put('/admin/custom-requests/{customRequest}/reject', [AdminCustomRequestController::class, 'reject']);
    Route::get('/admin/conversations', [AdminConversationController::class, 'index']);
    Route::get('/admin/conversations/{userId}', [AdminConversationController::class, 'show']);
    Route::post('/admin/conversations/{userId}/messages', [AdminConversationController::class, 'reply'])->middleware('throttle:60,1');
});

// Demandes sur mesure (client)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/custom-requests', [CustomRequestController::class, 'index']);
    Route::get('/custom-requests/{customRequest}', [CustomRequestController::class, 'show']);
    Route::post('/custom-requests/{customRequest}/preview', [CustomRequestController::class, 'preview'])->middleware('throttle:30,1');
    Route::post('/custom-requests/{customRequest}/cancel', [CustomRequestController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/custom-requests', [CustomRequestController::class, 'store'])->middleware('throttle:5,1');
    Route::post('/custom-requests/{customRequest}/accept', [CustomRequestController::class, 'accept'])->middleware('throttle:10,1');
});

// Notifications et messages (client et admin)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/summary', [NotificationController::class, 'summary'])->middleware('throttle:120,1');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);

    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:20,1');
});