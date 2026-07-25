<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Акт приема-передачи оказанных услуг № {{ $act['number'] }}</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000000;
            margin: 2.5cm;
            padding: 0;
        }
        
        .document-wrapper {
            max-width: 100%;
            margin: 0 auto;
        }
        
        .document-header {
            text-align: center;
            margin-bottom: 25pt;
            padding-bottom: 15pt;
            border-bottom: 1pt solid #000000;
        }
        
        .document-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 10pt 0;
            text-transform: uppercase;
            letter-spacing: 1pt;
        }
        
        .document-number {
            font-size: 12pt;
            font-weight: bold;
            margin: 5pt 0;
        }
        
        .document-content {
            margin-bottom: 20pt;
        }
        
        .contract-parties {
            text-align: justify;
            margin-bottom: 25pt;
            line-height: 1.6;
        }
        
        .services-table-wrapper {
            margin: 25pt 0 30pt 0;
        }
        
        .services-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
            table-layout: fixed;
        }
        
        .services-table th {
            border: 1pt solid #000000;
            padding: 8pt;
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }
        
        .services-table td {
            border: 1pt solid #000000;
            padding: 8pt;
            vertical-align: top;
        }
        
        .col-number {
            width: 8%;
            text-align: center;
            font-weight: bold;
        }
        
        .col-service {
            width: 72%;
            text-align: left;
        }
        
        .col-hours {
            width: 20%;
            text-align: center;
        }
        
        .task-title {
            font-weight: bold;
            margin-bottom: 3pt;
            display: block;
        }
        
        .task-description {
            font-size: 10pt;
            color: #333333;
            margin: 0;
            line-height: 1.4;
        }
        
        .total-summary {
            text-align: right;
            margin: 20pt 0 30pt 0;
            font-size: 12pt;
            font-weight: bold;
        }
        
        .document-date {
            text-align: center;
            margin-top: 6pt;
            font-size: 11pt;
        }
        
        .conditions-section {
            margin-bottom: 40pt;
            line-height: 1.6;
        }
        
        .conditions-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
            counter-reset: conditions;
        }
        
        .condition-item {
            margin-bottom: 8pt;
            position: relative;
            padding-left: 25pt;
            text-align: justify;
        }
        
        .condition-item:before {
            content: counter(conditions) ".";
            counter-increment: conditions;
            position: absolute;
            left: 0;
            font-weight: bold;
            width: 20pt;
            text-align: right;
        }
        
        .signatures-section {
            margin-top: 60pt;
        }
        
        .signatures-container {
            width: 100%;
            margin-top: 40pt;
        }
        
        .signature-block {
            width: 45%;
            display: inline-block;
            vertical-align: top;
            min-height: 120pt;
        }
        
        .signature-title {
            font-weight: bold;
            margin-bottom: 10pt;
            display: block;
        }
        
        .signature-company {
            margin-bottom: 50pt;
            min-height: 20pt;
        }
        
        .signature-line {
            border-bottom: 1pt solid #000000;
            width: 100%;
            margin-top: 40pt;
        }
        
        .signature-name {
            text-align: center;
            font-size: 11pt;
            margin-top: 5pt;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="document-wrapper">
        <header class="document-header">
            <h1 class="document-title">Акт приема-передачи оказанных услуг</h1>
            <div class="document-number">№ {{ $act['number'] }}</div>
            @if(!empty($act['date']))
                <div class="document-date">
                    от {{ \Illuminate\Support\Carbon::parse($act['date'])->format('d.m.Y') }}
                </div>
            @endif
        </header>
        
        <main class="document-content">
            <section class="contract-parties">
                Общество с ограниченной ответственностью «{{ $act['customer'] }}», именуемое в дальнейшем «Заказчик», и {{ $act['executor'] }}, являющийся плательщиком налога на профессиональный доход, именуемый в дальнейшем «Исполнитель», составили настоящий акт приема-передачи оказанных услуг к договору возмездного оказания услуг о нижеследующем:
            </section>
            
            <section class="services-table-wrapper">
                <table class="services-table">
                    <thead>
                        <tr>
                            <th class="col-number">№</th>
                            <th class="col-service">Вид услуги</th>
                            <th class="col-hours">Часы</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalHours = 0;
                        @endphp
                        
                        @if(isset($tasks) && count($tasks) > 0)
                            @foreach($tasks as $index => $task)
                                @php
                                    if (!$task) continue;
                                    
                                    $taskName = is_array($task)
                                        ? ($task['name'] ?? 'Задача без названия')
                                        : ($task->name ?? 'Задача без названия');
                                    $taskDescription = is_array($task)
                                        ? ($task['description'] ?? '')
                                        : ($task->description ?? '');
                                    $taskHours = is_array($task)
                                        ? ($task['hours'] ?? 0)
                                        : ($task->pivot->hours ?? $task->estimation_hours ?? 0);
                                    
                                    if (!is_numeric($taskHours)) {
                                        $taskHours = 0;
                                    }
                                    
                                    $taskHours = (float) $taskHours;
                                    $totalHours += $taskHours;
                                @endphp
                                
                                <tr>
                                    <td class="col-number">{{ $index + 1 }}</td>
                                    <td class="col-service">
                                        @if($taskName)
                                            <span class="task-title">{{ $taskName }}</span>
                                        @endif
                                        @if($taskDescription)
                                            <div class="task-description">{{ $taskDescription }}</div>
                                        @endif
                                    </td>
                                    <td class="col-hours">{{ number_format($taskHours, 2, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="3" class="text-center">Нет задач в акте</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </section>
            
            <section class="total-summary">
                Итого:
                <span class="total-hours">
                    {{ number_format($totalHours, 2, ',', ' ') }} ч.
                </span>
            </section>
            
            <section class="conditions-section">
                <ol class="conditions-list">
                    <li class="condition-item">
                        Стоимость предоставленных услуг составляет указанную сумму. Стороны не имеют каких-либо претензий друг к другу.
                    </li>
                    <li class="condition-item">
                        Настоящий акт составлен на русском языке в двух экземплярах равной юридической силы.
                    </li>
                </ol>
            </section>
        </main>
        
        <footer class="signatures-section">
            <div class="signatures-container">
                <div class="signature-block">
                    <span class="signature-title">Генеральный директор</span>
                    <div class="signature-company">ООО «{{ $act['customer'] }}»</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $act['customer_director'] ?? '____________________' }}</div>
                </div>
                
                <div class="signature-block" style="float: right;">
                    <span class="signature-title">Исполнитель</span>
                    <div class="signature-company">{{ $act['executor'] }}</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ $act['executor_fullname'] ?? '____________________' }}</div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>