// Passkey (WebAuthn) ceremonies for sign-in, password confirmation and the Profile passkey list.
// The server (Fortify + laravel/passkeys) sends options with base64url binary fields and expects the same back.

export function toBase64Url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (const byte of bytes) binary += String.fromCharCode(byte);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function fromBase64Url(value) {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64 + '='.repeat((4 - (base64.length % 4)) % 4));
    const bytes = new Uint8Array(binary.length);
    for (let index = 0; index < binary.length; index += 1) bytes[index] = binary.charCodeAt(index);
    return bytes.buffer;
}

const descriptors = list => (list || []).map(item => ({ ...item, id: fromBase64Url(item.id) }));

export function creationOptions(options) {
    return { ...options, challenge: fromBase64Url(options.challenge), user: { ...options.user, id: fromBase64Url(options.user.id) }, excludeCredentials: descriptors(options.excludeCredentials) };
}

export function requestOptions(options) {
    return { ...options, challenge: fromBase64Url(options.challenge), allowCredentials: descriptors(options.allowCredentials) };
}

export function serializeCredential(credential) {
    const { response } = credential;
    const encoded = { clientDataJSON: toBase64Url(response.clientDataJSON) };
    if (response.attestationObject) {
        encoded.attestationObject = toBase64Url(response.attestationObject);
        if (typeof response.getTransports === 'function') encoded.transports = response.getTransports();
    } else {
        encoded.authenticatorData = toBase64Url(response.authenticatorData);
        encoded.signature = toBase64Url(response.signature);
        if (response.userHandle) encoded.userHandle = toBase64Url(response.userHandle);
    }
    return { id: credential.id, rawId: toBase64Url(credential.rawId), type: credential.type, response: encoded };
}

export function passkeysSupported(win = window) {
    return Boolean(win.PublicKeyCredential && win.navigator?.credentials?.create && win.isSecureContext);
}

async function request(url, method = 'GET', body = undefined) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    });
    let data = {};
    try { data = await response.json(); } catch { /* Empty or HTML body. */ }
    return { response, data };
}

function serverMessage(data, fallback) {
    const errors = data?.errors ? Object.values(data.errors).flat() : [];
    return errors[0] || data?.message || fallback;
}

function setup(root, t) {
    const error = root.querySelector('[data-passkey-error]');
    const buttons = root.querySelectorAll('button');
    const show = message => { if (error) { error.textContent = message; error.hidden = !message; } };
    const busy = state => buttons.forEach(button => { button.disabled = state; });

    async function run(task) {
        show('');
        busy(true);
        try {
            await task();
        } catch (failure) {
            // NotAllowedError covers a cancelled or timed-out prompt; say so plainly instead of a browser message.
            show(failure?.name === 'NotAllowedError' ? t('The passkey prompt was cancelled or timed out.') : (failure?.userMessage || t('The passkey could not be used. Try again.')));
            busy(false);
        }
    }
    const fail = message => Object.assign(new Error(message), { userMessage: message });

    async function verify() {
        const options = await request(root.dataset.optionsUrl);
        if (!options.response.ok) throw fail(serverMessage(options.data, t('The passkey could not be used. Try again.')));
        const credential = await navigator.credentials.get({ publicKey: requestOptions(options.data.options) });
        const remember = root.querySelector('[data-passkey-remember]') || document.querySelector('input[name="remember"]');
        const result = await request(root.dataset.submitUrl, 'POST', { credential: serializeCredential(credential), remember: Boolean(remember?.checked) });
        if (!result.response.ok) throw fail(serverMessage(result.data, t('The passkey could not be used. Try again.')));
        window.location.assign(root.dataset.redirect || result.data.redirect || '/');
    }

    async function register(name) {
        const options = await request(root.dataset.optionsUrl);
        if (options.response.status === 423) { window.location.assign(root.dataset.confirmUrl); return; }
        if (!options.response.ok) throw fail(serverMessage(options.data, t('The passkey could not be added. Try again.')));
        const credential = await navigator.credentials.create({ publicKey: creationOptions(options.data.options) });
        const result = await request(root.dataset.submitUrl, 'POST', { name, credential: serializeCredential(credential) });
        if (result.response.status === 423) { window.location.assign(root.dataset.confirmUrl); return; }
        if (!result.response.ok) throw fail(serverMessage(result.data, t('The passkey could not be added. Try again.')));
        // The server flashes a status for the next page load, so reload to show it with the updated list.
        window.location.reload();
    }

    if (root.matches('form')) {
        root.addEventListener('submit', event => {
            event.preventDefault();
            const name = root.querySelector('input[name="name"]')?.value.trim() || '';
            run(() => register(name));
        });
    } else {
        root.querySelector('[data-passkey-start]')?.addEventListener('click', () => run(verify));
    }
}

export function setupPasskeys(t) {
    const roots = document.querySelectorAll('[data-passkey]');
    if (!roots.length) return;
    const supported = passkeysSupported();
    document.querySelectorAll('[data-passkey-unsupported]').forEach(element => { element.hidden = supported; });
    if (!supported) return;
    roots.forEach(root => {
        root.hidden = false;
        setup(root, t);
    });
}
