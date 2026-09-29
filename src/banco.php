<?php

declare(strict_types=1);

/**
 * Conexão e acesso a dados (SQLite via PDO).
 * O esquema é criado automaticamente na primeira execução.
 */

function conectarBanco(string $arquivo): PDO
{
    if (!is_dir(dirname($arquivo))) {
        mkdir(dirname($arquivo), 0775, true);
    }

    $pdo = new PDO('sqlite:' . $arquivo);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS orcamentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente TEXT NOT NULL,
            subtotal REAL NOT NULL,
            desconto_percentual REAL NOT NULL DEFAULT 0,
            total REAL NOT NULL,
            validade_dias INTEGER NOT NULL DEFAULT 15,
            criado_em TEXT NOT NULL
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS orcamento_itens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            orcamento_id INTEGER NOT NULL REFERENCES orcamentos(id) ON DELETE CASCADE,
            descricao TEXT NOT NULL,
            quantidade REAL NOT NULL,
            valor_unitario REAL NOT NULL
        )
    ');

    return $pdo;
}

function salvarOrcamento(PDO $pdo, string $cliente, float $desconto, int $validadeDias, array $itens): int
{
    $subtotal = calcularSubtotal($itens);
    $total = round($subtotal * (1 - $desconto / 100), 2);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO orcamentos (cliente, subtotal, desconto_percentual, total, validade_dias, criado_em)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$cliente, $subtotal, $desconto, $total, $validadeDias, date('Y-m-d H:i:s')]);
        $id = (int) $pdo->lastInsertId();

        $stmtItem = $pdo->prepare('
            INSERT INTO orcamento_itens (orcamento_id, descricao, quantidade, valor_unitario)
            VALUES (?, ?, ?, ?)
        ');
        foreach ($itens as $item) {
            $stmtItem->execute([$id, $item['descricao'], $item['quantidade'], $item['valor_unitario']]);
        }

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function buscarOrcamento(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM orcamentos WHERE id = ?');
    $stmt->execute([$id]);
    $orcamento = $stmt->fetch();
    if (!$orcamento) {
        return null;
    }

    $stmtItens = $pdo->prepare('SELECT * FROM orcamento_itens WHERE orcamento_id = ? ORDER BY id');
    $stmtItens->execute([$id]);
    $orcamento['itens'] = $stmtItens->fetchAll();

    return $orcamento;
}

function listarOrcamentos(PDO $pdo, int $limite): array
{
    $stmt = $pdo->prepare('SELECT * FROM orcamentos ORDER BY id DESC LIMIT ?');
    $stmt->bindValue(1, $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function resumoGeral(PDO $pdo): array
{
    $linha = $pdo->query('SELECT COUNT(*) AS quantidade, COALESCE(SUM(total), 0) AS total FROM orcamentos')->fetch();
    $quantidade = (int) $linha['quantidade'];
    $total = (float) $linha['total'];

    return [
        'quantidade' => $quantidade,
        'total' => $total,
        'media' => $quantidade > 0 ? $total / $quantidade : 0.0,
    ];
}
