<div class="dropdown" wire:poll.30s>
    <a class="nav-link position-relative" href="#" role="button"
       data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificações">
        <i class="bi bi-bell"></i>
        @if ($totalNaoLidas > 0)
            <span class="badge-vencida rounded-pill position-absolute"
                  style="top:-4px; right:-8px; font-size:.65rem; padding:.2em .45em;">
                {{ $totalNaoLidas > 9 ? '9+' : $totalNaoLidas }}
            </span>
        @endif
    </a>
    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 320px;">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <strong class="small">Notificações</strong>
            @if ($totalNaoLidas > 0)
                <span class="text-muted small">{{ $totalNaoLidas }} não lida(s)</span>
            @endif
        </div>

        @if ($recentes->isEmpty())
            <div class="empty-state py-4">
                <i class="bi bi-bell-slash empty-icon"></i>
                <div class="empty-sub">Nenhuma notificação nova</div>
            </div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($recentes as $notif)
                    <li class="list-group-item py-2" wire:key="painel-notif-{{ $notif->id }}">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">{{ $notif->titulo }}</div>
                                <p class="mb-0 text-muted" style="font-size:.8rem;">
                                    {{ \Illuminate\Support\Str::limit($notif->mensagem, 80) }}
                                </p>
                            </div>
                            <button class="btn-ghost btn-icon btn-sm btn-action-edit flex-shrink-0"
                                    wire:click="marcarLida({{ $notif->id }})"
                                    data-bs-toggle="tooltip" data-bs-title="Marcar como lida">
                                <i class="bi bi-check2"></i>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="text-center border-top py-2">
            <a href="{{ route('notificacoes', ['tenant' => app(\App\Services\TenantManager::class)->tenant()]) }}"
               class="small text-decoration-none">
                Ver todas
            </a>
        </div>
    </div>
</div>
