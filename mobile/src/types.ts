export type User = {
  id: number;
  name: string;
  display_name: string;
  email: string;
  avatar_url?: string;
  initials: string;
  color: string;
  roles: string[];
};

export type ChatSummary = {
  id: number;
  type: string;
  title: string;
  unread: number;
  muted: boolean;
  pinned: boolean;
  preview: string;
  last_id: number;
  updated_at?: string;
  avatar: { url: string; initials: string; color: string; shape: string };
};

export type ChatMessage = {
  id: number;
  chat_id: number;
  mine: boolean;
  system: boolean;
  deleted: boolean;
  text: string;
  html?: string;
  author: { id: number; name: string; initials: string; color: string; avatar_url?: string };
  parent?: { id: number; author: string; preview: string } | null;
  task?: { id: number; name: string } | null;
  forwarded: boolean;
  attachments: Array<{ id: number; name: string; mime: string; kind: string; url: string }>;
  created_at?: string;
  created_label: string;
};

export type TaskCard = {
  id: number;
  key?: string;
  name: string;
  status: string;
  status_label: string;
  status_color: string;
  priority?: string | number | null;
  project?: string | null;
  category?: string | null;
  queue?: string | null;
  executor?: string | null;
  role: string;
  end_datetime?: string | null;
  end_label?: string | null;
  description?: string;
  description_html?: string;
  creator?: string | null;
  estimation_hours?: number | null;
  hours_spent?: number | null;
  type_task?: string | null;
  attachments?: Array<{ id: number; name: string; url: string; mime: string }>;
  links?: Array<{ id: number; type: string; related_id: number; related_key?: string; related_name?: string }>;
  observers?: Array<{ id: number; name: string }>;
};

export type CommentCard = {
  id: number;
  text: string;
  html?: string;
  system: boolean;
  author: { id: number; name: string; initials: string; color: string };
  parent_id?: number | null;
  attachments: Array<{ id: number; name: string; url: string }>;
  created_at?: string;
  created_label: string;
};

export type StatusAction = {
  to: string;
  label: string;
  tone?: string;
  confirm?: string | null;
};

export type MentionUser = {
  id: number;
  name: string;
  aliases?: string[];
};
