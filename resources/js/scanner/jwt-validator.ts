import type { ScannerKeys } from './types';
import * as ed from '@noble/ed25519';

export interface JwtResult {
    valid: boolean;
    volunteerId: number | null;
    error?: string;
}

function base64UrlDecode(str: string): Uint8Array {
    const padded = str.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

function parseHeader(token: string): Record<string, unknown> | null {
    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            return null;
        }
        const headerBytes = base64UrlDecode(parts[0]);
        return JSON.parse(new TextDecoder().decode(headerBytes));
    } catch {
        return null;
    }
}

function parsePayload(token: string): Record<string, unknown> | null {
    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            return null;
        }
        const payloadBytes = base64UrlDecode(parts[1]);
        return JSON.parse(new TextDecoder().decode(payloadBytes));
    } catch {
        return null;
    }
}

async function verifyEd25519(token: string, publicKeyB64: string): Promise<boolean> {
    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            return false;
        }

        const signingInput = new TextEncoder().encode(`${parts[0]}.${parts[1]}`);
        const signature = base64UrlDecode(parts[2]);
        const publicKey = Uint8Array.from(atob(publicKeyB64), (c) => c.charCodeAt(0));

        return await ed.verifyAsync(signature, signingInput, publicKey);
    } catch {
        return false;
    }
}

export async function validateJwt(token: string, keys: ScannerKeys): Promise<JwtResult> {
    const header = parseHeader(token);
    if (!header) {
        return { valid: false, volunteerId: null, error: 'Malformed token' };
    }

    const payload = parsePayload(token);
    if (!payload) {
        return { valid: false, volunteerId: null, error: 'Malformed token' };
    }

    // Reject alg: none
    if (header.alg === 'none' || !header.alg) {
        return { valid: false, volunteerId: null, error: 'Unsupported algorithm' };
    }

    // EdDSA verification
    if (header.alg === 'EdDSA') {
        // Try current key first
        if (await verifyEd25519(token, keys.current)) {
            return {
                valid: true,
                volunteerId: (payload.volunteer_id as number) ?? null,
            };
        }

        // Fall back to previous key
        if (await verifyEd25519(token, keys.previous)) {
            return {
                valid: true,
                volunteerId: (payload.volunteer_id as number) ?? null,
            };
        }

        return { valid: false, volunteerId: null, error: 'Invalid signature' };
    }

    return { valid: false, volunteerId: null, error: 'Unsupported algorithm' };
}
