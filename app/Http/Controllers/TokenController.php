<?php

namespace App\Http\Controllers;

use App\Models\Notificacao;
use App\Models\Processo;
use App\Models\TokenCnj;
use App\Services\ProcessoApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function telaCnj(string $data)
    {
        $hoje = Carbon::now()->toDateString();
        if ($data !== $hoje) {
            abort(404);
        }

        return view('token-cnj');
    }

    public function store(Request $request)
    {
        TokenCnj::create([
            'token' => $request->input('token'),
        ]);

        $notificados = $this->sincronizarProcessosAtivos();

        return back()->with(
            'status',
            "Token salvo. Sincronização concluída — {$notificados} processo(s) com nova atualização."
        );
    }

    /**
     * Percorre todos os processos ativos (de todas as empresas, pois roda sem tenant),
     * consulta o CNJ e, quando a data do último movimento no CNJ for mais recente que a
     * última atualização gravada localmente, cria uma Notificação para o advogado.
     *
     * @return int Quantidade de processos que geraram notificação.
     */
    private function sincronizarProcessosAtivos(): int
    {
        $processos = Processo::withoutGlobalScopes()
            ->where('ativo', true)
            ->get();

        $notificados = 0;

        foreach ($processos as $processo) {
            try {
                $cnj = ProcessoApiService::consultarApi($processo);

                // API falhou ou retornou estrutura inesperada — pula sem interromper o lote.
                if ($cnj === null) {
                    continue;
                }

                $dataHoraCnj = $cnj['content'][0]['tramitacoes'][0]['ultimoMovimento']['dataHora'] ?? null;

                if ($dataHoraCnj === null) {
                    continue;
                }

                $movimentoCnj = Carbon::parse($dataHoraCnj);
                $localAtual   = $processo->ultima_atualizacao; // Carbon|null (cast no model)

                // Notifica quando nunca houve sincronização (null) ou o CNJ é mais recente.
                if ($localAtual !== null && $movimentoCnj->lessThanOrEqualTo($localAtual)) {
                    continue;
                }

                Notificacao::create([
                    'processo_id' => $processo->id,
                    'empresa_id'  => $processo->empresa_id,
                    'tenant'      => $processo->tenant,
                    'titulo'      => "{$processo->numero} - Processo Atualizado",
                    'mensagem'    => "Nova movimentação detectada no CNJ em {$movimentoCnj->format('d/m/Y H:i')}. "
                        . 'Entre em contato com o cliente.',
                    'lida'        => false,
                ]);

                // Avança a data local para não notificar novamente a mesma movimentação.
                $processo->update(['ultima_atualizacao' => $movimentoCnj]);

                $notificados++;
            } catch (\Throwable $e) {
                logger()->error('TokenController: falha ao sincronizar processo', [
                    'processo_id' => $processo->id,
                    'numero'      => $processo->numero,
                    'erro'        => $e->getMessage(),
                ]);
            }
        }

        return $notificados;
    }
}
