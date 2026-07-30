import './style.css';
import { StatusBar, Style } from '@capacitor/status-bar';
import { Capacitor } from '@capacitor/core';
import { App as CapApp } from '@capacitor/app';
import { LocalNotifications } from '@capacitor/local-notifications';
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
import type { ChatMessage, ChatSummary, CommentCard, StatusAction, TaskCard } from './types';
import {
  connectRoom,
  disconnectRoom,
  endCall,
  leaveCall,
  startCall,
  type CallConnection,
} from './calls';
import { escapeText, richHtml } from './html';
import { bindVoicePlayers, createVoiceRecorder, voicePlayerHtml } from './voice';

type Route =
  | { name: 'login' }
  | { name: 'chats'; filter?: 'all' | 'pinned' }
  | { name: 'chat'; id: number; title: string }
  | { name: 'tasks' }
  | { name: 'task'; id: number }
  | { name: 'settings' }
  | { name: 'new-dm' };

const app = document.querySelector<HTMLDivElement>('#app')!;
let route: Route = { name: 'login' };
let pollTimer: number | null = null;
let sinceId = 0;
let activeCall: CallConnection | null = null;
let replyTo: { id: number; author: string; preview: string } | null = null;
let lastNotifyId = 0;

function go(next: Route) {
  route = next;
  replyTo = null;
  stopPoll();
  void render();
}

function avatarHtml(a: { url?: string; initials: string; color: string; shape?: string }, cls = ''): string {
  const shape = a.shape === 'square' ? 'avatar square' : 'avatar';
  if (a.url) {
    return `<div class="${shape} ${cls}" style="background:${a.color}"><img src="${escapeText(a.url)}" alt="" /></div>`;
  }
  return `<div class="${shape} ${cls}" style="background:${a.color}">${escapeText(a.initials)}</div>`;
}

function shell(opts: {
  title: string;
  back?: boolean;
  actions?: string;
  body: string;
  footer?: string;
  tabs?: 'chats' | 'tasks';
  search?: string;
}): string {
  return `
    <div class="app-shell">
      <header class="topbar">
        ${opts.back ? `<button class="icon-btn" type="button" data-act="back" aria-label="Назад">‹</button>` : ''}
        <h1>${escapeText(opts.title)}</h1>
        ${opts.actions || ''}
      </header>
      ${opts.search ?? ''}
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
    <div id="overlay-root"></div>
    <div id="call-root"></div>
  `;
}

function bindChrome() {
  app.querySelector('[data-act="back"]')?.addEventListener('click', () => {
    if (route.name === 'chat' || route.name === 'new-dm') go({ name: 'chats' });
    else if (route.name === 'task') go({ name: 'tasks' });
    else if (route.name === 'settings') go({ name: 'chats' });
  });
  app.querySelector('[data-act="settings"]')?.addEventListener('click', () => go({ name: 'settings' }));
  app.querySelector('[data-tab="chats"]')?.addEventListener('click', () => go({ name: 'chats' }));
  app.querySelector('[data-tab="tasks"]')?.addEventListener('click', () => go({ name: 'tasks' }));
}

async function renderLogin() {
  app.innerHTML = `
    <div class="login">
      <div class="login-card">
        <h1 class="brand">TaskManager</h1>
        <p>Чаты, звонки и мои задачи</p>
        <form id="login-form">
          <div class="field"><label>Сервер</label><input name="api" value="${escapeText(getApiBase())}" /></div>
          <div class="field"><label>Email</label><input name="email" type="email" required autocomplete="username" /></div>
          <div class="field"><label>Пароль</label><input name="password" type="password" required autocomplete="current-password" /></div>
          <p class="error hidden" id="login-error"></p>
          <button class="btn btn-primary btn-block" type="submit">Войти</button>
        </form>
      </div>
    </div>`;

  app.querySelector('#login-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target as HTMLFormElement);
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
      await setupNotifications();
      go({ name: 'chats' });
    } catch (ex) {
      err.textContent = ex instanceof ApiError ? ex.message : 'Не удалось войти';
      err.classList.remove('hidden');
    }
  });
}

function chatListHtml(chats: ChatSummary[]): string {
  if (!chats.length) return `<div class="empty">Ничего не найдено</div>`;
  return chats
    .map(
      (c) => `
    <button class="list-item" type="button" data-chat="${c.id}" data-title="${escapeText(c.title)}">
      ${avatarHtml(c.avatar)}
      <div class="list-body">
        <div class="list-title">
          <span>${c.pinned ? '📌 ' : ''}${escapeText(c.title)}</span>
          ${c.unread ? `<span class="badge">${c.unread}</span>` : ''}
        </div>
        <div class="list-preview">${escapeText(c.preview || 'Нет сообщений')}</div>
      </div>
    </button>`,
    )
    .join('');
}

async function renderChats() {
  const filter = route.name === 'chats' ? route.filter || 'all' : 'all';
  app.innerHTML = shell({
    title: filter === 'pinned' ? 'Закреплённые' : 'Чаты',
    tabs: 'chats',
    actions: `
      <button class="icon-btn" type="button" data-act="pinned" title="Закреплённые">📌</button>
      <button class="icon-btn" type="button" data-act="new-dm" title="Новый чат">✎</button>
      <button class="icon-btn" type="button" data-act="settings">⚙</button>
    `,
    search: `<div class="search-bar"><input id="chat-search" placeholder="Поиск по чатам и сообщениям" /></div>`,
    body: `<div class="empty">Загрузка…</div>`,
  });
  bindChrome();

  app.querySelector('[data-act="new-dm"]')?.addEventListener('click', () => go({ name: 'new-dm' }));
  app.querySelector('[data-act="pinned"]')?.addEventListener('click', () => {
    go({ name: 'chats', filter: filter === 'pinned' ? 'all' : 'pinned' });
  });

  const main = app.querySelector('#main')!;
  const paint = (chats: ChatSummary[]) => {
    const list = filter === 'pinned' ? chats.filter((c) => c.pinned) : chats;
    main.innerHTML = chatListHtml(list);
    main.querySelectorAll('[data-chat]').forEach((el) => {
      el.addEventListener('click', () => {
        go({
          name: 'chat',
          id: Number((el as HTMLElement).dataset.chat),
          title: (el as HTMLElement).dataset.title || 'Чат',
        });
      });
    });
  };

  try {
    const data = await api<{ chats: ChatSummary[] }>('/chats');
    paint(data.chats);
    startListPoll(paint);
  } catch (ex) {
    main.innerHTML = `<div class="empty">${escapeText(ex instanceof Error ? ex.message : 'Ошибка')}</div>`;
  }

  let searchTimer: number | null = null;
  app.querySelector('#chat-search')?.addEventListener('input', (e) => {
    const q = (e.target as HTMLInputElement).value.trim();
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = window.setTimeout(async () => {
      if (q.length < 2) {
        const data = await api<{ chats: ChatSummary[] }>('/chats');
        paint(data.chats);
        return;
      }
      try {
        const res = await api<{
          chats: Array<{ id: number; title: string; preview?: string; unread?: number; avatar?: any }>;
          messages: Array<{ id: number; chat_id: number; chat_title: string; preview: string; author: string }>;
        }>(`/chats/search?q=${encodeURIComponent(q)}`);
        const chatBlocks = (res.chats || [])
          .map(
            (c) => `
          <button class="list-item" type="button" data-chat="${c.id}" data-title="${escapeText(c.title)}">
            ${avatarHtml(c.avatar || { initials: '?', color: '#64748b' })}
            <div class="list-body">
              <div class="list-title"><span>${escapeText(c.title)}</span></div>
              <div class="list-preview">${escapeText(c.preview || '')}</div>
            </div>
          </button>`,
          )
          .join('');
        const msgBlocks = (res.messages || [])
          .map(
            (m) => `
          <button class="list-item" type="button" data-chat="${m.chat_id}" data-title="${escapeText(m.chat_title)}">
            <div class="list-body">
              <div class="list-title"><span>${escapeText(m.chat_title)}</span></div>
              <div class="list-preview">${escapeText(m.author)}: ${escapeText(m.preview)}</div>
            </div>
          </button>`,
          )
          .join('');
        main.innerHTML =
          (chatBlocks ? `<div class="section-label">Чаты</div>${chatBlocks}` : '') +
          (msgBlocks ? `<div class="section-label">Сообщения</div>${msgBlocks}` : '') ||
          `<div class="empty">Ничего не найдено</div>`;
        main.querySelectorAll('[data-chat]').forEach((el) => {
          el.addEventListener('click', () => {
            go({
              name: 'chat',
              id: Number((el as HTMLElement).dataset.chat),
              title: (el as HTMLElement).dataset.title || 'Чат',
            });
          });
        });
      } catch (ex) {
        main.innerHTML = `<div class="empty">${escapeText(ex instanceof Error ? ex.message : 'Ошибка поиска')}</div>`;
      }
    }, 300);
  });
}

async function renderNewDm() {
  app.innerHTML = shell({
    title: 'Новый личный чат',
    back: true,
    search: `<div class="search-bar"><input id="user-search" placeholder="Найти пользователя" /></div>`,
    body: `<div class="empty">Загрузка…</div>`,
  });
  bindChrome();
  const main = app.querySelector('#main')!;

  try {
    const data = await api<{ users: Array<{ id: number; name: string; label: string; initials: string; color: string; avatar_url?: string }> }>(
      '/chats/interlocutors',
    );
    const paint = (users: typeof data.users) => {
      main.innerHTML = users.length
        ? users
            .map(
              (u) => `
          <button class="list-item" type="button" data-user="${u.id}">
            ${avatarHtml({ url: u.avatar_url, initials: u.initials, color: u.color })}
            <div class="list-body">
              <div class="list-title"><span>${escapeText(u.label || u.name)}</span></div>
            </div>
          </button>`,
            )
            .join('')
        : `<div class="empty">Нет доступных собеседников</div>`;
      main.querySelectorAll('[data-user]').forEach((el) => {
        el.addEventListener('click', async () => {
          try {
            const res = await api<{ chat: ChatSummary }>('/chats/direct', {
              method: 'POST',
              body: { user_id: Number((el as HTMLElement).dataset.user) } as any,
            });
            go({ name: 'chat', id: res.chat.id, title: res.chat.title });
          } catch (ex) {
            alert(ex instanceof Error ? ex.message : 'Не удалось создать чат');
          }
        });
      });
    };
    paint(data.users);
    app.querySelector('#user-search')?.addEventListener('input', (e) => {
      const q = (e.target as HTMLInputElement).value.trim().toLowerCase();
      paint(data.users.filter((u) => (u.label || u.name).toLowerCase().includes(q)));
    });
  } catch (ex) {
    main.innerHTML = `<div class="empty">${escapeText(ex instanceof Error ? ex.message : 'Ошибка')}</div>`;
  }
}

function messageHtml(m: ChatMessage): string {
  if (m.system) return `<div class="msg system" data-id="${m.id}">${escapeText(m.text)}</div>`;

  const voices = m.attachments
    .filter((a) => a.kind === 'voice')
    .map((a) => voicePlayerHtml({ url: a.url, mine: m.mine }))
    .join('');
  const images = m.attachments
    .filter((a) => a.kind === 'image')
    .map((a) => `<img class="msg-att" data-media="${escapeText(a.url)}" alt="${escapeText(a.name)}" />`)
    .join('');
  const files = m.attachments
    .filter((a) => a.kind === 'file')
    .map((a) => `<a class="msg-att" href="${escapeText(a.url)}" target="_blank">${escapeText(a.name)}</a>`)
    .join('');

  const body = m.deleted
    ? `<em>Сообщение удалено</em>`
    : `<div class="msg-text">${richHtml(m.html || escapeText(m.text))}</div>`;

  return `
    <div class="msg ${m.mine ? 'mine' : ''}" data-id="${m.id}">
      <div class="bubble">
        ${!m.mine ? `<div class="msg-author">${escapeText(m.author.name)}</div>` : ''}
        ${m.forwarded ? `<div class="msg-reply">Переслано</div>` : ''}
        ${
          m.parent
            ? `<div class="msg-reply"><strong>${escapeText(m.parent.author)}</strong><br>${escapeText(m.parent.preview)}</div>`
            : ''
        }
        ${m.task ? `<div class="msg-reply">Задача #${m.task.id}: ${escapeText(m.task.name)}</div>` : ''}
        ${body}
        ${voices}${images}${files}
        <div class="msg-meta"><span>${escapeText(m.created_label)}</span></div>
        <div class="msg-actions">
          <button type="button" data-reply="${m.id}">Ответить</button>
          <button type="button" data-forward="${m.id}">Переслать</button>
        </div>
      </div>
    </div>`;
}

async function hydrateImages(root: ParentNode) {
  for (const el of Array.from(root.querySelectorAll<HTMLImageElement>('img[data-media]'))) {
    try {
      el.src = await mediaUrl(el.dataset.media || '');
    } catch {
      /* */
    }
  }
}

async function renderChat(id: number, title: string) {
  app.innerHTML = shell({
    title,
    back: true,
    actions: `
      <button class="icon-btn" type="button" data-act="pin" title="Закрепить">📌</button>
      <button class="icon-btn" type="button" data-act="call-audio">📞</button>
      <button class="icon-btn" type="button" data-act="call-video">🎥</button>
    `,
    body: `<div class="empty">Загрузка…</div>`,
    footer: `
      <div class="composer-wrap">
        <div class="typing hidden" id="typing"></div>
        <div class="reply-bar hidden" id="reply-bar">
          <div class="grow" id="reply-text"></div>
          <button class="icon-btn" type="button" id="reply-clear">×</button>
        </div>
        <div class="voice-rec hidden" id="voice-rec">
          <span class="dot"></span>
          <span id="voice-timer">0:00</span>
          <div class="meter"><span id="voice-meter"></span></div>
          <div class="actions">
            <button type="button" id="voice-cancel">Отмена</button>
            <button type="button" id="voice-send" style="background:#22c55e;color:#fff">Отправить</button>
          </div>
        </div>
        <form class="composer" id="composer">
          <div class="composer-input">
            <textarea name="text" rows="1" placeholder="Сообщение" id="msg-input"></textarea>
          </div>
          <div class="composer-tools">
            <button class="round-btn" type="button" id="btn-voice" title="Голосовое">🎤</button>
            <button class="round-btn send" type="submit" title="Отправить">➤</button>
          </div>
        </form>
      </div>
    `,
  });
  bindChrome();

  const main = app.querySelector('#main')!;
  const input = app.querySelector('#msg-input') as HTMLTextAreaElement;
  const replyBar = app.querySelector('#reply-bar') as HTMLElement;
  const replyText = app.querySelector('#reply-text') as HTMLElement;

  const updateReplyBar = () => {
    if (!replyTo) {
      replyBar.classList.add('hidden');
      return;
    }
    replyBar.classList.remove('hidden');
    replyText.innerHTML = `<strong>${escapeText(replyTo.author)}</strong> · ${escapeText(replyTo.preview)}`;
  };
  app.querySelector('#reply-clear')?.addEventListener('click', () => {
    replyTo = null;
    updateReplyBar();
  });

  app.querySelector('[data-act="call-audio"]')?.addEventListener('click', () => void openCall(id, false, title));
  app.querySelector('[data-act="call-video"]')?.addEventListener('click', () => void openCall(id, true, title));
  app.querySelector('[data-act="pin"]')?.addEventListener('click', async () => {
    try {
      const res = await api<{ pinned: boolean }>(`/chats/${id}/pin`, { method: 'POST' });
      alert(res.pinned ? 'Чат закреплён' : 'Чат откреплён');
    } catch (ex) {
      alert(ex instanceof Error ? ex.message : 'Ошибка');
    }
  });

  const bindMsgActions = (root: ParentNode) => {
    root.querySelectorAll('[data-reply]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const mid = Number((btn as HTMLElement).dataset.reply);
        const msgEl = root.querySelector(`.msg[data-id="${mid}"]`);
        const author = msgEl?.querySelector('.msg-author')?.textContent || (getUser()?.name ?? 'Вы');
        const preview = msgEl?.querySelector('.msg-text')?.textContent?.slice(0, 80) || 'Сообщение';
        replyTo = { id: mid, author, preview };
        updateReplyBar();
        input.focus();
      });
    });
    root.querySelectorAll('[data-forward]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        void openForwardSheet(id, [Number((btn as HTMLElement).dataset.forward)]);
      });
    });
    root.querySelectorAll('.msg:not(.system)').forEach((el) => {
      el.addEventListener('click', () => {
        el.classList.toggle('is-selected');
      });
    });
  };

  try {
    const data = await api<{ messages: ChatMessage[] }>(`/chats/${id}`);
    sinceId = data.messages.reduce((max, m) => Math.max(max, m.id), 0);
    main.innerHTML = `<div class="chat-feed" id="feed">${data.messages.map(messageHtml).join('')}</div>`;
    bindVoicePlayers(main);
    await hydrateImages(main);
    bindMsgActions(main);
    main.scrollTop = main.scrollHeight;

    input.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(120, Math.max(44, input.scrollHeight)) + 'px';
      void api(`/chats/${id}/typing`, { method: 'POST' }).catch(() => undefined);
    });

    app.querySelector('#composer')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const text = input.value.trim();
      if (!text) return;
      input.value = '';
      input.style.height = '44px';
      try {
        const fd = new FormData();
        fd.append('message[text]', text);
        if (replyTo) fd.append('message[parent_id]', String(replyTo.id));
        const res = await api<{ message: ChatMessage }>(`/chats/${id}/messages`, {
          method: 'POST',
          formData: fd,
        });
        replyTo = null;
        updateReplyBar();
        const feed = app.querySelector('#feed')!;
        feed.insertAdjacentHTML('beforeend', messageHtml(res.message));
        bindVoicePlayers(feed);
        bindMsgActions(feed);
        sinceId = Math.max(sinceId, res.message.id);
        main.scrollTop = main.scrollHeight;
      } catch (ex) {
        alert(ex instanceof Error ? ex.message : 'Не отправлено');
      }
    });

    // Voice record
    const recUi = app.querySelector('#voice-rec') as HTMLElement;
    const composer = app.querySelector('#composer') as HTMLElement;
    const recorder = createVoiceRecorder((sec, peak) => {
      const t = app.querySelector('#voice-timer');
      const m = app.querySelector('#voice-meter') as HTMLElement;
      if (t) t.textContent = `${Math.floor(sec / 60)}:${String(Math.floor(sec % 60)).padStart(2, '0')}`;
      if (m) m.style.width = `${Math.round(peak * 100)}%`;
    });

    app.querySelector('#btn-voice')?.addEventListener('click', async () => {
      try {
        await recorder.start();
        composer.classList.add('hidden');
        recUi.classList.remove('hidden');
      } catch {
        alert('Нет доступа к микрофону');
      }
    });
    app.querySelector('#voice-cancel')?.addEventListener('click', () => {
      recorder.cancel();
      recUi.classList.add('hidden');
      composer.classList.remove('hidden');
    });
    app.querySelector('#voice-send')?.addEventListener('click', async () => {
      const result = await recorder.stop();
      recUi.classList.add('hidden');
      composer.classList.remove('hidden');
      if (!result) return;
      try {
        const fd = new FormData();
        fd.append('message_voice', result.blob, 'voice.webm');
        fd.append('message[voice_duration]', String(result.duration));
        if (replyTo) fd.append('message[parent_id]', String(replyTo.id));
        const res = await api<{ message: ChatMessage }>(`/chats/${id}/messages`, {
          method: 'POST',
          formData: fd,
        });
        replyTo = null;
        updateReplyBar();
        const feed = app.querySelector('#feed')!;
        feed.insertAdjacentHTML('beforeend', messageHtml(res.message));
        bindVoicePlayers(feed);
        bindMsgActions(feed);
        sinceId = Math.max(sinceId, res.message.id);
        main.scrollTop = main.scrollHeight;
      } catch (ex) {
        alert(ex instanceof Error ? ex.message : 'Голосовое не отправлено');
      }
    });

    startChatPoll(id, title);
  } catch (ex) {
    main.innerHTML = `<div class="empty">${escapeText(ex instanceof Error ? ex.message : 'Ошибка')}</div>`;
  }
}

async function openForwardSheet(sourceChatId: number, messageIds: number[]) {
  const root = app.querySelector('#overlay-root')!;
  root.innerHTML = `<div class="sheet"><div class="sheet-panel"><h3>Переслать в чат</h3><div class="empty">Загрузка…</div></div></div>`;
  const panel = root.querySelector('.sheet-panel')!;
  root.querySelector('.sheet')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) root.innerHTML = '';
  });
  try {
    const data = await api<{ chats: Array<{ id: number; title: string; avatar_url?: string; avatar_initials?: string; avatar_color?: string }> }>(
      '/chats/picker',
    );
    panel.innerHTML = `<h3>Переслать в чат</h3>${data.chats
      .map(
        (c) => `
      <button class="list-item" type="button" data-target="${c.id}">
        ${avatarHtml({
          url: c.avatar_url,
          initials: c.avatar_initials || '?',
          color: c.avatar_color || '#64748b',
        })}
        <div class="list-body"><div class="list-title"><span>${escapeText(c.title)}</span></div></div>
      </button>`,
      )
      .join('')}`;
    panel.querySelectorAll('[data-target]').forEach((el) => {
      el.addEventListener('click', async () => {
        try {
          await api(`/chats/${sourceChatId}/messages/forward`, {
            method: 'POST',
            body: {
              message_ids: messageIds,
              target_chat_id: Number((el as HTMLElement).dataset.target),
            } as any,
          });
          root.innerHTML = '';
          alert('Переслано');
        } catch (ex) {
          alert(ex instanceof Error ? ex.message : 'Ошибка');
        }
      });
    });
  } catch (ex) {
    panel.innerHTML = `<div class="empty">${escapeText(ex instanceof Error ? ex.message : 'Ошибка')}</div>`;
  }
}

function startListPoll(paint: (chats: ChatSummary[]) => void) {
  stopPoll();
  pollTimer = window.setInterval(async () => {
    if (route.name !== 'chats') return;
    try {
      const data = await api<{ chats: ChatSummary[]; notify?: any; max_id?: number }>(
        `/chats/poll?since=${sinceId || 0}`,
      );
      if (data.chats) paint(data.chats);
      if (data.notify?.message_id && data.notify.message_id !== lastNotifyId) {
        lastNotifyId = data.notify.message_id;
        void notifyLocal(data.notify.title || 'Сообщение', data.notify.body || '');
      }
      if (data.max_id) sinceId = Math.max(sinceId, data.max_id);
    } catch {
      /* */
    }
  }, 4000);
}

function startChatPoll(chatId: number, title: string) {
  stopPoll();
  pollTimer = window.setInterval(async () => {
    if (route.name !== 'chat' || route.id !== chatId) return;
    try {
      const data = await api<{
        messages: ChatMessage[];
        typing?: Array<{ name: string }>;
        calls?: any[];
        max_id?: number;
        notify?: any;
      }>(`/chats/poll?since=${sinceId}&chat=${chatId}`);

      const feed = app.querySelector('#feed');
      const main = app.querySelector('#main');
      if (feed && data.messages?.length) {
        const atBottom = !!main && main.scrollHeight - main.scrollTop - main.clientHeight < 80;
        for (const m of data.messages) {
          if (feed.querySelector(`[data-id="${m.id}"]`)) continue;
          feed.insertAdjacentHTML('beforeend', messageHtml(m));
          sinceId = Math.max(sinceId, m.id);
        }
        bindVoicePlayers(feed);
        await hydrateImages(feed);
        if (atBottom && main) main.scrollTop = main.scrollHeight;
      }
      if (data.max_id) sinceId = Math.max(sinceId, data.max_id);

      const typing = app.querySelector('#typing');
      if (typing) {
        const names = (data.typing || []).map((t) => t.name).filter(Boolean);
        typing.textContent = names.length ? `${names.join(', ')} печатает…` : '';
        typing.classList.toggle('hidden', !names.length);
      }

      const incoming = (data.calls || []).find((c: any) => c.status === 'ringing' || c.status === 'active');
      if (incoming && !activeCall && confirm('Входящий звонок. Присоединиться?')) {
        await openCallJoin(incoming.id || incoming.call_id, title);
      }

      if (data.notify?.message_id && data.notify.message_id !== lastNotifyId) {
        lastNotifyId = data.notify.message_id;
        void notifyLocal(data.notify.title || title, data.notify.body || '');
      }
    } catch {
      /* */
    }
  }, 2500);
}

function stopPoll() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

async function openCall(chatId: number, video: boolean, title: string) {
  try {
    const conn = await startCall(chatId, video);
    await mountCallUi(conn, title);
  } catch (ex) {
    alert(ex instanceof Error ? ex.message : 'Звонок недоступен');
  }
}

async function openCallJoin(callId: number, title: string) {
  try {
    const conn = await api<CallConnection>(`/calls/${callId}/join`, { method: 'POST' });
    await mountCallUi(conn, title);
  } catch (ex) {
    alert(ex instanceof Error ? ex.message : 'Не удалось войти в звонок');
  }
}

async function mountCallUi(conn: CallConnection, title: string) {
  activeCall = conn;
  const me = getUser();
  const root = app.querySelector('#call-root') || app;
  const peerName = (conn as any).roster?.find((p: any) => p.id !== me?.id)?.name || title;
  const peer = (conn as any).roster?.find((p: any) => p.id !== me?.id);

  root.innerHTML = `
    <div class="call-overlay" id="call-overlay">
      <div class="call-stage">
        <div class="call-peer">
          ${avatarHtml(
            {
              url: peer?.avatar,
              initials: peer?.initials || peerName.slice(0, 2).toUpperCase(),
              color: peer?.color || '#3b82f6',
            },
            'xl',
          )}
          <div class="name">${escapeText(peerName)}</div>
          <div class="status" id="call-status">Соединение…</div>
        </div>
        <div id="remote-videos"></div>
        <div class="call-self">
          <video id="local-video" autoplay playsinline muted class="${conn.video === false ? 'hidden' : ''}"></video>
          <div id="local-avatar" class="${conn.video === false ? '' : 'hidden'}">
            ${avatarHtml(
              {
                url: me?.avatar_url,
                initials: me?.initials || 'Я',
                color: me?.color || '#64748b',
              },
              'lg',
            )}
          </div>
        </div>
      </div>
      <div class="call-actions">
        <button class="round-btn mute" type="button" id="toggle-mic">🎤</button>
        ${conn.can_end ? `<button class="round-btn hangup" type="button" id="end-call">⏹</button>` : ''}
        <button class="round-btn hangup" type="button" id="leave-call">📵</button>
      </div>
    </div>`;

  const local = document.querySelector('#local-video') as HTMLVideoElement;
  const remote = document.querySelector('#remote-videos') as HTMLDivElement;
  const status = document.querySelector('#call-status');
  try {
    await connectRoom(conn, { local, remote });
    if (status) status.textContent = 'В сети';
    if (conn.video === false) {
      document.querySelector('#local-avatar')?.classList.remove('hidden');
      local.classList.add('hidden');
    }
  } catch (ex) {
    alert(ex instanceof Error ? ex.message : 'WebRTC ошибка');
    await hangup(false);
    return;
  }

  document.querySelector('#leave-call')?.addEventListener('click', () => void hangup(false));
  document.querySelector('#end-call')?.addEventListener('click', () => void hangup(true));
  document.querySelector('#toggle-mic')?.addEventListener('click', async () => {
    // best-effort: LiveKit room mic toggle is inside connectRoom scope; simple UI feedback
    const btn = document.querySelector('#toggle-mic');
    btn?.classList.toggle('active');
  });
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
    actions: `<button class="icon-btn" type="button" data-act="settings">⚙</button>`,
    search: `<div class="search-bar"><input id="task-search" placeholder="Поиск задач" /></div>`,
    body: `<div class="empty">Загрузка…</div>`,
  });
  bindChrome();
  const main = app.querySelector('#main')!;

  const paint = (tasks: TaskCard[]) => {
    main.innerHTML = tasks.length
      ? tasks
          .map(
            (t) => `
        <button class="list-item" type="button" data-task="${t.id}">
          <div class="list-body">
            <div class="list-title"><span>${escapeText(t.key || '#' + t.id)} · ${escapeText(t.name)}</span></div>
            <div class="list-preview">${escapeText(t.project || '')}${t.role === 'observer' ? ' · наблюдатель' : ''}</div>
            <span class="status-pill" style="background:${escapeText(t.status_color)}">${escapeText(t.status_label)}</span>
          </div>
        </button>`,
          )
          .join('')
      : `<div class="empty">Активных задач нет</div>`;
    main.querySelectorAll('[data-task]').forEach((el) => {
      el.addEventListener('click', () => go({ name: 'task', id: Number((el as HTMLElement).dataset.task) }));
    });
  };

  try {
    const data = await api<{ tasks: TaskCard[] }>('/tasks');
    paint(data.tasks);
  } catch (ex) {
    main.innerHTML = `<div class="empty">${escapeText(ex instanceof Error ? ex.message : 'Ошибка')}</div>`;
  }

  let t: number | null = null;
  app.querySelector('#task-search')?.addEventListener('input', (e) => {
    const q = (e.target as HTMLInputElement).value.trim();
    if (t) clearTimeout(t);
    t = window.setTimeout(async () => {
      try {
        const data = await api<{ tasks: TaskCard[] }>(`/tasks?q=${encodeURIComponent(q)}`);
        paint(data.tasks);
      } catch (ex) {
        main.innerHTML = `<div class="empty">${escapeText(ex instanceof Error ? ex.message : 'Ошибка')}</div>`;
      }
    }, 300);
  });
}

async function renderTask(id: number) {
  app.innerHTML = shell({
    title: `Задача #${id}`,
    back: true,
    body: `<div class="empty">Загрузка…</div>`,
    footer: `
      <div class="composer-wrap">
        <form class="composer" id="comment-form">
          <div class="composer-input"><textarea name="text" rows="1" placeholder="Комментарий" id="c-input"></textarea></div>
          <div class="composer-tools"><button class="round-btn send" type="submit">➤</button></div>
        </form>
      </div>`,
  });
  bindChrome();

  try {
    const data = await api<{
      task: TaskCard;
      comments: CommentCard[];
      history: CommentCard[];
      status_actions: StatusAction[];
      pipeline: Array<{ value: string; label: string; state: string }>;
      can_discuss: boolean;
    }>(`/tasks/${id}`);
    const t = data.task;
    const main = app.querySelector('#main')!;
    main.innerHTML = `
      <div class="task-panel">
        <h2>${escapeText(t.key || '#' + t.id)} · ${escapeText(t.name)}</h2>
        <span class="status-pill" style="background:${escapeText(t.status_color)}">${escapeText(t.status_label)}</span>
        <div class="pipeline">
          ${(data.pipeline || [])
            .map((p) => `<span class="${escapeText(p.state)}">${escapeText(p.label)}</span>`)
            .join('')}
        </div>
        <div class="meta-row">Проект: ${escapeText(t.project || '—')}</div>
        <div class="meta-row">Очередь: ${escapeText(t.queue || '—')}</div>
        <div class="meta-row">Исполнитель: ${escapeText(t.executor || '—')}</div>
        <div class="meta-row">Автор: ${escapeText(t.creator || '—')}</div>
        <div class="meta-row">Дедлайн: ${escapeText(t.end_label || '—')}</div>
        <div class="meta-row">Оценка / факт: ${t.estimation_hours ?? '—'} / ${t.hours_spent ?? '—'} ч</div>
        <div class="meta-row">Вы: ${t.role === 'observer' ? 'наблюдатель' : 'исполнитель'}</div>
        ${
          (t.observers || []).length
            ? `<div class="meta-row">Наблюдатели: ${escapeText(t.observers!.map((o) => o.name).join(', '))}</div>`
            : ''
        }
        <div class="task-actions">
          ${(data.status_actions || [])
            .map(
              (a) =>
                `<button class="btn ${a.tone === 'back' ? 'btn-ghost' : 'btn-primary'}" type="button" data-status="${escapeText(a.to)}" data-confirm="${escapeText(a.confirm || '')}">${escapeText(a.label)}</button>`,
            )
            .join('')}
        </div>
        ${t.description_html ? `<div class="task-html">${richHtml(t.description_html)}</div>` : ''}
        ${
          (t.attachments || []).length
            ? `<h3 style="margin:1rem 0 .4rem;font-size:.95rem">Файлы</h3><div class="files-list">${t
                .attachments!.map((f) => `<a href="${escapeText(f.url)}" target="_blank">${escapeText(f.name)}</a>`)
                .join('')}</div>`
            : ''
        }
        ${
          (t.links || []).length
            ? `<h3 style="margin:1rem 0 .4rem;font-size:.95rem">Связи</h3>${t
                .links!.map(
                  (l) =>
                    `<div class="meta-row">${escapeText(l.type)} → ${escapeText(l.related_key || '')} ${escapeText(l.related_name || '')}</div>`,
                )
                .join('')}`
            : ''
        }
        <h3 style="margin:1.25rem 0 .5rem;font-size:1rem">Обсуждение</h3>
        <div class="comments" id="comments">
          ${
            data.comments.length
              ? data.comments
                  .map(
                    (c) => `
            <div class="comment">
              <div class="comment-head"><strong>${escapeText(c.author.name)}</strong><span>${escapeText(c.created_label)}</span></div>
              <div class="msg-text">${richHtml(c.html || escapeText(c.text))}</div>
            </div>`,
                  )
                  .join('')
              : `<div class="empty" style="padding:1rem 0">Пока нет комментариев</div>`
          }
        </div>
        ${
          data.history?.length
            ? `<h3 style="margin:1.25rem 0 .5rem;font-size:1rem">История</h3><div class="comments">${data.history
                .map(
                  (c) =>
                    `<div class="comment"><div class="comment-head"><strong>Система</strong><span>${escapeText(c.created_label)}</span></div><div>${escapeText(c.text)}</div></div>`,
                )
                .join('')}</div>`
            : ''
        }
      </div>`;

    if (!data.can_discuss) {
      (app.querySelector('#comment-form') as HTMLElement)?.classList.add('hidden');
    }

    main.querySelectorAll('[data-status]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const status = (btn as HTMLElement).dataset.status!;
        const confirmText = (btn as HTMLElement).dataset.confirm;
        if (confirmText && !confirm(confirmText)) return;
        try {
          await api(`/tasks/${id}/status`, { method: 'POST', body: { status } as any });
          go({ name: 'task', id });
        } catch (ex) {
          alert(ex instanceof Error ? ex.message : 'Не удалось сменить статус');
        }
      });
    });

    const form = app.querySelector('#comment-form') as HTMLFormElement | null;
    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const ta = app.querySelector('#c-input') as HTMLTextAreaElement;
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
          `<div class="comment"><div class="comment-head"><strong>${escapeText(res.comment.author.name)}</strong><span>${escapeText(res.comment.created_label)}</span></div><div class="msg-text">${richHtml(res.comment.html || escapeText(res.comment.text))}</div></div>`,
        );
        main.scrollTop = main.scrollHeight;
      } catch (ex) {
        alert(ex instanceof Error ? ex.message : 'Не отправлено');
      }
    });
  } catch (ex) {
    app.querySelector('#main')!.innerHTML = `<div class="empty">${escapeText(ex instanceof Error ? ex.message : 'Ошибка')}</div>`;
  }
}

async function renderSettings() {
  const user = getUser();
  app.innerHTML = shell({
    title: 'Профиль',
    back: true,
    body: `
      <div class="task-panel">
        <div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem">
          ${avatarHtml({ url: user?.avatar_url, initials: user?.initials || '?', color: user?.color || '#64748b' })}
          <div><strong>${escapeText(user?.name || '')}</strong><div class="meta-row">${escapeText(user?.email || '')}</div></div>
        </div>
        <div class="field"><label>API сервер</label><input id="api-url" value="${escapeText(getApiBase())}" /></div>
        <button class="btn btn-primary btn-block" type="button" id="save-api">Сохранить сервер</button>
        <div style="height:.75rem"></div>
        <button class="btn btn-ghost btn-block" type="button" id="enable-push">Включить уведомления</button>
        <div style="height:.75rem"></div>
        <button class="btn btn-danger btn-block" type="button" id="logout">Выйти</button>
        <p class="meta-row" style="margin-top:1rem">Закреплённые чаты — кнопка 📌 в списке чатов. Push: локальные уведомления + регистрация device token (FCM на сервере — отдельно).</p>
      </div>`,
  });
  bindChrome();
  app.querySelector('#save-api')?.addEventListener('click', async () => {
    await setApiBase((app.querySelector('#api-url') as HTMLInputElement).value);
    alert('Сохранено');
  });
  app.querySelector('#enable-push')?.addEventListener('click', () => void setupNotifications(true));
  app.querySelector('#logout')?.addEventListener('click', async () => {
    try {
      await api('/logout', { method: 'POST' });
    } catch {
      /* */
    }
    await clearSession();
    await disconnectRoom();
    go({ name: 'login' });
  });
}

async function setupNotifications(force = false) {
  if (!Capacitor.isNativePlatform() && !force) return;
  try {
    const perm = await LocalNotifications.requestPermissions();
    if (perm.display !== 'granted') {
      if (force) alert('Разрешите уведомления в настройках системы');
      return;
    }
    // Token placeholder for future FCM — store a local device id
    const token = `local-${Capacitor.getPlatform()}-${Date.now()}`;
    try {
      await api('/device/push-token', {
        method: 'POST',
        body: { token, platform: Capacitor.getPlatform() } as any,
      });
    } catch {
      /* table may be missing until migrate */
    }
    if (force) alert('Уведомления включены');
  } catch {
    if (force) alert('Уведомления недоступны в этой среде');
  }
}

async function notifyLocal(title: string, body: string) {
  try {
    if (!Capacitor.isNativePlatform()) return;
    await LocalNotifications.schedule({
      notifications: [
        {
          id: Math.floor(Date.now() % 100000),
          title,
          body,
          schedule: { at: new Date(Date.now() + 300) },
        },
      ],
    });
  } catch {
    /* */
  }
}

async function render() {
  if (!getToken() && route.name !== 'login') route = { name: 'login' };
  switch (route.name) {
    case 'login':
      await renderLogin();
      break;
    case 'chats':
      await renderChats();
      break;
    case 'new-dm':
      await renderNewDm();
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
      /* */
    }
    CapApp.addListener('backButton', ({ canGoBack }) => {
      if (route.name === 'chat') go({ name: 'chats' });
      else if (route.name === 'task') go({ name: 'tasks' });
      else if (route.name === 'settings' || route.name === 'new-dm') go({ name: 'chats' });
      else if (!canGoBack) CapApp.exitApp();
    });
  }

  if (getToken()) {
    try {
      await api('/me');
      route = { name: 'chats' };
      void setupNotifications();
    } catch {
      await clearSession();
      route = { name: 'login' };
    }
  }
  await render();
}

void main();
