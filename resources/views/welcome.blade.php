{{-- resources/views/welcome.blade.php --}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ env('APP_NAME') }} HelpDesk</title>
    <!-- Подключаем стили Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Фиксируем видео на фоне */
        .video-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1; /* Видео находится позади других элементов */
        }

        /* Основной контейнер */
        .content-container {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body class="font-sans antialiased flex items-center justify-center h-screen">

    <!-- Видео на фоне -->
    <video class="video-background" autoplay muted loop>
        <source src="{{ asset('videos/background.mp4') }}" type="video/mp4">
        Ваш браузер не поддерживает видео.
    </video>

    <!-- Контент -->
    <div class="content-container bg-white p-8 rounded-xl shadow-lg max-w-md w-full text-center">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">
            Добро пожаловать
        </h1>

        <p class="text-xl text-gray-600 mb-6">
            в менеджер задач {{ env('APP_NAME') }}
        </p>

        <a href="/admin" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-full text-lg font-semibold hover:bg-blue-700 transition duration-300">
            Перейти в панель управления
        </a>
    </div>

</body>
</html>
