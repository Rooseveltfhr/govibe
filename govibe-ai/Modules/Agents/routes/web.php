<?php

use Illuminate\Support\Facades\Route;
use Modules\Agents\Http\Controllers\AgentController;
use Modules\Agents\Http\Controllers\VoiceController;

/*
| Katalòg ajan yo: chwazi yon sektè, eseye l (Demo), epi kreye pa w.
|
| ⚠️ Pa gen otantifikasyon ankò: nenpòt moun ki gen URL la ka kreye yon ajan.
| Sa akseptab pou yon premye vèsyon w ap montre, li PA akseptab lè vrè machann
| ap sere konesans biznis yo ladan. Kont itilizatè = pwochen etap obligatwa.
*/

Route::prefix('agents')->name('agents.')->group(function (): void {
    Route::get('/', [AgentController::class, 'index'])->name('index');
    Route::get('/nouvo/{sector}', [AgentController::class, 'create'])->name('create');
    Route::post('/', [AgentController::class, 'store'])->name('store');
    Route::match(['get', 'post'], '/demo/{sector}', [AgentController::class, 'demo'])->name('demo');
    Route::post('/demo/{sector}/vwa', [AgentController::class, 'voice'])->name('demo.voice');
    // Vwa ajan an: chwazi youn nan bibliyotèk la oswa anrejistre pa w.
    // Anvan `/{agent}` pou `vwa` pa pran pou yon idantifyan ajan.
    Route::get('/{agent}/vwa', [VoiceController::class, 'edit'])->name('voice.edit');
    Route::post('/{agent}/vwa', [VoiceController::class, 'update'])->name('voice.update');
    Route::post('/{agent}/vwa/nouvo', [VoiceController::class, 'store'])->name('voice.store');

    Route::get('/{agent}', [AgentController::class, 'show'])->name('show');
});
