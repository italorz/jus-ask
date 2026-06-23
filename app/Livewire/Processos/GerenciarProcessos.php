<?php

namespace App\Livewire\Processos;

use App\Models\Cliente;
use App\Models\Processo;
use App\Services\ProcessoApiService;
use App\Services\TenantManager;
use Livewire\Component;
use Livewire\WithPagination;

class GerenciarProcessos extends Component
{
    use WithPagination;

    public ?int   $processoId        = null;
    public bool   $mostrarForm       = false;

    public ?int   $clienteId         = null;
    public string $numero            = '';
    public string $ultimaAtualizacao = '';
    public bool   $ativo             = true;

    // ── Pesquisa / filtros ──────────────────────────────
    public string $busca          = '';
    public string $filtroSituacao = ''; // '' | concluido | em_andamento
    public string $filtroAtivo    = ''; // '' | 1 | 0

    public function mount()
    {
        if (! app(TenantManager::class)->check()) {
            return redirect()->route('home');
        }
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroSituacao(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroAtivo(): void
    {
        $this->resetPage();
    }

    public function limparFiltros(): void
    {
        $this->reset(['busca', 'filtroSituacao', 'filtroAtivo']);
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'clienteId'         => ['nullable', 'integer'],
            'numero'            => ['required', 'string', 'max:100'],
            'ultimaAtualizacao' => ['nullable', 'date'],
            'ativo'             => ['boolean'],
        ];
    }

    public function novo(): void
    {
        $this->resetForm();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $processo = Processo::findOrFail($id);

        $this->processoId        = $processo->id;
        $this->clienteId         = $processo->cliente_id;
        $this->numero            = $processo->numero;
        $this->ultimaAtualizacao = $processo->ultima_atualizacao?->format('Y-m-d') ?? '';
        $this->ativo             = $processo->ativo;

        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        if ($this->clienteId) {
            Cliente::findOrFail($this->clienteId);
        }

        $dados = [
            'cliente_id'         => $this->clienteId,
            'numero'             => $this->numero,
            'ultima_atualizacao' => $this->ultimaAtualizacao ?: null,
            'ativo'              => $this->ativo,
        ];

        if ($this->processoId) {
            Processo::findOrFail($this->processoId)->update($dados);
            session()->flash('status', 'Processo atualizado.');
        } else {
            // Cadastro POR NÚMERO: upsert por tenant+número e ativo definido pela
            // análise do último movimento (concluído → inativo; em andamento → ativo).
            $tm = app(TenantManager::class);
            $res = ProcessoApiService::registrarPorNumero($this->numero, $tm->tenant(), $tm->id(), $this->clienteId);

            if ($res['sincronizado']) {
                $situacao = $res['processo']->situacao === 'concluido' ? 'concluído (inativo)' : 'em andamento (ativo)';
                session()->flash('status', ($res['novo'] ? 'Processo cadastrado' : 'Processo já existia e foi atualizado')
                    . " e sincronizado — {$situacao}.");
            } else {
                session()->flash('warning', 'Processo salvo. Não foi possível sincronizar com a API (PDPJ).');
            }
        }

        $this->resetForm();
        $this->mostrarForm = false;
    }

    public function sincronizar(int $id): void
    {
        $processo = Processo::findOrFail($id);

        // Sincronização manual = forçada: ignora a barreira de "sem novas atualizações".
        $res = ProcessoApiService::sincronizarForcado($processo);

        if ($res['ok']) {
            session()->flash('status', 'Processo sincronizado (forçado) com os dados atuais do PDPJ.');
        } else {
            session()->flash('warning', 'Não foi possível consultar o PDPJ agora. Tente novamente.');
        }
    }

    public function excluir(int $id): void
    {
        Processo::findOrFail($id)->delete();
        session()->flash('status', 'Processo removido.');
    }

    public function cancelar(): void
    {
        $this->resetForm();
        $this->mostrarForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['processoId', 'clienteId', 'numero', 'ultimaAtualizacao']);
        $this->ativo = true;
        $this->resetValidation();
    }

    public function render()
    {
        $termo = trim($this->busca);
        $digitos = preg_replace('/\D+/', '', $termo) ?? '';

        $processos = Processo::query()
            ->with('cliente')
            ->when($termo !== '', function ($q) use ($termo, $digitos) {
                $q->where(function ($sub) use ($termo, $digitos) {
                    // Campos do processo
                    $sub->where('numero', 'like', "%{$termo}%")
                        ->orWhere('assunto', 'like', "%{$termo}%")
                        ->orWhere('tribunal', 'like', "%{$termo}%")
                        ->orWhere('classe', 'like', "%{$termo}%");

                    // Número por dígitos (com ou sem máscara)
                    if ($digitos !== '') {
                        $sub->orWhereRaw("regexp_replace(numero, '\\D', '', 'g') like ?", ["%{$digitos}%"]);
                    }

                    // Dados do cliente
                    $sub->orWhereHas('cliente', function ($c) use ($termo, $digitos) {
                        $c->where('nome', 'like', "%{$termo}%")
                            ->orWhere('email', 'like', "%{$termo}%")
                            ->orWhere('cpf', 'like', "%{$termo}%")
                            ->orWhere('cnpj', 'like', "%{$termo}%")
                            ->orWhere('telefone', 'like', "%{$termo}%");

                        if ($digitos !== '') {
                            $c->orWhereRaw("regexp_replace(coalesce(cnpj,''), '\\D', '', 'g') like ?", ["%{$digitos}%"])
                              ->orWhereRaw("regexp_replace(coalesce(cpf,''), '\\D', '', 'g') like ?", ["%{$digitos}%"]);
                        }
                    });
                });
            })
            ->when($this->filtroSituacao !== '', fn ($q) => $q->where('situacao', $this->filtroSituacao))
            ->when($this->filtroAtivo !== '', fn ($q) => $q->where('ativo', $this->filtroAtivo === '1'))
            ->orderByDesc('ultima_atualizacao')
            ->paginate(15);

        return view('livewire.processos.gerenciar-processos', [
            'processos' => $processos,
            'clientes'  => Cliente::orderBy('nome')->get(),
        ])->extends('layouts.app');
    }
}
