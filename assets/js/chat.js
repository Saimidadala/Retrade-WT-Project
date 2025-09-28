(function(){
  function escapeHtml(str){
    if (typeof str !== 'string') return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatTime(ts){
    try {
      const d = new Date(ts);
      return d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
    } catch(e){ return '' }
  }

  const chatModalEl = document.getElementById('chatModal');
  if (!chatModalEl) return;

  let socket = null;
  let joinedRoom = null;
  let typingTimeout = null;
  let lastTypedAt = 0;

  const chatMessages = document.getElementById('chatMessages');
  const chatInput = document.getElementById('chatInput');
  const sendBtn = document.getElementById('sendMsgBtn');
  const presenceDot = document.getElementById('chatPresence');
  const typingIndicator = document.getElementById('typingIndicator');

  const bsModal = new bootstrap.Modal(chatModalEl);

  function scrollToBottom(){
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function appendMessage({ me, name, text, ts }){
    const bubble = document.createElement('div');
    bubble.className = 'chat-message ' + (me ? 'me' : 'other');
    bubble.innerHTML = `<div>${escapeHtml(text)}</div><span class="meta">${me ? 'You' : escapeHtml(name)} • ${formatTime(ts)}</span>`;
    chatMessages.appendChild(bubble);
    scrollToBottom();
  }

  function setPresence(online){
    if (!presenceDot) return;
    presenceDot.classList.toggle('online', !!online);
  }

  function setTyping(show){
    if (!typingIndicator) return;
    typingIndicator.style.display = show ? 'block' : 'none';
  }

  async function fetchToken({ productId, buyerId, sellerId, role }){
    const form = new FormData();
    form.append('product_id', productId);
    if (role === 'seller') {
      form.append('buyer_id', buyerId);
      const res = await fetch('api/ws_token_seller.php', { method: 'POST', body: form, credentials: 'same-origin' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Failed to get chat token');
      return data; // { token, ws_url, room, negotiation_id, user }
    }
    const res = await fetch('api/ws_token.php', { method: 'POST', body: form, credentials: 'same-origin' });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to get chat token');
    return data; // { token, ws_url, room, negotiation_id, user }
  }

  function bindSocketEvents(user){
    socket.on('connect', ()=> setPresence(true));
    socket.on('disconnect', ()=> setPresence(false));

    socket.on('joined', (payload)=>{
      // payload: { room }
      joinedRoom = payload.room;
    });

    socket.on('message', (msg)=>{
      // msg: { userId, name, text, ts }
      appendMessage({ me: (msg.userId === user.id), name: msg.name, text: msg.text, ts: msg.ts });
    });

    socket.on('typing', ({ userId, typing, name })=>{
      // Only show typing if it's the other user
      if (userId !== user.id) setTyping(!!typing);
    });
  }

  async function sendMessage(){
    if (!socket || !socket.connected || !joinedRoom) return;
    const text = chatInput.value.trim();
    if (!text) return;
    if (text.length > 1000) {
      alert('Message too long (max 1000 characters).');
      return;
    }
    socket.emit('message', { room: joinedRoom, text });
    // Save message
    try {
      if (window.__negotiationId && window.__currentUser) {
        const form = new FormData();
        form.append('negotiation_id', String(window.__negotiationId));
        form.append('sender_id', String(window.__currentUser.id));
        form.append('message', text);
        await fetch('api/chat_message_save.php', { method: 'POST', body: form, credentials: 'same-origin' });
      }
    } catch(e){}
    chatInput.value = '';
  }

  function notifyTyping(){
    if (!socket || !socket.connected || !joinedRoom) return;
    const now = Date.now();
    if (now - lastTypedAt > 300) {
      socket.emit('typing', { room: joinedRoom, typing: true });
      lastTypedAt = now;
      setTimeout(()=> socket.emit('typing', { room: joinedRoom, typing: false }), 3000);
    }
  }

  async function openChatFromButton(btn){
    try {
      const productId = btn.getAttribute('data-product-id');
      const buyerId = btn.getAttribute('data-buyer-id');
      const sellerId = btn.getAttribute('data-seller-id');
      const role = btn.getAttribute('data-role') || 'buyer';
      console.debug('Chat: openChatFromButton click', { role, productId, buyerId, sellerId });
      bsModal.show();
      setPresence(false);
      setTyping(false);
      chatMessages.innerHTML = '';
      console.debug('Chat: requesting token', { role });
      const { token, ws_url, room, negotiation_id, user } = await fetchToken({ productId, buyerId, sellerId, role });
      console.debug('Chat: token ok', { ws_url, room, negotiation_id, user });
      window.__negotiationId = negotiation_id;
      window.__currentUser = user;
      if (socket) {
        try { socket.disconnect(); } catch(e){}
        socket = null;
      }

      socket = io(ws_url, {
        path: '/socket.io',
        auth: { token },
        withCredentials: true,
        // transports left default to allow polling/websocket negotiation
      });

      bindSocketEvents(user);
      socket.on('connect', ()=> console.debug('Chat: socket connected'));

      // Temporary: surface connection errors to help diagnose issues like "Transport unknown"
      socket.on('connect_error', (err)=>{
        console.error('Chat connect_error:', err?.message || err);
      });

      // Join the negotiated room once connected so the server will accept our messages
      socket.once('connect', ()=>{
        try {
          socket.emit('join', { room });
          console.debug('Chat: join emitted', { room });
        } catch(e){}
      });

      // autofocus input
      setTimeout(()=> chatInput?.focus(), 300);

      // Load history
      try {
        if (window.__negotiationId) {
          const params = new URLSearchParams({ negotiation_id: String(window.__negotiationId), limit: '100' });
          const res = await fetch('api/chat_history.php?' + params.toString(), { credentials: 'same-origin' });
          const data = await res.json();
          if (res.ok && Array.isArray(data.messages)) {
            data.messages.forEach(m => {
              appendMessage({ me: (m.sender_id === user.id), name: m.sender_name || 'User', text: m.message, ts: m.created_at });
            });
            console.debug('Chat: history loaded', { count: data.messages.length });
          }
        }
      } catch(e){}

    } catch (e) {
      alert(e.message || 'Unable to start chat');
      console.error('Chat: openChatFromButton failed', e);
    }
  }
  // Event delegation: support multiple buttons
  document.addEventListener('click', function(e){
    const target = e.target.closest('.openChatBtn, #openChatBtn');
    if (target) {
      e.preventDefault();
      openChatFromButton(target);
    }
  });

  if (sendBtn) sendBtn.addEventListener('click', sendMessage);
  chatInput.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    } else {
      notifyTyping();
    }
  });
})();
