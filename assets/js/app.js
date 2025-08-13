function share(fileId) {
  fetch(`includes/share_link.php?id=${fileId}`)
    .then(res => res.json())
    .then(json => {
      prompt('Share this link:', json.url);
    })
    .catch(console.error);
}
