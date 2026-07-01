<?php

use App\Mcp\Servers\ProcessosServer;
use Laravel\Mcp\Facades\Mcp;

// Servidor local (stdio) — usado pelo Claude Code via `php artisan mcp:start jusclaude`.
Mcp::local('jusclaude', ProcessosServer::class);

// Rotas de descoberta OAuth 2.1 e registro dinâmico de client (RFC 7591/8414).
Mcp::oauthRoutes();

// Servidor web (HTTP) — autenticado via OAuth (Passport). Exige login no sistema.
Mcp::web('/mcp/jusclaude', ProcessosServer::class)
    ->middleware('auth:api');
