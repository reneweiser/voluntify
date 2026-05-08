import { readFile } from 'node:fs/promises';

const fixtureFile = new URL('./.generated/fixtures.json', import.meta.url);

export async function loadFixtures() {
    return JSON.parse(await readFile(fixtureFile, 'utf8'));
}
