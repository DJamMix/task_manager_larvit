import { Room, RoomEvent, Track, createLocalVideoTrack } from 'livekit-client';
import { api } from './api';

let room: Room | null = null;

export type CallConnection = {
  token: string;
  ws_url: string;
  room: string;
  call_id: number;
  video?: boolean;
  can_end?: boolean;
  me?: { id: number; name: string; avatar?: string; initials?: string; color?: string };
  roster?: Array<{ id: number; name: string; avatar?: string; initials?: string; color?: string }>;
};

export async function startCall(chatId: number, video = true): Promise<CallConnection> {
  return api<CallConnection>(`/chats/${chatId}/calls/start?video=${video ? 1 : 0}`, {
    method: 'POST',
  });
}

export async function joinCall(callId: number): Promise<CallConnection> {
  return api<CallConnection>(`/calls/${callId}/join`, { method: 'POST' });
}

export async function leaveCall(callId: number): Promise<void> {
  try {
    await api(`/calls/${callId}/leave`, { method: 'POST' });
  } catch {
    /* ignore */
  }
}

export async function endCall(callId: number): Promise<void> {
  try {
    await api(`/calls/${callId}/end`, { method: 'POST' });
  } catch {
    /* ignore */
  }
}

export async function connectRoom(
  conn: CallConnection,
  els: { local: HTMLVideoElement; remote: HTMLDivElement },
): Promise<Room> {
  await disconnectRoom();
  room = new Room({ adaptiveStream: true, dynacast: true });

  room.on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
    if (track.kind === Track.Kind.Video || track.kind === Track.Kind.Audio) {
      const el = track.attach();
      el.dataset.participant = participant.identity;
      if (track.kind === Track.Kind.Video) {
        el.style.width = '100%';
        el.style.borderRadius = '12px';
        els.remote.appendChild(el);
      } else {
        document.body.appendChild(el);
      }
    }
  });

  room.on(RoomEvent.TrackUnsubscribed, (track) => {
    track.detach().forEach((el) => el.remove());
  });

  await room.connect(conn.ws_url, conn.token);

  if (conn.video !== false) {
    try {
      const cam = await createLocalVideoTrack({ facingMode: 'user' });
      await room.localParticipant.publishTrack(cam);
      cam.attach(els.local);
    } catch {
      /* camera optional */
    }
  }

  try {
    await room.localParticipant.setMicrophoneEnabled(true);
  } catch {
    /* mic optional until permission */
  }

  return room;
}

export async function disconnectRoom(): Promise<void> {
  if (room) {
    await room.disconnect();
    room = null;
  }
}
