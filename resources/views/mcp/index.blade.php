@extends('layouts.app')

@section('titulo', 'MCP - Jus-Ask')

@section('content')
    @php($tokenExemplo = $plainToken ?: 'SEU_TOKEN')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="mb-4">
                    <p class="text-muted mb-1">Integração MCP</p>
                    <h1 class="h3 mb-2">Conectar o Claude aos seus processos</h1>
                    <p class="text-muted mb-0">
                        Servidor MCP que permite ao Claude consultar os processos da sua empresa por
                        CNPJ (base do PDPJ/CNJ) e gerar análises e gráficos. O acesso usa
                        <strong>login OAuth</strong>: só quem tem conta neste sistema consegue conectar.
                    </p>
                </div>

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                {{-- Claude Desktop (OAuth) --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Claude Desktop</h2>
                        <ol class="mb-3 ps-3">
                            <li>Abra <strong>Settings → Connectors → Add custom connector</strong>.</li>
                            <li>Em <em>URL</em>, cole o endereço do servidor:</li>
                        </ol>
                        <pre class="bg-light border rounded p-3 mb-2"><code>{{ $mcpUrl }}</code></pre>
                        <p class="text-muted mb-0">
                            Ao conectar, o Claude abre o navegador para você <strong>entrar com a sua
                            conta</strong> deste sistema e autorizar o acesso. Sem conta, a conexão não
                            é concluída.
                        </p>
                    </div>
                </div>

                {{-- Claude Code (OAuth) --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Claude Code</h2>
                        <p class="text-muted mb-2">No terminal:</p>
                        <pre class="bg-light border rounded p-3 mb-2"><code>claude mcp add --transport http jusclaude {{ $mcpUrl }}</code></pre>
                        <p class="text-muted mb-0">
                            No primeiro uso, o Claude Code inicia o login OAuth automaticamente
                            (abre o navegador para autenticar). Confira com <code>claude mcp list</code>.
                        </p>
                    </div>
                </div>

                {{-- Uso --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Como usar no chat</h2>
                        <p class="text-muted mb-2">Depois de conectar, peça em linguagem natural:</p>
                        <pre class="bg-light border rounded p-3 mb-3"><code>Consulte os processos do CNPJ 52.123.916/0001-32.

Gere um gráfico dos processos do CNPJ 52123916000132 por tribunal e por ano.</code></pre>
                        <p class="text-muted mb-0 small">
                            A ferramenta <code>consultar-processos-por-cnpj</code> usa o token CNJ da
                            <strong>sua empresa</strong> (definido pelo usuário autenticado) e retorna
                            um resumo por processo com contagens por tribunal, ano e classe.
                        </p>
                    </div>
                </div>

                {{-- Acesso programático (REST, sem OAuth) --}}
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Acesso via API (opcional, sem OAuth)</h2>
                        <p class="text-muted mb-2">
                            Para integrações HTTP diretas (fora do Claude), há um endpoint REST
                            autenticado por token. Gere o token da empresa:
                        </p>

                        @if ($plainToken)
                            <p class="text-muted mb-2">
                                Copie agora — este token <strong>não será exibido novamente</strong>.
                            </p>
                            <pre class="bg-light border rounded p-3 mb-3"><code>{{ $plainToken }}</code></pre>
                        @elseif ($token)
                            <p class="text-muted mb-2">
                                Token ativo: <code>{{ $token->token_preview }}</code>
                                @if ($token->last_used_at)
                                    — último uso em {{ $token->last_used_at->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        @else
                            <p class="text-muted mb-2">Nenhum token gerado ainda.</p>
                        @endif

                        <form method="POST" action="{{ route('mcp.token.regenerate', ['tenant' => $tenant]) }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                {{ $token ? 'Gerar novo token' : 'Gerar token' }}
                            </button>
                        </form>

                        <pre class="bg-light border rounded p-3 mb-0"><code>POST {{ $restEndpoint }}
Authorization: Bearer {{ $tokenExemplo }}
Content-Type: application/json

{ "cnpj": "52123916000132" }</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
