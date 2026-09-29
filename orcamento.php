<?php
/**
 * Visualização de um orçamento salvo, em formato de documento pronto para impressão/PDF.
 */

declare(strict_types=1);

require __DIR__ . '/src/banco.php';
require __DIR__ . '/src/helpers.php';

$pdo = conectarBanco(__DIR__ . '/dados/orcamentos.sqlite');
$orcamento = buscarOrcamento($pdo, (int) ($_GET['id'] ?? 0));

if ($orcamento === null) {
    http_response_code(404);
    exit('Orçamento não encontrado.');
}

$valorDesconto = $orcamento['subtotal'] - $orcamento['total'];
$validoAte = date('d/m/Y', strtotime($orcamento['criado_em'] . ' + ' . $orcamento['validade_dias'] . ' days'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento <?= numeroOrcamento((int) $orcamento['id']) ?> · Orçamento Fácil</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="pagina-documento">
<div class="barra-acoes nao-imprimir">
    <div class="container barra-acoes-conteudo">
        <a href="index.php" class="link">← Voltar</a>
        <?php if (isset($_GET['novo'])): ?>
            <span class="selo-sucesso">Orçamento salvo</span>
        <?php endif; ?>
        <button class="botao botao-primario" onclick="window.print()">Imprimir / salvar PDF</button>
    </div>
</div>

<article class="documento">
    <header class="documento-cabecalho">
        <div>
            <div class="logo"><span class="logo-marca">OF</span> Orçamento Fácil</div>
        </div>
        <div class="documento-meta">
            <span class="documento-tipo">Orçamento</span>
            <span class="mono">Nº <?= numeroOrcamento((int) $orcamento['id']) ?></span>
        </div>
    </header>

    <section class="documento-info">
        <div>
            <span class="rotulo">Cliente</span>
            <strong><?= e($orcamento['cliente']) ?></strong>
        </div>
        <div>
            <span class="rotulo">Emissão</span>
            <strong><?= data($orcamento['criado_em']) ?></strong>
        </div>
        <div>
            <span class="rotulo">Válido até</span>
            <strong><?= $validoAte ?></strong>
        </div>
    </section>

    <table class="tabela tabela-documento">
        <thead>
            <tr>
                <th>Descrição</th>
                <th class="num">Qtd.</th>
                <th class="num">Valor unit.</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orcamento['itens'] as $item): ?>
            <tr>
                <td><?= e($item['descricao']) ?></td>
                <td class="num"><?= numero((float) $item['quantidade']) ?></td>
                <td class="num"><?= moeda((float) $item['valor_unitario']) ?></td>
                <td class="num"><?= moeda((float) $item['quantidade'] * (float) $item['valor_unitario']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <dl class="totais totais-documento">
        <div><dt>Subtotal</dt><dd><?= moeda((float) $orcamento['subtotal']) ?></dd></div>
        <?php if ($orcamento['desconto_percentual'] > 0): ?>
            <div><dt>Desconto (<?= numero((float) $orcamento['desconto_percentual']) ?>%)</dt><dd>– <?= moeda($valorDesconto) ?></dd></div>
        <?php endif; ?>
        <div class="total"><dt>Total</dt><dd><?= moeda((float) $orcamento['total']) ?></dd></div>
    </dl>

    <footer class="documento-rodape">
        Este orçamento é válido por <?= (int) $orcamento['validade_dias'] ?> dias a partir da data de emissão.
    </footer>
</article>
</body>
</html>
