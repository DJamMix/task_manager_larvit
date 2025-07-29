<div class="telegram-connect-wrapper">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm animate__animated animate__fadeIn">
                    <div class="card-body p-5 text-center">
                        <!-- Анимированная иконка Telegram -->
                        <div class="telegram-icon animate__animated animate__bounceIn mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="#0088cc">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.03-.09.06-.42-.08-.59-.14-.17-.42-.12-.6-.07-.26.08-4.39 2.79-6.21 3.88-.59.36-1.13.54-1.62.53-.53-.01-1.54-.3-2.29-.54-.92-.3-1.66-.46-1.6-.97.03-.28.4-.57 1.1-.88 4.48-1.96 7.47-3.3 9.39-4.04 4.1-1.57 4.95-1.84 5.5-1.94.16-.03.48-.07.7.08.17.12.22.3.23.42.02.12.03.39-.03.61z"/>
                            </svg>
                        </div>

                        <h3 class="card-title mb-4 animate__animated animate__fadeInUp">Подключение Telegram</h3>
                        
                        <!-- Анимированный код -->
                        <div class="verification-code animate__animated animate__fadeIn animate__delay-1s">
                            <p class="text-muted mb-2">Ваш код для привязки:</p>
                            <div class="d-flex justify-content-center">
                                <div class="code-badge animate__animated animate__pulse animate__infinite">
                                    {{ $code }}
                                </div>
                            </div>
                        </div>

                        <!-- Основная кнопка -->
                        <div class="mt-4 animate__animated animate__fadeIn animate__delay-1s">
                            <a href="{{ $url }}" 
                               class="btn btn-telegram btn-lg px-5 py-3"
                               target="_blank"
                               rel="noopener noreferrer"
                               onclick="window.open('{{ $url }}', '_blank').focus(); return false;">
                                <i class="icon-paper-plane me-2"></i> Открыть Telegram
                            </a>
                        </div>

                        <!-- Альтернативная инструкция -->
                        <div class="alternative-instructions mt-5 animate__animated animate__fadeIn animate__delay-2s">
                            <div class="alert alert-light border">
                                <h5 class="alert-heading mb-3">
                                    <i class="icon-question me-2"></i>Не открылось?
                                </h5>
                                
                                <div class="input-group mb-3">
                                    <input type="text" 
                                           class="form-control copy-input" 
                                           value="{{ $code }}" 
                                           id="telegramCode" 
                                           readonly>
                                    <button class="btn btn-outline-primary copy-btn" 
                                            type="button" 
                                            onclick="copyCode()">
                                        <i class="icon-copy me-1"></i> Копировать
                                    </button>
                                </div>
                                
                                <ol class="text-start ps-3">
                                    <li class="mb-2">Откройте Telegram и найдите <strong>@crewdev_task_manage_bot</strong></li>
                                    <li class="mb-2">Нажмите "Start" или отправьте команду <code>/start</code></li>
                                    <li>Вставьте скопированный код</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .telegram-connect-wrapper {
        background: linear-gradient(135deg, #ffffff 0%, #ffffff 100%);
        min-height: 100vh;
        padding-top: 2rem;
        border-radius: 10px;
    }
    
    .card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-5px);
    }
    
    .telegram-icon {
        margin: 0 auto;
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 136, 204, 0.1);
        border-radius: 50%;
    }
    
    .btn-telegram {
        background-color: #0088cc;
        color: white;
        border: none;
        font-weight: 500;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 136, 204, 0.2);
    }
    
    .btn-telegram:hover {
        background-color: #0077b3;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 8px rgba(0, 136, 204, 0.3);
    }
    
    .code-badge {
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: 2px;
        padding: 0.75rem 1.5rem;
        background: rgba(0, 136, 204, 0.1);
        color: #0088cc;
        border-radius: 8px;
        border: 1px dashed #0088cc;
    }
    
    .copy-input {
        border-right: none;
        font-weight: 500;
        letter-spacing: 1px;
    }
    
    .copy-btn {
        transition: all 0.2s ease;
    }
    
    .copy-btn:hover {
        background-color: #0088cc;
        color: white;
    }
    
    .animate__delay-1s {
        animation-delay: 0.5s;
    }
    
    .animate__delay-2s {
        animation-delay: 1s;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<script>
    function copyCode() {
        const codeInput = document.getElementById('telegramCode');
        codeInput.select();
        document.execCommand('copy');
        
        // Анимация кнопки
        const copyBtn = document.querySelector('.copy-btn');
        copyBtn.innerHTML = '<i class="icon-check me-1"></i> Скопировано!';
        copyBtn.classList.add('btn-success');
        copyBtn.classList.remove('btn-outline-primary');
        
        setTimeout(() => {
            copyBtn.innerHTML = '<i class="icon-copy me-1"></i> Копировать';
            copyBtn.classList.remove('btn-success');
            copyBtn.classList.add('btn-outline-primary');
        }, 2000);
        
        // Показать уведомление
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: 'success',
            title: 'Код скопирован в буфер обмена!'
        });
    }
    
    // Автоматически открыть Telegram в новой вкладке при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            window.open('{{ $url }}', '_blank');
        }, 500);
    });
</script>