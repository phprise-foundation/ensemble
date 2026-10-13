const TOKEN_KEY = 'board_token';

const state = {
  board: null,
  agents: [],
  items: [],
  view: 'board',
};

const el = {
  login: document.getElementById('login'),
  app: document.getElementById('app'),
  columns: document.getElementById('columns'),
  boards: document.getElementById('view-boards'),
  agents: document.getElementById('view-agents'),
  title: document.getElementById('view-title'),
  actions: document.getElementById('view-actions'),
  modal: document.getElementById('modal'),
  modalBody: document.getElementById('modal-body'),
  copilot: document.getElementById('copilot'),
  copilotMessages: document.getElementById('copilot-messages'),
};

document.getElementById('login-form').addEventListener('submit', signIn);
document.getElementById('logout').addEventListener('click', signOut);
document.getElementById('modal-close').addEventListener('click', closeModal);
document.getElementById('copilot-toggle').addEventListener('click', toggleCopilot);
document.getElementById('copilot-close').addEventListener('click', closeCopilot);
document.getElementById('copilot-form').addEventListener('submit', sendChat);
document.querySelectorAll('.nav').forEach((button) => {
  button.addEventListener('click', () => showView(button.dataset.view));
});

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
    showApp();
  } catch (error) {
    document.getElementById('login-error').textContent = 'Invalid password';
  }
}

function signOut() {
  localStorage.removeItem(TOKEN_KEY);
  el.login.classList.remove('hidden');
  el.app.classList.add('hidden');
  el.copilot.classList.add('hidden');
}

function showApp() {
  el.login.classList.add('hidden');
  el.app.classList.remove('hidden');
  setActiveNav('board');
  loadAll().catch(() => {});
}

async function loadAll() {
  const [board, agents] = await Promise.all([api('GET', '/api/board'), api('GET', '/api/agents')]);
  state.board = board.board;
  state.agents = agents.agents;
  await refresh();
  renderBoards();
  renderAgents();
}

async function refresh() {
  const data = await api('GET', '/api/items');
  state.items = data.items;
  renderBoard();
}

function showView(view) {
  state.view = view;
  setActiveNav(view);
  document.getElementById('view-board').classList.toggle('hidden', view !== 'board');
  el.boards.classList.toggle('hidden', view !== 'boards');
  el.agents.classList.toggle('hidden', view !== 'agents');
  const titles = { board: 'Board', boards: 'Boards', agents: 'Agents' };
  el.title.textContent = titles[view];
  el.actions.innerHTML = '';
  if (view === 'board') {
    el.actions.appendChild(button('New task', openNewTask));
    refresh().catch(() => {});
  }
  if (view === 'boards') {
    renderBoards();
  }
  if (view === 'agents') {
    renderAgents();
  }
}

function setActiveNav(view) {
  document.querySelectorAll('.nav').forEach((nav) => {
    nav.classList.toggle('active', nav.dataset.view === view);
  });
}

function button(label, handler, className) {
  const element = document.createElement('button');
  element.textContent = label;
  if (className) {
    element.className = className;
  }
  element.addEventListener('click', handler);
  return element;
}

function renderBoard() {
  el.columns.innerHTML = '';
  for (const column of state.board.columns) {
    const items = state.items.filter((item) => item.status === column.id);
    el.columns.appendChild(renderColumn(column, items));
  }
}

function renderColumn(column, items) {
  const section = document.createElement('section');
  section.className = 'column';
  const aging = items.filter((item) => isAging(item)).length;
  const header = document.createElement('header');
  header.className = 'column-header';
  header.innerHTML = `<h2>${escapeHtml(column.name)}${column.final ? ' &#10003;' : ''}</h2>
    <span class="count">${items.length}${aging ? ` · <b class="warn">${aging} aging</b>` : ''}</span>`;
  section.appendChild(header);
  for (const item of items) {
    section.appendChild(renderCard(item));
  }
  return section;
}

function renderCard(item) {
  const card = document.createElement('article');
  card.className = 'card';
  if (item.blocked) {
    card.classList.add('blocked');
  }
  if (isAging(item)) {
    card.classList.add('aging');
  }
  const badges = [
    item.role ? `<span class="badge role">${escapeHtml(agentName(item.role))}</span>` : '',
    item.worker ? `<span class="badge worker">${escapeHtml(item.worker)}</span>` : '',
    item.blocked ? '<span class="badge block">blocked</span>' : '',
    isAging(item) ? `<span class="badge aging">${ageLabel(item)} in stage</span>` : '',
  ].join('');
  card.innerHTML = `<h3>#${item.id} ${escapeHtml(item.title)}</h3><div class="badges">${badges}</div>`;
  card.addEventListener('click', () => openItem(item));
  return card;
}

function agentName(id) {
  const agent = state.agents.find((candidate) => candidate.id === id);
  return agent ? agent.name : id;
}

function isAging(item) {
  return ageHours(item) >= state.board.aging_hours;
}

function ageHours(item) {
  return (Date.now() - Date.parse(item.entered_at)) / 3600000;
}

function ageLabel(item) {
  const hours = ageHours(item);
  return hours < 48 ? `${Math.floor(hours)}h` : `${Math.floor(hours / 24)}d`;
}

async function openItem(item) {
  el.modalBody.innerHTML = '<p>Loading…</p>';
  openModal();
  const data = await api('GET', `/api/items/${item.id}`);
  renderItemDetail(data.item);
}

function renderItemDetail(item) {
  const columnOptions = state.board.columns
    .map((column) => option(column.id, column.name, column.id === item.status))
    .join('');
  const agentOptions = `<option value="">—</option>${state.agents
    .map((agent) => option(agent.id, agent.name, agent.id === item.role))
    .join('')}`;
  el.modalBody.innerHTML = `
    <h2>#${item.id} ${escapeHtml(item.title)}</h2>
    <div class="field">
      <label>Column</label>
      <select id="item-status">${columnOptions}</select>
    </div>
    <div class="field">
      <label>Agent</label>
      <select id="item-role">${agentOptions}</select>
    </div>
    <div class="field">
      <label>Worker</label>
      <input id="item-worker" value="${escapeHtml(item.worker)}" />
    </div>
    <p class="body">${escapeHtml(item.body)}</p>
    ${renderHistory(item)}
    <section class="comments">
      <h3>Comments</h3>
      <div class="comment-list">${item.comments.map(renderComment).join('') || '<p class="muted">No comments</p>'}</div>
      <textarea id="comment-body" placeholder="Write a comment…"></textarea>
      <button id="add-comment">Comment</button>
    </section>
    <div class="detail-actions">
      <button id="save-item">Save</button>
      <button id="close-item" class="danger">Close</button>
    </div>
  `;
  document.getElementById('save-item').addEventListener('click', () => saveItem(item.id));
  document.getElementById('close-item').addEventListener('click', () => closeItem(item.id));
  document.getElementById('add-comment').addEventListener('click', () => addComment(item.id));
}

function renderHistory(item) {
  if (!item.history || !item.history.length) {
    return '';
  }
  const rows = item.history
    .slice()
    .reverse()
    .map((event) => {
      const from = event.from ? columnName(event.from) : 'created';
      return `<li><span class="muted">${escapeHtml(event.at)}</span> ${escapeHtml(from)} → <b>${escapeHtml(columnName(event.to))}</b> <span class="muted">by ${escapeHtml(event.actor)}</span></li>`;
    })
    .join('');
  return `<section class="history"><h3>History</h3><ul>${rows}</ul></section>`;
}

function columnName(id) {
  const column = state.board.columns.find((candidate) => candidate.id === id);
  return column ? column.name : id;
}

function option(value, label, selected) {
  return `<option value="${escapeHtml(value)}"${selected ? ' selected' : ''}>${escapeHtml(label)}</option>`;
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
  const columnOptions = state.board.columns
    .map((column, index) => option(column.id, column.name, index === 0))
    .join('');
  const agentOptions = `<option value="">—</option>${state.agents
    .map((agent) => option(agent.id, agent.name, false))
    .join('')}`;
  el.modalBody.innerHTML = `
    <h2>New task</h2>
    <div class="field"><label>Title</label><input id="new-title" /></div>
    <div class="field"><label>Description</label><textarea id="new-body"></textarea></div>
    <div class="field"><label>Column</label><select id="new-status">${columnOptions}</select></div>
    <div class="field"><label>Agent</label><select id="new-role">${agentOptions}</select></div>
    <button id="create-item">Create</button>
  `;
  document.getElementById('create-item').addEventListener('click', createItem);
  openModal();
}

async function createItem() {
  const body = {
    title: document.getElementById('new-title').value,
    body: document.getElementById('new-body').value,
    status: document.getElementById('new-status').value,
    role: document.getElementById('new-role').value || null,
  };
  await api('POST', '/api/items', body);
  closeModal();
  refresh();
}

function renderBoards() {
  if (!state.board || state.view !== 'boards') {
    return;
  }
  el.boards.innerHTML = `
    <section class="panel">
      <h2>Board</h2>
      <div class="field"><label>Name</label><input id="board-name" value="${escapeHtml(state.board.name)}" /></div>
      <div class="field"><label>Aging threshold (hours)</label><input id="board-aging" type="number" min="1" value="${state.board.aging_hours}" /></div>
      <button id="board-save">Save</button>
    </section>
    <section class="panel">
      <h2>Columns</h2>
      <div id="column-rows"></div>
      <div class="row add-row">
        <input id="new-column-name" placeholder="New column name" />
        <label class="inline"><input id="new-column-final" type="checkbox" /> final</label>
        <button id="new-column">Add</button>
      </div>
    </section>
  `;
  document.getElementById('board-save').addEventListener('click', saveBoard);
  document.getElementById('new-column').addEventListener('click', createColumn);
  const rows = document.getElementById('column-rows');
  for (const column of state.board.columns) {
    rows.appendChild(renderColumnRow(column));
  }
}

function renderColumnRow(column) {
  const row = document.createElement('div');
  row.className = 'row column-row';
  const roleOptions = `<option value="">—</option>${state.agents
    .map((agent) => option(agent.id, agent.name, agent.id === column.role))
    .join('')}`;
  const transitionOptions = state.board.columns
    .filter((candidate) => candidate.id !== column.id)
    .map((candidate) => option(candidate.id, candidate.name, column.transitions.includes(candidate.id)))
    .join('');
  row.innerHTML = `
    <input class="name" value="${escapeHtml(column.name)}" />
    <input class="position" type="number" min="0" value="${column.position}" />
    <select class="role" title="Role that owns this stage">${roleOptions}</select>
    <select class="transitions" multiple title="Allowed next columns">${transitionOptions}</select>
    <label class="inline"><input class="final" type="checkbox" ${column.final ? 'checked' : ''} /> final</label>
    <button class="save">Save</button>
    <button class="danger remove">Delete</button>
  `;
  row.querySelector('.save').addEventListener('click', () => updateColumn(column.id, row));
  row.querySelector('.remove').addEventListener('click', () => deleteColumn(column.id));
  return row;
}

async function saveBoard() {
  await api('PATCH', '/api/board', {
    name: document.getElementById('board-name').value,
    aging_hours: Number(document.getElementById('board-aging').value),
  });
  await reloadBoard();
}

async function createColumn() {
  await api('POST', '/api/board/columns', {
    name: document.getElementById('new-column-name').value,
    final: document.getElementById('new-column-final').checked,
  });
  await reloadBoard();
}

async function updateColumn(id, row) {
  await api('PATCH', `/api/board/columns/${id}`, {
    name: row.querySelector('.name').value,
    position: Number(row.querySelector('.position').value),
    final: row.querySelector('.final').checked,
    role: row.querySelector('.role').value || null,
    transitions: Array.from(row.querySelector('.transitions').selectedOptions).map((opt) => opt.value),
  });
  await reloadBoard();
}

async function deleteColumn(id) {
  await api('DELETE', `/api/board/columns/${id}`);
  await reloadBoard();
}

async function reloadBoard() {
  const board = await api('GET', '/api/board');
  state.board = board.board;
  renderBoards();
  if (!el.app.classList.contains('hidden')) {
    renderBoard();
  }
}

function renderAgents() {
  if (state.view !== 'agents') {
    return;
  }
  const rows = state.agents.map(renderAgentRow).join('');
  el.agents.innerHTML = `
    <section class="panel">
      <h2>Agents</h2>
      <div id="agent-rows">${rows || '<p class="muted">No agents</p>'}</div>
    </section>
    <section class="panel">
      <h2>New agent</h2>
      <div class="field"><label>Name</label><input id="new-agent-name" /></div>
      <div class="field"><label>Slug (optional)</label><input id="new-agent-slug" /></div>
      <div class="field"><label>Description</label><input id="new-agent-description" /></div>
      <div class="field"><label>Rule</label><input id="new-agent-rule" placeholder="e.g. coder" /></div>
      <div class="field"><label>Profile</label><input id="new-agent-profile" placeholder="e.g. skeptic" /></div>
      <div class="field"><label>Model</label><input id="new-agent-model" placeholder="provider/model" /></div>
      <button id="new-agent">Add</button>
    </section>
  `;
  document.getElementById('new-agent').addEventListener('click', createAgent);
  for (const agent of state.agents) {
    const row = document.getElementById(`agent-${agent.id}`);
    if (!row) {
      continue;
    }
    row.querySelector('.save').addEventListener('click', () => updateAgent(agent.id, row));
    row.querySelector('.remove').addEventListener('click', () => deleteAgent(agent.id));
  }
}

function renderAgentRow(agent) {
  return `
    <div class="agent" id="agent-${escapeHtml(agent.id)}">
      <div class="row">
        <input class="name" value="${escapeHtml(agent.name)}" placeholder="Name" />
        <input class="description" value="${escapeHtml(agent.description)}" placeholder="Description" />
        <label class="inline"><input class="active" type="checkbox" ${agent.active ? 'checked' : ''} /> active</label>
      </div>
      <div class="row">
        <input class="rule" value="${escapeHtml(agent.rule)}" placeholder="rule (e.g. coder)" />
        <input class="profile" value="${escapeHtml(agent.profile)}" placeholder="profile" />
        <input class="model" value="${escapeHtml(agent.model)}" placeholder="provider/model" />
        <input class="fallback_model" value="${escapeHtml(agent.fallback_model)}" placeholder="fallback model" />
        <input class="interval" type="number" min="1" value="${agent.interval}" title="seconds between scans" />
        <input class="workspace" value="${escapeHtml(agent.workspace)}" placeholder="/workspace" />
      </div>
      <div class="row">
        <button class="save">Save</button>
        <button class="danger remove">Delete</button>
      </div>
    </div>
  `;
}

async function createAgent() {
  await api('POST', '/api/agents', {
    name: document.getElementById('new-agent-name').value,
    slug: document.getElementById('new-agent-slug').value,
    description: document.getElementById('new-agent-description').value,
    rule: document.getElementById('new-agent-rule').value,
    profile: document.getElementById('new-agent-profile').value,
    model: document.getElementById('new-agent-model').value,
  });
  await reloadAgents();
}

async function updateAgent(id, row) {
  await api('PATCH', `/api/agents/${id}`, {
    name: row.querySelector('.name').value,
    description: row.querySelector('.description').value,
    active: row.querySelector('.active').checked,
    rule: row.querySelector('.rule').value,
    profile: row.querySelector('.profile').value,
    model: row.querySelector('.model').value,
    fallback_model: row.querySelector('.fallback_model').value,
    interval: Number(row.querySelector('.interval').value),
    workspace: row.querySelector('.workspace').value,
  });
  await reloadAgents();
}

async function deleteAgent(id) {
  await api('DELETE', `/api/agents/${id}`);
  await reloadAgents();
}

async function reloadAgents() {
  const agents = await api('GET', '/api/agents');
  state.agents = agents.agents;
  renderAgents();
  renderBoard();
}

function openModal() {
  el.modal.classList.remove('hidden');
}

function closeModal() {
  el.modal.classList.add('hidden');
}

function toggleCopilot() {
  el.copilot.classList.toggle('hidden');
  if (!el.copilot.classList.contains('hidden')) {
    loadChat().catch(() => {});
  }
}

function closeCopilot() {
  el.copilot.classList.add('hidden');
}

async function loadChat() {
  const data = await api('GET', '/api/chat');
  renderChat(data.messages);
}

function renderChat(messages) {
  el.copilotMessages.innerHTML =
    messages.map(renderMessage).join('') || '<p class="muted">No messages yet</p>';
  el.copilotMessages.scrollTop = el.copilotMessages.scrollHeight;
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
  div.textContent = value === null || value === undefined ? '' : String(value);
  return div.innerHTML;
}

if (token()) {
  showApp();
}

setInterval(() => {
  if (token() && state.view === 'board' && !el.app.classList.contains('hidden')) {
    refresh().catch(() => {});
  }
}, 5000);

setInterval(() => {
  if (token() && !el.copilot.classList.contains('hidden')) {
    loadChat().catch(() => {});
  }
}, 5000);
