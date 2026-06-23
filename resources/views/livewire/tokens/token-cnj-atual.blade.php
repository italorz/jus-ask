<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0">Token CNJ atual</h1>
        <button class="btn btn-outline-secondary btn-sm" wire:click="carregar" wire:loading.attr="disabled" wire:target="carregar">
            <span wire:loading.remove wire:target="carregar">Atualizar</span>
            <span wire:loading wire:target="carregar">
                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                Atualizando...
            </span>
        </button>
    </div>

    <p class="text-muted" style="font-size:.9rem;">
        Último token CNJ cadastrado para o tenant <code>{{ $tenant }}</code>.
    </p>

    @if ($token)
        <div class="card">
            <div class="card-body">
                <label for="tokenCnjValor" class="form-label">Token</label>
                <div class="input-group">
                    <input type="text" id="tokenCnjValor" class="form-control font-monospace"
                           value="{{ $token }}" readonly>
                    <button class="btn btn-primary" type="button" onclick="copiarTokenCnj(this)">Copiar</button>
                </div>
                @if ($criadoEm)
                    <div class="form-text">Cadastrado em {{ $criadoEm }}.</div>
                @endif

                @if ($expiraEm)
                    <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-{{ $expirado ? 'danger' : 'success' }} fs-6">
                            {{ $expirado ? 'EXPIRADO' : 'VÁLIDO' }}
                        </span>
                        <span class="{{ $expirado ? 'text-danger' : 'text-success' }} fw-semibold">
                            Expira em {{ $expiraEm }}
                        </span>
                        <small class="text-muted">(validade de 7h a partir do cadastro)</small>
                    </div>
                @endif
            </div>
        </div>

        <script>
            function copiarTokenCnj(btn) {
                var el = document.getElementById('tokenCnjValor');
                el.select();
                el.setSelectionRange(0, 99999);
                var done = function () {
                    var txt = btn.textContent;
                    btn.textContent = 'Copiado!';
                    setTimeout(function () { btn.textContent = txt; }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(el.value).then(done).catch(function () {
                        document.execCommand('copy'); done();
                    });
                } else {
                    document.execCommand('copy'); done();
                }
            }
        </script>
    @else
        <div class="alert alert-warning mb-0">
            Nenhum token CNJ cadastrado para o tenant <code>{{ $tenant }}</code> ainda.
        </div>
    @endif
</div>
