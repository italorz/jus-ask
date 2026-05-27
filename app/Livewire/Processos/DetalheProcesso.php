<?php

namespace App\Livewire\Processos;

use App\Models\ConteudoProcesso;
use App\Models\Processo;
use App\Models\ProcessoConteudo;
use App\Services\TenantManager;
use Livewire\Component;

class DetalheProcesso extends Component
{
    public Processo $processo;

    public ?int $conteudoId = null;
    public string $numeroProcesso = '';
    public string $conteudo = '';

    public function mount(Processo $processo)
    {
        if (! app(TenantManager::class)->check()) {
            return redirect()->route('home');
        }

        $this->processo = $processo;
        $this->numeroProcesso = $processo->numero;
    }

    protected function rules(): array
    {
        return [
            'numeroProcesso' => ['required', 'string', 'max:100'],
            'conteudo' => ['required', 'string'],
        ];
    }

    public function editar(int $id): void
    {
        $registro = ConteudoProcesso::where('processo_id', $this->processo->id)->findOrFail($id);

        $this->conteudoId = $registro->id;
        $this->numeroProcesso = $registro->numero_processo;
        $this->conteudo = $registro->conteudo;
    }

    public function salvar(): void
    {
        $this->validate();

        $dados = [
            'processo_id' => $this->processo->id,
            'numero_processo' => $this->numeroProcesso,
            'conteudo' => $this->conteudo,
        ];

        if ($this->conteudoId) {
            ConteudoProcesso::where('processo_id', $this->processo->id)
                ->findOrFail($this->conteudoId)
                ->update($dados);
            session()->flash('status', 'Conteúdo atualizado.');
        } else {
            ConteudoProcesso::create($dados);
            session()->flash('status', 'Conteúdo adicionado.');
        }

        $this->resetForm();
    }

    public function excluir(int $id): void
    {
        ConteudoProcesso::where('processo_id', $this->processo->id)
            ->findOrFail($id)
            ->delete();
        session()->flash('status', 'Conteúdo removido.');
    }

    public function resetForm(): void
    {
        $this->reset(['conteudoId', 'conteudo']);
        $this->numeroProcesso = $this->processo->numero;
        $this->resetValidation();
    }

    public function render()
    {
        $apiSnapshots = ProcessoConteudo::where('processo_id', $this->processo->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($snap) {
                $raw = $snap->conteudo_json;
                // Registros antigos foram gravados como string (body); decodifica novamente se necessário.
                if (is_string($raw)) {
                    $raw = json_decode($raw, true);
                }
                $content = $raw['content'][0] ?? null;
                $tram    = $content['tramitacoes'][0] ?? null;
                return [
                    'id'                          => $snap->id,
                    'sincronizado_em'             => $snap->created_at,
                    'numero_processo'             => $content['numeroProcesso'] ?? null,
                    'tribunal_sigla'              => $tram['tribunal']['sigla'] ?? null,
                    'tribunal_nome'               => $tram['tribunal']['nome'] ?? null,
                    'segmento'                    => $tram['tribunal']['segmento'] ?? null,
                    'data_ajuizamento'            => $tram['dataHoraAjuizamento'] ?? null,
                    'data_ultima_distribuicao'    => $tram['dataHoraUltimaDistribuicao'] ?? null,
                    'valor_acao'                  => $tram['valorAcao'] ?? null,
                    'classe'                      => $tram['classe'][0]['descricao'] ?? null,
                    'assunto'                     => $tram['assunto'][0]['descricao'] ?? null,
                    'assunto_hierarquia'          => $tram['assunto'][0]['hierarquia'] ?? null,
                    'movimentos'                  => $tram['movimentos'] ?? [],
                ];
            });

        return view('livewire.processos.detalhe-processo', [
            'conteudos'    => ConteudoProcesso::where('processo_id', $this->processo->id)
                ->orderByDesc('created_at')
                ->get(),
            'apiSnapshots' => $apiSnapshots,
        ])->extends('layouts.app');
    }
}
