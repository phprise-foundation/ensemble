const STATUSES = [
  'intent',
  'feasibility',
  'design',
  'plan',
  'qa-refinement',
  'coding',
  'reviewing',
  'testing',
  'done',
];

const ROLES = [
  'product-owner',
  'solutions-architect',
  'systems-architect',
  'framework-architect',
  'tech-lead',
  'qa',
  'coder',
];

const TOKEN_KEY = 'board_token';

const login = document.getElementById('login');
const board = document.getElementById('board');
const columns = document.getElementById('columns');
const modal = document.getElementById('modal');
const modalBody = document.getElementById('modal-body');
const copilot = document.getElementById('copilot');
const copilotMessages = document.getElementById('copilot-messages');

document.getElementById('login-form').addEventListener('submit', signIn);
document.getElementById('logout').addEventListener('click', signOut);
document.getElementById('new-task').addEventListener('click', openNewTask);
document.getElementById('modal-close').addEventListener('click', closeModal);
document.getElementById('copilot-toggle').addEventListener('click', toggleCopilot);
document.getElementById('copilot-close').addEventListener('click', closeCopilot);
document.getElementById('copilot-form').addEventListener('submit', sendChat);

function token() {
  return localStorage.getItem(TOKEN_KEY);
}

async function api(method, path, body) {
  const headers = { 'Content-Type': 'application/json' };
  const auth = token();
  if (auth) {
    headers.Authorization = `Bearer ${auth}`;
  }
  const response = await fetch(path, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (!response.ok) {
    throw new Error(`${response.status} ${response.statusText}`);
  }
  return response.json();
}

async function signIn(event) {
  event.preventDefault();
  const password = document.getElementById('password').value;
  try {
    const data = await api('POST', '/api/login', { password });
    localStorage.setItem(TOKEN_KEY, data.token);
    showBoard();
  } catch (error) {
    document.getElementById('login-error').textContent = 'Invalid password';
  }
}

function signOut() {
  localStorage.removeItem(TOKEN_KEY);
  login.classList.remove('hidden');
  board.classList.add('hidden');
  copilot.classList.add('hidden');
}

function showBoard() {
  login.classList.add('hidden');
  board.classList.remove('hidden');
  refresh();
}

async function refresh() {
  const data = await api('GET', '/api/items');
  renderBoard(data.items);
}

function renderBoard(items) {
  columns.innerHTML = '';
  for (const status of STATUSES) {
    columns.appendChild(renderColumn(status, items.filter((item) => item.status === status)));
  }
}

function renderColumn(status, items) {
  const column = document.createElement('section');
  column.className = 'column';
  const header = document.createElement('header');
  header.className = 'column-header';
  header.innerHTML = `<h2>${status}</h2><span class="count">${items.length}</span>`;
  column.appendChild(header);
  for (const item of items) {
    column.appendChild(renderCard(item));
  }
  return column;
}

function renderCard(item) {
  const card = document.createElement('article');
  card.className = 'card';
  if (item.blocked) {
    card.classList.add('blocked');
  }
  const badges = [
    item.role ? `<span class="badge role">${item.role}</span>` : '',
    item.worker ? `<span class="badge worker">${item.worker}</span>` : '',
    item.blocked ? '<span class="badge block">blocked</span>' : '',
  ].join('');
  card.innerHTML = `<h3>${escapeHtml(item.title)}</h3><div class="badges">${badges}</div>`;
  card.addEventListener('click', () => openItem(item));
  return card;
}

async function openItem(item) {
  modalBody.innerHTML = '<p>Loading…</p>';
  openModal();
  const data = await api('GET', `/api/items/${item.id}`);
  renderItemDetail(data.item);
}

function renderItemDetail(item) {
  modalBody.innerHTML = `
    <h2>#${item.id} ${escapeHtml(item.title)}</h2>
    <div class="field">
      <label>Status</label>
      <select id="item-status">${STATUSES.map((s) => option(s, s === item.status)).join('')}</select>
    </div>
    <div class="field">
      <label>Role</label>
      <select id="item-role">
        <option value="">—</option>
        ${ROLES.map((r) => option(r, r === item.role)).join('')}
      </select>
    </div>
    <div class="field">
      <label>Worker</label>
      <input id="item-worker" value="${escapeHtml(item.worker)}" />
    </div>
    <p class="body">${escapeHtml(item.body)}</p>
    <section class="comments">
      <h3>Comments</h3>
      <div class="comment-list">${item.comments.map(renderComment).join('') || '<p class="muted">No comments</p>'}</div>
      <textarea id="comment-body" placeholder="Write a comment…"></textarea>
      <button id="add-comment" data-id="${item.id}">Comment</button>
    </section>
    <div class="detail-actions">
      <button id="save-item" data-id="${item.id}">Save</button>
      <button id="close-item" data-id="${item.id}" class="danger">Close</button>
    </div>
  `;
  document.getElementById('save-item').addEventListener('click', () => saveItem(item.id));
  document.getElementById('close-item').addEventListener('click', () => closeItem(item.id));
  document.getElementById('add-comment').addEventListener('click', () => addComment(item.id));
}

function option(value, selected) {
  return `<option value="${value}"${selected ? ' selected' : ''}>${value}</option>`;
}

function renderComment(comment) {
  return `<div class="comment"><span class="author">${escapeHtml(comment.author)}</span><p>${escapeHtml(comment.body)}</p></div>`;
}

async function saveItem(id) {
  const body = {
    status: document.getElementById('item-status').value,
    role: document.getElementById('item-role').value || null,
    worker: document.getElementById('item-worker').value,
  };
  await api('PATCH', `/api/items/${id}`, body);
  closeModal();
  refresh();
}

async function addComment(id) {
  const body = { author: 'user', body: document.getElementById('comment-body').value };
  await api('POST', `/api/items/${id}/comments`, body);
  const data = await api('GET', `/api/items/${id}`);
  renderItemDetail(data.item);
}

async function closeItem(id) {
  await api('POST', `/api/items/${id}/close`, {});
  closeModal();
  refresh();
}

function openNewTask() {
  modalBody.innerHTML = `
    <h2>New task</h2>
    <div class="field"><label>Title</label><input id="new-title" /></div>
    <div class="field"><label>Description</label><textarea id="new-body"></textarea></div>
    <div class="field"><label>Role</label><select id="new-role"><option value="">—</option>${ROLES.map((r) => option(r, false)).join('')}</select></div>
    <button id="create-item">Create</button>
  `;
  document.getElementById('create-item').addEventListener('click', createItem);
  openModal();
}

async function createItem() {
  const body = {
    title: document.getElementById('new-title').value,
    body: document.getElementById('new-body').value,
    role: document.getElementById('new-role').value || null,
  };
  await api('POST', '/api/items', body);
  closeModal();
  refresh();
}

function openModal() {
  modal.classList.remove('hidden');
}

function closeModal() {
  modal.classList.add('hidden');
}

function toggleCopilot() {
  copilot.classList.toggle('hidden');
  if (!copilot.classList.contains('hidden')) {
    loadChat().catch(() => {});
  }
}

function closeCopilot() {
  copilot.classList.add('hidden');
}

async function loadChat() {
  const data = await api('GET', '/api/chat');
  renderChat(data.messages);
}

function renderChat(messages) {
  copilotMessages.innerHTML =
    messages.map(renderMessage).join('') || '<p class="muted">No messages yet</p>';
  copilotMessages.scrollTop = copilotMessages.scrollHeight;
}

function renderMessage(message) {
  return `<div class="chat-message ${message.role}"><span class="chat-role">${message.role}</span><p>${escapeHtml(message.body)}</p></div>`;
}

async function sendChat(event) {
  event.preventDefault();
  const input = document.getElementById('copilot-input');
  const message = input.value.trim();
  if (!message) {
    return;
  }
  input.value = '';
  await api('POST', '/api/chat', { message });
  await loadChat();
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value;
  return div.innerHTML;
}

if (token()) {
  showBoard();
}

setInterval(() => {
  if (token() && !board.classList.contains('hidden')) {
    refresh().catch(() => {});
  }
}, 5000);

setInterval(() => {
  if (token() && !copilot.classList.contains('hidden')) {
    loadChat().catch(() => {});
  }
}, 5000);
