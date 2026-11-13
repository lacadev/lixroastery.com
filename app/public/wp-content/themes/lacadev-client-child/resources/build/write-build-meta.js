/**
 * Ghi metadata phiên bản parent mà child theme vừa build cùng vào dist/build-meta.json.
 */
const fs = require('fs');
const path = require('path');

const parentStylePath = path.resolve(__dirname, '../../../lacadev-client/theme/style.css');
const distDir = path.resolve(__dirname, '../../dist');
const metaPath = path.join(distDir, 'build-meta.json');

let parentVersion = 'unknown';
if (fs.existsSync(parentStylePath)) {
    const css = fs.readFileSync(parentStylePath, 'utf8');
    const match = css.match(/^[ \t/*#]*Version\s*:\s*([^\r\n]+)/im);
    if (match && match[1]) {
        parentVersion = match[1].trim();
    }
}

fs.mkdirSync(distDir, { recursive: true });

const payload = {
    parent_version: parentVersion,
    built_at: new Date().toISOString(),
};

fs.writeFileSync(metaPath, JSON.stringify(payload, null, 2) + '\n', 'utf8');
