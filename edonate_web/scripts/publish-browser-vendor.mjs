import { copyFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const copies = [
    ['bootstrap/dist/css/bootstrap.min.css', 'bootstrap/bootstrap.min.css'],
    ['bootstrap/dist/js/bootstrap.bundle.min.js', 'bootstrap/bootstrap.bundle.min.js'],
    ['bootstrap/LICENSE', 'bootstrap/LICENSE'],
    ['sweetalert2/dist/sweetalert2.all.min.js', 'sweetalert2/sweetalert2.all.min.js'],
    ['sweetalert2/LICENSE', 'sweetalert2/LICENSE'],
    ['fullcalendar/index.global.min.js', 'fullcalendar/index.global.min.js'],
    ['fullcalendar/LICENSE.md', 'fullcalendar/LICENSE.md'],
    ['leaflet-control-geocoder/dist/Control.Geocoder.js', 'leaflet-control-geocoder/Control.Geocoder.js'],
    ['leaflet-control-geocoder/dist/Control.Geocoder.js.map', 'leaflet-control-geocoder/Control.Geocoder.js.map'],
    ['leaflet-control-geocoder/dist/Control.Geocoder.css', 'leaflet-control-geocoder/Control.Geocoder.css'],
    ['leaflet-control-geocoder/LICENSE', 'leaflet-control-geocoder/LICENSE'],
];
for (const [source, target] of copies) {
    const destination = new URL(`../../public_html/vendor/${target}`, import.meta.url);
    await mkdir(new URL('.', destination), { recursive: true });
    await copyFile(new URL(`../node_modules/${source}`, import.meta.url), destination);
    process.stdout.write(`Published ${fileURLToPath(destination)}\n`);
}
