import { copyFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const copies = [
    ['bootstrap/dist/css/bootstrap.min.css', 'bootstrap/bootstrap.min.css'],
    ['bootstrap/dist/js/bootstrap.bundle.min.js', 'bootstrap/bootstrap.bundle.min.js'],
    ['bootstrap/LICENSE', 'bootstrap/LICENSE'],
    ['sweetalert2/dist/sweetalert2.all.min.js', 'sweetalert2/sweetalert2.all.min.js'],
    ['sweetalert2/LICENSE', 'sweetalert2/LICENSE'],
];
for (const [source, target] of copies) {
    const destination = new URL(`../../public_html/vendor/${target}`, import.meta.url);
    await mkdir(new URL('.', destination), { recursive: true });
    await copyFile(new URL(`../node_modules/${source}`, import.meta.url), destination);
    process.stdout.write(`Published ${fileURLToPath(destination)}\n`);
}
