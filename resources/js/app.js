window.amescoCopy = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).catch(() => amescoCopyFallback(text));
    } else {
        amescoCopyFallback(text);
    }
};

function amescoCopyFallback(text) {
    const el = document.createElement('textarea');
    el.value = text;
    el.setAttribute('readonly', '');
    el.style.position = 'fixed';
    el.style.left = '-9999px';
    document.body.appendChild(el);
    el.select();
    try {
        document.execCommand('copy');
    } catch (e) {
        // ignore — nothing else we can do without a secure context
    }
    document.body.removeChild(el);
}

window.amescoDownload = function (url, filename) {
    fetch(url)
        .then((response) => response.blob())
        .then((blob) => {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        });
};
