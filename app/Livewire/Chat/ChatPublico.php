<?php

namespace App\Livewire\Chat;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Services\GeminiService;
use League\CommonMark\CommonMarkConverter;
use Livewire\Component;

class ChatPublico extends Component
{
    // Contexto do tenant — gravado no mount() e persiste via estado Livewire
    public int    $empresaId   = 0;
    public string $tenantSlug  = '';
    public string $empresaNome = '';

    // Fase de identificação
    public bool   $identificado  = false;
    public string $telefoneInput = '';
    public string $erroTelefone  = '';
    public int    $clienteId     = 0;
    public string $clienteNome   = '';

    // Fase de chat
    public string $mensagemInput = '';
    public array  $messages      = [];
    public bool   $processando   = false;
    public string $systemPrompt  = '';
    public string $erroApi       = '';   // mensagem real de erro da API (vazia = sem erro)

    public function mount(string $tenant): void
    {
        $empresa = Empresa::where('tenant', $tenant)->firstOrFail();

        $this->empresaId   = $empresa->id;
        $this->tenantSlug  = $empresa->tenant;
        $this->empresaNome = $empresa->nome;
    }

    public function identificar(): void
    {
        $this->erroTelefone = '';

        if (empty(trim($this->telefoneInput))) {
            $this->erroTelefone = 'Informe seu número de telefone.';
            return;
        }

        $cliente = $this->buscarClientePorTelefone($this->telefoneInput);

        if (! $cliente) {
            $this->erroTelefone = 'Telefone não encontrado. Verifique o número ou entre em contato com o escritório.';
            return;
        }

        $this->clienteId   = $cliente->id;
        $this->clienteNome = $cliente->nome;

        $this->systemPrompt = app(GeminiService::class)
            ->buildSystemPrompt($this->empresaId, $this->clienteId);

        $this->identificado = true;

        $welcome = "Olá, {$cliente->nome}! Sou o assistente jurídico virtual. Posso responder perguntas sobre os seus processos cadastrados. Como posso ajudá-lo?";

        $this->messages[] = [
            'role' => 'model',
            'text' => $welcome,
            'html' => $this->toHtml($welcome),
            'hora' => now()->format('H:i'),
        ];

        $this->dispatch('scroll-to-bottom');
    }

    public function enviar(): void
    {
        $texto = trim($this->mensagemInput);

        if (! $texto || $this->processando || ! $this->identificado) {
            return;
        }

        $this->mensagemInput = '';
        $this->erroApi       = '';
        $this->processando   = true;

        // Captura o histórico ANTES de adicionar a mensagem atual.
        // Pula a mensagem de boas-vindas (index 0), que foi gerada localmente.
        $history = collect($this->messages)
            ->slice(1)
            ->map(fn ($m) => ['role' => $m['role'], 'text' => $m['text']])
            ->values()
            ->toArray();

        $this->messages[] = [
            'role' => 'user',
            'text' => $texto,
            'html' => null,
            'hora' => now()->format('H:i'),
        ];

        try {
            $resposta = app(GeminiService::class)->chat(
                empresaId:    $this->empresaId,
                clienteId:    $this->clienteId,
                systemPrompt: $this->systemPrompt,
                history:      $history,
                userMessage:  $texto,
            );

            $this->messages[] = [
                'role' => 'model',
                'text' => $resposta,
                'html' => $this->toHtml($resposta),
                'hora' => now()->format('H:i'),
            ];

            $this->dispatch('scroll-to-bottom');
        } catch (\Throwable $e) {
            // Remove a mensagem do usuário da lista para evitar histórico inconsistente
            array_pop($this->messages);
            $this->mensagemInput = $texto; // devolve o texto para o usuário reenviar
            $this->erroApi       = $e->getMessage();
        } finally {
            $this->processando = false;
        }
    }

    /**
     * Busca cliente por telefone com comparação normalizada (apenas dígitos).
     * O formato armazenado pode variar: "(11) 99999-9999", "11999999999", etc.
     */
    private function buscarClientePorTelefone(string $input): ?Cliente
    {
        // Tentativa exata primeiro
        $cliente = Cliente::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->where('telefone', $input)
            ->first();

        if ($cliente) {
            return $cliente;
        }

        $digitsInput = preg_replace('/\D/', '', $input);
        $inputNorm   = $this->normalizarDigitos($digitsInput);

        return Cliente::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->whereNotNull('telefone')
            ->get()
            ->first(function (Cliente $c) use ($digitsInput, $inputNorm) {
                $storedDigits = preg_replace('/\D/', '', (string) $c->telefone);
                $storedNorm   = $this->normalizarDigitos($storedDigits);

                return $storedNorm === $inputNorm || $storedDigits === $digitsInput;
            });
    }

    private function normalizarDigitos(string $digits): string
    {
        // Remove o código do país (55) se presente e o número tiver comprimento excessivo
        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            return substr($digits, 2);
        }
        return $digits;
    }

    private function toHtml(string $markdown): string
    {
        static $converter = null;
        $converter ??= new CommonMarkConverter([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($markdown)->getContent();
    }

    public function render()
    {
        return view('livewire.chat.chat-publico')
            ->extends('layouts.chat');
    }
}
