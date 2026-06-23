<?php

namespace App\Livewire\Clientes;

use App\Models\ChaveGemini;
use App\Models\Cliente;
use App\Models\Processo;
use App\Services\ProcessoApiService;
use App\Services\TenantManager;
use Illuminate\Validation\Rule;
use Livewire\Component;

class GerenciarClientes extends Component
{
    public ?int  $clienteId    = null;
    public bool  $criandoNovo  = false;   // true enquanto cria (wizard); false ao editar (abas livres)
    public string $busca       = '';
    public string $filtroTipo  = '';     // '' | cliente | prospectado | prospeccao
    public string $abaAtiva    = 'dados'; // 'dados' | 'processos' | 'chave'

    // Campos do cliente
    public string $nome      = '';
    public string $telefone  = '';
    public string $email     = '';
    public string $cpf       = '';
    public string $endereco  = '';
    public string $numero    = '';
    public string $bairro    = '';
    public string $cidade    = '';
    public string $estado    = '';
    public string $cep       = '';
    public string $tipo      = 'cliente'; // funil: prospeccao | prospectado | cliente

    // Campos do processo (form interno)
    public bool   $mostrarFormProcesso      = false;
    public bool   $modoVincular             = false;  // toggle Novo/Vincular
    public ?int   $processoId               = null;
    public string $processoNumero           = '';
    public string $processoUltimaAtualizacao = '';
    public bool   $processoAtivo            = true;

    // Chave Gemini
    public ?int $chaveGeminiId = null;

    // ─────────────────────────────────────────────
    // Boot / mount
    // ─────────────────────────────────────────────

    public function mount(): void
    {
        if (! app(TenantManager::class)->check()) {
            redirect()->route('home');
        }
    }

    // ─────────────────────────────────────────────
    // Regras de validação
    // ─────────────────────────────────────────────

    protected function rulesCliente(): array
    {
        $tenant = app(TenantManager::class)->tenant();

        return [
            'nome'     => ['required', 'string', 'max:255'],
            'tipo'     => ['required', 'in:cliente,prospectado,prospeccao'],
            // telefone/email/cpf são opcionais (clientes de prospecção por CNPJ não têm).
            'telefone' => [
                'nullable', 'string', 'max:20',
                Rule::unique('clientes', 'telefone')->where('tenant', $tenant)->ignore($this->clienteId),
            ],
            'email'    => [
                'nullable', 'email', 'max:255',
                Rule::unique('clientes', 'email')->where('tenant', $tenant)->ignore($this->clienteId),
            ],
            'cpf'      => [
                'nullable', 'string', 'max:20',
                Rule::unique('clientes', 'cpf')->where('tenant', $tenant)->ignore($this->clienteId),
            ],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero'   => ['nullable', 'string', 'max:50'],
            'bairro'   => ['nullable', 'string', 'max:255'],
            'cidade'   => ['nullable', 'string', 'max:255'],
            'estado'   => ['nullable', 'string', 'max:2'],
            'cep'      => ['nullable', 'string', 'max:10'],
        ];
    }

    protected function rulesProcesso(): array
    {
        return [
            'processoNumero'            => ['required', 'string', 'max:100'],
            'processoUltimaAtualizacao' => ['nullable', 'date'],
            'processoAtivo'             => ['boolean'],
        ];
    }

    // ─────────────────────────────────────────────
    // CRUD Cliente
    // ─────────────────────────────────────────────

    public function novo(): void
    {
        $this->resetClienteForm();
        $this->criandoNovo = true;
        $this->dispatch('abrirModal');
    }

    public function editar(int $id): void
    {
        $cliente = Cliente::findOrFail($id);

        $this->clienteId      = $cliente->id;
        $this->nome           = (string) $cliente->nome;
        $this->tipo           = $cliente->tipo ?? 'cliente';
        $this->telefone       = (string) $cliente->telefone;
        $this->email          = (string) $cliente->email;
        $this->cpf            = (string) $cliente->cpf;
        $this->endereco       = (string) $cliente->endereco;
        $this->numero         = (string) $cliente->numero;
        $this->bairro         = (string) $cliente->bairro;
        $this->cidade         = (string) $cliente->cidade;
        $this->estado         = (string) $cliente->estado;
        $this->cep            = (string) $cliente->cep;
        $this->chaveGeminiId  = $cliente->chave_gemini_id;

        $this->criandoNovo         = false;
        $this->abaAtiva            = 'dados';
        $this->mostrarFormProcesso = false;
        $this->modoVincular        = false;

        $this->dispatch('abrirModal');
    }

    public function salvar(): void
    {
        $dados = $this->validate(
            $this->rulesCliente(),
            [],
            [
                'nome'     => 'nome',
                'telefone' => 'telefone',
                'email'    => 'e-mail',
                'cpf'      => 'CPF',
                'endereco' => 'endereço',
                'numero'   => 'número',
                'bairro'   => 'bairro',
                'cidade'   => 'cidade',
                'estado'   => 'estado',
                'cep'      => 'CEP',
            ]
        );

        // Campos com unique (tenant, col): vazio vira NULL para não colidir.
        foreach (['telefone', 'email', 'cpf'] as $campo) {
            if (($dados[$campo] ?? '') === '') {
                $dados[$campo] = null;
            }
        }

        if ($this->clienteId) {
            Cliente::findOrFail($this->clienteId)->update($dados);
            session()->flash('status', 'Cliente atualizado.');
        } else {
            $cliente         = Cliente::create($dados);
            $this->clienteId = $cliente->id;
            $this->criandoNovo = true; // mantém o wizard ativo
            $this->abaAtiva  = 'processos'; // avança automaticamente
        }

        $this->resetValidation();
    }

    public function cancelar(): void
    {
        $this->resetClienteForm();
        $this->dispatch('fecharModal');
    }

    public function excluir(int $id): void
    {
        Cliente::findOrFail($id)->delete();
        session()->flash('status', 'Cliente removido.');
    }

    // ─────────────────────────────────────────────
    // CRUD Processo (dentro do modal)
    // ─────────────────────────────────────────────

    public function novoProcesso(): void
    {
        $this->resetProcessoForm();
        $this->modoVincular        = false;
        $this->mostrarFormProcesso = true;
    }

    public function ativarVincular(): void
    {
        $this->resetProcessoForm();
        $this->modoVincular        = true;
        $this->mostrarFormProcesso = false;
    }

    public function editarProcesso(int $id): void
    {
        $processo = Processo::findOrFail($id);

        $this->processoId                 = $processo->id;
        $this->processoNumero             = $processo->numero;
        $this->processoUltimaAtualizacao  = $processo->ultima_atualizacao?->format('Y-m-d') ?? '';
        $this->processoAtivo              = $processo->ativo;
        $this->modoVincular               = false;
        $this->mostrarFormProcesso        = true;
    }

    public function salvarProcesso(): void
    {
        $this->validate(
            $this->rulesProcesso(),
            [],
            [
                'processoNumero'            => 'número do processo',
                'processoUltimaAtualizacao' => 'última atualização',
            ]
        );

        $dados = [
            'cliente_id'         => $this->clienteId,
            'numero'             => $this->processoNumero,
            'ultima_atualizacao' => $this->processoUltimaAtualizacao ?: null,
            'ativo'              => $this->processoAtivo,
        ];

        if ($this->processoId) {
            Processo::findOrFail($this->processoId)->update($dados);
            session()->flash('status', 'Processo atualizado.');
        } else {
            // Cadastro POR NÚMERO: upsert por tenant+número + ativo pela análise do
            // último movimento (concluído → inativo; em andamento → ativo).
            $tm = app(TenantManager::class);
            $res = ProcessoApiService::registrarPorNumero($this->processoNumero, $tm->tenant(), $tm->id(), $this->clienteId);

            if ($res['sincronizado']) {
                $situacao = $res['processo']->situacao === 'concluido' ? 'concluído (inativo)' : 'em andamento (ativo)';
                session()->flash('status', ($res['novo'] ? 'Processo cadastrado' : 'Processo já existia e foi atualizado')
                    . " e sincronizado — {$situacao}.");
            } else {
                session()->flash('warning', 'Processo salvo. Não foi possível sincronizar com a API (PDPJ).');
            }
        }

        $this->resetProcessoForm();
    }

    public function cancelarProcesso(): void
    {
        $this->resetProcessoForm();
    }

    public function excluirProcesso(int $id): void
    {
        Processo::findOrFail($id)->delete();
        $this->resetProcessoForm();
        session()->flash('status', 'Processo removido.');
    }

    public function sincronizarProcesso(int $id): void
    {
        $processo = Processo::findOrFail($id);
        $resultado = app(ProcessoApiService::class)->sincronizarComVerificacao($processo);

        if ($resultado['atualizado']) {
            session()->flash('status', 'Processo sincronizado com nova atualizacao.');
        } else {
            session()->flash('warning', 'Processo sincronizado sem novas atualizacoes.');
        }
    }

    /** Vincula um processo sem cliente ao cliente atual. */
    public function vincularProcesso(int $id): void
    {
        $processo = Processo::whereNull('cliente_id')->findOrFail($id);
        $processo->update(['cliente_id' => $this->clienteId]);

        $this->modoVincular = false;
        session()->flash('status', 'Processo vinculado com sucesso.');
    }

    // ─────────────────────────────────────────────
    // Chave Gemini
    // ─────────────────────────────────────────────

    public function salvarChaveGemini(): void
    {
        $this->validate([
            'chaveGeminiId' => ['nullable', 'integer', Rule::exists('chaves_gemini', 'id')],
        ]);

        Cliente::findOrFail($this->clienteId)->update(['chave_gemini_id' => $this->chaveGeminiId]);

        $this->dispatch('fecharModal');
        session()->flash('status', 'Cliente configurado com sucesso.');
        $this->resetClienteForm();
    }

    // ─────────────────────────────────────────────
    // Reset helpers
    // ─────────────────────────────────────────────

    protected function resetClienteForm(): void
    {
        $this->reset([
            'clienteId', 'criandoNovo',
            'nome', 'tipo', 'telefone', 'email', 'cpf',
            'endereco', 'numero', 'bairro', 'cidade', 'estado', 'cep',
            'chaveGeminiId',
        ]);
        $this->abaAtiva = 'dados';
        $this->resetProcessoForm();
        $this->resetValidation();
    }

    protected function resetProcessoForm(): void
    {
        $this->reset([
            'processoId', 'processoNumero',
            'processoUltimaAtualizacao', 'modoVincular',
        ]);
        $this->processoAtivo       = true;
        $this->mostrarFormProcesso = false;
        $this->resetValidation();
    }

    // ─────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────

    public function render()
    {
        $clientes = Cliente::query()
            ->withCount('processos')
            ->when($this->filtroTipo, fn ($q) => $q->where('tipo', $this->filtroTipo))
            ->when($this->busca, fn ($q) => $q->where(function ($sub) {
                $sub->where('nome', 'like', "%{$this->busca}%")
                    ->orWhere('email', 'like', "%{$this->busca}%")
                    ->orWhere('cpf', 'like', "%{$this->busca}%")
                    ->orWhere('cnpj', 'like', "%{$this->busca}%");
            }))
            ->orderBy('nome')
            ->get();

        // LAZY: só carrega os processos do cliente quando a aba "processos" está ativa
        // (cap de 300 para o modal não pesar em prospecções com muitos processos).
        $processos = ($this->clienteId && $this->abaAtiva === 'processos')
            ? Processo::where('cliente_id', $this->clienteId)
                ->orderByDesc('ultima_atualizacao')
                ->limit(300)
                ->get()
            : collect();

        $chavesGemini = ChaveGemini::orderBy('apelido')->get();

        // Só carrega a lista de processos disponíveis quando estiver no modo "vincular".
        $processosDisponiveis = $this->modoVincular
            ? Processo::whereNull('cliente_id')->orderBy('numero')->limit(300)->get()
            : collect();

        return view('livewire.clientes.gerenciar-clientes', compact(
            'clientes',
            'processos',
            'chavesGemini',
            'processosDisponiveis',
        ))->extends('layouts.app');
    }
}
