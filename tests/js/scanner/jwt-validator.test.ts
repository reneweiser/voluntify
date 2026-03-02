import { describe, it, expect } from 'vitest';
import { validateJwt } from '@/scanner/jwt-validator';
import type { ScannerKeys } from '@/scanner/types';

/**
 * Helper: create a signed JWT token using Web Crypto.
 */
async function createTestJwt(
    payload: Record<string, unknown>,
    secret: string
): Promise<string> {
    const header = { alg: 'HS256', typ: 'JWT' };
    const encoder = new TextEncoder();

    const headerB64 = base64UrlEncode(JSON.stringify(header));
    const payloadB64 = base64UrlEncode(JSON.stringify(payload));
    const signingInput = `${headerB64}.${payloadB64}`;

    const key = await crypto.subtle.importKey(
        'raw',
        encoder.encode(secret),
        { name: 'HMAC', hash: 'SHA-256' },
        false,
        ['sign']
    );

    const signature = await crypto.subtle.sign('HMAC', key, encoder.encode(signingInput));
    const sigB64 = base64UrlEncode(String.fromCharCode(...new Uint8Array(signature)));

    return `${signingInput}.${sigB64}`;
}

function base64UrlEncode(str: string): string {
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

describe('jwt-validator', () => {
    const currentKey = 'test-current-key';
    const previousKey = 'test-previous-key';
    const keys: ScannerKeys = { current: currentKey, previous: previousKey };

    it('validates token with correct current key', async () => {
        const token = await createTestJwt(
            { sub: 42, volunteer_id: 42, iat: Math.floor(Date.now() / 1000), exp: Math.floor(Date.now() / 1000) + 3600 },
            currentKey
        );

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(true);
        expect(result.volunteerId).toBe(42);
    });

    it('rejects token with wrong key', async () => {
        const token = await createTestJwt(
            { sub: 42, volunteer_id: 42, iat: Math.floor(Date.now() / 1000), exp: Math.floor(Date.now() / 1000) + 3600 },
            'wrong-key'
        );

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(false);
    });

    it('tries previous key on current key failure', async () => {
        const token = await createTestJwt(
            { sub: 99, volunteer_id: 99, iat: Math.floor(Date.now() / 1000), exp: Math.floor(Date.now() / 1000) + 3600 },
            previousKey
        );

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(true);
        expect(result.volunteerId).toBe(99);
    });

    it('rejects expired tokens', async () => {
        const token = await createTestJwt(
            { sub: 42, volunteer_id: 42, iat: Math.floor(Date.now() / 1000) - 7200, exp: Math.floor(Date.now() / 1000) - 3600 },
            currentKey
        );

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(false);
    });

    it('rejects malformed tokens', async () => {
        const result = await validateJwt('not-a-jwt', keys);
        expect(result.valid).toBe(false);
    });

    it('extracts volunteer_id from payload', async () => {
        const token = await createTestJwt(
            { sub: 7, volunteer_id: 7, iat: Math.floor(Date.now() / 1000), exp: Math.floor(Date.now() / 1000) + 3600 },
            currentKey
        );

        const result = await validateJwt(token, keys);
        expect(result.valid).toBe(true);
        expect(result.volunteerId).toBe(7);
    });
});
