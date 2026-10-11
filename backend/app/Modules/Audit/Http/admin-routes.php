<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\AuditEntryController;
use Illuminate\Support\Facades\Route;

Route::get('audit-entries', [AuditEntryController::class, 'index'])->name('audit-entries.index');
