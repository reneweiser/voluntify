import type { ScannerKeys } from './types';

export interface JwtResult {
    valid: boolean;
    volunteerId: number | null;
    error?: string;
}

function base64UrlDecode(str: string): string {
    const padded = str.replace(/-/g, '+').replace(/_/g, '/');
    return atob(padded);
}

function base64UrlEncode(str: string): string {
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

async function verifySignature(token: string, secret: string): Promise<boolean> {
    const parts = token.split('.');
    if (parts.length !== 3) {
        return false;
    }

    const signingInput = `${parts[0]}.${parts[1]}`;
    const signature = parts[2];
    const encoder = new TextEncoder();

    const key = await crypto.subtle.importKey(
        'raw',
        encoder.encode(secret),
        { name: 'HMAC', hash: 'SHA-256' },
        false,
        ['verify']
    );

    const signatureBytes = Uint8Array.from(base64UrlDecode(signature), (c) => c.charCodeAt(0));

    return crypto.subtle.verify('HMAC', key, signatureBytes, encoder.encode(signingInput));
}

function parsePayload(token: string): Record<string, unknown> | null {
    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            return null;
        }
        return JSON.parse(base64UrlDecode(parts[1]));
    } catch {
        return null;
    }
}

export async function validateJwt(token: string, keys: ScannerKeys): Promise<JwtResult> {
    const payload = parsePayload(token);
    if (!payload) {
        return { valid: false, volunteerId: null, error: 'Malformed token' };
    }

    // Check expiration
    const now = Math.floor(Date.now() / 1000);
    if (typeof payload.exp === 'number' && payload.exp < now) {
        return { valid: false, volunteerId: null, error: 'Token expired' };
    }

    // Try current key first, then previous key
    const currentValid = await verifySignature(token, keys.current);
    if (currentValid) {
        return {
            valid: true,
            volunteerId: (payload.volunteer_id as number) ?? null,
        };
    }

    const previousValid = await verifySignature(token, keys.previous);
    if (previousValid) {
        return {
            valid: true,
            volunteerId: (payload.volunteer_id as number) ?? null,
        };
    }

    return { valid: false, volunteerId: null, error: 'Invalid signature' };
}
