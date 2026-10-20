import { createBoard, updateBoard, renderHistory, playSuccessChime, playWinnerFanfare, isSoundEnabled, setSoundEnabled } from './bingo.js';

const state = JSON.parse(document.getElementById('state-data').textContent);
const gameId = state.game.id;

const lastLetter = document.getElementById('last-letter');
const lastNumber = document.getElementById('last-number');
const board = document.getElementById('board');
const history = document.getElementById('history');
const prizeName = document.getElementById('prize-name');
const prizeRule = document.getElementById('prize-rule');
const prizePosition = document.getElementById('prize-position');
const remaining = document.getElementById('remaining-count');
const winnerOverlay = document.getElementById('winner-overlay');
const winnerMessage = document.getElementById('winner-message');
const waitingBanner = document.getElementById('waiting-banner');
const pausedBanner = document.getElementById('paused-banner');
const gameName = document.getElementById('game-name');
const fullscreenBtn = document.getElementById('fullscreen-btn');

let lastSeenWinnerCount = state.winners.length;
let lastSeenDrawn = state.drawn.length;

function render(st) {
    Object.assign(state, st);
    gameName.textContent = state.game.name;

    lastLetter.textContent = state.last?.letter ?? '';
    lastNumber.textContent = state.last?.number ?? '--';
    lastLetter.classList.toggle('invisible', !state.last?.letter);

    updateBoard(board, state);
    renderHistory(history, state);
    remaining.textContent = state.remaining_count;

    prizeName.textContent = state.current_prize?.name ?? 'Todos os prêmios já foram ganhos';
    prizeRule.textContent = state.current_prize?.rule_label ?? '';
    prizePosition.textContent = state.current_prize
        ? `Prêmio ${state.current_prize.position} de ${state.current_prize.total}`
        : '';

    const naBoaCount = document.getElementById('na-boa-count');
    if (naBoaCount) naBoaCount.textContent = state.cards_summary?.na_boa ?? 0;

    waitingBanner.classList.toggle('hidden', state.game.status !== 'setup');
    pausedBanner.classList.toggle('hidden', state.game.status !== 'paused');

    // Som de nova pedra
    if (state.drawn.length > lastSeenDrawn && state.game.status === 'in_progress') {
        playSuccessChime();
    }
    lastSeenDrawn = state.drawn.length;

    // Mensagem de ganhador validado
    if (state.winners.length > lastSeenWinnerCount) {
        const winner = state.winners[state.winners.length - 1];
        winnerMessage.innerHTML = `
            <p class="text-2xl font-black uppercase tracking-widest text-amber-300">🏆 Temos um ganhador!</p>
            <p class="mt-3 text-5xl font-black">${winner.prize_name}</p>
            <p class="mt-4 text-3xl font-bold text-white">Cartela ${winner.card_code}${winner.winner_name ? ' — ' + winner.winner_name : ''}</p>
        `;
        winnerOverlay.classList.remove('hidden');
        playWinnerFanfare();
        setTimeout(() => winnerOverlay.classList.add('hidden'), 12000);
    }
    lastSeenWinnerCount = state.winners.length;
}

async function poll() {
    try {
        const response = await fetch(`/games/${gameId}/state`, { headers: { Accept: 'application/json' } });
        if (response.ok) render(await response.json());
    } catch (e) { /* mantém o último estado conhecido */ }
}

fullscreenBtn?.addEventListener('click', () => {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        document.documentElement.requestFullscreen().catch(() => {});
    }
});

// --- Som do telão (persistido no navegador) ---
const soundToggle = document.getElementById('sound-toggle');

function updateSoundToggle() {
    if (soundToggle) {
        soundToggle.textContent = isSoundEnabled() ? '🔊' : '🔇';
        soundToggle.title = isSoundEnabled() ? 'Desativar sons' : 'Ativar sons';
    }
}

soundToggle?.addEventListener('click', () => {
    setSoundEnabled(!isSoundEnabled());
    updateSoundToggle();
});

updateSoundToggle();

// Polling: o sistema é local/offline, então a latência é desprezível.
setInterval(poll, 2000);

createBoard(board, state);
render(state);
