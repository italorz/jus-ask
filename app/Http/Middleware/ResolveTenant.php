<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a empresa (tenant) ativa do usuario logado a partir da sessao.
 * Um usuario pode ser membro de varias empresas; a sessao guarda qual
 * esta selecionada (chave "empresa_ativa_id").
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $tenantManager = app(TenantManager::class);

        if ($user) {
            $tenantSlug = $request->route('tenant');

            if ($tenantSlug) {
                // Tenant vem da URL: valida que o usuário é membro.
                $membro = $user->membros()
                    ->whereHas('empresa', fn ($q) => $q->where('tenant', $tenantSlug))
                    ->where('ativo', true)
                    ->first();

                abort_unless($membro, 403, 'Você não tem acesso a este tenant.');

                $tenantManager->set($membro->empresa);
                $request->session()->put('empresa_ativa_id', $membro->empresa_id);
            } else {
                // Requests sem {tenant} (Livewire AJAX, /home, etc.) usam sessão.
                $empresaId = $request->session()->get('empresa_ativa_id');
                $membro    = null;

                if ($empresaId) {
                    $membro = $user->membros()
                        ->where('empresa_id', $empresaId)
                        ->where('ativo', true)
                        ->first();
                }

                if (! $membro) {
                    $membro = $user->membros()->where('ativo', true)->first();
                }

                if ($membro) {
                    $tenantManager->set($membro->empresa);
                    $request->session()->put('empresa_ativa_id', $membro->empresa_id);
                }
            }
        } else {
            // Rotas públicas com {tenant} na URL (ex: /{tenant}/chat).
            $tenantSlug = $request->route('tenant');
            if ($tenantSlug) {
                $empresa = \App\Models\Empresa::where('tenant', $tenantSlug)->first();
                if ($empresa) {
                    $tenantManager->set($empresa);
                }
            }
        }

        return $next($request);
    }
}
