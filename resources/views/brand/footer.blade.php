<footer class="task-manager-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <span class="logo">CrewDev</span>
            <p class="footer-slogan">Управляйте задачами эффективно</p>
        </div>
        
        <div class="footer-links">
            <div class="tech-links">
                <h4>Технологии</h4>
                <ul>
                    <li><a href="#" target="_blank">Laravel</a></li>
                    <li><a href="#" target="_blank">Vue.js</a></li>
                    <li><a href="#" target="_blank">Docker</a></li>
                </ul>
            </div>
            
            <div class="dev-links">
                <h4>Разработка</h4>
                <ul>
                    <li><a href="#" target="_blank">GitHub</a></li>
                    <li><a href="#" target="_blank">Документация</a></li>
                    <li><a href="#" target="_blank">API</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="footer-copyright">
        <p>© {{ date('Y') }} CrewDev. Все права защищены.</p>
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Можно добавить плавное появление футера
        const footer = document.querySelector('.task-manager-footer');
        if (footer) {
            footer.style.opacity = '0';
            footer.style.transition = 'opacity 0.5s ease';
            
            setTimeout(() => {
                footer.style.opacity = '1';
            }, 100);
        }
        
        // Дополнительные скрипты при необходимости
    });
</script>