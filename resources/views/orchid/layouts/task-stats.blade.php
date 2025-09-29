<div class="mb-4">
    <h4 class="mb-3">Статистика задач</h4>
    <div class="row">
        <!-- Всего задач -->
        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?" class="card text-decoration-none">
                <div class="card-body text-center p-3">
                    <div class="text-primary mb-2">
                        <i class="icon-folder" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="text-dark">{{ $stats['total'] ?? 0 }}</h3>
                    <small class="text-muted">Всего задач</small>
                </div>
            </a>
        </div>

        <!-- Срочные -->
        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?priority=emergency,blocker" class="card text-decoration-none">
                <div class="card-body text-center p-3">
                    <div class="text-danger mb-2">
                        <i class="icon-fire" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="text-dark">{{ $stats['urgent'] ?? 0 }}</h3>
                    <small class="text-muted">Срочные</small>
                </div>
            </a>
        </div>

        <!-- Высокий приоритет -->
        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?priority=high" class="card text-decoration-none">
                <div class="card-body text-center p-3">
                    <div class="text-warning mb-2">
                        <i class="icon-clock" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="text-dark">{{ $stats['high_priority'] ?? 0 }}</h3>
                    <small class="text-muted">Высокий приоритет</small>
                </div>
            </a>
        </div>

        <!-- В работе -->
        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?status=in_progress" class="card text-decoration-none">
                <div class="card-body text-center p-3">
                    <div class="text-info mb-2">
                        <i class="icon-refresh" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="text-dark">{{ $stats['in_progress'] ?? 0 }}</h3>
                    <small class="text-muted">В работе</small>
                </div>
            </a>
        </div>

        <!-- Сегодня создано -->
        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?today=1" class="card text-decoration-none">
                <div class="card-body text-center p-3">
                    <div class="text-success mb-2">
                        <i class="icon-calendar" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="text-dark">{{ $stats['today_created'] ?? 0 }}</h3>
                    <small class="text-muted">Сегодня создано</small>
                </div>
            </a>
        </div>

        <!-- Просрочено -->
        <div class="col-md-4 col-lg-2 mb-3">
            <a href="?overdue=1" class="card text-decoration-none">
                <div class="card-body text-center p-3">
                    <div class="text-dark mb-2">
                        <i class="icon-exclamation" style="font-size: 2rem;"></i>
                    </div>
                    <h3 class="text-dark">{{ $stats['overdue'] ?? 0 }}</h3>
                    <small class="text-muted">Просрочено</small>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
.card {
    transition: all 0.3s ease;
    border: 1px solid #e3e6f0;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-color: #b7b9cc;
}

.icon-folder:before { content: "📁"; font-style: normal; }
.icon-fire:before { content: "🔥"; font-style: normal; }
.icon-clock:before { content: "⏰"; font-style: normal; }
.icon-refresh:before { content: "🔄"; font-style: normal; }
.icon-calendar:before { content: "📅"; font-style: normal; }
.icon-exclamation:before { content: "⚠️"; font-style: normal; }
</style>