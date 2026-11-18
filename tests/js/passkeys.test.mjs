import test from 'node:test';
import assert from 'node:assert/strict';
import { creationOptions, fromBase64Url, passkeysSupported, requestOptions, serializeCredential, toBase64Url } from '../../resources/js/passkeys.js';

const bytes = values => new Uint8Array(values).buffer;

test('base64url round-trips binary values without padding', () => {
    const value = bytes([0, 251, 255, 62, 63, 1]);
    const encoded = toBase64Url(value);
    assert.equal(encoded, 'APv_Pj8B');
    assert.deepEqual(new Uint8Array(fromBase64Url(encoded)), new Uint8Array(value));
});

test('server options become binary WebAuthn options', () => {
    const created = creationOptions({ challenge: 'AQID', rp: { id: 'example.test', name: 'example.test' }, user: { id: 'BAU', name: 'a@example.test', displayName: 'A' }, excludeCredentials: [{ type: 'public-key', id: 'Bgc' }] });
    assert.deepEqual(new Uint8Array(created.challenge), new Uint8Array([1, 2, 3]));
    assert.deepEqual(new Uint8Array(created.user.id), new Uint8Array([4, 5]));
    assert.deepEqual(new Uint8Array(created.excludeCredentials[0].id), new Uint8Array([6, 7]));
    assert.equal(created.rp.id, 'example.test');

    const requested = requestOptions({ challenge: 'AQID', rpId: 'example.test', userVerification: 'required' });
    assert.deepEqual(requested.allowCredentials, []);
    assert.equal(requested.userVerification, 'required');
});

test('registration and sign-in credentials serialize to the fields the server reads', () => {
    const registration = serializeCredential({ id: 'Bgc', rawId: bytes([6, 7]), type: 'public-key', response: { clientDataJSON: bytes([1]), attestationObject: bytes([2]), getTransports: () => ['internal'] } });
    assert.deepEqual(registration, { id: 'Bgc', rawId: 'Bgc', type: 'public-key', response: { clientDataJSON: 'AQ', attestationObject: 'Ag', transports: ['internal'] } });

    const assertion = serializeCredential({ id: 'Bgc', rawId: bytes([6, 7]), type: 'public-key', response: { clientDataJSON: bytes([1]), authenticatorData: bytes([3]), signature: bytes([4]), userHandle: bytes([5]) } });
    assert.deepEqual(assertion.response, { clientDataJSON: 'AQ', authenticatorData: 'Aw', signature: 'BA', userHandle: 'BQ' });
    const withoutHandle = serializeCredential({ id: 'Bgc', rawId: bytes([6, 7]), type: 'public-key', response: { clientDataJSON: bytes([1]), authenticatorData: bytes([3]), signature: bytes([4]), userHandle: null } });
    assert.equal('userHandle' in withoutHandle.response, false);
});

test('passkeys need WebAuthn in a secure context', () => {
    const credentials = { create() {}, get() {} };
    assert.equal(passkeysSupported({ PublicKeyCredential() {}, navigator: { credentials }, isSecureContext: true }), true);
    assert.equal(passkeysSupported({ PublicKeyCredential() {}, navigator: { credentials }, isSecureContext: false }), false);
    assert.equal(passkeysSupported({ navigator: { credentials }, isSecureContext: true }), false);
});
