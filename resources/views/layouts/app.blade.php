<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Jus-Ask') }}</title>

    {{-- Aplica data-theme antes do CSS para evitar flash --}}
    <script>(function(){var t=localStorage.getItem('jus-theme')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles

    <style>
        /* ===== Layout com menu lateral ===== */
        .app-shell { display: flex; min-height: 100vh; }

        .app-sidebar {
            width: 250px; flex: 0 0 250px;
            background: var(--bg-nav);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; bottom: 0;
            height: 100vh; overflow-y: auto;
            z-index: 1040;
            transition: transform .22s ease;
        }
        .sidebar-brand {
            padding: 1rem 1.25rem;
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem; font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-brand a { color: #fff; text-decoration: none; }
        .sidebar-brand .brand-icon { color: var(--accent); margin-right: .4rem; }

        .sidebar-nav { padding: .6rem 0; display: flex; flex-direction: column; gap: .1rem; }
        .sidebar-section {
            padding: .9rem 1.25rem .3rem; font-size: .67rem;
            letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.4);
        }
        .sidebar-link {
            display: flex; align-items: center; gap: .65rem;
            padding: .58rem 1.25rem;
            color: rgba(255,255,255,.82); text-decoration: none;
            font-size: .92rem; border-left: 3px solid transparent;
            transition: background .15s, color .15s, border-color .15s;
        }
        .sidebar-link .ico { width: 1.2rem; text-align: center; }
        .sidebar-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .sidebar-link.active { background: rgba(255,255,255,.10); color: #fff; border-left-color: var(--accent); font-weight: 600; }

        /* Coluna principal */
        .app-main {
            flex: 1 1 auto; min-width: 0;
            display: flex; flex-direction: column;
            margin-left: 250px; transition: margin-left .22s ease;
        }
        .app-topbar {
            display: flex; align-items: center; gap: .6rem;
            padding: .5rem 1.1rem; min-height: 56px;
            background: var(--bg-surface); border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 1030;
        }
        .btn-sidebar-toggle {
            background: transparent; border: 1px solid var(--border); color: var(--text);
            width: 38px; height: 38px; border-radius: .45rem;
            font-size: 1.15rem; line-height: 1; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
        }
        .btn-sidebar-toggle:hover { background: var(--bg-body); }
        .topbar-brand { color: var(--text); text-decoration: none; font-family: 'Playfair Display', serif; font-weight: 700; }
        .topbar-right { margin-left: auto; display: flex; align-items: center; gap: .3rem; }

        /* Estado oculto (desktop) */
        .app-shell.sidebar-hidden .app-sidebar { transform: translateX(-100%); }
        .app-shell.sidebar-hidden .app-main { margin-left: 0; }

        /* Sem sidebar (visitante) */
        .app-shell.no-sidebar .app-main { margin-left: 0; }

        /* Backdrop (mobile) */
        .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1035; }

        @media (max-width: 768px) {
            .app-main { margin-left: 0; }
            .app-sidebar { transform: translateX(-100%); }
            .app-shell.sidebar-open .app-sidebar { transform: translateX(0); }
            .app-shell.sidebar-open .sidebar-backdrop { display: block; }
        }

        /* ===== Loading global (ondas/ripple) ===== */
        .global-loading {
            position: fixed; inset: 0; z-index: 2000;
            display: none; align-items: center; justify-content: center;
            background: rgba(0, 0, 0, .12);
        }
        .global-loading.show { display: flex; }
        .wave-loader { position: relative; width: 72px; height: 72px; }
        .wave-loader div {
            position: absolute; border: 4px solid var(--accent, #0d6efd);
            opacity: 1; border-radius: 50%;
            animation: waveRipple 1.2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
        }
        .wave-loader div:nth-child(2) { animation-delay: -0.6s; }
        @keyframes waveRipple {
            0%   { top: 32px; left: 32px; width: 0; height: 0; opacity: 1; }
            100% { top: 0; left: 0; width: 64px; height: 64px; opacity: 0; }
        }

        /* ===== Paginação no tema escuro ===== */
        [data-theme="dark"] .page-link {
            background-color: var(--bg-surface);
            border-color: var(--border);
            color: var(--text);
        }
        [data-theme="dark"] .page-link:hover {
            background-color: var(--bg-body);
            border-color: var(--border);
            color: var(--text);
        }
        [data-theme="dark"] .page-item.active .page-link {
            background-color: var(--accent);
            border-color: var(--accent);
            color: #1c2b3a;
        }
        [data-theme="dark"] .page-item.disabled .page-link {
            background-color: var(--bg-surface);
            border-color: var(--border);
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    @php
        $tenant = app(\App\Services\TenantManager::class);
    @endphp

    <div id="appShell" class="app-shell @guest no-sidebar @endguest">

        @auth
            {{-- ===== Menu lateral ===== --}}
            <aside class="app-sidebar" id="appSidebar">
                <div class="sidebar-brand">
                    <a href="{{ url('/') }}"><span class="brand-icon">⚖</span>{{ config('app.name', 'Jus-Ask') }}</a>
                </div>

                <nav class="sidebar-nav">
                    <a class="sidebar-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                        <i class="ico bi bi-house"></i> Painel
                    </a>
                    <a class="sidebar-link {{ request()->routeIs('consulta-cnpj') ? 'active' : '' }}" href="{{ route('consulta-cnpj') }}">
                        <i class="ico bi bi-search"></i> Consulta CNPJ
                    </a>

                    @if ($tenant->check())
                        <div class="sidebar-section">{{ $tenant->empresa()?->nome ?? 'Empresa' }}</div>

                        <a class="sidebar-link {{ request()->routeIs('clientes') ? 'active' : '' }}" href="{{ route('clientes', ['tenant' => $tenant->tenant()]) }}">
                            <i class="ico bi bi-people"></i> Clientes
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('processos') ? 'active' : '' }}" href="{{ route('processos', ['tenant' => $tenant->tenant()]) }}">
                            <i class="ico bi bi-briefcase"></i> Processos
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('graficos') ? 'active' : '' }}" href="{{ route('graficos', ['tenant' => $tenant->tenant()]) }}">
                            <i class="ico bi bi-bar-chart"></i> Gráficos
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('crm') ? 'active' : '' }}" href="{{ route('crm', ['tenant' => $tenant->tenant()]) }}">
                            <i class="ico bi bi-kanban"></i> CRM
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('site') ? 'active' : '' }}" href="{{ route('site', ['tenant' => $tenant->tenant()]) }}">
                            <i class="ico bi bi-globe2"></i> Meu site
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('chaves-gemini') ? 'active' : '' }}" href="{{ route('chaves-gemini', ['tenant' => $tenant->tenant()]) }}">
                            <i class="ico bi bi-key"></i> Chaves IA
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('mcp.*') ? 'active' : '' }}" href="{{ route('mcp.index', ['tenant' => $tenant->tenant()]) }}">
                            <i class="ico bi bi-plug"></i> MCP
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('token-cnj.atual') ? 'active' : '' }}" href="{{ route('token-cnj.atual', ['tenant' => $tenant->tenant()]) }}">
                            <i class="ico bi bi-shield-lock"></i> Token CNJ
                        </a>
                    @endif

                    @can('super-admin')
                        <div class="sidebar-section">Admin</div>
                        <a class="sidebar-link {{ request()->routeIs('admin.empresas') ? 'active' : '' }}" href="{{ route('admin.empresas') }}">
                            <i class="ico bi bi-building"></i> Empresas
                        </a>
                    @endcan
                </nav>
            </aside>

            <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
        @endauth

        {{-- ===== Coluna principal ===== --}}
        <div class="app-main">
            <header class="app-topbar">
                @auth
                    <button class="btn-sidebar-toggle" id="btnSidebarToggle" type="button"
                            title="Ocultar/exibir menu" aria-label="Alternar menu">☰</button>
                @endauth

                <a class="topbar-brand {{ auth()->check() ? 'd-none d-md-none' : '' }}" href="{{ url('/') }}">
                    <span style="color:var(--accent)">⚖</span> {{ config('app.name', 'Jus-Ask') }}
                </a>

                <div class="topbar-right">
                    <button id="btn-theme-toggle" title="Alternar tema claro/escuro">🌙</button>

                    @guest
                        @if (Route::has('login'))
                            <a class="nav-link" href="{{ route('login') }}">Entrar</a>
                        @endif
                        @if (Route::has('register'))
                            <a class="nav-link" href="{{ route('register') }}">Criar conta</a>
                        @endif
                    @else
                        @if ($tenant->check())
                            @php $notifCount = \App\Models\Notificacao::where('lida', false)->count(); @endphp
                            <a class="nav-link position-relative px-2"
                               href="{{ route('notificacoes', ['tenant' => $tenant->tenant()]) }}"
                               title="Notificações">
                                <i class="bi bi-bell fs-5"></i>
                                @if ($notifCount > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                          style="font-size:.55rem;padding:.25em .45em;">
                                        {{ $notifCount > 99 ? '99+' : $notifCount }}
                                    </span>
                                @endif
                            </a>
                        @endif

                        @if (auth()->user()->empresas->isNotEmpty())
                            <div class="dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button"
                                   data-bs-toggle="dropdown" aria-expanded="false">
                                    <span style="font-size:.73rem;opacity:.65;margin-right:.15rem;">empresa</span>
                                    <strong>{{ $tenant->empresa()?->nome ?? '—' }}</strong>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @foreach (auth()->user()->empresas as $empresa)
                                        <li>
                                            <form action="{{ route('empresa.trocar') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="empresa_id" value="{{ $empresa->id }}">
                                                <button type="submit"
                                                        class="dropdown-item {{ $tenant->id() === $empresa->id ? 'active' : '' }}">
                                                    {{ $empresa->nome }}
                                                    <small class="text-muted d-block" style="font-size:.74rem;">
                                                        {{ $empresa->tenant }}
                                                    </small>
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                {{ Auth::user()->name }}
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    Sair
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </div>
                        </div>
                    @endguest
                </div>
            </header>

            <main class="py-4">
                <div class="container">
                    @if (session('status'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('status') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if (session('erro'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ session('erro') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                </div>
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            var shell = document.getElementById('appShell');
            var btn = document.getElementById('btnSidebarToggle');
            var backdrop = document.getElementById('sidebarBackdrop');
            if (!shell || !btn) return;

            var KEY = 'jus-sidebar-hidden';
            function isMobile() { return window.matchMedia('(max-width: 768px)').matches; }

            // Restaura estado (apenas desktop)
            if (!isMobile() && localStorage.getItem(KEY) === '1') {
                shell.classList.add('sidebar-hidden');
            }

            btn.addEventListener('click', function () {
                if (isMobile()) {
                    shell.classList.toggle('sidebar-open');
                } else {
                    shell.classList.toggle('sidebar-hidden');
                    localStorage.setItem(KEY, shell.classList.contains('sidebar-hidden') ? '1' : '0');
                }
            });

            if (backdrop) {
                backdrop.addEventListener('click', function () { shell.classList.remove('sidebar-open'); });
            }
        })();
    </script>

    {{-- Overlay de loading global (ondas) --}}
    <div id="global-loading" class="global-loading" aria-hidden="true">
        <div class="wave-loader"><div></div><div></div></div>
    </div>

    @livewireScripts

    <script>
        // Mostra o loader em ondas sempre que uma ação do Livewire demorar (> 250ms),
        // para o usuário saber que precisa aguardar. Some assim que a resposta chega.
        document.addEventListener('livewire:init', () => {
            const overlay = document.getElementById('global-loading');
            if (!overlay) return;

            Livewire.hook('commit', ({ respond }) => {
                const timer = setTimeout(() => overlay.classList.add('show'), 250);
                respond(() => {
                    clearTimeout(timer);
                    overlay.classList.remove('show');
                });
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
