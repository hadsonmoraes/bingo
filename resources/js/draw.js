import { createBoard, updateBoard, renderHistory, playErrorBeep, playSuccessChime, postJson } from './bingo.js';

const state = JSON.parse(document.getElementById('state-data').textContent);
const gameId = state.game.id;
const playingStatuses = ['in_progress', 'paused', 'finished'];

// --- Elementos ---
const keypad = document.getElementById('keypad');
const alertBox = document.getElementById('alert-box');
const board = document.getElementById('board');
const history = document.getElementById('history');
const lastDisplay = document.getElementById('last-display');
const lastLetter = document.getElementById('last-letter');
const lastNumber = document.getElementById('last-number');
const drawnCount = document.getElementById('drawn-count');
const remainingCount = document.getElementById('remaining-count');
const pauseBtn = document.getElementById('pause-btn');
const resumeBtn = document.getElementById('resume-btn');
const finishBtn = document.getElementById('finish-btn');
const startBtn = document.getElementById('start-btn');
const undoModal = document.getElementById('undo-modal');
const undoReason = document.getElementById('undo-reason');
const undoConfirm = document.getElementById('undo-confirm');
const undoCancel = document.getElementById('undo-cancel');
const undoTarget = document.getElementById('undo-target');
const pausedOverlay = document.getElementById('paused-overlay');
const winnerPanel = document.getElementById('winner-panel');
const cardCodeInput = document.getElementById('card-code');
const prizeSelect = document.getElementById('prize-select');
const winnerNameInput = document.getElementById('winner-name');
const validateBtn = document.getElementById('validate-btn');
const winnersList = document.getElementById('winners-list');
const currentPrizeName = document.getElementById('current-prize-name');
const thresholdSelect = document.getElementById('threshold-select');
const naBoaList = document.getElementById('na-boa-list');
const csInPlay = document.getElementById('cs-in-play');
const csBlocksInPlay = document.getElementById('cs-blocks-in-play');
const csNaBoa = document.getElementById('cs-na-boa');
const csNaBoaBlocks = document.getElementById('cs-na-boa-blocks');
const csFar = document.getElementById('cs-far');
const csWinners = document.getElementById('cs-winners');
const csWinningBlocks = document.getElementById('cs-winning-blocks');
const csCancelled = document.getElementById('cs-cancelled');
const winnerOverlay = document.getElementById('winner-overlay');
const winnerOverlayDetails = document.getElementById('winner-overlay-details');
const winnerUndoModal = document.getElementById('winner-undo-modal');
const winnerUndoReason = document.getElementById('winner-undo-reason');
const winnerUndoConfirm = document.getElementById('winner-undo-confirm');
const winnerUndoCancel = document.getElementById('winner-undo-cancel');

let entry = '';
let pendingWinnerId = null;

// Criar painel inicial no Keyboard
if (keypad) {
    createBoard(keypad, state);
}

// --- Keyboard de números clicável ---
if (keypad) {
    keypad.addEventListener('click', (e) => {
        const cell = e.target.closest('[data-number]');
        if (!cell) return;
        if (state.game.status !== 'in_progress') return;
        const n = parseInt(cell.dataset.number, 10);
        if (state.drawn && state.drawn.includes(n)) {
            showAlert('Este número já foi sorteado.', 'error');
            return;
        }
        entry = String(n).padStart(2, '0');
        confirmDraw();
    });
}

// --- Renderização ---
function render(st) {
    Object.assign(state, st);
    updateBoard(keypad, state);
    renderHistory(history, state);

    lastDisplay.classList.toggle('opacity-30', !state.last);
    lastLetter.textContent = state.last?.letter ?? '—';
    lastNumber.textContent = state.last?.number ?? '--';
    drawnCount.textContent = state.drawn_count;
    remainingCount.textContent = state.remaining_count;

    const status = state.game.status;
    startBtn?.classList.toggle('hidden', status !== 'setup');
    pauseBtn?.classList.toggle('hidden', status !== 'in_progress');
    resumeBtn?.classList.toggle('hidden', status !== 'paused');
    finishBtn?.classList.toggle('hidden', !['in_progress', 'paused'].includes(status));
    pausedOverlay?.classList.toggle('hidden', status !== 'paused');
    // desabilita visualmente números do painel; createBoard/updateBoard já controla estados
    winnerPanel?.classList.toggle('hidden', !['in_progress', 'paused'].includes(status));

    // Seletor de prêmios em disputa
    if (prizeSelect) {
        const current = prizeSelect.value;
        prizeSelect.innerHTML = '<option value="">Selecione o prêmio…</option>' + state.prizes
            .filter((p) => p.winner === null)
            .map((p) => `<option value="${p.id}">${p.name} — ${p.rule_label}</option>`)
            .join('');
        if ([...prizeSelect.options].some((o) => o.value === current)) prizeSelect.value = current;
    }

    currentPrizeName.textContent = state.current_prize
        ? `${state.current_prize.name} (${state.current_prize.rule_label})`
        : 'Todos os prêmios já foram ganhos';

    if (winnersList) {
        winnersList.innerHTML = state.winners.map((w) => `
            <li class="flex items-center justify-between gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm">
                <span><strong>${w.prize_name}</strong> · cartela ${w.card_code}${w.block ? ' · bloco ' + w.block : ''}${w.winner_name ? ' · ' + w.winner_name : ''}</span>
                <span class="flex items-center gap-2">
                    <span class="text-slate-500">${w.validated_at}</span>
                    <button data-winner-undo="${w.id}" title="Desfazer validação (ex.: cartela não vendida)"
                            class="rounded-lg bg-red-100 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-200">Desfazer</button>
                </span>
            </li>
        `).join('');
    }

    // Painel de cartelas e blocos na boa — contabilizações separadas:
    // uma cartela física com N blocos continua sendo UMA cartela.
    const summary = state.cards_summary || {};
    if (csInPlay) {
        csInPlay.textContent = summary.total_in_play ?? 0;
        csBlocksInPlay.textContent = summary.blocks_in_play ?? 0;
        csNaBoa.textContent = summary.na_boa ?? 0;
        csNaBoaBlocks.textContent = summary.na_boa_blocks ?? 0;
        csFar.textContent = summary.far ?? 0;
        csWinners.textContent = summary.winners ?? 0;
        csWinningBlocks.textContent = summary.winning_blocks ?? 0;
        csCancelled.textContent = summary.cancelled ?? 0;

        if (thresholdSelect && document.activeElement !== thresholdSelect) {
            thresholdSelect.value = String(summary.threshold ?? 1);
        }

        const naBoaBlocks = summary.na_boa_blocks_list || [];
        naBoaList.innerHTML = naBoaBlocks.length === 0
            ? '<p class="py-4 text-center text-sm text-slate-400">Nenhum bloco na boa no momento.</p>'
            : naBoaBlocks.map((block) => `
                <div class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm ${block.missing === 0 ? 'bg-amber-200' : 'bg-amber-50'}">
                    <span class="font-mono font-bold">${block.code}/${block.block}
                        <span class="font-sans font-normal text-slate-500">· ${block.prize}</span>
                    </span>
                    <span class="text-xs font-semibold text-amber-800">
                        ${block.missing === 0 ? 'COMPLETA — valide!' : 'Faltam ' + block.missing + ': ' + block.numbers.join(', ')}
                    </span>
                </div>
            `).join('');
    }
}

function showAlert(message, type) {
    if (!alertBox) return;
    alertBox.textContent = message;
    alertBox.className = 'rounded-xl px-4 py-3 font-semibold ' + (type === 'error'
        ? 'bg-red-100 text-red-800 ring-2 ring-red-300 animate-pulse'
        : 'bg-emerald-100 text-emerald-800');
    alertBox.classList.remove('hidden');
    clearTimeout(alertBox._timer);
    alertBox._timer = setTimeout(() => alertBox.classList.add('hidden'), 5000);
}

// --- Confirmar pedra ---
async function confirmDraw() {
    if (!entry) {
        showAlert('Selecione um número no painel.', 'error');
        playErrorBeep();
        return;
    }

    const number = parseInt(entry, 10);
    entry = '';

    const { ok, data } = await postJson(`/games/${gameId}/draws`, { number });

    if (!ok) {
        showAlert(data.message || 'Não foi possível registrar a pedra.', 'error');
        if (data.code === 'duplicate') playErrorBeep();
        if (data.state) render(data.state);
        return;
    }

    showAlert(data.message, 'success');
    playSuccessChime();
    render(data.state);
}

// --- Desfazer último registro ---
let undoModalOpen = false;

undoBtnSetup();
function undoBtnSetup() {
    const btn = document.getElementById('undo-btn');
    if (!btn) return;

    btn.addEventListener('click', () => {
        if (!state.last) {
            showAlert('Não há pedras registradas para desfazer.', 'error');
            return;
        }
        undoTarget.textContent = state.last.letter
            ? `${state.last.letter} · ${state.last.number}`
            : `${state.last.number}`;
        undoReason.value = '';
        undoModal.classList.remove('hidden');
        undoModalOpen = true;
        undoReason.focus();
    });
}

undoCancel.addEventListener('click', () => undoModal.classList.add('hidden'));
undoModal.addEventListener('click', (e) => { if (e.target === undoModal) undoModal.classList.add('hidden'); });

undoConfirm.addEventListener('click', async () => {
    const reason = undoReason.value.trim();
    if (reason.length < 3) {
        undoReason.classList.add('ring-2', 'ring-red-400');
        undoReason.focus();
        return;
    }

    const { ok, data } = await postJson(`/games/${gameId}/draws/undo`, { reason });

    if (!ok) {
        showAlert(data.message || 'Não foi possível desfazer o registro.', 'error');
        undoModal.classList.add('hidden');
        return;
    }

    undoModal.classList.add('hidden');
    showAlert(data.message, 'success');
    render(data.state);
});

undoReason.addEventListener('input', () => undoReason.classList.remove('ring-2', 'ring-red-400'));

// --- Pausar / retomar / encerrar / iniciar ---
async function lifecycle(url, message) {
    const { ok, data } = await postJson(url);
    if (data.state) render(data.state);
    showAlert(data.message || message, ok ? 'success' : 'error');
}

pauseBtn?.addEventListener('click', () => lifecycle(`/games/${gameId}/pause`, 'Partida pausada.'));
resumeBtn?.addEventListener('click', () => lifecycle(`/games/${gameId}/resume`, 'Partida retomada.'));
startBtn?.addEventListener('click', async () => {
    const { ok, data } = await postJson(`/games/${gameId}/start`);
    if (ok) window.location.reload();
});
finishBtn?.addEventListener('click', () => {
    if (confirm('Encerrar a partida? Não será possível registrar novas pedras.')) {
        lifecycle(`/games/${gameId}/finish`, 'Partida encerrada.');
    }
});

// --- Validação de ganhador ---
validateBtn?.addEventListener('click', async () => {
    const cardCode = cardCodeInput.value.trim().toUpperCase();
    const prizeId = prizeSelect.value;

    if (!cardCode || !prizeId) {
        showAlert('Informe o código da cartela e selecione o prêmio.', 'error');
        return;
    }

    const { ok, data } = await postJson(`/games/${gameId}/winners/validate`, {
        card_code: cardCode,
        prize_id: prizeId,
        winner_name: winnerNameInput.value.trim() || null,
    });

    showAlert(data.message, ok ? 'success' : 'error');
    if (ok) {
        cardCodeInput.value = '';
        winnerNameInput.value = '';
        prizeSelect.value = '';
        playSuccessChime();
        showWinnerOverlay(data.winner);
        if (data.state) render(data.state);
    }
});

// --- Anúncio "TEMOS UM GANHADOR!" ---
function showWinnerOverlay(winner) {
    if (!winnerOverlay || !winner) return;

    winnerOverlayDetails.innerHTML = `
        ${winner.name ? `<p class="font-bold">${winner.name}</p>` : ''}
        <p>Cartela <strong>${winner.card_code}</strong>${winner.block ? ' · bloco <strong>' + winner.block + '</strong>' : ''}</p>
        <p>Prêmio: <strong>${winner.prize}</strong> (${winner.rule_label})</p>
        <p class="text-sm text-slate-500">${winner.validated_at}</p>
    `;
    winnerOverlay.classList.remove('hidden');
    winnerOverlay.classList.add('flex');

    clearTimeout(winnerOverlay._timer);
    winnerOverlay._timer = setTimeout(() => {
        winnerOverlay.classList.add('hidden');
        winnerOverlay.classList.remove('flex');
    }, 8000);
}

// --- Desfazer validação de ganhador (ex.: cartela não vendida) ---
winnersList?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-winner-undo]');
    if (!btn) return;

    pendingWinnerId = btn.dataset.winnerUndo;
    winnerUndoReason.value = '';
    winnerUndoModal.classList.remove('hidden');
    winnerUndoReason.focus();
});

winnerUndoCancel?.addEventListener('click', () => winnerUndoModal.classList.add('hidden'));
winnerUndoModal?.addEventListener('click', (e) => {
    if (e.target === winnerUndoModal) winnerUndoModal.classList.add('hidden');
});

winnerUndoConfirm?.addEventListener('click', async () => {
    const reason = winnerUndoReason.value.trim();
    if (reason.length < 3) {
        winnerUndoReason.classList.add('ring-2', 'ring-red-400');
        winnerUndoReason.focus();
        return;
    }

    const { ok, data } = await postJson(`/games/${gameId}/winners/${pendingWinnerId}/undo`, { reason });

    if (!ok) {
        showAlert(data.message || 'Não foi possível desfazer a validação.', 'error');
        winnerUndoModal.classList.add('hidden');
        return;
    }

    winnerUndoModal.classList.add('hidden');
    showAlert(data.message, 'success');
    if (data.state) render(data.state);
});

winnerUndoReason?.addEventListener('input', () => winnerUndoReason.classList.remove('ring-2', 'ring-red-400'));

// --- Limite de proximidade (cartelas na boa) ---
thresholdSelect?.addEventListener('change', async () => {
    const { ok, data } = await postJson(`/games/${gameId}/na-boa-threshold`, {
        threshold: parseInt(thresholdSelect.value, 10),
    });
    if (data.state) render(data.state);
    if (!ok) showAlert(data.message || 'Não foi possível atualizar o limite.', 'error');
});

// --- Polling leve para manter a tela do operador sincronizada ---
setInterval(async () => {
    try {
        const response = await fetch(`/games/${gameId}/state`, { headers: { Accept: 'application/json' } });
        if (response.ok) render(await response.json());
    } catch (e) { /* offline momentâneo — mantém o estado atual */ }
}, 5000);

render(state);
