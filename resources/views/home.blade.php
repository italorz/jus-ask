@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="h3 mb-4">Painel</h1>

    @if (! $empresa)
        <div class="alert alert-warning">
            Nenhuma empresa (tenant) ativa. Use o menu superior para selecionar uma empresa.
        </div>
    @else
        <div class="alert alert-info">
            Empresa ativa: <strong>{{ $empresa->nome }}</strong>
            &mdash; tenant: <code>{{ $empresa->tenant }}</code>
            @if ($empresa->is_pessoa_fisica)
                <span class="badge bg-secondary">pessoa física (OAB)</span>
            @else
                <span class="badge bg-secondary">empresa (CNPJ)</span>
            @endif
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="display-6">{{ $totalClientes }}</div>
                        <div class="text-muted">Clientes</div>
                        <a href="{{ route('clientes', ['tenant' => $empresa->tenant]) }}" class="stretched-link"></a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="display-6">{{ $totalProcessos }}</div>
                        <div class="text-muted">Processos</div>
                        <a href="{{ route('processos', ['tenant' => $empresa->tenant]) }}" class="stretched-link"></a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="display-6">{{ $processosAbertos }}</div>
                        <div class="text-muted">Processos em aberto</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (! is_null($totalEmpresas))
        <div class="card mt-4">
            <div class="card-body">
                <h2 class="h5">Administração</h2>
                <p class="mb-2">Você é super-admin. Total de empresas (tenants) no sistema: <strong>{{ $totalEmpresas }}</strong>.</p>
                <a href="{{ route('admin.empresas') }}" class="btn btn-sm btn-outline-primary">Ver todas as empresas</a>
            </div>
        </div>
    @endif
</div>
@endsection
