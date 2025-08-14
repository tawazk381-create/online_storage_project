// File: assets/js/app.js
// Called from markup: onclick="share(123)"
(function () {
  'use strict';

  window.share = function share(fileId) {
    if (!fileId) return;

    fetch(`includes/share_link.php?id=${fileId}`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(res => {
        if (!res.ok) {
          return res.text().then(t => { throw new Error(t || 'Server error'); });
        }
        return res.json();
      })
      .then(json => {
        if (!json || json.error) {
          const msg = json && json.error ? json.error : 'Unknown error creating share link.';
          alert('Error: ' + msg);
          return;
        }

        const url = json.url;
        if (!url) {
          alert('Server did not return a share url.');
          return;
        }

        // Open in new tab and try to copy
        try {
          window.open(url, '_blank', 'noopener');
        } catch (e) {
          // ignore
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(() => {
            alert('Share link opened in a new tab and copied to clipboard.');
          }).catch(() => {
            fallbackShow(url);
          });
        } else {
          fallbackShow(url);
        }
      })
      .catch(err => {
        console.error('share() error:', err);
        alert('An error occurred while generating the share link. See console for details.');
      });

    function fallbackShow(url) {
      try { prompt('Share this link (copy it):', url); } catch (e) { alert(url); }
    }
  };
})();
