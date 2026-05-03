// ─── State ────────────────────────────────────────────────────────────────────
let selectedTherapistId = null;
let pollingInterval = null;

// ─── DOM refs ─────────────────────────────────────────────────────────────────
const chatUsers = document.getElementById('chatUsers');
const chatMessages = document.getElementById('chatMessages');
const chatHeader = document.getElementById('chatHeader');
const chatInputArea = document.getElementById('chatInputArea');
const messageInput = document.getElementById('messageInput');
const sendBtn = document.getElementById('sendBtn');
const fileInput = document.getElementById('fileInput');
const chatSearch = document.getElementById('chatSearch');
const emojiBtn = document.getElementById('emojiBtn');
const menuBtn = document.getElementById('menuBtn');
const sidebar = document.querySelector('.sidebar');
const logoutBtn = document.getElementById('logoutBtn');

// ─── Boot ─────────────────────────────────────────────────────────────────────
loadConversations();

// ─── Conversations ────────────────────────────────────────────────────────────

function loadConversations() {
  apiPost({ action: 'get_conversations' })
    .then(data => {
      // If the server returned an error object instead of an array, surface it
      if (!Array.isArray(data)) {
        const msg = data?.error ?? 'استجابة غير متوقعة من الخادم.';
        chatUsers.innerHTML = `<div class="chat-error">${escapeHtml(msg)}</div>`;
        return;
      }
      const isFirstLoad = selectedTherapistId === null;
      renderConversations(data);
      // Auto-select the most recent conversation on first load
      if (isFirstLoad && data.length > 0) {
        selectTherapist(data[0].therapist_id, data[0].therapist_name);
      }
    })
    .catch(err => {
      chatUsers.innerHTML = `<div class="chat-error">تعذّر تحميل المحادثات. (${escapeHtml(err.message)})</div>`;
    });
}

function renderConversations(conversations) {
  if (conversations.length === 0) {
    chatUsers.innerHTML = '<div class="chat-empty">لا توجد محادثات بعد.</div>';
    return;
  }

  chatUsers.innerHTML = '';
  conversations.forEach(conv => {
    const div = document.createElement('div');
    div.classList.add('chat-user');
    if (conv.therapist_id === selectedTherapistId) {
      div.classList.add('active');
    }

    const avatarLetter = (conv.therapist_name || '؟')[0];
    const unreadBadge = conv.unread_count > 0
      ? `<span class="unread-badge">${conv.unread_count}</span>`
      : '';

    div.innerHTML = `
      <div class="avatar">${avatarLetter}</div>
      <div class="conv-info">
        <h4>${escapeHtml(conv.therapist_name)}</h4>
        <p>${escapeHtml(conv.last_message)}</p>
      </div>
      <div class="conv-meta">
        <span class="conv-time">${escapeHtml(conv.last_message_time)}</span>
        ${unreadBadge}
      </div>
    `;

    div.addEventListener('click', () => selectTherapist(conv.therapist_id, conv.therapist_name));
    chatUsers.appendChild(div);
  });
}

// ─── Conversation selection ───────────────────────────────────────────────────

function selectTherapist(therapistId, therapistName) {
  selectedTherapistId = therapistId;

  // Update header
  chatHeader.innerHTML = `<h3>${escapeHtml(therapistName)}</h3>`;

  // Show input area
  chatInputArea.style.display = '';

  // Highlight active conversation
  document.querySelectorAll('.chat-user').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.chat-user').forEach(el => {
    if (el.querySelector('h4')?.textContent === therapistName) {
      el.classList.add('active');
    }
  });

  // Load messages
  loadMessages();

  // Start polling for new messages every 5 seconds
  clearInterval(pollingInterval);
  pollingInterval = setInterval(loadMessages, 5000);
}

// ─── Messages ─────────────────────────────────────────────────────────────────

function loadMessages() {
  if (!selectedTherapistId) return;

  apiPost({ action: 'get_messages', therapistId: selectedTherapistId })
    .then(data => {
      if (!Array.isArray(data)) return;
      renderMessages(data);
      // Refresh conversation list to clear unread badges
      loadConversations();
    })
    .catch(() => {
      // Silent fail on polling — don't disrupt the user
    });
}

function renderMessages(messages) {
  const wasAtBottom = isScrolledToBottom();

  chatMessages.innerHTML = '';

  if (messages.length === 0) {
    chatMessages.innerHTML = '<div class="chat-empty">لا توجد رسائل بعد. ابدأ المحادثة!</div>';
    return;
  }

  messages.forEach(msg => {
    const div = document.createElement('div');
    div.classList.add('message', msg.isMe ? 'me' : 'other');
    div.dataset.messageId = msg.message_id;

    if (msg.type === 'IMAGE' && msg.file_path) {
      const img = document.createElement('img');
      img.src = escapeHtml(msg.file_path);
      img.style.maxWidth = '200px';
      img.style.borderRadius = '8px';
      div.appendChild(img);
    } else if (msg.type === 'FILE' && msg.file_path) {
      const link = document.createElement('a');
      link.href = escapeHtml(msg.file_path);
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.innerText = '📎 ملف مرفق';
      div.appendChild(link);
    } else {
      div.appendChild(document.createTextNode(msg.content ?? ''));
    }

    const span = document.createElement('span');
    span.textContent = msg.time;
    div.appendChild(span);

    chatMessages.appendChild(div);
  });

  // Only auto-scroll if the user was already at the bottom
  if (wasAtBottom) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }
}

// ─── Send message ─────────────────────────────────────────────────────────────

function sendMessage() {
  if (!selectedTherapistId) return;

  const content = messageInput.value.trim();
  if (!content) return;

  // Optimistic UI — append locally immediately
  appendOptimisticMessage(content);
  messageInput.value = '';

  apiPost({ action: 'send_message', therapistId: selectedTherapistId, content })
    .then(data => {
      if (data.success) {
        // Reload to get the real message (with server timestamp)
        loadMessages();
      } else {
        showSendError();
      }
    })
    .catch(() => showSendError());
}

function appendOptimisticMessage(content) {
  // Remove placeholder if present
  const placeholder = chatMessages.querySelector('.chat-empty, .chat-placeholder');
  if (placeholder) placeholder.remove();

  const div = document.createElement('div');
  div.classList.add('message', 'me', 'optimistic');

  div.appendChild(document.createTextNode(content));

  const span = document.createElement('span');
  const now = new Date();
  span.textContent = now.toLocaleTimeString('ar', { hour: '2-digit', minute: '2-digit', hour12: false });
  div.appendChild(span);

  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function showSendError() {
  // Remove the optimistic message that failed
  const optimistic = chatMessages.querySelector('.optimistic');
  if (optimistic) optimistic.remove();

  const err = document.createElement('div');
  err.className = 'chat-error';
  err.textContent = 'فشل إرسال الرسالة. حاول مجدداً.';
  chatMessages.appendChild(err);
  setTimeout(() => err.remove(), 3000);
}

// ─── File attachment ──────────────────────────────────────────────────────────

fileInput.addEventListener('change', () => {
  const file = fileInput.files[0];
  if (!file || !selectedTherapistId) return;

  const formData = new FormData();
  formData.append('file', file);

  fetch('/That-Copy/Database/chat-upload.php', {
    method: 'POST',
    body: formData,
  })
    .then(r => r.json())
    .then(data => {
      if (data.file_path) {
        // Send a FILE or IMAGE message referencing the uploaded path
        const type = file.type.startsWith('image/') ? 'IMAGE' : 'FILE';
        return apiPost({
          action: 'send_message',
          therapistId: selectedTherapistId,
          content: '',
          type,
          file_path: data.file_path,
        });
      }
    })
    .then(() => loadMessages())
    .catch(() => {
      const err = document.createElement('div');
      err.className = 'chat-error';
      err.textContent = 'فشل رفع الملف.';
      chatMessages.appendChild(err);
      setTimeout(() => err.remove(), 3000);
    });

  // Reset input so the same file can be re-uploaded
  fileInput.value = '';
});

// ─── Emoji picker ─────────────────────────────────────────────────────────────

emojiBtn.addEventListener('click', () => {
  const oldPicker = document.querySelector('.emoji-picker');
  if (oldPicker) { oldPicker.remove(); return; }

  const picker = document.createElement('div');
  picker.classList.add('emoji-picker');
  Object.assign(picker.style, {
    position: 'absolute',
    bottom: '60px',
    left: '50%',
    transform: 'translateX(-50%)',
    background: '#fff',
    border: '1px solid #ddd',
    padding: '10px',
    borderRadius: '8px',
    display: 'flex',
    flexWrap: 'wrap',
    gap: '10px',
    boxShadow: '0 4px 10px rgba(0,0,0,0.1)',
    zIndex: '100',
  });

  const emojis = ['😀', '😍', '😂', '😢', '😎', '👍', '❤️', '🙌', '🎉', '🤔', '😴', '😡', '🤩', '🥳', '😇', '😭'];
  emojis.forEach(e => {
    const span = document.createElement('span');
    span.textContent = e;
    span.style.cssText = 'cursor:pointer;font-size:20px;';
    span.addEventListener('click', () => {
      messageInput.value += e;
      picker.remove();
      messageInput.focus();
    });
    picker.appendChild(span);
  });

  document.body.appendChild(picker);

  // Close picker when clicking outside
  setTimeout(() => {
    document.addEventListener('click', function closePicker(ev) {
      if (!picker.contains(ev.target) && ev.target !== emojiBtn) {
        picker.remove();
        document.removeEventListener('click', closePicker);
      }
    });
  }, 0);
});

// ─── Search / filter conversations ───────────────────────────────────────────

chatSearch.addEventListener('input', () => {
  const query = chatSearch.value.trim().toLowerCase();
  document.querySelectorAll('#chatUsers .chat-user').forEach(el => {
    const name = (el.querySelector('h4')?.textContent ?? '').toLowerCase();
    el.style.display = name.includes(query) ? '' : 'none';
  });
});

// ─── Send on Enter / button click ────────────────────────────────────────────

sendBtn.addEventListener('click', sendMessage);
messageInput.addEventListener('keypress', e => {
  if (e.key === 'Enter') sendMessage();
});

// ─── Sidebar toggle ───────────────────────────────────────────────────────────

let overlay = document.querySelector('.sidebar-overlay');
if (!overlay) {
  overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);
}

menuBtn.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('open');
});

overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('open');
});

// ─── Logout ───────────────────────────────────────────────────────────────────

if (logoutBtn) {
  logoutBtn.addEventListener('click', () => {
    window.location.href = '/That-Copy/Auth/loginpage/login.php';
  });
}

// ─── Utilities ────────────────────────────────────────────────────────────────

/**
 * POST helper — sends URLSearchParams to the chat API and returns parsed JSON.
 * @param {Record<string, string|number>} params
 * @returns {Promise<any>}
 */
function apiPost(params) {
  return fetch(CHAT_API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(params),
  }).then(r => {
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
  });
}

/**
 * Escapes HTML special characters to prevent XSS when inserting user content.
 * @param {string} str
 * @returns {string}
 */
function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Returns true if the chat messages container is scrolled to (or near) the bottom.
 * @returns {boolean}
 */
function isScrolledToBottom() {
  return chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 60;
}