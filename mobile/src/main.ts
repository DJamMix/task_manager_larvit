import './style.css';
import { StatusBar, Style } from '@capacitor/status-bar';
import { Capacitor } from '@capacitor/core';
import {
  api,
  ApiError,
  bootSession,
  clearSession,
  getApiBase,
  getToken,
  getUser,
  mediaUrl,
  setApiBase,
  setSession,
} from './api';
import type { ChatMessage, ChatSummary, CommentCard, TaskCard } from './types';
import {
  connectRoom,
  disconnectRoom,
  endCall,
  leaveCall,
  startCall,
  type CallConnection,
} from './calls';

type Route =
  | { name: 'login' }
  | { name: 'chats' }
  | { name: 'chat'; id: number; title: string }
  | { name: 'tasks' }
  | { name: 'task'; id: number }
  | { name: 'settings' };

const app = document.querySelector<HTMLDivElement>('#app')!;
let route: Route = { name: 'login' };
let pollTimer: number | null = null;
let sinceId = 0;
let activeCall: CallConnection | null = null;

function go(next: Route) {
  route = next;
  stopPoll();
  void render();
}

function esc(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function avatarHtml(a: { url?: string; initials: string; color: string; shape?: string }): string {
  const cls = a.shape === 'square' ? 'avatar square' : 'avatar';
  if (a.url) {
    return `<div class="${cls}" style="background:${a.color}"><img src="${esc(a.url)}" alt="" /></div>`;
  }
  return `<div class="${cls}" style="background:${a.color}">${esc(a.initials)}</div>`;
}

function shell(opts: {
  title: string;
  back?: () => void;
  actions?: string;
  body: string;
  footer?: string;
  tabs?: 'chats' | 'tasks';
}): string {
  return `
    <div class="app-shell">
      <header class="topbar">
        ${opts.back ? `<button class="icon-btn" type="button" data-act="back" aria-label="Назад">‹</button>` : ''}
        <h1>${esc(opts.title)}</h1>
        ${opts.actions || ''}
      </header>
      <main class="content" id="main">${opts.body}</main>
      ${opts.footer || ''}
      ${
        opts.tabs
          ? `<nav class="tabs">
              <button type="button" data-tab="chats" class="${opts.tabs === 'chats' ? 'active' : ''}">Чаты</button>
              <button type="button" data-tab="tasks" class="${opts.tabs === 'tasks' ? 'active' : ''}">Задачи</button>
            </nav>`
          : ''
      }
    </div>
    <div id="call-root"></div>
  `;
}

async function renderLogin() {
  app.innerHTML = `
    <div class="login">
      <div class="login-card">
        <h1 class="brand">TaskManager</h1>
        <p>Мобильный доступ к чатам и вашим задачам</p>
        <form id="login-form">
          <div class="field">
            <label>Сервер</label>
            <input name="api" value="${esc(getApiBase())}" autocomplete="url" />
          </div>
          <div class="field">
            <label>Email</label>
            <input name="email" type="email" required autocomplete="username" />
          </div>
          <div class="field">
            <label>Пароль</label>
            <input name="password" type="password" required autocomplete="current-password" />
          </div>
          <p class="error hidden" id="login-error"></p>
          <button class="btn btn-primary" type="submit">Войти</button>
        </form>
      </div>
    </div>
  `;

  app.querySelector('#login-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target as HTMLFormElement;
    const fd = new FormData(form);
    const err = app.querySelector('#login-error') as HTMLElement;
    err.classList.add('hidden');
    try {
      await setApiBase(String(fd.get('api') || ''));
      const data = await api<{ token: string; user: any }>('/login', {
        method: 'POST',
        body: {
          email: String(fd.get('email')),
          password: String(fd.get('password')),
          device_name: Capacitor.getPlatform(),
        } as any,
      });
      await setSession(data.token, data.user);
      go({ name: 'chats' });
    } catch (ex) {
      err.textContent = ex instanceof ApiError ? ex.message : 'Не удалось войти';
      err.classList.remove('hidden');
    }
  });
}

async function renderChats() {
  app.innerHTML = shell({
    title: 'Чаты',
    tabs: 'chats',
    actions: `<button class="icon-btn" type="button" data-act="settings" title="Настройки">⚙</button>`,
    body: `<div class="empty">Загрузка…</div>`,
  });
  bindChrome();

  try {
    const data = await api<{ chats: ChatSummary[] }>('/chats');
    const main = app.querySelector('#main')!;
    if (!data.chats.length) {
      main.innerHTML = `<div class="empty">Пока нет чатов</div>`;
      return;
    }
    main.innerHTML = data.chats
      .map(
        (c) => `
      <button class="list-item" type="button" data-chat="${c.id}" data-title="${esc(c.title)}">
        ${avatarHtml(c.avatar)}
        <div class="list-body">
          <div class="list-title">
            <span>${esc(c.title)}</span>
            ${c.unread ? `<span class="badge">${c.unread}</span>` : ''}
          </div>
          <div class="list-preview">${esc(c.preview || 'Нет сообщений')}</div>
        </div>
      </button>`,
      )
      .join('');

    main.querySelectorAll('[data-chat]').forEach((el) => {
      el.addEventListener('click', () => {
        const id = Number((el as HTMLElement).dataset.chat);
        const title = (el as HTMLElement).dataset.title || 'Чат';
        go({ name: 'chat', id, title });
      });
    });

    startListPoll();
  } catch (ex) {
    app.querySelector('#main')!.innerHTML = `<div class="empty">${esc(
      ex instanceof Error ? ex.message : 'Ошибка',
    )}</div>`;
  }
}

function messageHtml(m: ChatMessage): string {
  if (m.system) {
    return `<div class="msg system" data-id="${m.id}">${esc(m.text)}</div>`;
  }
  const atts = m.attachments
    .map((a) => {
      if (a.kind === 'image') {
        return `<img class="msg-att" data-media="${esc(a.url)}" alt="${esc(a.name)}" />`;
      }
      if (a.kind === 'voice') {
        return `<audio class="msg-att" controls preload="none" data-media="${esc(a.url)}"></audio>`;
      }
      return `<a class="msg-att" href="${esc(a.url)}" target="_blank" rel="noopener">${esc(a.name)}</a>`;
    })
    .join('');

  return `
    <div class="msg ${m.mine ? 'mine' : ''}" data-id="${m.id}">
      <div class="bubble">
        ${!m.mine ? `<div class="msg-author">${esc(m.author.name)}</div>` : ''}
        ${
          m.parent
            ? `<div class="msg-reply"><strong>${esc(m.parent.author)}</strong><br>${esc(m.parent.preview)}</div>`
            : ''
        }
        ${m.task ? `<div class="msg-reply">Задача #${m.task.id}: ${esc(m.task.name)}</div>` : ''}
        ${m.deleted ? `<em>Сообщение удалено</em>` : `<div class="msg-text">${esc(m.text)}</div>`}
        ${atts}
        <div class="msg-meta"><span>${esc(m.created_label)}</span></div>
      </div>
    </div>`;
}

async function hydrateMedia(root: ParentNode) {
  const nodes = root.querySelectorAll<HTMLElement>('[data-media]');
  for (const el of Array.from(nodes)) {
    const src = el.dataset.media;
    if (!src) continue;
    try {
      const blob = await mediaUrl(src);
      if (el.tagName === 'IMG' || el.tagName === 'AUDIO' || el.tagName === 'VIDEO') {
        (el as HTMLMediaElement | HTMLImageElement).src = blob;
      }
    } catch {
      /* keep original */
    }
  }
}

async function renderChat(id: number, title: string) {
  app.innerHTML = shell({
    title,
    back: () => go({ name: 'chats' }),
    actions: `
      <button class="icon-btn" type="button" data-act="call-audio" title="Аудиозвонок">📞</button>
      <button class="icon-btn" type="button" data-act="call-video" title="Видеозвонок">🎥</button>
    `,
    body: `<div class="empty">Загрузка…</div>`,
    footer: `
      <div class="typing hidden" id="typing"></div>
      <form class="composer" id="composer">
        <textarea name="text" rows="1" placeholder="Сообщение" required></textarea>
        <button class="btn btn-primary" type="submit">➤</button>
      </form>
    `,
  });
  bindChrome();

  app.querySelector('[data-act="call-audio"]')?.addEventListener('click', () => void openCall(id, false));
  app.querySelector('[data-act="call-video"]')?.addEventListener('click', () => void openCall(id, true));

  try {
    const data = await api<{
      messages: ChatMessage[];
      has_more: boolean;
      oldest_id: number | null;
    }>(`/chats/${id}`);
    const main = app.querySelector('#main')!;
    sinceId = data.messages.reduce((max, m) => Math.max(max, m.id), 0);
    main.innerHTML = `<div class="chat-feed" id="feed">${data.messages.map(messageHtml).join('')}</div>`;
    await hydrateMedia(main);
    main.scrollTop = main.scrollHeight;

    const composer = app.querySelector('#composer') as HTMLFormElement;
    const ta = composer.querySelector('textarea')!;
    ta.addEventListener('input', () => {
      ta.style.height = 'auto';
      ta.style.height = Math.min(120, ta.scrollHeight) + 'px';
      void api(`/chats/${id}/typing`, { method: 'POST' }).catch(() => undefined);
    });
    composer.addEventListener('submit', async (e) => {
      e.preventDefault();
      const text = ta.value.trim();
      if (!text) return;
      ta.value = '';
      ta.style.height = '';
      try {
        const fd = new FormData();
        fd.append('message[text]', text);
        const res = await api<{ message: ChatMessage }>(`/chats/${id}/messages`, {
          method: 'POST',
          formData: fd,
        });
        const feed = app.querySelector('#feed')!;
        feed.insertAdjacentHTML('beforeend', messageHtml(res.message));
        sinceId = Math.max(sinceId, res.message.id);
        main.scrollTop = main.scrollHeight;
      } catch (ex) {
        alert(ex instanceof Error ? ex.message : 'Не отправлено');
      }
    });

    startChatPoll(id);
  } catch (ex) {
    app.querySelector('#main')!.innerHTML = `<div class="empty">${esc(
      ex instanceof Error ? ex.message : 'Ошибка',
    )}</div>`;
  }
}

function startListPoll() {
  stopPoll();
  pollTimer = window.setInterval(async () => {
    if (route.name !== 'chats') return;
    try {
      const data = await api<{ chats: ChatSummary[] }>(`/chats/poll?since=${sinceId || 0}`);
      // soft refresh list titles/unread
      const main = app.querySelector('#main');
      if (!main || !data.chats) return;
      // Only update badges without full redraw if DOM matches
      data.chats.forEach((c) => {
        const el = main.querySelector(`[data-chat="${c.id}"]`);
        if (!el) return;
        const badge = el.querySelector('.badge');
        const preview = el.querySelector('.list-preview');
        if (preview) preview.textContent = c.preview || 'Нет сообщений';
        if (c.unread) {
          if (badge) badge.textContent = String(c.unread);
          else {
            const title = el.querySelector('.list-title');
            title?.insertAdjacentHTML('beforeend', `<span class="badge">${c.unread}</span>`);
          }
        } else {
          badge?.remove();
        }
      });
    } catch {
      /* ignore */
    }
  }, 4000);
}

function startChatPoll(chatId: number) {
  stopPoll();
  pollTimer = window.setInterval(async () => {
    if (route.name !== 'chat' || route.id !== chatId) return;
    try {
      const data = await api<{
        messages: ChatMessage[];
        typing?: Array<{ user_id: number; name: string }>;
        calls?: any[];
        max_id?: number;
      }>(`/chats/poll?since=${sinceId}&chat=${chatId}`);

      const feed = app.querySelector('#feed');
      const main = app.querySelector('#main');
      if (feed && data.messages?.length) {
        const atBottom = main ! && main.scrollHeight - main.scrollTop - main.clientHeight < 80;
        for (const m of data.messages) {
          if (feed.querySelector(`[data-id="${m.id}"]`)) continue;
          feed.insertAdjacentHTML('beforeend', messageHtml(m));
          sinceId = Math.max(sinceId, m.id);
        }
        await hydrateMedia(feed);
        if (atBottom && main) main.scrollTop = main.scrollHeight;
      }
      if (typeof data.max_id === 'number' && data.max_id > sinceId) {
        sinceId = data.max_id;
      }

      const typing = app.querySelector('#typing');
      if (typing) {
        const names = (data.typing || []).map((t) => t.name).filter(Boolean);
        if (names.length) {
          typing.textContent = `${names.join(', ')} печатает…`;
          typing.classList.remove('hidden');
        } else {
          typing.classList.add('hidden');
        }
      }

      const incoming = (data.calls || []).find((c: any) => c.status === 'ringing' || c.status === 'active');
      if (incoming && !activeCall) {
        // show soft prompt once
        if (confirm(`Входящий звонок. Присоединиться?`)) {
          await openCallJoin(incoming.id || incoming.call_id);
        }
      }
    } catch {
      /* ignore */
    }
  }, 2500);
}

function stopPoll() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

async function openCall(chatId: number, video: boolean) {
  try {
    const conn = await startCall(chatId, video);
    await mountCallUi(conn);
  } catch (ex) {
    alert(ex instanceof Error ? ex.message : 'Звонок недоступен');
  }
}

async function openCallJoin(callId: number) {
  try {
    const conn = await api<CallConnection>(`/calls/${callId}/join`, { method: 'POST' });
    await mountCallUi(conn);
  } catch (ex) {
    alert(ex instanceof Error ? ex.message : 'Не удалось войти в звонок');
  }
}

async function mountCallUi(conn: CallConnection) {
  activeCall = conn;
  const root = app.querySelector('#call-root') || app;
  root.innerHTML = `
    <div class="call-overlay" id="call-overlay">
      <h2 style="margin:0 0 .75rem">Звонок</h2>
      <div id="remote-videos" style="flex:1;display:flex;flex-direction:column;gap:.5rem;overflow:auto"></div>
      <video id="local-video" autoplay playsinline muted></video>
      <div class="call-actions">
        ${conn.can_end ? `<button class="btn btn-danger" type="button" id="end-call">Завершить</button>` : ''}
        <button class="btn btn-primary" type="button" id="leave-call">Выйти</button>
      </div>
    </div>
  `;
  const local = document.querySelector('#local-video') as HTMLVideoElement;
  const remote = document.querySelector('#remote-videos') as HTMLDivElement;
  try {
    await connectRoom(conn, { local, remote });
  } catch (ex) {
    alert(ex instanceof Error ? ex.message : 'WebRTC ошибка');
    await hangup(false);
    return;
  }

  document.querySelector('#leave-call')?.addEventListener('click', () => void hangup(false));
  document.querySelector('#end-call')?.addEventListener('click', () => void hangup(true));
}

async function hangup(end: boolean) {
  const id = activeCall?.call_id;
  await disconnectRoom();
  if (id) {
    if (end) await endCall(id);
    else await leaveCall(id);
  }
  activeCall = null;
  document.querySelector('#call-overlay')?.remove();
}

async function renderTasks() {
  app.innerHTML = shell({
    title: 'Мои задачи',
    tabs: 'tasks',
    actions: `<button class="icon-btn" type="button" data-act="settings" title="Настройки">⚙</button>`,
    body: `<div class="empty">Загрузка…</div>`,
  });
  bindChrome();

  try {
    const data = await api<{ tasks: TaskCard[] }>('/tasks');
    const main = app.querySelector('#main')!;
    if (!data.tasks.length) {
      main.innerHTML = `<div class="empty">Активных задач нет</div>`;
      return;
    }
    main.innerHTML = data.tasks
      .map(
        (t) => `
      <button class="list-item" type="button" data-task="${t.id}">
        <div class="list-body">
          <div class="list-title"><span>#${t.id} · ${esc(t.name)}</span></div>
          <div class="list-preview">${esc(t.project || '')}${t.role === 'observer' ? ' · наблюдатель' : ''}</div>
          <span class="status-pill" style="background:${esc(t.status_color)}">${esc(t.status_label)}</span>
        </div>
      </button>`,
      )
      .join('');
    main.querySelectorAll('[data-task]').forEach((el) => {
      el.addEventListener('click', () => go({ name: 'task', id: Number((el as HTMLElement).dataset.task) }));
    });
  } catch (ex) {
    app.querySelector('#main')!.innerHTML = `<div class="empty">${esc(
      ex instanceof Error ? ex.message : 'Ошибка',
    )}</div>`;
  }
}

async function renderTask(id: number) {
  app.innerHTML = shell({
    title: `Задача #${id}`,
    back: () => go({ name: 'tasks' }),
    body: `<div class="empty">Загрузка…</div>`,
    footer: `
      <form class="composer" id="comment-form">
        <textarea name="text" rows="1" placeholder="Комментарий" required></textarea>
        <button class="btn btn-primary" type="submit">➤</button>
      </form>
    `,
  });
  bindChrome();

  try {
    const data = await api<{ task: TaskCard; comments: CommentCard[] }>(`/tasks/${id}`);
    const t = data.task;
    const main = app.querySelector('#main')!;
    main.innerHTML = `
      <div class="task-panel">
        <h2>${esc(t.name)}</h2>
        <span class="status-pill" style="background:${esc(t.status_color)}">${esc(t.status_label)}</span>
        <div class="meta-row">Проект: ${esc(t.project || '—')}</div>
        <div class="meta-row">Исполнитель: ${esc(t.executor || '—')}</div>
        <div class="meta-row">Вы: ${t.role === 'observer' ? 'наблюдатель' : 'исполнитель'}</div>
        ${t.description ? `<p style="margin-top:1rem;white-space:pre-wrap">${esc(t.description)}</p>` : ''}
        <h3 style="margin:1.25rem 0 .5rem;font-size:1rem">Комментарии</h3>
        <div class="comments" id="comments">
          ${
            data.comments.length
              ? data.comments
                  .map(
                    (c) => `
            <div class="comment" data-id="${c.id}">
              <div class="comment-head"><strong>${esc(c.author.name)}</strong><span>${esc(c.created_label)}</span></div>
              <div>${esc(c.text)}</div>
            </div>`,
                  )
                  .join('')
              : `<div class="empty" style="padding:1rem 0">Пока нет комментариев</div>`
          }
        </div>
      </div>
    `;

    const form = app.querySelector('#comment-form') as HTMLFormElement;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const ta = form.querySelector('textarea')!;
      const text = ta.value.trim();
      if (!text) return;
      try {
        const res = await api<{ comment: CommentCard }>(`/tasks/${id}/comments`, {
          method: 'POST',
          body: { text } as any,
        });
        ta.value = '';
        const box = app.querySelector('#comments')!;
        box.querySelector('.empty')?.remove();
        box.insertAdjacentHTML(
          'beforeend',
          `<div class="comment" data-id="${res.comment.id}">
            <div class="comment-head"><strong>${esc(res.comment.author.name)}</strong><span>${esc(res.comment.created_label)}</span></div>
            <div>${esc(res.comment.text)}</div>
          </div>`,
        );
        main.scrollTop = main.scrollHeight;
      } catch (ex) {
        alert(ex instanceof Error ? ex.message : 'Не отправлено');
      }
    });
  } catch (ex) {
    app.querySelector('#main')!.innerHTML = `<div class="empty">${esc(
      ex instanceof Error ? ex.message : 'Ошибка',
    )}</div>`;
  }
}

async function renderSettings() {
  const user = getUser();
  app.innerHTML = shell({
    title: 'Профиль',
    back: () => go({ name: 'chats' }),
    body: `
      <div class="task-panel">
        <div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem">
          ${avatarHtml({
            url: user?.avatar_url,
            initials: user?.initials || '?',
            color: user?.color || '#64748b',
          })}
          <div>
            <strong>${esc(user?.name || '')}</strong>
            <div class="meta-row">${esc(user?.email || '')}</div>
          </div>
        </div>
        <div class="field">
          <label>API сервер</label>
          <input id="api-url" value="${esc(getApiBase())}" />
        </div>
        <button class="btn btn-primary" type="button" id="save-api">Сохранить сервер</button>
        <div style="height:1rem"></div>
        <button class="btn btn-danger" type="button" id="logout">Выйти</button>
      </div>
    `,
  });
  bindChrome();
  app.querySelector('#save-api')?.addEventListener('click', async () => {
    const v = (app.querySelector('#api-url') as HTMLInputElement).value;
    await setApiBase(v);
    alert('Сохранено');
  });
  app.querySelector('#logout')?.addEventListener('click', async () => {
    try {
      await api('/logout', { method: 'POST' });
    } catch {
      /* ignore */
    }
    await clearSession();
    await disconnectRoom();
    go({ name: 'login' });
  });
}

function bindChrome() {
  app.querySelector('[data-act="back"]')?.addEventListener('click', () => {
    if (route.name === 'chat') go({ name: 'chats' });
    else if (route.name === 'task') go({ name: 'tasks' });
    else if (route.name === 'settings') go({ name: 'chats' });
  });
  app.querySelector('[data-act="settings"]')?.addEventListener('click', () => go({ name: 'settings' }));
  app.querySelector('[data-tab="chats"]')?.addEventListener('click', () => go({ name: 'chats' }));
  app.querySelector('[data-tab="tasks"]')?.addEventListener('click', () => go({ name: 'tasks' }));
}

async function render() {
  if (!getToken() && route.name !== 'login') {
    route = { name: 'login' };
  }
  switch (route.name) {
    case 'login':
      await renderLogin();
      break;
    case 'chats':
      await renderChats();
      break;
    case 'chat':
      await renderChat(route.id, route.title);
      break;
    case 'tasks':
      await renderTasks();
      break;
    case 'task':
      await renderTask(route.id);
      break;
    case 'settings':
      await renderSettings();
      break;
  }
}

async function main() {
  await bootSession();
  if (Capacitor.isNativePlatform()) {
    try {
      await StatusBar.setStyle({ style: Style.Light });
    } catch {
      /* web */
    }
  }

  if (getToken()) {
    try {
      await api('/me');
      route = { name: 'chats' };
    } catch {
      await clearSession();
      route = { name: 'login' };
    }
  }
  await render();
}

void main();
