<?php

use Illuminate\Support\Facades\Route;

// Rasin nan mennen dirèkteman sou katalòg ajan an: se sa yon vizitè vin
// chèche. Yon paj akèy ki pa fè anyen se yon etap anplis pou granmesi.
Route::redirect('/', '/agents')->name('home');
