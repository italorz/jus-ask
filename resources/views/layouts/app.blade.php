<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Jus-Ask') }}</title>

    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    @php($tenant = app(\App\Services\TenantManager::class))
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-bold" href="{{ url('/') }}">
                    {{ config('app.name', 'Jus-Ask') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto">
                        @auth
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('home') }}">Painel</a>
                            </li>
                            @if ($tenant->check())
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('clientes', ['tenant' => $tenant->tenant()]) }}">Clientes</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('processos', ['tenant' => $tenant->tenant()]) }}">Processos</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('site', ['tenant' => $tenant->tenant()]) }}">Meu site</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('chaves-gemini', ['tenant' => $tenant->tenant()]) }}">Chaves Gemini</a>
                                </li>
                            @endif
                            @can('super-admin')
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.empresas') }}">Admin</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('admin.token-cnj') }}">Token CNJ</a>
                                </li>
                            @endcan
                        @endauth
                    </ul>

                    <ul class="navbar-nav ms-auto">
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">Entrar</a>
                                </li>
                            @endif
                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">Criar conta</a>
                                </li>
                            @endif
                        @else
                            @if (auth()->user()->empresas->isNotEmpty())
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Empresa: <strong>{{ $tenant->empresa()?->nome ?? '—' }}</strong>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @foreach (auth()->user()->empresas as $empresa)
                                            <li>
                                                <form action="{{ route('empresa.trocar') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="empresa_id" value="{{ $empresa->id }}">
                                                    <button type="submit" class="dropdown-item {{ $tenant->id() === $empresa->id ? 'active' : '' }}">
                                                        {{ $empresa->nome }}
                                                        <small class="text-muted d-block">tenant: {{ $empresa->tenant }}</small>
                                                    </button>
                                                </form>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endif

                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    {{ Auth::user()->name }}
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        Sair
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            <div class="container">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
                @if (session('erro'))
                    <div class="alert alert-danger">{{ session('erro') }}</div>
                @endif
            </div>
            @yield('content')
        </main>
    </div>

    @livewireScripts
</body>
</html>
