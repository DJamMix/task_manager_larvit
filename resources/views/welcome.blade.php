<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ env('APP_NAME') }} HelpDesk</title>
    <!-- Подключаем Tailwind CSS с дополнительными настройками -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'fade-in': 'fadeIn 1s ease-in-out',
                        'slide-up': 'slideUp 0.8s ease-out',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { 
                                opacity: '0',
                                transform: 'translateY(20px)'
                            },
                            '100%': { 
                                opacity: '1',
                                transform: 'translateY(0)'
                            },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f5f5 0%, #e5e5e5 100%);
            overflow-x: hidden;
        }
        
        .card {
            backdrop-filter: blur(16px);
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-primary {
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: all 0.5s;
        }
        
        .btn-primary:hover::after {
            left: 100%;
        }
        
        .task-preview {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .task-preview:hover {
            transform: translateX(5px);
            border-left-color: #3b82f6;
        }
        
        .logo {
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        
        .particle {
            position: absolute;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            pointer-events: none;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <!-- Частицы фона -->
    <div id="particles-js"></div>
    
    <!-- Основной контент -->
    <div class="card w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden animate-fade-in">
        <div class="flex flex-col md:flex-row">
            <!-- Левая часть - информация -->
            <div class="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center">
                <div class="animate-slide-up">
                    <div class="flex items-center justify-center md:justify-start mb-6">
                        <svg class="logo w-10 h-10 text-gray-900 mr-3" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 8V16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M8 12H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h1 class="text-2xl font-bold text-gray-900">CrewDev</h1>
                    </div>
                    
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Менеджер задач</h2>
                    <p class="text-gray-600 mb-8">Профессиональное управление проектами для вашей команды и клиентов</p>
                    
                    <div class="space-y-4 mb-8">
                        <div class="task-preview bg-white p-4 rounded-lg shadow-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-blue-500 mr-3"></div>
                                <p class="text-gray-800 font-medium">Новая функция разработки</p>
                            </div>
                            <p class="text-gray-500 text-sm mt-1">В процессе • Приоритет: Высокий</p>
                        </div>
                        
                        <div class="task-preview bg-white p-4 rounded-lg shadow-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-green-500 mr-3"></div>
                                <p class="text-gray-800 font-medium">Исправление бага</p>
                            </div>
                            <p class="text-gray-500 text-sm mt-1">Завершено • Клиент: ООО "Технологии"</p>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="/admin" class="btn-primary px-6 py-3 bg-gray-900 text-white rounded-lg font-medium text-center transition-all duration-300">
                            Панель управления
                        </a>
                        <a href="#" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-medium text-center hover:bg-gray-50 transition-all duration-300">
                            Поробуй потом
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Правая часть - графическая -->
            <div class="w-full md:w-1/2 bg-gray-50 p-8 md:p-12 flex items-center justify-center relative overflow-hidden">
                <div class="relative w-full h-64 md:h-full">
                    <!-- Анимированные элементы интерфейса -->
                    <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-xl shadow-md animate-float" style="animation-delay: 0s;">
                        <div class="p-4">
                            <div class="flex justify-between items-center mb-3">
                                <div class="w-20 h-2 bg-gray-200 rounded"></div>
                                <div class="w-6 h-6 bg-gray-200 rounded-full"></div>
                            </div>
                            <div class="space-y-2">
                                <div class="w-full h-2 bg-gray-100 rounded"></div>
                                <div class="w-3/4 h-2 bg-gray-100 rounded"></div>
                                <div class="w-1/2 h-2 bg-gray-100 rounded"></div>
                            </div>
                            <div class="mt-4 flex space-x-2">
                                <div class="w-8 h-8 bg-blue-100 rounded-full"></div>
                                <div class="w-8 h-8 bg-green-100 rounded-full"></div>
                                <div class="w-8 h-8 bg-yellow-100 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="absolute top-1/4 right-0 w-56 h-56 bg-white rounded-xl shadow-md animate-float" style="animation-delay: 1s;">
                        <div class="p-4">
                            <div class="flex items-center mb-3">
                                <div class="w-6 h-6 bg-gray-200 rounded-full mr-2"></div>
                                <div class="w-16 h-2 bg-gray-200 rounded"></div>
                            </div>
                            <div class="h-24 bg-gray-100 rounded mb-2"></div>
                            <div class="flex justify-between">
                                <div class="w-12 h-2 bg-gray-200 rounded"></div>
                                <div class="w-8 h-2 bg-gray-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="absolute bottom-0 left-1/4 w-48 h-48 bg-white rounded-xl shadow-md animate-float" style="animation-delay: 2s;">
                        <div class="p-3">
                            <div class="flex items-center mb-2">
                                <div class="w-4 h-4 bg-gray-200 rounded-full mr-2"></div>
                                <div class="w-12 h-1.5 bg-gray-200 rounded"></div>
                            </div>
                            <div class="space-y-1.5">
                                <div class="w-full h-1 bg-gray-100 rounded"></div>
                                <div class="w-full h-1 bg-gray-100 rounded"></div>
                                <div class="w-2/3 h-1 bg-gray-100 rounded"></div>
                            </div>
                            <div class="mt-2 flex justify-end">
                                <div class="w-6 h-6 bg-gray-200 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Создание частиц фона
        document.addEventListener('DOMContentLoaded', function() {
            const particlesContainer = document.getElementById('particles-js');
            const particleCount = 30;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Случайные параметры частицы
                const size = Math.random() * 10 + 5;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const opacity = Math.random() * 0.2 + 0.05;
                const animationDuration = Math.random() * 20 + 10;
                const animationDelay = Math.random() * 5;
                
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${posX}%`;
                particle.style.top = `${posY}%`;
                particle.style.opacity = opacity;
                particle.style.animation = `float ${animationDuration}s ease-in-out ${animationDelay}s infinite`;
                
                particlesContainer.appendChild(particle);
            }
            
            // Анимация при наведении на кнопку
            const btnPrimary = document.querySelector('.btn-primary');
            if (btnPrimary) {
                btnPrimary.addEventListener('mouseenter', function() {
                    const particles = document.querySelectorAll('.particle');
                    particles.forEach(particle => {
                        particle.style.transform = 'translateY(-5px)';
                    });
                });
                
                btnPrimary.addEventListener('mouseleave', function() {
                    const particles = document.querySelectorAll('.particle');
                    particles.forEach(particle => {
                        particle.style.transform = 'translateY(0)';
                    });
                });
            }
        });
        
        // Плавное появление элементов при загрузке
        document.body.style.opacity = '0';
        window.addEventListener('load', function() {
            document.body.style.transition = 'opacity 0.5s ease-in-out';
            document.body.style.opacity = '1';
        });
    </script>
</body>
</html>