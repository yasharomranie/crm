// Runs the DINOv2 model inside the visitor's phone (onnxruntime-web, WASM,
// self-hosted under /vendor/guide/) and compares what the camera sees with
// the stored photo fingerprints. A picture never leaves the phone: only the
// resulting numbers are compared (or, when enrolling a photo, sent as a
// fingerprint). The same code makes the fingerprints and matches the frames,
// which is what keeps the two comparable.

const BASE = '/vendor/guide/';
const MODEL_URL = BASE + 'dinov2-small-q8.onnx';
const SIZE = 224;
const MEAN = [0.485, 0.456, 0.406];
const STD = [0.229, 0.224, 0.225];

let enginePromise = null;
let workCanvas = null;

/** Loads the runtime and the model once; later calls reuse them. */
export function loadEngine(onProgress = () => {}) {
    if (!enginePromise) {
        enginePromise = (async () => {
            onProgress('runtime');
            const ort = await import(BASE + 'ort.wasm.min.mjs');
            ort.env.wasm.wasmPaths = BASE;
            ort.env.wasm.numThreads = 1;

            onProgress('model');
            const response = await fetch(MODEL_URL);
            if (!response.ok) {
                throw new Error('model-download-failed');
            }
            const bytes = new Uint8Array(await response.arrayBuffer());

            onProgress('session');
            const session = await ort.InferenceSession.create(bytes, { executionProviders: ['wasm'] });

            return { ort, session };
        })().catch((error) => {
            enginePromise = null;
            throw error;
        });
    }

    return enginePromise;
}

function normalise(vector) {
    let sum = 0;
    for (let i = 0; i < vector.length; i++) {
        sum += vector[i] * vector[i];
    }
    const norm = Math.sqrt(sum) || 1;
    const out = new Float32Array(vector.length);
    for (let i = 0; i < vector.length; i++) {
        out[i] = vector[i] / norm;
    }

    return out;
}

/** Centre-crops the source to a square and returns its normalised CHW pixels. */
function preprocess(source, sourceWidth, sourceHeight) {
    if (!workCanvas) {
        workCanvas = document.createElement('canvas');
        workCanvas.width = SIZE;
        workCanvas.height = SIZE;
    }
    const context = workCanvas.getContext('2d', { willReadFrequently: true });
    const side = Math.min(sourceWidth, sourceHeight);
    const sx = (sourceWidth - side) / 2;
    const sy = (sourceHeight - side) / 2;
    context.drawImage(source, sx, sy, side, side, 0, 0, SIZE, SIZE);

    const rgba = context.getImageData(0, 0, SIZE, SIZE).data;
    const plane = SIZE * SIZE;
    const tensor = new Float32Array(3 * plane);
    for (let i = 0; i < plane; i++) {
        for (let channel = 0; channel < 3; channel++) {
            tensor[channel * plane + i] = (rgba[i * 4 + channel] / 255 - MEAN[channel]) / STD[channel];
        }
    }

    return tensor;
}

/**
 * Fingerprint of one image or video frame: the model's CLS token joined with
 * the average of its patch tokens, each scaled to length 1, then the whole
 * scaled again.
 */
export async function embed(source, sourceWidth, sourceHeight) {
    const { ort, session } = await loadEngine();
    const input = new ort.Tensor('float32', preprocess(source, sourceWidth, sourceHeight), [1, 3, SIZE, SIZE]);
    const results = await session.run({ [session.inputNames[0]]: input });
    const name = session.outputNames.includes('last_hidden_state') ? 'last_hidden_state' : session.outputNames[0];
    const output = results[name];
    const data = output.data;

    if (output.dims.length !== 3) {
        return normalise(data);
    }

    const tokens = output.dims[1];
    const width = output.dims[2];
    const cls = normalise(data.subarray(0, width));
    const mean = new Float32Array(width);
    for (let token = 1; token < tokens; token++) {
        for (let i = 0; i < width; i++) {
            mean[i] += data[token * width + i];
        }
    }
    for (let i = 0; i < width; i++) {
        mean[i] /= Math.max(tokens - 1, 1);
    }

    const joined = new Float32Array(width * 2);
    joined.set(cls, 0);
    joined.set(normalise(mean), width);

    return normalise(joined);
}

/** Decodes an uploaded or fetched picture (respecting its EXIF rotation) and fingerprints it. */
export async function embedBlob(blob) {
    const bitmap = await createImageBitmap(blob, { imageOrientation: 'from-image' });
    try {
        return await embed(bitmap, bitmap.width, bitmap.height);
    } finally {
        bitmap.close();
    }
}

export function similarity(a, b) {
    if (a.length !== b.length) {
        return -1;
    }
    let dot = 0;
    for (let i = 0; i < a.length; i++) {
        dot += a[i] * b[i];
    }

    return dot;
}

/**
 * Ranks the objects by their best-matching photo. It only counts as a match
 * when the best score reaches the threshold AND beats the runner-up by the
 * margin - two similar objects therefore stay "unsure" instead of being guessed.
 */
export function rank(vector, objects, threshold, margin) {
    const ranking = objects
        .map((object) => {
            let score = -1;
            for (const reference of object.embeddings) {
                score = Math.max(score, similarity(vector, reference));
            }

            return { object, score };
        })
        .filter((entry) => entry.score > -1)
        .sort((a, b) => b.score - a.score);

    const best = ranking[0] ?? null;
    const second = ranking[1] ?? null;
    const gap = best ? best.score - (second ? second.score : 0) : 0;
    const matched = !!best && best.score >= threshold && (!second || gap >= margin);

    return { best, second, gap, matched, ranking };
}

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/** Reports an anonymous outcome (no picture, no identity); failures are ignored. */
export function reportEvent(payload) {
    return fetch('/guide/events', {
        method: 'POST',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(payload),
    }).catch(() => {});
}
