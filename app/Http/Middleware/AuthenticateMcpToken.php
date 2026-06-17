<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Models\McpToken;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica o servidor MCP web pelo token MCP por empresa (Authorization: Bearer).
 * Quando válido, resolve a empresa do token e ativa o TenantManager, de modo que
 * a tool herde o tenant automaticamente.
 */
class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return response()->json(['message' => 'Token MCP ausente.'], 401);
        }

        $mcpToken = McpToken::where('token_hash', hash('sha256', $plainToken))
            ->latest()
            ->first();

        if (! $mcpToken) {
            return response()->json(['message' => 'Token MCP inválido.'], 401);
        }

        $mcpToken->update(['last_used_at' => now()]);

        $empresa = Empresa::where('tenant', $mcpToken->tenant)->first();
        if ($empresa) {
            app(TenantManager::class)->set($empresa);
        }

        return $next($request);
    }
}
