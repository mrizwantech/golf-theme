const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/mobile-app-prompt.js'), 'utf8');
const now = 1800000000000;
const week = 7 * 24 * 60 * 60 * 1000;

function setup(options = {}) {
    const listeners = {};
    const close = {};
    const later = {};
    const download = {};
    const media = { matches: options.mobile !== false, addEventListener: (_, fn) => { media.change = fn; } };
    const storage = new Map();
    const warnings = [];
    if (options.dismissed !== undefined) storage.set('ttn_app_prompt_dismissed_at', String(options.dismissed));
    let splash = options.splash ? { classList: { contains: () => false } } : null;
    let mutation;
    let disconnected = false;
    const prompt = {
        open: false,
        showModal() { this.open = true; },
        close() { this.open = false; },
        querySelectorAll: () => [close, later],
        querySelector: () => download,
        addEventListener: (name, fn) => { listeners[name] = fn; },
    };
    for (const element of [close, later, download]) {
        element.addEventListener = (_, fn) => { element.click = fn; };
    }
    if (options.unsupported) prompt.showModal = undefined;
    vm.runInNewContext(source, {
        navigator: { userAgent: options.ua || 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36' },
        Date: { now: () => now },
        console: { warn: (...args) => warnings.push(args) },
        MutationObserver: function (callback) {
            mutation = callback;
            this.observe = () => {};
            this.disconnect = () => { disconnected = true; };
        },
        document: {
            addEventListener: (_, fn) => fn(),
            getElementById: (id) => id === 'ttn-mobile-app-prompt' ? prompt : splash,
            querySelector: () => options.otherDialog ? {} : null,
            body: { classList: { contains: () => Boolean(options.checkout) } },
        },
        window: {
            matchMedia: (query) => query.includes('max-width') ? media : { matches: Boolean(options.standalone) },
            localStorage: {
                getItem(key) {
                    if (options.storageBlocked) throw new Error('Storage blocked');
                    return storage.get(key) || null;
                },
                setItem(key, value) {
                    if (options.storageBlocked) throw new Error('Storage blocked');
                    storage.set(key, value);
                },
            },
        },
    });
    return {
        prompt, close, later, download, listeners, media, storage, warnings,
        removeSplash() { splash = null; mutation(); },
        disconnected: () => disconnected,
    };
}

assert.equal(setup().prompt.open, true, 'Show on Android phones');
for (const options of [
    { ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile Safari/604.1' },
    { ua: 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) Mobile Safari/604.1' },
    { ua: 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Chrome/130.0 Safari/537.36' },
    { ua: 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0 Safari/537.36' },
    { ua: 'Mozilla/5.0 (Linux; Android 14; wv) Version/4.0 Chrome/130.0 Mobile Safari/537.36' },
    { mobile: false }, { checkout: true }, { standalone: true }, { unsupported: true },
    { otherDialog: true }, { dismissed: now - week + 1 },
]) {
    assert.equal(setup(options).prompt.open, false, JSON.stringify(options));
}
assert.equal(setup({ dismissed: now - week }).prompt.open, true, 'Show at exactly seven days');
assert.equal(setup({ dismissed: now - week - 1 }).prompt.open, true, 'Show after seven days');
assert.equal(setup({ dismissed: 'invalid' }).prompt.open, true, 'Ignore malformed timestamps');
for (const target of ['close', 'later', 'download', 'cancel', 'escape']) {
    const app = setup();
    let prevented = false;
    if (target === 'cancel') app.listeners.cancel({ preventDefault() { prevented = true; } });
    else if (target === 'escape') app.listeners.keydown({ key: 'Escape', preventDefault() { prevented = true; }, stopPropagation() {} });
    else app[target].click();
    assert.equal(app.prompt.open, false, target + ' closes dialog');
    assert.equal(app.storage.get('ttn_app_prompt_dismissed_at'), String(now), target + ' remembers dismissal');
    if (target === 'cancel' || target === 'escape') assert.equal(prevented, true);
}
const waiting = setup({ splash: true });
assert.equal(waiting.prompt.open, false, 'Wait for launch splash');
waiting.removeSplash();
assert.equal(waiting.prompt.open, true, 'Show after splash is removed');
assert.equal(waiting.disconnected(), true, 'Stop observing after splash');
const resized = setup();
resized.media.matches = false;
resized.media.change();
assert.equal(resized.prompt.open, false, 'Hide outside mobile viewport');
assert.equal(resized.storage.size, 0, 'Resizing is not a dismissal');
resized.media.matches = true;
resized.media.change();
assert.equal(resized.prompt.open, true, 'Show when returning to mobile viewport');
const blocked = setup({ storageBlocked: true });
assert.equal(blocked.prompt.open, true);
blocked.later.click();
assert.equal(blocked.prompt.open, false, 'Storage failure must not prevent closing');
assert.equal(blocked.warnings.length, 2, 'Report storage failures explicitly');
console.log('Mobile app prompt JavaScript tests passed.');
