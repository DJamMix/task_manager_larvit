<form action="{{ $route }}" method="GET" class="mb-4">
    <div class="row">
        <div class="col-md-8">
            <label class="form-label">Быстрый поиск</label>
            <div class="input-group">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Поиск по названию, описанию..." 
                       value="{{ $search ?? '' }}"
                       onkeypress="if(event.keyCode === 13) this.form.submit()">
                <button type="submit" class="btn btn-primary">
                    <i class="icon-magnifier"></i> Найти
                </button>
                @if($search ?? false)
                    <a href="{{ $route }}" class="btn btn-secondary">
                        <i class="icon-close"></i> Сбросить
                    </a>
                @endif
            </div>
            <div class="form-text">Ищите задачи по названию, описанию или статусу</div>
        </div>
    </div>
</form>