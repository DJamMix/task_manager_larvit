/** Safe-ish HTML for chat/task content from backend renderer */
export function richHtml(html: string): string {
  if (!html) return '';
  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
  const root = doc.body.firstElementChild;
  if (!root) return '';

  const allowed = new Set([
    'P', 'BR', 'B', 'STRONG', 'I', 'EM', 'U', 'S', 'UL', 'OL', 'LI',
    'A', 'SPAN', 'DIV', 'PRE', 'CODE', 'BLOCKQUOTE', 'H1', 'H2', 'H3', 'H4',
    'IMG', 'HR',
  ]);

  const walk = (node: Node): string => {
    if (node.nodeType === Node.TEXT_NODE) {
      return escapeText(node.textContent || '');
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return '';
    const el = node as HTMLElement;
    const tag = el.tagName;
    if (!allowed.has(tag)) {
      return Array.from(el.childNodes).map(walk).join('');
    }
    if (tag === 'BR') return '<br>';
    if (tag === 'HR') return '<hr>';
    if (tag === 'IMG') {
      const src = el.getAttribute('src') || '';
      if (!/^https?:\/\//i.test(src) && !src.startsWith('/') && !src.startsWith('blob:')) return '';
      const alt = escapeText(el.getAttribute('alt') || '');
      return `<img src="${escapeAttr(src)}" alt="${alt}" class="rich-img" />`;
    }
    if (tag === 'A') {
      const href = el.getAttribute('href') || '';
      if (!/^(https?:|mailto:|\/)/i.test(href)) {
        return Array.from(el.childNodes).map(walk).join('');
      }
      const inner = Array.from(el.childNodes).map(walk).join('');
      return `<a href="${escapeAttr(href)}" target="_blank" rel="noopener">${inner}</a>`;
    }
    const inner = Array.from(el.childNodes).map(walk).join('');
    const cls = el.className ? ` class="${escapeAttr(String(el.className))}"` : '';
    return `<${tag.toLowerCase()}${cls}>${inner}</${tag.toLowerCase()}>`;
  };

  return Array.from(root.childNodes).map(walk).join('');
}

export function escapeText(s: string | null | undefined): string {
  return String(s ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function escapeAttr(s: string): string {
  return escapeText(s).replace(/'/g, '&#39;');
}
