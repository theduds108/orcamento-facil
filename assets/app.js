// Atualiza desconto e total em tempo real no painel de resumo.
(function () {
    const totais = document.querySelector('.totais[data-subtotal]');
    const campoDesconto = document.getElementById('desconto');
    if (!totais || !campoDesconto) return;

    const subtotal = parseFloat(totais.dataset.subtotal) || 0;
    const elDesconto = document.getElementById('valor-desconto');
    const elTotal = document.getElementById('valor-total');
    const formatar = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    function atualizar() {
        let pct = parseFloat(campoDesconto.value.replace(',', '.')) || 0;
        pct = Math.min(Math.max(pct, 0), 100);
        const desconto = subtotal * pct / 100;
        elDesconto.textContent = '– ' + formatar(desconto);
        elTotal.textContent = formatar(subtotal - desconto);
    }

    campoDesconto.addEventListener('input', atualizar);
    atualizar();
})();
