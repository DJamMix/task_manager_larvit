import { mediaUrl } from './api';

const PLAY_SVG =
  '<svg class="voice-icon voice-icon-play" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>';
const PAUSE_SVG =
  '<svg class="voice-icon voice-icon-pause" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>';

function barsHtml(n = 28): string {
  let html = '';
  for (let i = 0; i < n; i++) {
    const h = 20 + Math.round(70 * Math.abs(Math.sin(i * 0.7 + 1)));
    html += `<span class="voice-bar" style="height:${h}%"></span>`;
  }
  return html;
}

export function voicePlayerHtml(opts: {
  url: string;
  mine: boolean;
  label?: string;
}): string {
  return `
    <div class="voice ${opts.mine ? 'mine' : ''}" data-voice-src="${opts.url.replace(/"/g, '&quot;')}">
      <button type="button" class="voice-play" aria-label="Play">${PLAY_SVG}${PAUSE_SVG}</button>
      <div class="voice-body">
        <div class="voice-wave"><div class="voice-bars">${barsHtml()}</div></div>
        <span class="voice-time">${opts.label || '0:00'}</span>
      </div>
    </div>`;
}

function fmt(sec: number): string {
  if (!Number.isFinite(sec) || sec < 0) return '0:00';
  const s = Math.floor(sec);
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

let activeAudio: HTMLAudioElement | null = null;
let activeRoot: HTMLElement | null = null;

export function bindVoicePlayers(root: ParentNode): void {
  root.querySelectorAll<HTMLElement>('.voice[data-voice-src]').forEach((el) => {
    if (el.dataset.bound === '1') return;
    el.dataset.bound = '1';
    const btn = el.querySelector('.voice-play') as HTMLButtonElement;
    const timeEl = el.querySelector('.voice-time') as HTMLElement;
    const bars = el.querySelectorAll('.voice-bar');

    let audio: HTMLAudioElement | null = null;
    let ready = false;

    const ensure = async () => {
      if (audio) return audio;
      const src = el.dataset.voiceSrc || '';
      const blob = await mediaUrl(src);
      audio = new Audio(blob);
      audio.preload = 'metadata';
      audio.addEventListener('loadedmetadata', () => {
        ready = true;
        timeEl.textContent = fmt(audio!.duration);
      });
      audio.addEventListener('timeupdate', () => {
        const d = audio!.duration || 1;
        const p = audio!.currentTime / d;
        bars.forEach((b, i) => {
          b.classList.toggle('played', i / bars.length <= p);
        });
        timeEl.textContent = fmt(audio!.currentTime);
      });
      audio.addEventListener('ended', () => {
        el.classList.remove('playing');
        timeEl.textContent = fmt(audio!.duration);
      });
      return audio;
    };

    btn.addEventListener('click', async () => {
      try {
        const a = await ensure();
        if (activeAudio && activeAudio !== a) {
          activeAudio.pause();
          activeRoot?.classList.remove('playing');
        }
        if (a.paused) {
          await a.play();
          el.classList.add('playing');
          activeAudio = a;
          activeRoot = el;
        } else {
          a.pause();
          el.classList.remove('playing');
        }
        if (ready) timeEl.textContent = fmt(a.paused ? a.duration : a.currentTime);
      } catch {
        /* ignore */
      }
    });
  });
}

export type VoiceRecorder = {
  start: () => Promise<void>;
  stop: () => Promise<{ blob: Blob; duration: number } | null>;
  cancel: () => void;
  isRecording: () => boolean;
};

export function createVoiceRecorder(onTick: (sec: number, peak: number) => void): VoiceRecorder {
  let stream: MediaStream | null = null;
  let recorder: MediaRecorder | null = null;
  let chunks: BlobPart[] = [];
  let startedAt = 0;
  let timer: number | null = null;
  let recording = false;
  let mimeType = '';

  const pickMime = (): string => {
    const candidates = [
      'audio/webm;codecs=opus',
      'audio/webm',
      'audio/mp4',
      'audio/aac',
      'audio/ogg;codecs=opus',
    ];
    return candidates.find((m) => MediaRecorder.isTypeSupported(m)) || '';
  };

  const api: VoiceRecorder = {
    isRecording: () => recording,
    async start() {
      stream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
        },
      });
      mimeType = pickMime();
      recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
      chunks = [];
      recorder.ondataavailable = (e) => {
        if (e.data.size) chunks.push(e.data);
      };
      recorder.start(200);
      startedAt = Date.now();
      recording = true;
      timer = window.setInterval(() => {
        const sec = (Date.now() - startedAt) / 1000;
        onTick(sec, 0.4 + Math.random() * 0.5);
        if (sec >= 180) void api.stop();
      }, 200);
    },
    async stop() {
      if (!recorder || !recording) return null;
      recording = false;
      if (timer) clearInterval(timer);
      timer = null;
      const duration = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
      const rec = recorder;
      const blob = await new Promise<Blob>((resolve) => {
        rec.onstop = () => {
          resolve(new Blob(chunks, { type: rec.mimeType || mimeType || 'audio/webm' }));
        };
        try {
          rec.stop();
        } catch {
          resolve(new Blob(chunks, { type: mimeType || 'audio/webm' }));
        }
      });
      stream?.getTracks().forEach((t) => t.stop());
      stream = null;
      recorder = null;
      return { blob, duration };
    },
    cancel() {
      recording = false;
      if (timer) clearInterval(timer);
      timer = null;
      try {
        recorder?.stop();
      } catch {
        /* */
      }
      stream?.getTracks().forEach((t) => t.stop());
      stream = null;
      recorder = null;
      chunks = [];
    },
  };

  return api;
}
