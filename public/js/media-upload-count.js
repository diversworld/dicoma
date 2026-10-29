const mediaFileInput = document.getElementById('media-file');
const mediaSelectedCount = document.getElementById('media-selected-count');

if (mediaFileInput && mediaSelectedCount) {
    mediaFileInput.addEventListener('change', () => {
        mediaSelectedCount.value = String(mediaFileInput.files.length);
    });
}
