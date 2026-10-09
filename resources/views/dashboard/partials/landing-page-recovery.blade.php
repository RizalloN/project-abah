<script>
(() => {
  // Persist a small retry budget across navigation; never invalidate snapshot caches.
  const recoverLandingPage = (pending, browser, page) => {
    const url = new URL(browser.location.href);
    const key = 'landing-page-recovery:' + url.pathname + ':'
      + (url.searchParams.get('periode') || '') + ':' + (url.searchParams.get('cabang') || '');
    const delays = [30000, 90000, 180000];
    let attempts;
    try {
      if (!pending) { browser.sessionStorage.removeItem(key); return; }
      attempts = Number(browser.sessionStorage.getItem(key) || 0);
      if (!Number.isInteger(attempts) || attempts < 0 || attempts >= delays.length) return;
    } catch (_) { return; }
    const reread = () => {
      // Do not close a nominative dialog or interrupt a filter being edited.
      const editing = ['INPUT', 'SELECT', 'TEXTAREA'].includes(page.activeElement?.tagName);
      const dialog = Array.from(page.querySelectorAll('.modal.show, [role="dialog"]:not([hidden]), dialog[open]'))
        .some(element => element.getClientRects().length > 0);
      if (page.visibilityState !== 'visible' || editing || dialog) {
        browser.setTimeout(reread, 15000);
        return;
      }
      try { browser.sessionStorage.setItem(key, String(attempts + 1)); }
      catch (_) { return; }
      if (url.searchParams.has('refresh')) {
        url.searchParams.delete('refresh');
        browser.location.replace(url.toString());
      } else {
        browser.location.reload();
      }
    };
    browser.setTimeout(reread, delays[attempts]);
  };
  recoverLandingPage(@json((bool) data_get($dashboard ?? [], 'meta.refresh_pending', false)), window, document);
})();
</script>
