<div class="mb-4">
    <h4 class="mb-3">Статистика задач</h4>
    <div class="row">
        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?" class="card text-decoration-none bg-white rounded-3">
                <div class="card-body text-center p-4">
                    <div class="text-primary mb-2"><i class="icon-folder" style="font-size: 2rem;"></i></div>
                    <h3 class="text-dark mb-1">{{ $stats['total'] ?? 0 }}</h3>
                    <small class="text-muted">Всего задач</small>
                </div>
            </a>
        </div>

        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?priority[]=blocker&priority[]=emergency" class="card text-decoration-none bg-white rounded-3">
                <div class="card-body text-center p-4">
                    <div class="text-danger mb-2"><i class="icon-fire" style="font-size: 2rem;"></i></div>
                    <h3 class="text-dark mb-1">{{ $stats['urgent'] ?? 0 }}</h3>
                    <small class="text-muted">Срочные</small>
                </div>
            </a>
        </div>

        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?priority[]=high" class="card text-decoration-none bg-white rounded-3">
                <div class="card-body text-center p-4">
                    <div class="text-warning mb-2"><i class="icon-clock" style="font-size: 2rem;"></i></div>
                    <h3 class="text-dark mb-1">{{ $stats['high_priority'] ?? 0 }}</h3>
                    <small class="text-muted">Высокий приоритет</small>
                </div>
            </a>
        </div>

        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?filter[status]=in_progress" class="card text-decoration-none bg-white rounded-3">
                <div class="card-body text-center p-4">
                    <div class="text-info mb-2"><i class="icon-refresh" style="font-size: 2rem;"></i></div>
                    <h3 class="text-dark mb-1">{{ $stats['in_progress'] ?? 0 }}</h3>
                    <small class="text-muted">В работе</small>
                </div>
            </a>
        </div>

        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?" class="card text-decoration-none bg-white rounded-3">
                <div class="card-body text-center p-4">
                    <div class="text-danger mb-2"><i class="icon-calendar" style="font-size: 2rem;"></i></div>
                    <h3 class="text-dark mb-1">{{ $stats['overdue'] ?? 0 }}</h3>
                    <small class="text-muted">Просрочено</small>
                </div>
            </a>
        </div>

        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?filter[status]=completed" class="card text-decoration-none bg-white rounded-3">
                <div class="card-body text-center p-4">
                    <div class="text-success mb-2"><i class="icon-check" style="font-size: 2rem;"></i></div>
                    <h3 class="text-dark mb-1">{{ $stats['completed'] ?? 0 }}</h3>
                    <small class="text-muted">Завершено</small>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
.card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}
.card-body { padding: 1.5rem !important; }
.icon-folder:before { content: "📁"; font-style: normal; }
.icon-fire:before { content: "🔥"; font-style: normal; }
.icon-clock:before { content: "⏰"; font-style: normal; }
.icon-refresh:before { content: "🔄"; font-style: normal; }
.icon-calendar:before { content: "📅"; font-style: normal; }
.icon-check:before { content: "✅"; font-style: normal; }
</style>
