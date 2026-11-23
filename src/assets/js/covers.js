/* Cover lookups run sequentially, separately from rendering the book list. */
(() => {
  const script = document.currentScript;
  const token = script.dataset.coverToken;
  const placeholder = '/assets/brand/no-cover.png.jpg';
  const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  async function post(action, data = {}) {
    const response = await fetch('/' + action, {
      method: 'POST',
      body: new URLSearchParams({ token, ...data }),
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(35000),
    });
    const result = await response.json();
    if (!response.ok || result.error) throw new Error(result.error || 'Cover request failed.');
    return result;
  }
  function display(image, url) {
    image.onerror = () => {
      image.onerror = null;
      image.src = placeholder;
      if (url.startsWith('https://covers.openlibrary.org/')) {
        post('coverFailed', { id: image.dataset.bookId }).catch(() => {});
      }
    };
    image.src = url;
  }
  const queue = [];
  let running = false;
  let plexOffline = false;
  async function drain() {
    if (running) return;
    running = true;
    try {
      while (queue.length) {
        const image = queue.shift();
        // Paging away should not trigger lookups for rows no longer displayed.
        if (!image.isConnected) { delete image.dataset.coverQueued; continue; }
        let lookedUp = false;
        try {
          const result = await post('coverResolve', { id: image.dataset.coverId, plexUnavailable: plexOffline ? '1' : '0' });
          plexOffline = plexOffline || result.plexUnavailable;
          display(image, result.url);
          lookedUp = result.lookedUp;
          delete image.dataset.coverId;
        } catch (_) {
          // Leave it eligible for another attempt on the next table draw.
        }
        delete image.dataset.coverQueued;
        await pause(lookedUp ? 1200 : 50);
      }
    } finally { running = false; }
  }
  window.bookshelfCovers = {
    load(rows) {
      for (const row of rows) {
        row.querySelectorAll('img.book-cover-lazy').forEach((image) => {
          if (image.dataset.src) {
            display(image, image.dataset.src);
            delete image.dataset.src;
          } else if (image.dataset.coverId && !image.dataset.coverQueued) {
            image.dataset.coverQueued = '1';
            queue.push(image);
          }
        });
      }
      drain();
    },
  };

  document.addEventListener('DOMContentLoaded', () => {
    const coverPanel = document.getElementById('book-cover-panel');
    if (coverPanel) window.bookshelfCovers.load([coverPanel]);
    const start = document.getElementById('cover-start');
    if (!start) return;
    const stop = document.getElementById('cover-stop');
    const status = document.getElementById('cover-progress');
    const bar = document.getElementById('cover-progress-bar');
    const log = document.getElementById('cover-results');
    const storageKey = 'bookshelf-cover-catchup';
    let active = false;
    let state = { cursor: 0, processed: 0, counts: {}, complete: false };
    try { state = JSON.parse(sessionStorage.getItem(storageKey)) || state; } catch (_) {}
    const save = () => { try { sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch (_) {} };
    function summary() {
      return Object.entries(state.counts).map(([key, value]) => value + ' ' + ({ ready: 'available', deferred: 'deferred', missing: 'not found', review: 'need cover details' }[key] || key)).join(', ');
    }
    if (state.cursor && !state.complete) {
      start.textContent = 'Resume catch-up';
      status.textContent = 'Paused after ' + state.processed + ' books. ' + summary();
    }
    stop.addEventListener('click', () => {
      active = false;
      stop.disabled = true;
      status.textContent = 'Pausing after the current book…';
    });
    start.addEventListener('click', async () => {
      if (active) return;
      if (state.complete) state = { cursor: 0, processed: 0, counts: {}, complete: false };
      active = true;
      start.disabled = true;
      stop.disabled = false;
      bar.hidden = false;
      let plexUnavailable = false;
      try {
        while (active) {
          status.textContent = 'Checking covers… ' + state.processed + ' processed. ' + summary() + ' An Open Library lookup can take up to 20 seconds.';
          const result = await post('coverBatch', { cursor: state.cursor, plexUnavailable: plexUnavailable ? '1' : '0' });
          if (result.done) {
            state.complete = true;
            save();
            status.textContent = 'Catch-up complete. ' + summary()
              + (state.counts.deferred ? ' Deferred books will be retried on the next run.' : '');
            start.textContent = 'Check again';
            break;
          }
          state.cursor = result.cursor;
          state.processed++;
          state.counts[result.status] = (state.counts[result.status] || 0) + 1;
          plexUnavailable = plexUnavailable || result.plexUnavailable;
          save();
          const percent = Math.round(100 * state.processed / (state.processed + result.remaining));
          bar.value = percent;
          const item = document.createElement('li');
          const link = document.createElement('a');
          link.href = '/viewDetails/' + result.cursor;
          link.textContent = result.title;
          item.appendChild(link);
          item.appendChild(document.createTextNode(' — ' + (result.message || { ready: 'cover available', deferred: 'Cover lookup deferred; retry catch-up for details.', missing: 'no cover found', review: 'no confident cover match—add an ISBN, Open Library link or upload a cover' }[result.status] || result.status)));
          if (result.diagnostics && result.diagnostics.length) {
            const details = document.createElement('details');
            const heading = document.createElement('summary');
            heading.textContent = result.cached ? 'Previous lookup diagnostics (no new request)' : 'Lookup diagnostics';
            const diagnosticText = document.createElement('pre');
            diagnosticText.className = 'small mt-2 text-wrap';
            diagnosticText.textContent = JSON.stringify(result.diagnostics, null, 2);
            details.append(heading, diagnosticText);
            item.appendChild(details);
          }
          log.prepend(item);
          while (log.children.length > 20) log.lastChild.remove();
          // Metadata lookups are deliberately paced, even on a fast connection.
          if (active) await pause(result.lookedUp ? 1200 : 50);
        }
        if (!state.complete) {
          status.textContent = 'Paused. ' + state.processed + ' processed. ' + summary();
          start.textContent = 'Resume catch-up';
        }
      } catch (error) {
        status.textContent = error.message + ' Your progress is saved; resume to retry.';
        start.textContent = 'Resume catch-up';
      } finally {
        active = false;
        start.disabled = false;
        stop.disabled = true;
      }
    });
  });
})();
