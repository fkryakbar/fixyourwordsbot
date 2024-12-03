<?php

use App\Models\User;
use App\Services\Telegram;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    User::first()->delete();
    // Telegram::replyUnredMessage();
});
