import { generateKeyPairSync } from 'node:crypto';
import { writeFileSync } from 'node:fs';

function b64url(buf) {
    return Buffer.from(buf)
        .toString('base64')
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/g, '');
}

const outPath = process.argv[2];
const subject = process.argv[3] || 'mailto:admin@crewdev.ru';

if (!outPath) {
    console.error('Usage: node generate-vapid.mjs <out.json> [subject]');
    process.exit(1);
}

const { publicKey, privateKey } = generateKeyPairSync('ec', { namedCurve: 'prime256v1' });
const pubJwk = publicKey.export({ format: 'jwk' });
const privJwk = privateKey.export({ format: 'jwk' });

const x = Buffer.from(pubJwk.x, 'base64url');
const y = Buffer.from(pubJwk.y, 'base64url');
const d = Buffer.from(privJwk.d, 'base64url');
const uncompressed = Buffer.concat([Buffer.from([0x04]), x, y]);

writeFileSync(outPath, JSON.stringify({
    publicKey: b64url(uncompressed),
    privateKey: b64url(d),
    subject,
}, null, 2));

console.log('ok');
