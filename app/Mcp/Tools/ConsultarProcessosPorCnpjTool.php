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
#[Description('Consulta os processos de uma parte (pessoa jurídica) por CNPJ na base do PDPJ/CNJ. Retorna agregações por tribunal, ano e classe (prontas para gráficos), o total real e uma amostra dos processos. Consultas grandes (milhares de processos) são processadas em segundo plano: a primeira chamada retorna status "processing" e as chamadas seguintes retornam o andamento e, ao final, o resultado completo.')]
#[IsReadOnly]
#[IsIdempotent]
class ConsultarProcessosPorCnpjTool extends Tool
{
    public function handle(Request $request, McpProcessoService $service): Response|ResponseFactory
    {
        $validated = $request->validate([
            'cnpj' => ['required', 'string'],
            'tenant' => ['nullable', 'string'],
            'atualizar' => ['nullable', 'boolean'],
        ], [
            'cnpj.required' => 'Informe o CNPJ da parte (14 dígitos), ex.: 52123916000132.',
        ]);

        $tenant = $this->resolverTenant($request, $validated['tenant'] ?? null);

        try {
            $resultado = McpProcessoService::consultar(
                $validated['cnpj'],
                $tenant,
                (bool) ($validated['atualizar'] ?? false),
            );
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (RequestException $e) {
            return Response::error('Falha ao consultar a API do PDPJ (HTTP ' . $e->response->status() . ').');
        } catch (\Throwable $e) {
            return Response::error('Não foi possível consultar os processos: ' . $e->getMessage());
        }

        return Response::structured($resultado);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cnpj' => $schema->string()
                ->description('CNPJ da parte, com ou sem máscara (14 dígitos).')
                ->required(),

            'tenant' => $schema->string()
                ->description('Tenant da empresa cujo token CNJ deve ser usado. Opcional; quando omitido, usa o token padrão.'),

            'atualizar' => $schema->boolean()
                ->description('Opcional. Força refazer a consulta ignorando o cache (use só se quiser dados novos).'),
        ];
    }

    /**
     * Resolve o tenant cujo token CNJ será usado.
     *
     * No transporte web (OAuth) há um usuário autenticado: o tenant é restrito às
     * empresas das quais ele é membro ativo. No transporte local (stdio, sem usuário)
     * usa o argumento ou o fallback.
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
