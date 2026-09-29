<?php
/**
 * Orçamento Fácil
 * Gerador de orçamentos para pequenos prestadores de serviço.
 * PHP puro + SQLite (PDO), sem dependências externas.
 */

declare(strict_types=1);

session_start();

require __DIR__ . '/src/banco.php';
require __DIR__ . '/src/helpers.php';

$pdo = conectarBanco(__DIR__ . '/dados/orcamentos.sqlite');

$_SESSION['itens'] ??= [];
$erro = null;
$acao = $_POST['acao'] ?? null;

if ($acao === 'adicionar_item') {
    $descricao = trim($_POST['descricao'] ?? '');
    $quantidade = paraNumero($_POST['quantidade'] ?? '');
    $valorUnitario = paraNumero($_POST['valor_unitario'] ?? '');

    if ($descricao === '' || $quantidade <= 0 || $valorUnitario < 0) {
        $erro = 'Preencha descrição, quantidade e valor unitário com valores válidos.';
    } else {
        $_SESSION['itens'][] = compact('descricao', 'quantidade') + ['valor_unitario' => $valorUnitario];
        redirecionar('index.php');
    }
}

if (isset($_GET['remover_item'])) {
    unset($_SESSION['itens'][(int) $_GET['remover_item']]);
    $_SESSION['itens'] = array_values($_SESSION['itens']);
    redirecionar('index.php');
}

if ($acao === 'salvar_orcamento') {
    $cliente = trim($_POST['cliente'] ?? '');
    $desconto = min(max(paraNumero($_POST['desconto'] ?? '0'), 0), 100);
    $validadeDias = max((int) ($_POST['validade_dias'] ?? 15), 1);

    if ($cliente === '' || empty($_SESSION['itens'])) {
        $erro = 'Informe o cliente e adicione pelo menos um item antes de salvar.';
    } else {
        $id = salvarOrcamento($pdo, $cliente, $desconto, $validadeDias, $_SESSION['itens']);
        $_SESSION['itens'] = [];
        redirecionar('orcamento.php?id=' . $id . '&novo=1');
    }
}

$subtotal = calcularSubtotal($_SESSION['itens']);
$historico = listarOrcamentos($pdo, 8);
$resumo = resumoGeral($pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento Fácil</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topo">
    <div class="container topo-conteudo">
        <a href="index.php" class="logo"><span class="logo-marca">OF</span> Orçamento Fácil</a>
        <nav class="menu">
            <a href="index.php" class="ativo">Novo orçamento</a>
            <a href="#historico">Histórico</a>
        </nav>
    </div>
</header>

<main class="container">
    <div class="titulo-pagina">
        <h1>Novo orçamento</h1>
        <p>Adicione os itens do serviço, defina o cliente e salve para gerar o documento.</p>
    </div>

    <section class="indicadores">
        <div class="indicador">
            <span class="indicador-rotulo">Orçamentos emitidos</span>
            <span class="indicador-valor"><?= $resumo['quantidade'] ?></span>
        </div>
        <div class="indicador">
            <span class="indicador-rotulo">Valor total orçado</span>
            <span class="indicador-valor"><?= moeda($resumo['total']) ?></span>
        </div>
        <div class="indicador">
            <span class="indicador-rotulo">Ticket médio</span>
            <span class="indicador-valor"><?= moeda($resumo['media']) ?></span>
        </div>
    </section>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <div class="grade">
        <div class="coluna-principal">
            <section class="painel">
                <header class="painel-cabecalho">
                    <h2>Itens</h2>
                    <span class="contador"><?= count($_SESSION['itens']) ?> item(ns)</span>
                </header>

                <form method="post" class="form-item">
                    <input type="hidden" name="acao" value="adicionar_item">
                    <div class="campo campo-largo">
                        <label for="descricao">Descrição</label>
                        <input id="descricao" type="text" name="descricao" placeholder="Ex.: Instalação de tomada 20A" required>
                    </div>
                    <div class="campo">
                        <label for="quantidade">Qtd.</label>
                        <input id="quantidade" type="text" name="quantidade" inputmode="decimal" placeholder="1" required>
                    </div>
                    <div class="campo">
                        <label for="valor_unitario">Valor unit. (R$)</label>
                        <input id="valor_unitario" type="text" name="valor_unitario" inputmode="decimal" placeholder="0,00" required>
                    </div>
                    <button type="submit" class="botao botao-secundario">Adicionar</button>
                </form>

                <?php if (empty($_SESSION['itens'])): ?>
                    <div class="estado-vazio">Nenhum item adicionado ainda.</div>
                <?php else: ?>
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>Descrição</th>
                                <th class="num">Qtd.</th>
                                <th class="num">Valor unit.</th>
                                <th class="num">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($_SESSION['itens'] as $i => $item): ?>
                            <tr>
                                <td><?= e($item['descricao']) ?></td>
                                <td class="num"><?= numero($item['quantidade']) ?></td>
                                <td class="num"><?= moeda($item['valor_unitario']) ?></td>
                                <td class="num"><?= moeda($item['quantidade'] * $item['valor_unitario']) ?></td>
                                <td class="acoes"><a href="?remover_item=<?= $i ?>" class="link-remover">Remover</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>

        <aside class="coluna-lateral">
            <form method="post" class="painel resumo">
                <input type="hidden" name="acao" value="salvar_orcamento">
                <header class="painel-cabecalho"><h2>Resumo</h2></header>

                <div class="campo">
                    <label for="cliente">Cliente</label>
                    <input id="cliente" type="text" name="cliente" placeholder="Nome do cliente" required>
                </div>

                <div class="campo-duplo">
                    <div class="campo">
                        <label for="desconto">Desconto (%)</label>
                        <input id="desconto" type="text" name="desconto" inputmode="decimal" value="0">
                    </div>
                    <div class="campo">
                        <label for="validade_dias">Validade (dias)</label>
                        <input id="validade_dias" type="number" name="validade_dias" min="1" value="15">
                    </div>
                </div>

                <dl class="totais" data-subtotal="<?= $subtotal ?>">
                    <div><dt>Subtotal</dt><dd><?= moeda($subtotal) ?></dd></div>
                    <div><dt>Desconto</dt><dd id="valor-desconto">– <?= moeda(0) ?></dd></div>
                    <div class="total"><dt>Total</dt><dd id="valor-total"><?= moeda($subtotal) ?></dd></div>
                </dl>

                <button type="submit" class="botao botao-primario" <?= empty($_SESSION['itens']) ? 'disabled' : '' ?>>
                    Salvar e gerar orçamento
                </button>
            </form>
        </aside>
    </div>

    <section class="painel" id="historico">
        <header class="painel-cabecalho">
            <h2>Últimos orçamentos</h2>
        </header>
        <?php if (empty($historico)): ?>
            <div class="estado-vazio">Os orçamentos salvos aparecerão aqui.</div>
        <?php else: ?>
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Cliente</th>
                        <th>Emissão</th>
                        <th class="num">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($historico as $o): ?>
                    <tr>
                        <td class="mono"><?= numeroOrcamento((int) $o['id']) ?></td>
                        <td><?= e($o['cliente']) ?></td>
                        <td><?= data($o['criado_em']) ?></td>
                        <td class="num"><?= moeda((float) $o['total']) ?></td>
                        <td class="acoes"><a href="orcamento.php?id=<?= $o['id'] ?>" class="link">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>

<script src="assets/app.js"></script>
</body>
</html>
