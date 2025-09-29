<div class="mb-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="card-title mb-3 text-dark">
                        <i class="icon-magnifier me-2 text-primary"></i>
                        Быстрый поиск задач
                    </h5>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="icon-magnifier text-muted"></i>
                        </span>
                        <input type="text" 
                               name="search" 
                               class="form-control border-start-0 ps-0" 
                               placeholder="Введите название задачи, описание..." 
                               value="{{ request('search') }}"
                               onkeypress="if(event.keyCode == 13) this.form.submit()">
                    </div>
                    <small class="form-text text-muted mt-2">
                        <i class="icon-info me-1"></i>
                        Поиск по названию, описанию задачи или статусу
                    </small>
                </div>
                <div class="col-md-4">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                            <i class="icon-magnifier me-2"></i>
                            Найти задачи
                        </button>
                        @if(request('search'))
                            <a href="{{ route('platform.systems.my_tasks') }}" class="btn btn-outline-secondary">
                                <i class="icon-close me-2"></i>
                                Сбросить поиск
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form action="{{ route('platform.systems.my_tasks') }}" method="GET" class="d-none">
    <input type="text" name="search" value="{{ request('search') }}" id="search-input">
    <button type="submit" id="search-submit"></button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const hiddenInput = document.getElementById('search-input');
    const hiddenForm = document.querySelector('form.d-none');
    
    searchInput.addEventListener('input', function() {
        hiddenInput.value = this.value;
    });
    
    // Обработка кнопки поиска
    document.querySelector('button[type="submit"]').addEventListener('click', function(e) {
        e.preventDefault();
        hiddenForm.submit();
    });
    
    // Обработка Enter в поле ввода
    searchInput.addEventListener('keypress', function(e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            hiddenForm.submit();
        }
    });
});
</script>

<style>
.input-group-text {
    border-radius: 8px 0 0 8px !important;
    border-right: none !important;
}

.form-control {
    border-radius: 0 8px 8px 0 !important;
    border-left: none !important;
}

.form-control:focus {
    box-shadow: none;
    border-color: #dee2e6;
}

.card {
    border-radius: 12px;
    background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
}

.btn-primary {
    border-radius: 8px;
    font-weight: 500;
}

.btn-outline-secondary {
    border-radius: 8px;
}
</style>