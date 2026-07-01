<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Membro;
use App\Models\TokenCnj;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_servidor_mcp_web_exige_autenticacao(): void
    {
        // Sem token OAuth no header, o guard auth:api deve recusar.
        $this->postJson('/mcp/jusclaude', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
    }

    public function test_metadata_oauth_do_recurso_protegido_e_publica(): void
    {
        // Endpoint de descoberta usado pelo cliente MCP para iniciar o fluxo OAuth.
        $this->getJson('/.well-known/oauth-protected-resource/mcp/jusclaude')
            ->assertOk()
            ->assertJsonStructure(['authorization_servers']);
    }

    public function test_metadata_do_authorization_server_e_publica(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonStructure([
                'issuer',
                'authorization_endpoint',
                'token_endpoint',
                'registration_endpoint',
            ]);
    }

    public function test_usuario_autenticado_consulta_usando_o_tenant_da_sua_empresa(): void
    {
        $empresa = Empresa::create([
            'nome' => 'Empresa OAuth',
            'cnpj' => '12345678000199',
            'oab' => 'SP0001',
            'tenant' => '12345678000199',
            'is_pessoa_fisica' => false,
        ]);

        $user = User::create([
            'name' => 'Dono',
            'email' => 'dono@oauth.test',
            'password' => Hash::make('password'),
            'cpf' => '000',
            'oab' => 'SP0001',
        ]);

        Membro::create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'tenant' => $empresa->tenant,
            'papel' => 'dono',
            'ativo' => true,
        ]);

        TokenCnj::create(['token' => 'token-cnj', 'tenant' => $empresa->tenant]);

        Http::fake([
            'portaldeservicos.pdpj.jus.br/*' => Http::response([
                'content' => [[
                    'numeroProcesso' => '0011632-42.2024.5.15.0033',
                    'siglaTribunal' => 'TJSP',
                    'tramitacoes' => [[
                        'dataHoraAjuizamento' => '2024-03-10T10:00:00',
                        'tribunal' => ['nome' => 'TJSP', 'sigla' => 'TJSP'],
                        'classe' => [['descricao' => 'Comum']],
                        'assunto' => [['descricao' => 'Indenização']],
                    ]],
                ]],
            ], 200),
        ]);

        // Autentica no guard api (OAuth) sem passar pelo browser.
        Passport::actingAs($user, ['mcp:use']);

        $this->postJson('/mcp/jusclaude', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'consultar-processos-por-cnpj',
                'arguments' => ['cnpj' => '52123916000132'],
            ],
        ])->assertOk()->assertSee('por_tribunal');

        // Confirma que o token CNJ usado foi o do tenant da empresa do usuário.
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-cnj'));
    }
}
