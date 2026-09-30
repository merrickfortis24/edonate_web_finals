import { readFile, readdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { feature } from 'topojson-client';

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourceDirectory = path.resolve(process.argv[2] || path.join(appRoot, 'resources/data/source/barangays/lowres'));
const outputFile = path.resolve(process.argv[3] || path.join(appRoot, 'resources/data/philippines_barangay_centers_2019.json'));

function polygonCentroid(geometry) {
    if (!geometry) return null;

    const polygons = geometry.type === 'Polygon'
        ? [geometry.coordinates]
        : geometry.type === 'MultiPolygon'
            ? geometry.coordinates
            : [];
    let totalArea = 0;
    let weightedLongitude = 0;
    let weightedLatitude = 0;
    const bounds = { minLongitude: Infinity, minLatitude: Infinity, maxLongitude: -Infinity, maxLatitude: -Infinity };

    for (const polygon of polygons) {
        for (const ring of polygon) {
            let twiceArea = 0;
            let longitudeMoment = 0;
            let latitudeMoment = 0;

            for (let index = 0; index < ring.length; index += 1) {
                const [longitude, latitude] = ring[index];
                const [nextLongitude, nextLatitude] = ring[(index + 1) % ring.length];
                if (![longitude, latitude, nextLongitude, nextLatitude].every(Number.isFinite)) continue;

                bounds.minLongitude = Math.min(bounds.minLongitude, longitude);
                bounds.maxLongitude = Math.max(bounds.maxLongitude, longitude);
                bounds.minLatitude = Math.min(bounds.minLatitude, latitude);
                bounds.maxLatitude = Math.max(bounds.maxLatitude, latitude);

                const cross = longitude * nextLatitude - nextLongitude * latitude;
                twiceArea += cross;
                longitudeMoment += (longitude + nextLongitude) * cross;
                latitudeMoment += (latitude + nextLatitude) * cross;
            }

            if (Math.abs(twiceArea) > 1e-12) {
                const signedArea = twiceArea / 2;
                totalArea += signedArea;
                weightedLongitude += (longitudeMoment / (3 * twiceArea)) * signedArea;
                weightedLatitude += (latitudeMoment / (3 * twiceArea)) * signedArea;
            }
        }
    }

    if (Math.abs(totalArea) > 1e-12) {
        return { longitude: weightedLongitude / totalArea, latitude: weightedLatitude / totalArea };
    }

    if (Number.isFinite(bounds.minLongitude) && Number.isFinite(bounds.minLatitude)) {
        return {
            longitude: (bounds.minLongitude + bounds.maxLongitude) / 2,
            latitude: (bounds.minLatitude + bounds.maxLatitude) / 2,
        };
    }

    return null;
}

const fileNames = (await readdir(sourceDirectory)).filter((name) => name.endsWith('.json')).sort();
const centersByCode = new Map();
let invalidGeometryCount = 0;

for (const fileName of fileNames) {
    const topology = JSON.parse(await readFile(path.join(sourceDirectory, fileName), 'utf8'));

    for (const object of Object.values(topology.objects || {})) {
        const collection = feature(topology, object);
        for (const item of collection.features || []) {
            const properties = item.properties || {};
            const code = String(properties.ADM4_PCODE || '').trim().toUpperCase();
            const barangay = String(properties.ADM4_EN || '').trim();
            const city = String(properties.ADM3_EN || '').trim();
            const province = String(properties.ADM2_EN || '').trim();
            const center = polygonCentroid(item.geometry);

            if (!code || !barangay || !city || !province || !center
                || center.latitude < 4 || center.latitude > 22
                || center.longitude < 116 || center.longitude > 127) {
                invalidGeometryCount += 1;
                continue;
            }

            centersByCode.set(code, [
                code,
                barangay,
                city,
                province,
                Number(center.latitude.toFixed(7)),
                Number(center.longitude.toFixed(7)),
            ]);
        }
    }
}

const centers = [...centersByCode.values()].sort((left, right) => left[0].localeCompare(right[0]));
await writeFile(outputFile, JSON.stringify(centers));
process.stdout.write(`Wrote ${centers.length} barangay centers from ${fileNames.length} low-resolution TopoJSON files; skipped ${invalidGeometryCount} incomplete/out-of-range features.\n`);
