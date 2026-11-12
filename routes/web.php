<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/shop', 'welcome')->name('shop');
Route::view('/customer', 'welcome')->name('customer.dashboard');
Route::view('/account', 'welcome')->name('account');
Route::view('/profile', 'welcome')->name('profile');
Route::view('/product/{id}', 'welcome')->whereNumber('id')->name('product.details');
Route::view('/admin/{path?}', 'welcome')->where('path', '.*')->name('admin');
