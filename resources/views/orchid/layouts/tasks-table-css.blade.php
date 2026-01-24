<style>
/* Стили для таблицы задач */
.tasks-table {
    width: 100%;
    margin: 1rem 0;
}

.tasks-header {
    display: flex;
    width: 100%;
    padding: 0.75rem 0;
    border-bottom: 2px solid #dee2e6;
    font-weight: bold;
    background-color: #f8f9fa;
}

.tasks-row {
    display: flex;
    width: 100%;
    padding: 0.75rem 0;
    align-items: center;
}

.tasks-row:nth-child(even) {
    background-color: rgba(0,0,0,.02);
}

.tasks-row:hover {
    background-color: rgba(0,0,0,.05);
}

/* Колонки */
.col-select {
    width: 5%;
    text-align: center;
    min-width: 40px;
}

.col-task {
    width: 60%;
    padding: 0 10px;
    text-align: left;
}

.col-project {
    width: 15%;
    padding: 0 10px;
    text-align: left;
}

.col-hours {
    width: 20%;
    padding: 0 10px;
    text-align: right;
}

/* Стили для полей */
.readonly-field {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
}

.hours-input {
    text-align: right;
    width: 80px;
    margin-left: auto;
}

/* Чекбоксы */
.task-checkbox {
    margin: 0;
    vertical-align: middle;
}

/* Кнопки выбора */
.selection-buttons {
    margin-bottom: 1rem;
}

.selection-buttons .btn {
    margin-right: 0.5rem;
}

/* Итоговая строка */
.total-row {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 2px solid #dee2e6;
}

.total-row .form-group {
    margin-bottom: 0;
}

/* Адаптивность */
@media (max-width: 768px) {
    .tasks-table {
        overflow-x: auto;
    }
    
    .tasks-header, .tasks-row {
        min-width: 600px;
    }
    
    .col-select { width: 40px; }
    .col-task { width: 300px; }
    .col-project { width: 120px; }
    .col-hours { width: 100px; }
}
</style>