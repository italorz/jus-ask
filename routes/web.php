<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\VerificarProcessosController;
use App\Http\Controllers\WhatsappWebhookController;
use App\Livewire\Admin\ListaEmpresas;
use App\Livewire\Chat\ChatPublico;
use App\Livewire\ChavesGemini\GerenciarChavesGemini;
use App\Livewire\Clientes\GerenciarClientes;
use App\Livewire\Notificacoes\GerenciarNotificacoes;
use App\Livewire\Crm\Kanban;
use App\Livewire\Processos\ConsultaProcessoCnpj;
use App\Livewire\Processos\DetalheProcesso;
use App\Livewire\Processos\GerenciarProcessos;
use App\Livewire\Processos\GraficosProcessos;
use App\Livewire\Site\GerenciarSite;
use App\Livewire\Tokens\TokenCnjAtual;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Raiz e autenticação
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome');
Auth::routes(['verify' => false]);

/*
|--------------------------------------------------------------------------
| APIs sem tenant (autenticação própria por token, não por sessão)
|--------------------------------------------------------------------------
*/

// Endpoint MCP: consulta de processos por CNPJ. Não pertence a nenhum tenant;
// é autenticado pelo token MCP gerado na tela /{tenant}/mcp.
Route::post('/mcp/processos', [McpController::class, 'consultarProcessos'])
    ->name('mcp.processos');

// Webhook da Evolution API (WhatsApp). Autenticado por token na query (?token=).
// Preferencial com tenant no path; o fallback sem tenant aceita ?tenant=.
Route::post('/{tenant}/webhooks/whatsapp', [WhatsappWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp.tenant');
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp');

// Verificação de todos os processos ativos (estilo cron). Token na query (?token=).
Route::get('/processos/verificar', VerificarProcessosController::class)
    ->name('processos.verificar');

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
*/

// Chat público do cliente (com tenant, sem login).
Route::get('/{tenant}/chat', ChatPublico::class)->name('chat.publico');

// Micro-blog público (sem tenant).
Route::get('/blog/{site:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/blog/{site:slug}/{post:slug}', [BlogController::class, 'post'])->name('blog.post');

// Cadastro de token CNJ via link (com tenant).
Route::get('/{tenant}/token-cnj/{data}', [TokenController::class, 'telaCnj'])->name('getTokenCnj');
Route::post('/{tenant}/token-cnj', [TokenController::class, 'store'])->name('postTokenCnj');

/*
|--------------------------------------------------------------------------
| Rotas autenticadas (painel)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::post('/empresa/trocar', [EmpresaController::class, 'trocar'])->name('empresa.trocar');

    // Tela de teste: consulta de processos por CNPJ reaproveitando a lógica da tool MCP.
    // Não é tenant-scoped — usa sempre o último token CNJ gerado.
    Route::get('/consulta-processo-cnpj', ConsultaProcessoCnpj::class)->name('consulta-cnpj');

    Route::get('/admin/empresas', ListaEmpresas::class)
        ->middleware('can:super-admin')
        ->name('admin.empresas');

    // Painel da empresa (com tenant).
    Route::prefix('{tenant}')->group(function () {
        Route::get('/clientes', GerenciarClientes::class)->name('clientes');
        Route::get('/processos', GerenciarProcessos::class)->name('processos');
        Route::get('/graficos', GraficosProcessos::class)->name('graficos');
        Route::get('/crm', Kanban::class)->name('crm');
        Route::get('/processos/{processo}', DetalheProcesso::class)->name('processos.detalhe');
        Route::get('/site', GerenciarSite::class)->name('site');
        Route::get('/chaves-gemini', GerenciarChavesGemini::class)->name('chaves-gemini');
        Route::get('/notificacoes', GerenciarNotificacoes::class)->name('notificacoes');

        // Gestão do token MCP (a consulta em si é a rota global mcp.processos).
        Route::get('/mcp', [McpController::class, 'index'])->name('mcp.index');
        Route::post('/mcp/token', [McpController::class, 'regenerateToken'])->name('mcp.token.regenerate');

        // Último token CNJ cadastrado para o tenant ativo (visualização + copiar).
        Route::get('/token-cnj-atual', TokenCnjAtual::class)->name('token-cnj.atual');
    });
});
