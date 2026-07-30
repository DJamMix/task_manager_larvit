# TaskManager Mobile (Capacitor)

Android-приложение (APK) для TaskManagerLarVit: вход, чаты, звонки LiveKit, «мои задачи» и комментарии.

Работает через JSON API бэкенда: `/api/mobile/*`.

## Возможности (v1)

- Авторизация (Sanctum token)
- Список чатов + переписка + poll
- Аудио/видеозвонки (LiveKit), если настроены на сервере
- Мои задачи (исполнитель / наблюдатель) + комментарии
- Настройка URL сервера (прод / локальный)

## Требования

- Node.js 18+
- Android Studio (для сборки APK)
- JDK 17+ (рекомендуется; на машине может быть новее)
- Работающий Laravel-бэкенд с маршрутами `api/mobile`

## Быстрый старт (браузер)

```bash
cd mobile
npm install
npm run dev
```

В форме входа укажите API, например:

- прод: `https://tasks.crewdev.ru`
- эмулятор Android → хост-машина: `http://10.0.2.2:8000`
- устройство в той же сети: `http://192.168.x.x:8000`

## Сборка Android

```bash
cd mobile
npm install
npm run build
npx cap add android   # один раз
npx cap sync
npx cap open android
```

В Android Studio: **Build → Build Bundle(s) / APK(s) → Build APK(s)**.

Для HTTP (не HTTPS) к локальному API в `capacitor.config.ts` уже включён `server.cleartext: true`. На Android 9+ при необходимости добавьте `android:usesCleartextTraffic="true"` в `AndroidManifest.xml`.

## iOS

Позже: `npx cap add ios` на macOS + Xcode. Код UI общий.

## API (бэкенд)

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/api/mobile/login` | `{ email, password }` → token |
| GET | `/api/mobile/me` | профиль |
| POST | `/api/mobile/logout` | выход |
| GET | `/api/mobile/chats` | список чатов |
| GET | `/api/mobile/chats/{id}` | лента |
| POST | `/api/mobile/chats/{id}/messages` | отправка (`message[text]`) |
| GET | `/api/mobile/chats/poll` | обновления |
| POST | `/api/mobile/chats/{id}/calls/start` | звонок |
| GET | `/api/mobile/tasks` | мои задачи |
| GET | `/api/mobile/tasks/{id}` | карточка + комментарии |
| POST | `/api/mobile/tasks/{id}/comments` | `{ text }` |

Заголовок: `Authorization: Bearer <token>`.
