/**
 * Shared response handler: redirects to the sign-in page on 401,
 * otherwise parses JSON normally. Used by every API.* fetch call.
 */
function handleAuthRedirect(res) {
  if (res.status === 401) {
    window.location.href = '/sign/in';
    throw new Error('Session expired. Redirecting to sign in…');
  }
  return res.json();
}

const API = {

  /**
   * [AJAX] GET /api/customers
   * ?q=&is_active=&sort=name&dir=ASC&page=1&per_page=5
   */
  getCustomers({ q='', isActive='', sort='name', dir='ASC', page=1 } = {}) {
    return fetch(`/api/customers?q=${encodeURIComponent(q)}&is_active=${encodeURIComponent(isActive)}&sort=${encodeURIComponent(sort)}&dir=${encodeURIComponent(dir)}&page=${encodeURIComponent(page)}`)
        .then(handleAuthRedirect)
        .then(data => {
          if (data.error) throw new Error(data.error);

          data.totalPages = Math.max(1, Math.ceil(data.total / data.perPage));

          return data;
        });
  },

  /**
   * [AJAX] GET /api/customers/:id/activities
   * ?type=&q=&page=1&per_page=4
   */
  getActivities(customerId, { type='', q='', page=1 } = {}) {
    return fetch(`/api/customers/${customerId}/activities?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}&page=${encodeURIComponent(page)}`)
    .then(handleAuthRedirect)
    .then(data => {
      if (data.error) throw new Error(data.error);

      /* Available types for filter dropdown */
      data.allTypes = [...new Set((data.items || []).map(a => a.type))];
      data.totalPages = Math.max(1, Math.ceil(data.total / data.perPage))

      return data;
    });
  },

  /**
   * [AJAX] GET /api/activities/:id/comments
   */
  getComments(activityId) {
    return fetch(`/api/activities/${activityId}/comments`)
    .then(handleAuthRedirect)
    .then(data => {
      if (data.error) throw new Error(data.error);

      return data;
    });
  },

  /**
   * [AJAX] POST /api/activities/:id/comments/add
   */
  postComment(activityId, body) {
    const params = new URLSearchParams();
    params.set('text', body);

    return fetch(`/api/activities/${activityId}/comments/add`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params,
    })
      .then(handleAuthRedirect)
      .then(data => {
        if (!data.success) throw new Error(data.message || 'Failed to post comment.');
        return { ok: true, comment: data.comment };
      });
  },
};

let custState = { q:'', isActive:'', sort:'name', dir:'ASC', page:1 };
let openId    = null;

/* Per-customer activity filter+page state */
const actState = {};
function getActState(cid) {
  if (!actState[cid]) actState[cid] = { type:'', q:'', page:1 };
  return actState[cid];
}

/* ═══════════════════════════════════════════════════════════════════
     HELPERS
  ═══════════════════════════════════════════════════════════════════ */
function esc(s) {
  return String(s)
  .replace(/&/g,'&amp;').replace(/</g,'&lt;')
  .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function nowStr() {
  const d = new Date();
  return d.getFullYear() + '-' +
      String(d.getMonth()+1).padStart(2,'0') + '-' +
      String(d.getDate()).padStart(2,'0') + ' ' +
      String(d.getHours()).padStart(2,'0') + ':' +
      String(d.getMinutes()).padStart(2,'0');
}
function chevronSVG() {
  return `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>`;
}

/**
 * Pagination buttons
 */
function renderPagBtns(current, total, siblings = 1) {
  if (total <= 1) return '';

  // Small page counts: just show them all, no need to compress.
  const MAX_VISIBLE = 7;
  if (total <= MAX_VISIBLE) {
    const all = [];
    for (let i = 1; i <= total; i++) all.push(i);
    return all.map(n => pagBtnHtml(n, current)).join('');
  }

  const pages = new Set([1, total]);
  for (let i = current - siblings; i <= current + siblings; i++) {
    if (i >= 1 && i <= total) pages.add(i);
  }

  const sorted = [...pages].sort((a, b) => a - b);
  let html = '';
  let prev = null;

  for (const n of sorted) {
    if (prev !== null && n - prev > 1) {
      html += `<span class="pag-ellipsis">…</span>`;
    }
    html += pagBtnHtml(n, current);
    prev = n;
  }

  return html;
}

function pagBtnHtml(n, current) {
  return `<button class="pag-btn${n === current ? ' active' : ''}" data-pg="${n}">${n}</button>`;
}

function skeletonRows(n = 3) {
  return Array.from({length:n}, () =>
      `<tr><td colspan="5" style="padding:0">
      <div class="skeleton-row" style="padding:10px 12px">
        <div class="skeleton-line" style="width:${40+Math.random()*30|0}%"></div>
        <div class="skeleton-line" style="width:${25+Math.random()*20|0}%"></div>
      </div>
    </td></tr>`
  ).join('');
}

function showTableSpinner(show) {
  const wrap = document.getElementById('main-wrap');
  const existing = wrap.querySelector('.tbl-loading');
  if (show && !existing) {
    const d = document.createElement('div');
    d.className = 'tbl-loading';
    d.innerHTML = '<div class="spinner"></div>';
    wrap.appendChild(d);
  } else if (!show && existing) {
    existing.remove();
  }
}

async function loadCustomers(resetPage = false) {
  if (resetPage) custState.page = 1;

  showTableSpinner(true);
  document.getElementById('tbody').innerHTML = skeletonRows(10);

  try {
    const res = await API.getCustomers({
      q:       custState.q,
      isActive:  custState.isActive,
      sort:    custState.sort,
      dir:     custState.dir,
      page:    custState.page,
    });

    document.getElementById('result-count').textContent =
        `${res.total} result${res.total !== 1 ? 's' : ''}`;

    renderCustomerRows(res.items);
    renderCustPag(res.page, res.perPage, res.totalPages, res.total);
  } catch(e) {
    document.getElementById('tbody').innerHTML =
        `<tr><td colspan="5" class="act-empty" style="color:var(--err)">Failed to load. ${esc(e.message)}</td></tr>`;
  } finally {
    showTableSpinner(false);
  }
}

function renderCustomerRows(customers) {
  const tbody = document.getElementById('tbody');
  if (!customers.length) {
    tbody.innerHTML = `<tr><td colspan="5" class="act-empty">No customers match your filters.</td></tr>`;
    return;
  }

  tbody.innerHTML = customers.map(c => `
    <tr class="cr${openId===c.id?' open':''}" data-id="${c.id}">
      <td class="td-ch">${chevronSVG()}</td>
      <td class="td-n">${esc(c.name)}</td>
      <td class="td-e">${esc(c.email)}</td>
      <td><span class="badge ${c.isActive===true?'b-act':'b-ina'}">${c.isActive===true?'Active':'Inactive'}</span></td>
      <td style="color:var(--mu);font-size:12px">${esc(c.createdAt)}</td>
    </tr>
    <tr class="dr" data-id="${c.id}">
      <td colspan="5">
        <div class="di${openId===c.id?' open':''}" id="di-${c.id}">
          <div class="dc" id="dc-${c.id}"></div>
        </div>
      </td>
    </tr>`).join('');

  /* Bind row toggle */
  tbody.querySelectorAll('.cr').forEach(row => {
    row.addEventListener('click', () => toggleCustomer(+row.dataset.id, customers));
  });

  /* If a row was open before re-render, restore its content */
  if (openId) {
    const c = customers.find(x => x.id === openId);
    if (c) loadCustomerDetail(c, false);
  }
}

function renderCustPag(current, perPage, total, totalRows) {
  console.log(current, total, totalRows)
  const el = document.getElementById('cust-pag');
  if (total <= 1) { el.innerHTML = ''; return; }

  const from = (current-1)*perPage+1;
  const to   = Math.min(current*perPage, totalRows);

  el.innerHTML = `
    <span>${from}–${to} of ${totalRows}</span>
    <div class="pag-btns" id="cust-pag-btns">
      <button class="pag-btn" id="cpg-prev" data-pg="${current-1}" ${current<=1?'disabled':''}>&#8249;</button>
      ${renderPagBtns(current, total)}
      <button class="pag-btn" id="cpg-next" data-pg="${current+1}" ${current>=total?'disabled':''}>&#8250;</button>
    </div>`;

  el.querySelectorAll('.pag-btn[data-pg]').forEach(btn => {
    btn.addEventListener('click', () => {
      const pg = +btn.dataset.pg;
      if (!pg || pg < 1) return;
      custState.page = pg;
      loadCustomers();
    });
  });
}

function toggleCustomer(id, customers) {
  const wasOpen = openId === id;
  openId = wasOpen ? null : id;

  /* Update chevron + accordion class without full re-render */
  document.querySelectorAll('.cr').forEach(r => {
    const rid = +r.dataset.id;
    r.classList.toggle('open', rid === openId);
  });
  document.querySelectorAll('.dr').forEach(r => {
    const di = document.getElementById('di-' + r.dataset.id);
    if (di) di.classList.toggle('open', +r.dataset.id === openId);
  });

  if (openId) {
    const c = customers.find(x => x.id === openId);
    if (c) loadCustomerDetail(c, true);
  }
}

async function loadCustomerDetail(customer, scrollTo = false) {
  const dc = document.getElementById('dc-' + customer.id);
  if (!dc) return;

  /* Already rendered — just reload activities */
  const alreadyRendered = dc.querySelector('.dg');

  if (!alreadyRendered) {
    dc.innerHTML = `<div class="dg" style="padding-top:12px;margin-bottom:14px">
      <div class="df"><span class="dl">Phone</span><span class="dv">${customer.phone||'—'}</span></div>
      <div class="df"><span class="dl">Since</span><span class="dv">${esc(customer.createdAt)}</span></div>
      <div class="df"><span class="dl">Status</span><span class="dv">
        <span class="badge ${customer.isActive===true?'b-act':'b-ina'}">${customer.isActive===true?'Active':'Inactive'}</span>
      </span></div>
      ${customer.notes?`<div class="df" style="grid-column:1/-1"><span class="dl">Notes</span><span class="dv">${esc(customer.notes)}</span></div>`:''}
    </div>
    <div class="sec-title">Activity history</div>
    <div id="act-section-${customer.id}"></div>`;
  }

  await loadActivities(customer.id);

  if (scrollTo) {
    setTimeout(() => dc.scrollIntoView({ behavior:'smooth', block:'nearest' }), 50);
  }
}

async function loadActivities(customerId) {
  const container = document.getElementById('act-section-' + customerId);
  if (!container) return;

  const s = getActState(customerId);

  /* Show spinner inside the activity section */
  container.innerHTML = `<div class="at-wrap">
    <div style="padding:20px;display:flex;justify-content:center">
      <div class="spinner spinner-sm"></div>
    </div>
  </div>`;

  try {
    const res = await API.getActivities(customerId, {
      type:    s.type,
      q:       s.q,
      page:    s.page
    });

    renderActivities(customerId, res);
  } catch(e) {
    console.error(e);
    container.innerHTML = `<p style="color:var(--err);font-size:12px;padding:8px 0">Failed to load activities.</p>`;
  }
}

function renderActivities(customerId, res) {
  const container = document.getElementById('act-section-' + customerId);
  if (!container) return;

  const s = getActState(customerId);

  const typeOpts = res.allTypes.map(t =>
      `<option value="${t}"${s.type===t?' selected':''}>${esc(t)}</option>`
  ).join('');

  const rows = res.items.length
      ? res.items.map(a => `
      <tr>
        <td class="at-date">${esc(a.date)}</td>
        <td class="at-tc"><span class="atype tp-${a.type}">${esc(a.type)}</span></td>
        <td class="at-dc"><span class="at-det">${esc(a.details)}</span></td>
        ${renderCommentCell(a)}
      </tr>`).join('')
      : `<tr><td colspan="4" class="act-empty">No activities match the filters.</td></tr>`;

  const from = (res.page-1)*res.perPage+1;
  const to   = Math.min(res.page*res.perPage, res.total);
  const pagInfo = res.total ? `${from}–${to} of ${res.total}` : '0 results';

  container.innerHTML = `<div class="at-wrap">
    <div class="act-filters">
      <input type="search" placeholder="Filter details…" value="${esc(s.q)}"
        data-cid="${customerId}" data-key="q"/>
      <select data-cid="${customerId}" data-key="type">
        <option value=""${!s.type?' selected':''}>All types</option>
        ${typeOpts}
      </select>
    </div>
    <table class="at">
      <thead><tr>
        <th>Date</th><th>Type</th><th>Details</th><th>Comments</th>
      </tr></thead>
      <tbody>${rows}</tbody>
    </table>
    <div class="pag">
      <span>${pagInfo}</span>
      <div class="pag-btns">
        <button class="pag-btn" data-cid="${customerId}" data-pg="${res.page-1}" ${res.page<=1?'disabled':''}>&#8249;</button>
        ${renderPagBtns(res.page, res.totalPages).replace(/data-pg/g, `data-cid="${customerId}" data-pg`)}
        <button class="pag-btn" data-cid="${customerId}" data-pg="${res.page+1}" ${res.page>=res.totalPages?'disabled':''}>&#8250;</button>
      </div>
    </div>
  </div>`;

  /* Bind activity filters */
  container.querySelectorAll('.act-filters input[data-key], .act-filters select[data-key]').forEach(el => {
    const evt = el.tagName === 'SELECT' ? 'change' : 'input';
    el.addEventListener(evt, () => {
      const cid = +el.dataset.cid;
      const st  = getActState(cid);
      st[el.dataset.key] = el.value;
      st.page = 1;
      loadActivities(cid);
    });
  });

  const textInput = container.querySelector('.act-filters input[data-key="q"]');
  textInput.focus();
  const endOfText = textInput.value.length;
  textInput.setSelectionRange(endOfText, endOfText);


  /* Bind activity pagination */
  container.querySelectorAll('.pag-btn[data-cid][data-pg]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      const cid = +btn.dataset.cid;
      const pg  = +btn.dataset.pg;
      if (pg < 1) return;
      getActState(cid).page = pg;
      loadActivities(cid);
    });
  });

  /* Bind comment buttons */
  container.querySelectorAll('.cc-btn[data-act-id]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      openModal(+btn.dataset.actId, btn.dataset.actDetails);
    });
  });
}

function renderCommentCell(activity) {
  const last  = activity.lastComment;
  const count = activity.commentsCount || 0;

  if (!last) {
    return `<td class="cc-td">
      <div class="cc-none">No comments</div>
      <div style="padding:0 9px 7px">
        <button class="cc-btn" data-act-id="${activity.id}" data-act-details="${esc(activity.details)}">+ Add comment</button>
      </div>
    </td>`;
  }
  return `<td class="cc-td">
    <div class="cc-inner">
      <div class="cc-preview">${esc(last.body)}</div>
      <div class="cc-meta">${esc(last.author)} &middot; ${esc(last.date)}</div>
      <button class="cc-btn" data-act-id="${activity.id}" data-act-details="${esc(activity.details)}">
        &#128172; See all (${count}) / add
      </button>
    </div>
  </td>`;
}

/* ═══════════════════════════════════════════════════════════════════
     COMMENTS MODAL  (comments loaded when modal opens)
  ═══════════════════════════════════════════════════════════════════ */
function openModal(activityId, activityDetails) {
  closeModal();

  const bd = document.createElement('div');
  bd.className = 'modal-backdrop';
  bd.id = 'modal-bd';
  bd.addEventListener('click', e => { if (e.target === bd) closeModal(); });

  bd.innerHTML = `<div class="modal" role="dialog" aria-modal="true" aria-label="Comments">
    <div class="modal-head">
      <div>
        <div class="modal-title">Comments</div>
        <div class="modal-sub">${esc(activityDetails || '')}</div>
      </div>
      <button class="modal-close" id="mcls" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body" id="modal-body">
      <div style="display:flex;justify-content:center;padding:20px">
        <div class="spinner spinner-sm"></div>
      </div>
    </div>
    <div class="modal-hint" id="modal-hint"></div>
    <div class="modal-foot">
      <textarea class="modal-ta" id="modal-ta"
        placeholder="Write a comment… (Enter to post, Shift+Enter for new line)"
        rows="2"></textarea>
      <button class="modal-post" id="modal-post">&#9658; Post</button>
    </div>
  </div>`;

  document.body.appendChild(bd);

  document.getElementById('mcls').addEventListener('click', closeModal);
  document.getElementById('modal-post').addEventListener('click', () => submitComment(activityId));
  document.getElementById('modal-ta').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitComment(activityId); }
  });

  /* Load comments via API */
  loadModalComments(activityId);
  setTimeout(() => document.getElementById('modal-ta')?.focus(), 100);
}

async function loadModalComments(activityId) {
  const mbody = document.getElementById('modal-body');
  if (!mbody) return;

  try {
    const res = await API.getComments(activityId);
    renderModalComments(res.items);
  } catch(e) {
    if (mbody) mbody.innerHTML = `<div class="mc-none" style="color:var(--err)">Failed to load comments.</div>`;
  }
}

function renderModalComments(comments) {
  const mbody = document.getElementById('modal-body');
  if (!mbody) return;

  mbody.innerHTML = comments.length
      ? comments.map(c => commentHTML(c)).join('')
      : '<div class="mc-none">No comments yet. Be the first.</div>';
}

function commentHTML(c) {
  return `<div class="mc-item" id="mc-${c.id}">
    <div class="mc-meta">
      <span class="mc-author">${esc(c.author)}</span>
      <span class="mc-date">${esc(c.date)}</span>
    </div>
    <div class="mc-body">${esc(c.body)}</div>
  </div>`;
}

async function submitComment(activityId) {
  const ta      = document.getElementById('modal-ta');
  const hint    = document.getElementById('modal-hint');
  const postBtn = document.getElementById('modal-post');
  const body    = ta?.value.trim();

  if (!body) { if (hint) hint.textContent = 'Comment cannot be empty.'; return; }

  postBtn.disabled = true;
  if (hint) hint.textContent = '';

  try {
    const res = await API.postComment(activityId, body);

    /* Inject into modal */
    const mbody = document.getElementById('modal-body');
    if (mbody) {
      mbody.querySelector('.mc-none')?.remove();
      const div = document.createElement('div');
      div.innerHTML = commentHTML(res.comment);
      const node = div.firstElementChild;
      node.style.cssText = 'opacity:0;transition:opacity 180ms';
      mbody.appendChild(node);
      requestAnimationFrame(() => { node.style.opacity = '1'; });
      node.scrollIntoView({ behavior:'smooth', block:'nearest' });
    }

    if (ta) ta.value = '';

    /* Refresh the activity row so preview cell updates */
    refreshActivityCommentCell(activityId, res.comment);

  } catch(e) {
    if (hint) hint.textContent = e.message || 'Failed to post comment.';
  } finally {
    if (postBtn) postBtn.disabled = false;
  }
}

function refreshActivityCommentCell(activityId, latestComment) {
  /* Find the cc-btn for this activity and update its parent cell */
  const btn = document.querySelector(`.cc-btn[data-act-id="${activityId}"]`);
  if (!btn) return;

  const td = btn.closest('td.cc-td');
  if (!td) return;

  const count = 0;

  td.innerHTML = `<div class="cc-inner">
    <div class="cc-preview">${esc(latestComment.body)}</div>
    <div class="cc-meta">${esc(latestComment.author)} &middot; ${esc(latestComment.date)}</div>
    <button class="cc-btn" data-act-id="${activityId}" data-act-details="${td.closest('tr')?.querySelector('.at-det')?.textContent||''}">
      &#128172; See all (${count}) / add
    </button>
  </div>`;

  /* Re-bind the new button */
  td.querySelector('.cc-btn').addEventListener('click', e => {
    e.stopPropagation();
    const b = e.currentTarget;
    openModal(+b.dataset.actId, b.dataset.actDetails);
  });
}

function closeModal() {
  document.getElementById('modal-bd')?.remove();
}

/* ═══════════════════════════════════════════════════════════════════
     WIRING — search, filter, sort controls
  ═══════════════════════════════════════════════════════════════════ */
let searchTimer = null;

document.getElementById('search').addEventListener('input', e => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    custState.q = e.target.value;
    openId = null;
    loadCustomers(true);
  }, 300);                          /* debounce 300 ms */
});

document.getElementById('sf').addEventListener('change', e => {
  custState.isActive = e.target.value;
  openId = null;
  loadCustomers(true);
});

document.querySelectorAll('thead th[data-col]').forEach(th => {
  th.addEventListener('click', () => {
    const col = th.dataset.col;
    if (custState.sort === col) {
      custState.dir = custState.dir === 'ASC' ? 'DESC' : 'ASC';
    } else {
      custState.sort = col;
      custState.dir  = 'ASC';
    }
    loadCustomers(true);
  });
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModal();
});

/* ── Boot ── */
loadCustomers();