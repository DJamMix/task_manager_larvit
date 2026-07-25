@php
    $hasContext = (bool) ($has_project_context ?? false);
@endphp
<div class="act-list-intro">
    <div class="act-list-intro__card">
        <strong>Как составить акт</strong>
        <ol class="act-list-intro__steps mb-0">
            <li>Выберите проект{{ $hasContext ? ' (уже выбран в контексте)' : '' }}</li>
            <li>Отметьте задачи и при необходимости поправьте часы</li>
            <li>Сохраните и скачайте Word</li>
        </ol>
    </div>
</div>
