<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Акт оказанных услуг № {{ $act['number'] }}</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.35;
            color: #000;
            margin: 2cm;
        }
        p { margin: 0 0 8pt 0; text-align: justify; }
        .meta {
            width: 100%;
            margin-bottom: 18pt;
        }
        .meta td { vertical-align: top; font-size: 12pt; }
        .meta-left { width: 50%; text-align: left; }
        .meta-right { width: 50%; text-align: right; }
        .title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 6pt 0;
        }
        .subtitle {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            margin: 0 0 16pt 0;
        }
        .preamble { margin-bottom: 12pt; }
        .clause-title {
            font-weight: bold;
            margin: 12pt 0 8pt 0;
        }
        table.services {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 8pt 0 10pt 0;
            font-size: 11pt;
        }
        table.services th,
        table.services td {
            border: 1pt solid #000;
            padding: 5pt 6pt;
            vertical-align: top;
        }
        table.services th {
            text-align: center;
            font-weight: bold;
            background: #f3f3f3;
        }
        .c-num { width: 8%; text-align: center; }
        .c-name { width: 62%; text-align: left; }
        .c-unit { width: 12%; text-align: center; }
        .c-qty { width: 18%; text-align: center; }
        .total-line {
            margin: 8pt 0 4pt 0;
            font-weight: bold;
        }
        .total-words {
            margin: 0 0 12pt 0;
            font-style: italic;
        }
        .clauses p { margin-bottom: 6pt; }
        .sign-table {
            width: 100%;
            margin-top: 28pt;
            border-collapse: collapse;
        }
        .sign-table td {
            width: 48%;
            vertical-align: top;
            padding: 0;
        }
        .sign-spacer { width: 4%; }
        .sign-head {
            font-weight: bold;
            margin-bottom: 8pt;
            text-align: center;
        }
        .sign-party {
            min-height: 28pt;
            margin-bottom: 18pt;
            text-align: center;
        }
        .sign-line {
            border-bottom: 1pt solid #000;
            height: 18pt;
            margin-top: 28pt;
        }
        .sign-caption {
            text-align: center;
            font-size: 10pt;
            margin-top: 3pt;
        }
        .mp {
            margin-top: 10pt;
            text-align: center;
            font-size: 10pt;
        }
        .center { text-align: center; }
    </style>
</head>
<body>
@php
    $customer = \App\Support\ActDocumentFormatter::partyName((string) ($act['customer'] ?? ''));
    $executor = \App\Support\ActDocumentFormatter::partyName((string) ($act['executor'] ?? ''));
    $dateLong = $act['date_long'] ?? \App\Support\ActDocumentFormatter::dateLong($act['date'] ?? null);
    $city = trim((string) ($act['city'] ?? 'г. ________'));
    $contractRef = trim((string) ($act['contract_ref'] ?? ''));
    $info = trim((string) ($act['info'] ?? ''));
    $totalHours = (float) ($total_hours ?? 0);
    $hoursText = $act['hours_text'] ?? \App\Support\ActDocumentFormatter::hoursWithWords($totalHours);
    $hoursUnit = \App\Support\ActDocumentFormatter::hoursUnit($totalHours);
@endphp

<table class="meta">
    <tr>
        <td class="meta-left">{{ $city }}</td>
        <td class="meta-right">{{ $dateLong }}</td>
    </tr>
</table>

<p class="title">Акт</p>
<p class="subtitle">
    оказанных услуг № {{ $act['number'] }}
</p>

<p class="preamble">
    {{ $customer }}, именуемый(ое) в дальнейшем «Заказчик», с одной стороны, и
    {{ $executor }}, именуемый(ая) в дальнейшем «Исполнитель», с другой стороны,
    вместе именуемые «Стороны», а по отдельности — «Сторона»,
    составили настоящий Акт о нижеследующем:
</p>

@if($contractRef !== '')
    <p>
        Настоящий Акт составлен во исполнение {{ $contractRef }}.
    </p>
@elseif($info !== '')
    <p>
        Основание / примечание: {{ $info }}.
    </p>
@else
    <p>
        Настоящий Акт составлен во исполнение договора возмездного оказания услуг,
        заключённого между Сторонами (далее — Договор).
    </p>
@endif

<p class="clause-title">1. Исполнитель оказал, а Заказчик принял следующие услуги:</p>

<table class="services">
    <thead>
    <tr>
        <th class="c-num">№ п/п</th>
        <th class="c-name">Наименование услуги (работы)</th>
        <th class="c-unit">Ед. изм.</th>
        <th class="c-qty">Количество</th>
    </tr>
    </thead>
    <tbody>
    @forelse(($tasks ?? []) as $index => $task)
        @php
            $name = is_array($task) ? ($task['name'] ?? 'Услуга') : ($task->name ?? 'Услуга');
            $hours = (float) (is_array($task)
                ? ($task['hours'] ?? 0)
                : ($task->pivot->hours ?? 0));
        @endphp
        <tr>
            <td class="c-num">{{ $index + 1 }}</td>
            <td class="c-name">{{ $name }}</td>
            <td class="c-unit">час</td>
            <td class="c-qty">{{ \App\Support\ActDocumentFormatter::hoursNumeric($hours) }}</td>
        </tr>
    @empty
        <tr>
            <td class="center" colspan="4">Услуги не указаны</td>
        </tr>
    @endforelse
    <tr>
        <td class="c-name" colspan="3" style="text-align:right;font-weight:bold;">Итого:</td>
        <td class="c-qty" style="font-weight:bold;">
            {{ \App\Support\ActDocumentFormatter::hoursNumeric($totalHours) }}
        </td>
    </tr>
    </tbody>
</table>

<p class="total-line">
    Всего оказано услуг: {{ $hoursText }} {{ $hoursUnit }}.
</p>

<div class="clauses">
    <p class="clause-title">2. Качество и объём услуг</p>
    <p>
        Услуги оказаны Исполнителем в полном объёме, в согласованные сроки и надлежащего качества.
        Заказчик претензий по объёму, качеству и срокам оказания услуг не имеет.
    </p>

    <p class="clause-title">3. Расчёты</p>
    <p>
        Стоимость оказанных услуг определяется в соответствии с условиями Договора.
        Настоящий Акт является основанием для проведения взаимных расчётов между Сторонами
        в части принятых по настоящему Акту услуг.
    </p>

    <p class="clause-title">4. Заключительные положения</p>
    <p>
        Настоящий Акт составлен на русском языке в двух экземплярах, имеющих одинаковую юридическую силу,
        по одному экземпляру для каждой из Сторон.
    </p>
    <p>
        Во всём остальном, что не урегулировано настоящим Актом, Стороны руководствуются
        условиями Договора и законодательством Российской Федерации.
    </p>
</div>

<table class="sign-table">
    <tr>
        <td>
            <div class="sign-head">Заказчик</div>
            <div class="sign-party">{{ $customer }}</div>
            <div class="sign-line"></div>
            <div class="sign-caption">подпись / Ф. И. О.</div>
            <div class="mp">М.П.</div>
        </td>
        <td class="sign-spacer"></td>
        <td>
            <div class="sign-head">Исполнитель</div>
            <div class="sign-party">{{ $executor }}</div>
            <div class="sign-line"></div>
            <div class="sign-caption">подпись / Ф. И. О.</div>
            <div class="mp">М.П. (при наличии)</div>
        </td>
    </tr>
</table>
</body>
</html>
