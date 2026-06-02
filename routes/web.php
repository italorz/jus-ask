<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TokenController;
use App\Livewire\Admin\ListaEmpresas;
use App\Livewire\ChavesGemini\GerenciarChavesGemini;
use App\Livewire\TokenCnj\GerenciarTokenCnj;
use App\Livewire\Clientes\GerenciarClientes;
use App\Livewire\Notificacoes\GerenciarNotificacoes;
use App\Livewire\Processos\DetalheProcesso;
use App\Livewire\Processos\GerenciarProcessos;
use App\Livewire\Site\GerenciarSite;
use App\Models\TokenCnj;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Auth::routes(['verify' => false]);

// Rotas públicas com tenant (sem autenticação)
Route::prefix('{tenant}')->group(function () {
    Route::get('/chat', \App\Livewire\Chat\ChatPublico::class)->name('chat.publico');
});

Route::middleware('auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::post('/empresa/trocar', [EmpresaController::class, 'trocar'])->name('empresa.trocar');

    Route::prefix('{tenant}')->group(function () {
        Route::get('/clientes', GerenciarClientes::class)->name('clientes');
        Route::get('/processos', GerenciarProcessos::class)->name('processos');
        Route::get('/processos/{processo}', DetalheProcesso::class)->name('processos.detalhe');
        Route::get('/site', GerenciarSite::class)->name('site');
        Route::get('/chaves-gemini', GerenciarChavesGemini::class)->name('chaves-gemini');
        Route::get('/notificacoes', GerenciarNotificacoes::class)->name('notificacoes');
    });

    Route::get('/admin/empresas', ListaEmpresas::class)
        ->middleware('can:super-admin')
        ->name('admin.empresas');
});

// Micro-blog publico (sem auth e sem escopo de tenant).
Route::get('/blog/{site:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/blog/{site:slug}/{post:slug}', [BlogController::class, 'post'])->name('blog.post');

Route::get('/token-cnj/{data}', [TokenController::class, 'telaCnj'])
    ->name('getTokenCnj');
Route::post('/token-cnj', [TokenController::class, 'store'])
    ->name('postTokenCnj');

Route::get('teste-processo', function () {

    $token = TokenCnj::latest()->first();
    
    $response = Http::withToken(trim($token->token))->get(
        'https://portaldeservicos.pdpj.jus.br/api/v2/processos?numeroProcesso=00116324220245150033')->json();

    dd($response['content'][0]['tramitacoes'][0]['ultimoMovimento']['dataHora']);
    });
