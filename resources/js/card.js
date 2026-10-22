// Acompanhamento público de uma cartela (destino do QR Code impresso).
// Marca os números sorteados na própria grade e atualiza via polling —
// o sistema é local/offline, então a latência é desprezível.

let state = JSON.parse(document.getElementById('state-data').textContent);

const statusBadge = document.getElementById('status-badge');
const lastLetter = document.getElementById('last-letter');
const lastNumber = document.getElementById('last-number');
const drawnCount = document.getElementById('drawn-count');
const remaining = document.getElementById('remaining-count');
const currentPrize = document.getElementById('current-prize-name');
const winnersList = document.getElementById('winners-list');
const winnersEmpty = document.getElementById('winners-empty');
const bingoOverlay = document.getElementById('bingo-overlay');

const statusLabels = {
    setup: 'Aguardando início',
    in_progress: 'Partida em andamento',
    paused: 'Partida pausada',
    finished: 'Partida encerrada',
};

let lastSeenDrawn = state.drawn.length;
let bingoShown = false;

// Vibração — só após o primeiro toque do usuário, porque o navegador
// bloqueia navigator.vibrate sem interação prévia.
function vibrate(pattern) {
    if (navigator.userActivation?.hasBeenActive) {
        navigator.vibrate?.(pattern);
    }
}

function renderWinners() {
    winnersList.innerHTML = '';
    winnersEmpty.classList.toggle('hidden', state.winners.length > 0);

    for (const winner of state.winners) {
        const item = document.createElement('li');
        item.className = 'font-semibold text-slate-200';
        item.innerHTML = `🏆 <strong>${winner.prize_name ?? 'Prêmio'}</strong> · cartela ${winner.card_code ?? ''}` +
            (winner.winner_name ? ` · ${winner.winner_name}` : '') +
            (winner.at ? ` · ${winner.at}` : '');
        winnersList.appendChild(item);
    }
}

function render(st) {
    Object.assign(state, st);

    statusBadge.textContent = statusLabels[state.status] ?? state.status;
    statusBadge.classList.toggle('bg-emerald-600', state.status === 'in_progress');
    statusBadge.classList.toggle('bg-slate-800', state.status !== 'in_progress');

    lastLetter.textContent = state.last?.letter ?? '';
    lastNumber.textContent = state.last?.number ?? '--';
    drawnCount.textContent = state.drawn_count;
    remaining.textContent = state.remaining_count;
    currentPrize.textContent = state.current_prize?.name ?? 'Todos os prêmios já foram ganhos';

    // Números sorteados marcados na grade
    const drawn = new Set(state.drawn);
    document.querySelectorAll('td[data-number]').forEach((cell) => {
        const isDrawn = drawn.has(parseInt(cell.dataset.number, 10));
        cell.classList.toggle('bg-emerald-500', isDrawn);
        cell.classList.toggle('text-slate-950', isDrawn);
        cell.classList.toggle('text-slate-300', !isDrawn);
    });

    // Faltam quantos para cada bloco completar o seu prêmio
    let hasBingo = false;
    document.querySelectorAll('[data-block]').forEach((section) => {
        const block = state.blocks[parseInt(section.dataset.block, 10)];
        const badge = section.querySelector('[data-missing]');
        if (!block || !badge) {
            return;
        }

        if (block.missing === 0) {
            hasBingo = true;
            badge.textContent = 'BINGO!';
            badge.className = 'shrink-0 rounded-full bg-amber-400 px-2.5 py-0.5 text-[11px] font-black text-slate-950';
        } else {
            badge.textContent = `Faltam ${block.missing}`;
            badge.className = 'shrink-0 rounded-full bg-slate-800 px-2.5 py-0.5 text-[11px] font-black text-slate-300';
        }
    });

    // Destaque quando um bloco da própria cartela completa o prêmio
    if (hasBingo && !bingoShown) {
        bingoOverlay.classList.remove('hidden');
        vibrate([300, 150, 300]);
        setTimeout(() => bingoOverlay.classList.add('hidden'), 15000);
    }
    bingoShown = hasBingo;

    // Vibração curta a cada nova pedra
    if (state.drawn.length > lastSeenDrawn) {
        vibrate(120);
    }
    lastSeenDrawn = state.drawn.length;

    renderWinners();
}

async function poll() {
    try {
        const response = await fetch(`${location.pathname.replace(/\/+$/, '')}/state`, {
            headers: { Accept: 'application/json' },
        });
        if (response.ok) {
            render(await response.json());
        }
    } catch (e) { /* mantém o último estado conhecido */ }
}

render(state);
setInterval(poll, 3000);
