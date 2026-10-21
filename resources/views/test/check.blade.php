<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Teste de Conferência de Cartela — Bingo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
</head>
<body class="bg-slate-950 text-white min-h-screen">

<div class="max-w-2xl mx-auto p-6">

    <h1 class="text-3xl font-bold mb-6">Teste de Conferência de Cartela</h1>

    <div class="bg-slate-800 rounded-xl p-5 mb-6">
        <p class="text-sm text-slate-400 mb-4">Digite o código da cartela para conferir seu status:</p>
        
        <form id="check-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Código da Cartela</label>
                <input type="text" id="card-code" name="card-code"
                    class="w-full rounded-xl border-slate-300 py-2.5 px-4 text-sm font-mono uppercase focus:border-amber-500 focus:ring-amber-500"
                    placeholder="Ex.: C-0001" required>
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-amber-600 py-2.5 text-sm font-bold text-white hover:bg-amber-500 transition">
                Conferir Cartela
            </button>
        </form>

        <div id="result" class="mt-5 hidden"></div>
    </div>

    <div class="mt-8 pt-6 border-t border-slate-700">

        <h2 class="text-xl font-bold mb-4">Referência: Status da Cartela</h2>

        <div class="space-y-3">
            <div>
                <span class="text-slate-400">Disponível</span>
                <span class="badge badge--available inline-block ml-2 rounded-full px-2 py-0.5 text-xs font-semibold text-slate-300">AVAILABLE</span>
            </div>
            <div>
                <span class="text-slate-400">Em Jogo</span>
                <span class="badge badge--in-play inline-block ml-2 rounded-full px-2 py-0.5 text-xs font-semibold text-amber-400">IN PLAY</span>
            </div>
            <div>
                <span class="text-slate-400">Vendida</span>
                <span class="badge badge--sold inline-block ml-2 rounded-full px-2 py-0.5 text-xs font-semibold text-emerald-400">SOLD</span>
            </div>
            <div>
                <span class="text-slate-400">Cancelada</span>
                <span class="badge badge--cancelled inline-block ml-2 rounded-full px-2 py-0.5 text-xs font-semibold text-rose-400">CANCELLED</span>
            </div>
            <div>
                <span class="text-slate-400">Ganhadora</span>
                <span class="badge badge--winner inline-block ml-2 rounded-full px-2 py-0.5 text-xs font-semibold text-rose-500">WINNER</span>
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            Código de barras Code128 impresso no canhoto + QR Code na pré-visualização.
        </p>
    </div>

</div>

<script>
async function init() {
    const form = document.getElementById('check-form');
    const resultDiv = document.getElementById('result');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = document.getElementById('card-code').value.trim();
        if (!code) return;

        resultDiv.classList.add('hidden');
        resultDiv.innerHTML = '<div class="p-4 rounded-lg animate-fade-in">Carregando...</div>';

        try {
            const response = await fetch('/games/1/cards/check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ code }),
            });

            const data = await response.json();

            if (response.ok) {
                resultDiv.classList.remove('hidden');
                if (data.card.sold) {
                    resultDiv.innerHTML = `
                        <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200">
                            <p class="font-bold text-emerald-600">Cartela ${data.card.code} VENDIDA</p>
                            <p class="text-sm">Comprador: ${data.card.buyer_name || '—'}</p>
                            <p class="text-sm">${data.card.buyer_phone ? 'Telefone: ' + data.card.buyer_phone : '—'}</p>
                            <p class="text-sm mt-1">Status: <span class="font-semibold text-emerald-500">${data.card.statusLabel}</span></p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="p-4 rounded-lg bg-amber-50 border border-amber-200">
                            <p class="font-bold text-amber-600">Cartela ${data.card.code} EM JOGO</p>
                            <p class="text-sm">Sem comprador cadastrado</p>
                            <p class="text-sm mt-1">Status: <span class="font-semibold text-amber-400">${data.card.statusLabel}</span></p>
                        </div>
                    `;
                }
            } else {
                resultDiv.classList.remove('hidden');
                resultDiv.innerHTML = `<div class="p-4 rounded-lg bg-rose-50 border border-rose-200">
                    <p class="font-bold text-rose-600">Erro</p>
                    <p class="text-sm">${data.message || 'Erro desconhecido'}</p>
                </div>`;
            }
        } catch (err) {
            resultDiv.classList.remove('hidden');
            resultDiv.innerHTML = `<div class="p-4 rounded-lg bg-rose-50 border border-rose-200">
                <p class="font-bold text-rose-600">Erro de rede</p>
                <p class="text-sm">${err.message}</p>
            </div>`;
        }
    });
}

init();
</script>

</body>
</html>