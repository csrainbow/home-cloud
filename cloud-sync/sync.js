const fs = require('fs');
const path = require('path');
const chokidar = require('chokidar');
const axios = require('axios');
const FormData = require('form-data');
const notifier = require('node-notifier');

const CONFIG_PATH = path.join(__dirname, 'config.json');
const LOG_PATH = path.join(__dirname, 'sync.log');
const PENDING_FILE = path.join(__dirname, 'pending.json');
const PROGRESS_FILE = path.join(__dirname, 'progress.json');

let currentProgress = {
    uploading: null,
    queue: [],
    done: [],
    failed: []
};

function writeProgress() {
    fs.writeFileSync(PROGRESS_FILE, JSON.stringify(currentProgress, null, 2));
}

function log(msg) {
    const line = `[${new Date().toISOString()}] ${msg}`;
    console.log(line);
    fs.appendFileSync(LOG_PATH, line + '\n');
}

function loadConfig() {
    try {
        return JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
    } catch {
        log('ERROR: Cannot read config.json');
        process.exit(1);
    }
}

function notify(title, message) {
    notifier.notify({ title: title || 'Cloud Sync', message: message || '', sound: true, wait: false, timeout: 5 });
}

function loadPending() {
    try { return JSON.parse(fs.readFileSync(PENDING_FILE, 'utf8')); } catch { return []; }
}

function savePending(list) {
    fs.writeFileSync(PENDING_FILE, JSON.stringify(list, null, 2));
}

async function uploadFile(filePath, config) {
    const { ip, port, username, password } = config.server;
    const url = `http://${ip}:${port}/api/media/upload`;
    const fileName = path.basename(filePath);
    const fileSize = fs.statSync(filePath).size;
    const ext = path.extname(fileName).toLowerCase();

    // Check extension filter
    const exts = config.watch.extensions.map(e => e.toLowerCase());
    if (exts.length > 0 && !exts.includes(ext)) {
        log(`Skipped (extension): ${fileName}`);
        return true;
    }

    currentProgress.uploading = { name: fileName, percent: 0, speed: '0 B/s', size: fileSize, status: 'uploading' };
    writeProgress();

    try {
        const form = new FormData();
        form.append('file', fs.createReadStream(filePath), { filename: fileName, knownLength: fileSize });
        form.append('username', username);
        form.append('password', password);

        log(`Uploading: ${fileName} (${(fileSize / 1024 / 1024).toFixed(2)} MB)`);

        let startTime = Date.now();
        let lastLoaded = 0;

        const response = await axios.post(url, form, {
            headers: {
                ...form.getHeaders(),
                'user': username,
                'pass': password
            },
            params: { username, password },
            maxContentLength: Infinity,
            maxBodyLength: Infinity,
            timeout: 600000,
            onUploadProgress: (progressEvent) => {
                const loaded = progressEvent.loaded || 0;
                const total = progressEvent.total || fileSize;
                const pct = total > 0 ? Math.round((loaded / total) * 100) : 0;
                const elapsed = (Date.now() - startTime) / 1000;
                const bytesPerSec = elapsed > 0 ? (loaded / elapsed) : 0;
                const speed = bytesPerSec > 1048576 ? (bytesPerSec / 1048576).toFixed(1) + ' MB/s'
                            : bytesPerSec > 1024 ? (bytesPerSec / 1024).toFixed(1) + ' KB/s'
                            : bytesPerSec.toFixed(0) + ' B/s';

                if (currentProgress.uploading) {
                    currentProgress.uploading.percent = pct;
                    currentProgress.uploading.speed = speed;
                    currentProgress.uploading.loaded = loaded;
                    writeProgress();
                }
            }
        });

        if (response.data && response.data.success) {
            log(`OK: ${fileName}`);
            currentProgress.done.push({ name: fileName, size: fileSize, time: new Date().toISOString() });
            currentProgress.uploading = null;
            writeProgress();
            return true;
        } else {
            log(`FAIL: ${fileName} - server error`);
            currentProgress.failed.push({ name: fileName, error: 'server error', time: new Date().toISOString() });
            currentProgress.uploading = null;
            writeProgress();
            return false;
        }
    } catch (err) {
        log(`FAIL: ${fileName} - ${err.message}`);
        currentProgress.failed.push({ name: fileName, error: err.message, time: new Date().toISOString() });
        currentProgress.uploading = null;
        writeProgress();
        return false;
    }
}

async function processPending(config) {
    const pending = loadPending();
    if (pending.length === 0) return;

    currentProgress.queue = pending.map(p => ({ name: path.basename(p), status: 'pending' }));
    writeProgress();

    log(`Processing ${pending.length} pending files...`);
    const remaining = [];

    for (const filePath of pending) {
        if (!fs.existsSync(filePath)) continue;
        const ok = await uploadFile(filePath, config);
        if (!ok) remaining.push(filePath);
    }

    savePending(remaining);
    currentProgress.queue = [];
    if (remaining.length === 0) {
        currentProgress.done = [];
        currentProgress.failed = [];
        writeProgress();
        notify('Cloud Sync', 'Semua file pending berhasil diupload!');
    }
}

async function startWatching() {
    const config = loadConfig();
    const watchFolder = config.watch.folder;
    const debounceMs = config.watch.debounceMs || 3000;

    if (!fs.existsSync(watchFolder)) {
        fs.mkdirSync(watchFolder, { recursive: true });
    }

    // Reset progress on start
    currentProgress = { uploading: null, queue: [], done: [], failed: [] };
    writeProgress();

    log(`Starting watcher on: ${watchFolder}`);
    log(`Extensions: ${(config.watch.extensions || []).join(', ')}`);

    // Upload queue - serial processing (one file at a time)
    const uploadQueue = [];
    let processing = false;

    async function processQueue() {
        if (processing || uploadQueue.length === 0) return;
        processing = true;

        while (uploadQueue.length > 0) {
            const { filePath, fileName, resolve } = uploadQueue.shift();

            currentProgress.queue = uploadQueue.map(q => ({ name: q.fileName, status: 'pending' }));
            writeProgress();

            const ok = await uploadFile(filePath, config);

            currentProgress.queue = uploadQueue.map(q => ({ name: q.fileName, status: 'pending' }));
            writeProgress();

            if (ok) {
                notify('Cloud Sync', fileName + ' uploaded');
            } else {
                const pending = loadPending();
                pending.push(filePath);
                savePending(pending);
                notify('Cloud Sync', fileName + ' failed');
            }

            setTimeout(() => {
                currentProgress.done = currentProgress.done.filter(d => d.name !== fileName);
                currentProgress.failed = currentProgress.failed.filter(f => f.name !== fileName);
                writeProgress();
            }, 10000);

            resolve(ok);
        }

        processing = false;
    }

    const debounceTimers = new Map();

    const watcher = chokidar.watch(watchFolder, {
        ignored: /(^|[\/\\])\../,
        persistent: true,
        ignoreInitial: false,
        awaitWriteFinish: { stabilityThreshold: 2000, pollInterval: 500 }
    });

    watcher.on('add', (filePath) => {
        const fileName = path.basename(filePath);
        log(`Detected: ${fileName}`);

        if (debounceTimers.has(filePath)) clearTimeout(debounceTimers.get(filePath));

        debounceTimers.set(filePath, setTimeout(() => {
            debounceTimers.delete(filePath);

            new Promise(resolve => {
                uploadQueue.push({ filePath, fileName, resolve });
            });

            processQueue();
        }, debounceMs));
    });

    watcher.on('error', (err) => log(`Watcher error: ${err.message}`));

    setInterval(() => processPending(config), 300000);
    setTimeout(() => processPending(config), 5000);

    log('Cloud Sync running. Press Ctrl+C to stop.');
}

startWatching().catch(err => {
    log(`Fatal: ${err.message}`);
    notify('Cloud Sync Error', err.message);
    process.exit(1);
});
