<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Authorize Application - {{ config('app.name', 'MCP Server') }}</title>

    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Authorize MCP" />
    <link rel="manifest" href="/site.webmanifest" />

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="w-100" style="max-width: 28rem;">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                {{-- Header --}}
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle mb-3" style="width: 4rem; height: 4rem;">
                        <svg class="text-primary" width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>

                    <h1 class="h4 fw-semibold mb-2">Authorize {{ $client->name }}</h1>

                    <p class="text-body-secondary small mb-0">
                        This application will be able to use available MCP functionality.
                    </p>
                </div>

                {{-- User Info --}}
                <div class="border rounded p-3 bg-body-secondary mb-3">
                    <p class="text-body-secondary small mb-1">Logged in as:</p>
                    <p class="fw-medium mb-0">{{ $user->email }}</p>
                </div>

                {{-- Scopes / Permissions --}}
                @if(count($scopes) > 0)
                    <div class="mb-4">
                        <p class="small fw-semibold mb-2">Permissions:</p>
                        <ul class="list-unstyled mb-0">
                            @foreach($scopes as $scope)
                                <li class="d-flex align-items-start gap-2 mb-2">
                                    <span class="d-inline-block bg-primary rounded-circle flex-shrink-0 mt-2" style="width: .5rem; height: .5rem;"></span>
                                    <span class="small text-body-secondary">{{ $scope->description }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Buttons --}}
                <div class="d-flex gap-2">
                    {{-- Deny --}}
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-fill">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="btn btn-outline-secondary w-100">Cancel</button>
                    </form>

                    {{-- Approve --}}
                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-fill" id="authorizeForm">
                        @csrf
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="btn btn-primary w-100" id="authorizeButton">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="loadingSpinner" role="status" aria-hidden="true"></span>
                            <span id="authorizeText">Authorize</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('authorizeForm');
        const button = document.getElementById('authorizeButton');
        const authorizeText = document.getElementById('authorizeText');
        const loadingSpinner = document.getElementById('loadingSpinner');

        form.addEventListener('submit', function () {
            // Estado de carregamento
            button.disabled = true;
            authorizeText.textContent = 'Authorizing...';
            loadingSpinner.classList.remove('d-none');

            // Após o envio, observa o redirect e fecha a janela
            setTimeout(function () {
                const checkRedirect = setInterval(function () {
                    if (!window.location.href.includes('/oauth/authorize') ||
                        window.location.search.includes('code=') ||
                        window.location.search.includes('error=')) {
                        clearInterval(checkRedirect);
                        window.close();
                    }
                }, 100);

                // Fallback: fecha após 5 segundos
                setTimeout(function () {
                    clearInterval(checkRedirect);
                    window.close();
                }, 5000);
            }, 200);
        });

        // Botão cancelar
        const cancelForm = document.querySelector('form[method="POST"]:has(input[name="_method"][value="DELETE"])');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function () {
                setTimeout(function () { window.close(); }, 200);
            });
        }
    });
</script>
</body>
</html>
