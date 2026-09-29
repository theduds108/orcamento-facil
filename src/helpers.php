<?php

declare(strict_types=1);

/** Funções utilitárias de formatação e cálculo. */

date_default_timezone_set('America/Sao_Paulo');

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function paraNumero(string $valor): float
{
    // Aceita "1.234,56" e "1234.56"
    $valor = trim($valor);
    if (str_contains($valor, ',')) {
        $valor = str_replace(['.', ','], ['', '.'], $valor);
    }
    return is_numeric($valor) ? (float) $valor : 0.0;
}

function moeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function numero(float $valor): string
{
    return rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
}

function data(string $dataHora): string
{
    return date('d/m/Y', strtotime($dataHora));
}

function numeroOrcamento(int $id): string
{
    return str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function calcularSubtotal(array $itens): float
{
    return array_reduce(
        $itens,
        fn (float $soma, array $item) => $soma + (float) $item['quantidade'] * (float) $item['valor_unitario'],
        0.0
    );
}

function redirecionar(string $url): never
{
    header('Location: ' . $url);
    exit;
}
