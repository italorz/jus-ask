<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\HomeController;
use App\Livewire\Admin\ListaEmpresas;
use App\Livewire\ChavesGemini\GerenciarChavesGemini;
use App\Livewire\TokenCnj\GerenciarTokenCnj;
use App\Livewire\Clientes\GerenciarClientes;
use App\Livewire\Processos\DetalheProcesso;
use App\Livewire\Processos\GerenciarProcessos;
use App\Livewire\Site\GerenciarSite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
    });

    Route::get('/admin/empresas', ListaEmpresas::class)
        ->middleware('can:super-admin')
        ->name('admin.empresas');

    Route::get('/token-cnj', GerenciarTokenCnj::class)
        // ->middleware('can:super-admin')
        ->name('admin.token-cnj');
});

// Micro-blog publico (sem auth e sem escopo de tenant).
Route::get('/blog/{site:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/blog/{site:slug}/{post:slug}', [BlogController::class, 'post'])->name('blog.post');

// Route::get('teste-processo', function(){
//     $url = 'https://portaldeservicos.pdpj.jus.br/api/v2/processos?numeroProcesso=00116324220245150033';

//     try {
//         $response = Http::withToken('eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICI1dnJEZ1hCS21FLTdFb3J2a0U1TXU5VmxJZF9JU2dsMnY3QWYyM25EdkRVIn0.eyJleHAiOjE3NzkzNTQ3MjgsImlhdCI6MTc3OTMyNTkyOCwiYXV0aF90aW1lIjoxNzc5MzI1OTI4LCJqdGkiOiIyODc5MzA3Zi04NjdhLTRiNWEtOTMwOC04ZjM3ODE2YmU1NGQiLCJpc3MiOiJodHRwczovL3Nzby5jbG91ZC5wamUuanVzLmJyL2F1dGgvcmVhbG1zL3BqZSIsImF1ZCI6WyJicm9rZXIiLCJhY2NvdW50Il0sInN1YiI6ImY1ZWEyZmU0LWMwMWEtNDZlNy05YzU1LWUyZWZhZTE2ZjQ1NyIsInR5cCI6IkJlYXJlciIsImF6cCI6InBvcnRhbGV4dGVybm8tZnJvbnRlbmQiLCJub25jZSI6ImQ5ZGEzMWE2LTljM…JQU5JIiwicHJlZmVycmVkX3VzZXJuYW1lIjoiNDYwMTA0Nzg4OTgiLCJnaXZlbl9uYW1lIjoiSVRBTE8gUklDQ0kiLCJmYW1pbHlfbmFtZSI6IlpVTElBTkkiLCJlbWFpbCI6Iml0YWxvcnp1bGlhbmlAZ21haWwuY29tIn0.tsVnFoJPO99VbKyIhKjk74BMBAZtj1Q_xo8OmbiGyZpgIkhBHdO8lbzDpFw2QRAVeYq8M9oZEOD0PPT2o-XrMPXVfbBdXm8XDlppalEJ0kyzjmwsnhK4tqXBEZKJnf51Xu8DrVsOlF_fRv_AcVi9unw60tgIyOex5R1FFLKoyoud-KGpDJbDh03YBWGRaBJilwfTKrpBmNrat1YM--CIehJQbfSa5qCsZe8ttZd3Ph5H0l7lToH__Ggf7fL5jY6akd8cmnLNaC7A1Gp8yMmENn9trzT5V78YTpUm4jxoW43cRZ5BHXvNj-n2kedyFWLaFEKs2YzgcLBZTvsgh3qbIg')
//             ->withHeaders(['User-Agent' => 'curl/8.19.0'])
//             ->timeout(15)
//             ->withoutVerifying()
//             ->get($url);

//         dd([
//             'status' => $response->status(),
//             'body'   => $response->json() ?? $response->body(),
//         ]);
//     } catch (\Exception $e) {
//         dd('FALHOU: ' . $e->getMessage());
//     }
// });
