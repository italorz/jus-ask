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
#[Description('Consulta os processos de uma parte (pessoa jurídica) por CNPJ na base do PDPJ/CNJ e salva no banco do sistema. A 1ª chamada coleta os processos (cadastra a empresa como Cliente e grava cada processo como inativo, fora do monitoramento) — consultas grandes rodam em segundo plano. Chamadas seguintes retornam o andamento, as agregações (tribunal/ano/classe) e, via parâmetro "pagina", os processos já salvos no banco em lotes. Use "cancelar" para interromper uma coleta em andamento.')]
#[IsReadOnly]
#[IsIdempotent]
class ConsultarProcessosPorCnpjTool extends Tool
{
    public function handle(Request $request, McpProcessoService $service): Response|ResponseFactory
    {
        $validated = $request->validate([
            'cnpj' => ['required', 'string'],
            'tenant' => ['nullable', 'string'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'atualizar' => ['nullable', 'boolean'],
            'cancelar' => ['nullable', 'boolean'],
        ], [
            'cnpj.required' => 'Informe o CNPJ da parte (14 dígitos), ex.: 52123916000132.',
        ]);

        $tenant = $this->resolverTenant($request, $validated['tenant'] ?? null);

        if (empty($tenant)) {
            return Response::error('Não foi possível resolver a empresa (tenant) para esta consulta.');
        }

        try {
            // Cancelar uma coleta em andamento.
            if (! empty($validated['cancelar'])) {
                return Response::structured(McpProcessoService::cancelar($validated['cnpj'], $tenant));
            }

            // Ler os processos já salvos no banco, em lotes (processo a processo / por página).
            if (! empty($validated['pagina'])) {
                return Response::structured(McpProcessoService::lerDoBanco($validated['cnpj'], $tenant, (int) $validated['pagina']));
            }

            // Disparar/consultar (coleta + salva no banco) e devolver status/agregações.
            return Response::structured(McpProcessoService::consultar(
                $validated['cnpj'],
                $tenant,
                (bool) ($validated['atualizar'] ?? false),
            ));
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (RequestException $e) {
            return Response::error('Falha ao consultar a API do PDPJ (HTTP ' . $e->response->status() . ').');
        } catch (\Throwable $e) {
            return Response::error('Não foi possível consultar os processos: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cnpj' => $schema->string()
                ->description('CNPJ da parte, com ou sem máscara (14 dígitos).')
                ->required(),

            'tenant' => $schema->string()
                ->description('Tenant da empresa cujo token CNJ deve ser usado. Opcional; quando omitido, usa o do usuário/padrão.'),

            'pagina' => $schema->integer()
                ->description('Opcional. Lê os processos já salvos no banco em lotes (página 1, 2, ...).'),

            'atualizar' => $schema->boolean()
                ->description('Opcional. Força refazer a coleta no PDPJ ignorando o cache.'),

            'cancelar' => $schema->boolean()
                ->description('Opcional. Cancela uma coleta em andamento (mantém o que já foi salvo).'),
        ];
    }

    /**
     * Resolve o tenant cujo token CNJ será usado e onde os dados serão salvos.
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
