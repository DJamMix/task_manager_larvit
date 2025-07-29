document.addEventListener('DOMContentLoaded', function() {
    // Инициализация частиц фона
    initParticles();
    
    // Анимация карточек при наведении
    const cards = document.querySelectorAll('.crewdev-admin-card, .crewdev-stat-card, .crewdev-project-card, .crewdev-tech-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });

    // Добавляем анимацию для всех элементов с задержкой
    const animatedElements = document.querySelectorAll('[class*="crewdev-"]');
    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.animation = `crewdev-fadeIn 0.6s ease-out ${index * 0.1}s forwards`;
    });
});

function initParticles() {
    const container = document.querySelector('.crewdev-particles');
    if (!container) return;
    
    // Создаем частицы для фона
    for (let i = 0; i < 8; i++) {
        const particle = document.createElement('div');
        particle.className = 'crewdev-particle';
        
        // Случайные параметры
        const size = Math.random() * 150 + 50;
        const posX = Math.random() * 100;
        const posY = Math.random() * 100;
        const delay = Math.random() * 5;
        const duration = Math.random() * 20 + 10;
        const opacity = Math.random() * 0.1 + 0.03;
        
        // Применяем стили
        particle.style.width = `${size}px`;
        particle.style.height = `${size}px`;
        particle.style.left = `${posX}%`;
        particle.style.top = `${posY}%`;
        particle.style.opacity = opacity;
        particle.style.animation = `crewdev-float ${duration}s infinite ${delay}s ease-in-out`;
        particle.style.background = `radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%)`;
        
        container.appendChild(particle);
    }
}