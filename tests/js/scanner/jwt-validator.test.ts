import { describe, it, expect } from 'vitest';
import { validateJwt } from '@/scanner/jwt-validator';
import * as ed from '@noble/ed25519';
import type { ScannerKeys } from '@/scanner/types';

function base64UrlEncode(bytes: Uint8Array): string {
    const binary = String.fromCharCode(...bytes);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function base64UrlEncodeStr(str: string): string {
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/**
 * Create an Ed25519-signed JWT using @noble/ed25519.
 */
async function createEdDSAJwt(
    payload: Record<string, unknown>,
    privateKey: Uint8Array,
): Promise<string> {
    const header = { alg: 'EdDSA', typ: 'JWT' };
    const headerB64 = base64UrlEncodeStr(JSON.stringify(header));
    const payloadB64 = base64UrlEncodeStr(JSON.stringify(payload));
    const signingInput = `${headerB64}.${payloadB64}`;

    const signature = await ed.signAsync(
        new TextEncoder().encode(signingInput),
        privateKey,
    );

    return `${signingInput}.${base64UrlEncode(new Uint8Array(signature))}`;
}

describe('Ed25519 verification', () => {
    let privateKey: Uint8Array;
    let publicKeyB64: string;
    let keys: ScannerKeys;

    // Generate a fresh Ed25519 keypair for tests
    beforeAll(async () => {
        privateKey = ed.utils.randomSecretKey();
        const publicKey = await ed.getPublicKeyAsync(privateKey);
        publicKeyB64 = btoa(String.fromCharCode(...publicKey));

        // Use a different key for "previous"
        const prevPriv = ed.utils.randomSecretKey();
        const prevPub = await ed.getPublicKeyAsync(prevPriv);
        const prevPubB64 = btoa(String.fromCharCode(...prevPub));

        keys = { current: publicKeyB64, previous: prevPubB64 };
    });

    it('validates EdDSA-signed token with correct public key', async () => {
        const token = await createEdDSAJwt(
            { volunteer_id: 42, event_id: 1, iat: Math.floor(Date.now() / 1000) },
            privateKey,
        );

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(true);
        expect(result.volunteerId).toBe(42);
    });

    it('rejects EdDSA token with wrong public key', async () => {
        // Sign with a completely different key
        const wrongPriv = ed.utils.randomSecretKey();
        const token = await createEdDSAJwt(
            { volunteer_id: 42, event_id: 1, iat: Math.floor(Date.now() / 1000) },
            wrongPriv,
        );

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(false);
        expect(result.error).toBe('Invalid signature');
    });

    it('falls back to previous key on current key failure', async () => {
        // Create a keypair whose public key is the "previous" key
        const prevPriv = ed.utils.randomSecretKey();
        const prevPub = await ed.getPublicKeyAsync(prevPriv);
        const prevPubB64 = btoa(String.fromCharCode(...prevPub));

        const keysWithPrev: ScannerKeys = { current: publicKeyB64, previous: prevPubB64 };

        // Sign with the previous key
        const token = await createEdDSAJwt(
            { volunteer_id: 99, event_id: 1, iat: Math.floor(Date.now() / 1000) },
            prevPriv,
        );

        const result = await validateJwt(token, keysWithPrev);
        expect(result.valid).toBe(true);
        expect(result.volunteerId).toBe(99);
    });
});

describe('security', () => {
    const keys: ScannerKeys = { current: 'some-key-base64', previous: 'other-key-base64' };

    it('rejects HS256 token as unsupported algorithm', async () => {
        const header = base64UrlEncodeStr(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
        const payload = base64UrlEncodeStr(JSON.stringify({ volunteer_id: 7, event_id: 1, iat: Math.floor(Date.now() / 1000) }));
        const token = `${header}.${payload}.fake-signature`;

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(false);
        expect(result.error).toBe('Unsupported algorithm');
    });

    it('rejects token with alg: none', async () => {
        const header = base64UrlEncodeStr(JSON.stringify({ alg: 'none', typ: 'JWT' }));
        const payload = base64UrlEncodeStr(JSON.stringify({ volunteer_id: 1, event_id: 1, iat: Math.floor(Date.now() / 1000) }));
        const token = `${header}.${payload}.`;

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(false);
        expect(result.error).toBe('Unsupported algorithm');
    });

    it('rejects malformed token', async () => {
        const result = await validateJwt('not-a-jwt', keys);
        expect(result.valid).toBe(false);
        expect(result.error).toBe('Malformed token');
    });
});
