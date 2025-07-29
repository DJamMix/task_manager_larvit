
@vite(['resources/css/welcome.css', 'resources/js/welcome.js'])

<div class="crewdev-welcome-page">
    <!-- Анимированный фон -->
    <div class="crewdev-particles"></div>

    <!-- Основной контент -->
    <div class="crewdev-container">
        <!-- Шапка -->
        <div class="crewdev-header animate-fade-in">
            <div class="crewdev-logo-icon">
                <x-orchid-icon path="rocket" class="crewdev-icon-blue"/>
            </div>
            <h1 class="crewdev-title">
                Добро пожаловать в <span class="crewdev-accent">CrewDev</span>
            </h1>
            <p class="crewdev-subtitle">
                Умный менеджер задач для эффективной работы
            </p>
        </div>

        <!-- Основная карточка -->
        <div class="crewdev-main-card">
            <!-- Заголовок карточки -->
            <div class="crewdev-card-header">
                <h2 class="crewdev-card-title">
                    Здравствуйте, <span class="crewdev-user-name">{{ $user->name }}</span>!
                </h2>
            </div>

            <!-- Контент карточки -->
            <div class="crewdev-card-content">
                @if($user->hasAccess('platform.systems.users'))
                    <!-- Админ панель -->
                    <div class="crewdev-admin-grid">
                        <a href="{{ route('platform.systems.users') }}" class="crewdev-admin-card">
                            <x-orchid-icon path="people" class="crewdev-card-icon"/>
                            <h3 class="crewdev-card-heading">Пользователи</h3>
                            <p class="crewdev-card-text">Управление пользователями системы</p>
                        </a>
                        <!-- Другие карточки админа -->
                    </div>
                @elseif($user->hasAccess('platform.systems.my_tasks'))
                    <!-- Сотрудник -->
                    <div class="crewdev-employee-section">
                        <h3 class="crewdev-section-title">Ваша текущая активность</h3>
                        <div class="crewdev-stats-grid">
                            <div class="crewdev-stat-card">
                                <div class="crewdev-stat-value">{{ $stats['active_tasks'] }}</div>
                                <div class="crewdev-stat-label">Активных задач</div>
                            </div>
                            <!-- Другая статистика -->
                        </div>
                        <a href="{{ route('platform.systems.my_tasks') }}" class="crewdev-primary-btn">
                            <x-orchid-icon path="task" class="crewdev-btn-icon"/>
                            Мои задачи
                        </a>
                    </div>
                @else
                    <!-- Клиент -->
                    <div class="crewdev-client-section">
                        <h3 class="crewdev-section-title">Ваши проекты</h3>
                        
                        @forelse($projects as $project)
                            <div class="crewdev-project-card">
                                <div class="crewdev-project-header">
                                    <h4 class="crewdev-project-name">{{ $project['name'] }}</h4>
                                    <span class="crewdev-project-progress">{{ $project['progress'] }}%</span>
                                </div>
                                <div class="crewdev-progress-bar">
                                    <div class="crewdev-progress-fill" style="width: {{ $project['progress'] }}%"></div>
                                </div>
                                <div class="crewdev-project-stats">
                                    Завершено: {{ $project['completed_tasks_count'] }}/{{ $project['tasks_count'] }}
                                </div>
                            </div>
                        @empty
                            <p class="crewdev-empty-projects">У вас пока нет активных проектов</p>
                        @endforelse
                        
                        <a href="{{ route('platform.systems.client.projects') }}" class="crewdev-primary-btn">
                            <x-orchid-icon path="folder" class="crewdev-btn-icon"/>
                            Все проекты
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Технологии -->
        <div class="crewdev-technologies">
            <h3 class="crewdev-tech-title">Технологии платформы</h3>
            <div class="crewdev-tech-grid">
                @foreach($technologies as $tech)
                    <div class="crewdev-tech-card">
                        <div class="crewdev-tech-icon">
                            <x-orchid-icon path="code" class="crewdev-icon-blue"/>
                        </div>
                        <h4 class="crewdev-tech-name">{{ $tech['name'] }}</h4>
                        <p class="crewdev-tech-purpose">{{ $tech['purpose'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>