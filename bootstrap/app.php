<?php

use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve o tenant em todo request web (inclusive nas chamadas
        // /livewire/update), antes do route-model binding, garantindo que
        // o global scope multi-tenant esteja sempre ativo.
        $middleware->web(append: [
            ResolveTenant::class,
            \App\Http\Middleware\CheckSessionExpiry::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);

        // APIs externas (Evolution API e MCP) não enviam token CSRF.
        $middleware->validateCsrfTokens(except: [
            'mcp/processos',
            'mcp/jusclaude',
            'webhooks/whatsapp',
            '*/webhooks/whatsapp',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
