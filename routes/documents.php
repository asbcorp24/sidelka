<?php

use App\Http\Controllers\ContractController;
use Illuminate\Support\Facades\Route;

Route::get('/cabinet/legal/documents/{document}/download', [ContractController::class, 'downloadDocument'])
    ->name('contracts.document.download');
