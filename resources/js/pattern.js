// Editor de padrão personalizado usado nos formulários de prêmios.

export function setupPatternEditor(select, block, input, grid, rows, cols) {
    const cells = [];

    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            const cell = document.createElement('button');
            cell.type = 'button';
            cell.dataset.selected = 'false';
            cell.className = 'h-10 w-10 rounded-lg bg-white ring-1 ring-slate-300 transition hover:bg-amber-50';
            cell.addEventListener('click', () => {
                const selected = cell.dataset.selected !== 'true';
                cell.dataset.selected = String(selected);
                cell.classList.toggle('bg-amber-400', selected);
                cell.classList.toggle('ring-amber-500', selected);
                sync();
            });
            grid.appendChild(cell);
            cells.push(cell);
        }
    }

    grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;

    function sync() {
        const pattern = [];
        for (let r = 0; r < rows; r++) {
            const row = [];
            for (let c = 0; c < cols; c++) {
                row.push(cells[r * cols + c].dataset.selected === 'true');
            }
            pattern.push(row);
        }
        input.value = JSON.stringify(pattern);
    }

    // Carrega um padrão existente, se houver (edição)
    if (input.dataset.existingPattern) {
        try {
            const pattern = JSON.parse(input.dataset.existingPattern);
            for (let r = 0; r < rows; r++) {
                for (let c = 0; c < cols; c++) {
                    if (pattern?.[r]?.[c]) cells[r * cols + c].click();
                }
            }
        } catch (e) { /* padrão inválido — começa em branco */ }
    }

    function toggle() {
        block.hidden = select.value !== 'custom_pattern';
    }

    select.addEventListener('change', toggle);
    toggle();
}
