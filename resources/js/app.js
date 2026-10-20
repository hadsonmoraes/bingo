// Exportações de PDF em fila — painel na página de cartelas.
// Lotes grandes são gerados fora da requisição HTTP; o painel acompanha
// o andamento via polling e só continua atualizando enquanto houver
// exportações pendentes.

const pdfExportsPanel = document.getElementById('pdf-exports-panel');

const statusBadgeStyles = {
    pending: 'bg-slate-100 text-slate-600',
    processing: 'bg-amber-100 text-amber-800 animate-pulse',
    completed: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
};

function pdfExportRow(item) {
    const badgeClass = statusBadgeStyles[item.status] ?? statusBadgeStyles.pending;

    return `
        <li class="flex flex-wrap items-center gap-3 py-2.5 text-sm">
            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold ${badgeClass}">${item.status_label}</span>
            <span class="font-semibold">${item.cards_count} cartela(s)</span>
            <span class="text-slate-500">${item.created_at ?? ''}</span>
            ${item.status === 'failed' && item.error
                ? `<span class="text-xs text-red-600" title="${item.error.replace(/"/g, '&quot;')}">Erro: ${item.error}</span>`
                : ''}
            <span class="ml-auto flex items-center gap-2">
                ${item.download_url
                    ? `<a href="${item.download_url}" class="rounded-lg bg-red-700 px-3 py-1 text-xs font-semibold text-white hover:bg-red-600">⬇ Baixar</a>`
                    : ''}
                <form method="POST" action="${item.destroy_url}" class="inline"
                      onsubmit="return confirm('Remover esta exportação${item.file_name ? ' (o arquivo será apagado)' : ''}?')">
                    <input type="hidden" name="_method" value="DELETE">
                    <input type="hidden" name="_token" value="${document.querySelector('meta[name=csrf-token]').content}">
                    <button class="rounded-lg bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-300">Remover</button>
                </form>
            </span>
        </li>
    `;
}

async function pollPdfExports(url, followUp) {
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            return;
        }

        const { exports } = await response.json();
        const list = document.getElementById('pdf-exports-list');

        if (exports.length === 0) {
            pdfExportsPanel.classList.add('hidden');

            return;
        }

        pdfExportsPanel.classList.remove('hidden');
        list.innerHTML = exports.map(pdfExportRow).join('');

        // Continua atualizando apenas enquanto algo está na fila.
        const pending = exports.some((item) => item.status === 'pending' || item.status === 'processing');
        if (pending && followUp) {
            setTimeout(() => pollPdfExports(url, true), 3000);
        }
    } catch (e) { /* mantém o último estado conhecido */ }
}

pdfExportsPanel?.addEventListener('click', (event) => {
    // Reagenda o polling depois de ações no painel (ex.: remover exportação)
    if (event.target.matches('button, a')) {
        setTimeout(() => pollPdfExports(pdfExportsPanel.dataset.pollUrl, true), 500);
    }
});

pollPdfExports(pdfExportsPanel?.dataset.pollUrl, true);
