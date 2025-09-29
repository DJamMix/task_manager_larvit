<form action="{{ route('platform.mytasks') }}" method="GET" class="mb-4">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Быстрый поиск</h5>
            <div class="row">
                <div class="col-md-8">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Поиск по названию, описанию..." 
                           value="{{ $search ?? '' }}"
                           onkeypress="if(event.keyCode == 13) this.form.submit()">
                    <small class="form-text text-muted">Ищите задачи по названию, описанию или статусу</small>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-magnifier"></i> Найти
                    </button>
                    @if($search ?? false)
                        <a href="{{ route('platform.mytasks') }}" class="btn btn-secondary">
                            <i class="icon-close"></i> Сбросить
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</form>