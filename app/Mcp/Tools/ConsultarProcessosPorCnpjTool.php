<?php

namespace App\Mcp\Tools;

use App\Services\McpProcessoService;
use App\Services\TenantManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\RequestException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('consultar-processos-por-cnpj')]
#[Description('Consulta os processos de uma parte (pessoa jurídica) por CNPJ na base do PDPJ/CNJ. Retorna um resumo por processo (tribunal, classe, assunto, valor, ano) e agregações prontas para gráficos (por tribunal, por ano e por classe).')]
#[IsReadOnly]
#[IsIdempotent]
class ConsultarProcessosPorCnpjTool extends Tool
{
    public function handle(Request $request, McpProcessoService $service): Response|ResponseFactory
    {
        $validated = $request->validate([
            'cnpj' => ['required', 'string'],
            'tenant' => ['nullable', 'string'],
        ], [
            'cnpj.required' => 'Informe o CNPJ da parte (14 dígitos), ex.: 52123916000132.',
        ]);

        $tenant = $this->resolverTenant($request, $validated['tenant'] ?? null);

        try {
            $resultado = $service->consultarPorCnpj($validated['cnpj'], $tenant);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (RequestException $e) {
            return Response::error('Falha ao consultar a API do PDPJ (HTTP ' . $e->response->status() . ').');
        } catch (\Throwable $e) {
            return Response::error('Não foi possível consultar os processos: ' . $e->getMessage());
        }

        return Response::structured(McpProcessoService::resumir(
            $resultado['cnpj'],
            $resultado['data']['content'] ?? [],
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cnpj' => $schema->string()
                ->description('CNPJ da parte, com ou sem máscara (14 dígitos).')
                ->required(),

            'tenant' => $schema->string()
                ->description('Tenant da empresa cujo token CNJ deve ser usado. Opcional; quando omitido, usa o token padrão.'),
        ];
    }

    /**
     * Resolve o tenant cujo token CNJ será usado.
     *
     * No transporte web (OAuth) há um usuário autenticado: o tenant é restrito às
     * empresas das quais ele é membro ativo — assim ninguém usa o token CNJ de outra
     * empresa. No transporte local (stdio, sem usuário) usa o argumento ou o fallback.
     */
    private function resolverTenant(Request $request, ?string $tenantArg): ?string
    {
        $user = $request->user();

        if ($user) {
            $tenants = $user->membros()->where('ativo', true)->pluck('tenant');

            return $tenants->contains($tenantArg) ? $tenantArg : $tenants->first();
        }

        return $tenantArg ?? app(TenantManager::class)->tenant();
    }
}
