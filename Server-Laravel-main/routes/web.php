<?php

use App\Http\Controllers\DocumentController;
use App\Events\DocumentUpdated;
use App\Models\Document;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// ─── Interface e Controle de Sessão ───────────────────────────────────
Route::get('/',         [DocumentController::class, 'entrada'])->name('entrada');
Route::post('/nome',    [DocumentController::class, 'salvarNome'])->name('salvar-nome');
Route::get('/documento',[DocumentController::class, 'show'])->name('documento');


// ─── Nova Rota Proxy CRDT (Operações) ─────────────────────────────────
// Captura os deltas/ops enviados pelo front, encaminha ao Rust, 
// persiste o estado consolidado no SQLite e dispara o broadcast via Reverb.
Route::post('/document/{id}/ops', function (Request $request, $id) {
    // Repassa as operações originais para o Rust aplicar o CRDT
    $response = Http::timeout(3)->post(
        env('CRDT_SERVICE_URL', 'http://127.0.0.1:9000') . "/document/{$id}/ops",
        $request->all()
    );

    if ($response->successful()) {
        $content = $response->json('content', '');
        $site    = $request->input('site', 'Anônimo');

        // Persiste no SQLite
        Document::where('id', $id)->update(['content' => $content]);

        // Broadcast com o site correto para evitar eco próprio
        broadcast(new DocumentUpdated($content, $site));
    }

    return response()->noContent();
})->middleware('web')->name('document.ops');


// ─── Rotas de Compatibilidade Backwards ───────────────────────────────
// ATENÇÃO: Não remova estas duas linhas abaixo imediatamente. Como a sua view 
// (show.blade.php) possui chamadas estritas a route('document.autosave') e 
// route('document.save'), a remoção completa desses nomes causará um erro 
// fatal de execução (RouteNotFoundException) ao renderizar a página.
Route::post('/document/autosave', [DocumentController::class, 'update'])->name('document.autosave');
Route::post('/document/save',     [DocumentController::class, 'update'])->name('document.save');