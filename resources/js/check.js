import { postJson } from './bingo.js';

const { gameId } = window.bingoCheck;

const input = document.getElementById('check-card');
const button = document.getElementById('check-btn');
const resultBox = document.getElementById('check-result');
const alertBox = document.getElementById('alert-box');

function showAlert(message, type) {
    if (!message) {
        alertBox.classList.add('hidden');
        return;
    }
    const colors = { success: 'emerald', error: 'rose', none: 'slate' };
    const color = colors[type] || 'slate';
    alertBox.className = `mt-3 rounded-xl border border-${color}-200 bg-${color}-50 px-3 py-2 text-xs font-semibold text-${color}-700`;
    alertBox.textContent = message;
}

function renderSold(card) {
    resultBox.className = 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm';
    resultBox.innerHTML = `
        <p class="font-bold text-emerald-700">Cartela ${card.code} — VENDIDA</p>
        <p class="mt-0.5 text-slate-600">Comprador: ${card.buyer_name || '—'}${card.buyer_phone ? ' · ' + card.buyer_phone : ''}</p>
        <p class="mt-0.5 text-xs text-slate-500">Status: ${card.status}</p>`;
}

function renderInPlay(card) {
    resultBox.className = 'mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm';
    resultBox.innerHTML = `
        <p class="font-bold text-amber-700">Cartela ${card.code} — EM JOGO (sem comprador registrado)</p>
        <div class="mt-2 flex flex-wrap gap-1.5">
            <input id="quick-sell-name" type="text" placeholder="Nome do comprador"
                class="flex-1 min-w-40 rounded-xl border-slate-300 py-1.5 px-3 text-sm focus:border-amber-500 focus:ring-amber-500">
            <button id="quick-sell-btn" type="button"
                class="rounded-xl bg-emerald-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-emerald-500">
                Registrar venda
            </button>
        </div>`;
    resultBox.classList.remove('hidden');

    document.getElementById('quick-sell-btn')?.addEventListener('click', async () => {
        const name = document.getElementById('quick-sell-name').value.trim();
        if (!name) return showAlert('Informe o nome do comprador.', 'error');
        const r = await postJson(`/games/${gameId}/cards/${card.id}/buyer`, { buyer_name: name });
        if (!r.ok) return showAlert(r.data?.message || 'Erro ao registrar venda.', 'error');
        showAlert(`Venda da cartela ${card.code} registrada.`, 'success');
        renderSold({ ...card, buyer_name: name, sold: true, status: 'Vendida' });
    });
}

async function checkCard() {
    const code = input.value.trim();
    if (!code) return showAlert('Informe ou escaneie o código da cartela.', 'error');
    showAlert('', 'none');

    const r = await postJson(`/games/${gameId}/cards/check`, { code });
    if (!r.ok) {
        resultBox.classList.add('hidden');
        return showAlert((r.data && r.data.message) || 'Erro ao conferir.', 'error');
    }

    const card = r.data.card;
    if (card.sold) {
        renderSold(card);
    } else {
        renderInPlay(card);
    }

    // Pronto para a próxima leitura do canhoto.
    input.value = '';
    input.focus();
}

button?.addEventListener('click', checkCard);

// Leitores físicos de código de barras enviam "texto + Enter" ao final da leitura.
input?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        checkCard();
    }
});
