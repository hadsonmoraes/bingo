// Utilidades compartilhadas entre a tela do operador e a tela pública.

export function createBoard(container, state) {
    container.innerHTML = '';

    if (state.game.use_letters) {
        const perLetter = Math.ceil(state.game.number_max / 5);
        const wrap = document.createElement('div');
        wrap.className = 'space-y-1.5';

        state.letters.forEach((letter, index) => {
            const start = index * perLetter + 1;
            const end = Math.min(state.game.number_max, start + perLetter - 1);
            const count = end - start + 1;

            const row = document.createElement('div');
            row.className = 'grid gap-1';
            row.style.gridTemplateColumns = `2.5rem repeat(${count}, minmax(0, 1fr))`;

            const label = document.createElement('div');
            label.className = 'flex items-center justify-center rounded-md bg-slate-800 py-0.5 text-base font-black tracking-widest text-amber-300';
            label.textContent = letter;
            row.appendChild(label);

            for (let n = start; n <= end; n++) {
                row.appendChild(boardCell(n));
            }
            wrap.appendChild(row);
        });

        container.appendChild(wrap);
    } else {
        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-10 gap-1.5';

        for (let n = state.game.number_min; n <= state.game.number_max; n++) {
            grid.appendChild(boardCell(n));
        }

        container.appendChild(grid);
    }

    updateBoard(container, state);
}

function boardCell(n) {
    const cell = document.createElement('div');
    cell.dataset.number = n;
    cell.innerHTML = `<span class="text-xs font-bold sm:text-sm">${String(n).padStart(2, '0')}</span>`;
    cell.className = 'group relative flex aspect-square w-full items-center justify-center rounded-lg bg-slate-200/70 text-center font-bold text-slate-800 transition hover:bg-slate-300 active:scale-95';
    return cell;
}

function colLetter(n) {
    if (n >= 1 && n <= 15) return 'B';
    if (n >= 16 && n <= 30) return 'I';
    if (n >= 31 && n <= 45) return 'N';
    if (n >= 46 && n <= 60) return 'G';
    return 'O';
}

export function updateBoard(container, state) {
    const drawn = new Set(state.drawn);

    container.querySelectorAll('[data-number]').forEach((cell) => {
        const n = parseInt(cell.dataset.number, 10);
        const isLast = state.last && state.last.number === n;

        cell.classList.toggle('bg-amber-400', drawn.has(n));
        cell.classList.toggle('text-slate-900', drawn.has(n));
        cell.classList.toggle('font-black', drawn.has(n));
        cell.classList.toggle('ring-4', isLast);
        cell.classList.toggle('ring-emerald-400', isLast);
        cell.classList.toggle('bg-slate-200/70', !drawn.has(n));
        cell.classList.toggle('text-slate-700', !drawn.has(n));
        cell.classList.toggle('font-semibold', !drawn.has(n));
        cell.classList.toggle('opacity-40', drawn.has(n));
        cell.classList.toggle('pointer-events-none', drawn.has(n));
        cell.classList.toggle('cursor-not-allowed', drawn.has(n));
    });
}

export function renderHistory(container, state, limit = null, compact = false) {
    const items = limit ? state.history.slice(0, limit) : state.history;
    const chipClass = compact
        ? 'flex shrink-0 items-center justify-center rounded-lg bg-slate-700 px-2.5 py-1 text-base font-black text-white'
        : 'flex shrink-0 items-center justify-center rounded-xl bg-slate-700 px-3 py-2 text-2xl font-black text-white';
    container.innerHTML = items.map((item) => `
        <span class="${chipClass}">
            ${item.letter ? `<span class="mr-0.5 text-sm text-amber-300">${item.letter}</span>` : ''}${item.number}
        </span>
    `).join('');
}

// Alertas sonoros via Web Audio (funciona offline, sem arquivos)
let audioCtx = null;

const SOUND_STORAGE_KEY = 'bingo-sound';

/** Som ativado/desativado pelo operador (persistido no navegador). */
export function isSoundEnabled() {
    return localStorage.getItem(SOUND_STORAGE_KEY) !== 'off';
}

export function setSoundEnabled(enabled) {
    localStorage.setItem(SOUND_STORAGE_KEY, enabled ? 'on' : 'off');
}

export function playErrorBeep() {
    if (!isSoundEnabled()) return;

    const ctx = getAudio();
    if (!ctx) return;

    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sawtooth';
    osc.frequency.value = 220;
    gain.gain.setValueAtTime(0.25, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
    osc.connect(gain).connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.5);
}

export function playSuccessChime() {
    if (!isSoundEnabled()) return;

    const ctx = getAudio();
    if (!ctx) return;

    [880, 1320].forEach((freq, i) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        const t = ctx.currentTime + i * 0.12;
        gain.gain.setValueAtTime(0.2, t);
        gain.gain.exponentialRampToValueAtTime(0.001, t + 0.35);
        osc.connect(gain).connect(ctx.destination);
        osc.start(t);
        osc.stop(t + 0.4);
    });
}

export function playWinnerFanfare() {
    if (!isSoundEnabled()) return;

    const ctx = getAudio();
    if (!ctx) return;

    [523, 659, 784, 1047].forEach((freq, i) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.value = freq;
        const t = ctx.currentTime + i * 0.15;
        gain.gain.setValueAtTime(0.25, t);
        gain.gain.exponentialRampToValueAtTime(0.001, t + 0.5);
        osc.connect(gain).connect(ctx.destination);
        osc.start(t);
        osc.stop(t + 0.55);
    });
}

function getAudio() {
    if (audioCtx) return audioCtx;
    try {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        return audioCtx;
    } catch (e) {
        return null;
    }
}

export async function postJson(url, body = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));
    return { ok: response.ok, status: response.status, data };
}
