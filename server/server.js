const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const multer = require('multer');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '../public')));

app.use((req, res, next) => {
    if (req.path.startsWith('/api/')) {
        console.log(`[REQ] ${req.method} ${req.path} user=${req.headers.user || req.query.username || ''} ct=${req.headers['content-type'] || ''} len=${req.headers['content-length'] || '-'}`);
    }
    next();
});

// 🔐 DIREKTORI HDD EKSTERNAL ANDA
// Prioritas: .env (UPLOAD_DIR) -> fallback path Linux
const ABSOLUTE_HDD_DIR = process.env.UPLOAD_DIR || '/media/devmon/sda1-ata-WDC_WD5000LPVX-2/home-cloud-media';
const USERS_DB_FILE = path.join(__dirname, 'users.json');

// Pastikan DB User ada
if (!fs.existsSync(USERS_DB_FILE)) {
    const defaultData = [{ username: "admin", password: "admin" }];
    fs.writeFileSync(USERS_DB_FILE, JSON.stringify(defaultData, null, 4));
}

// Pastikan direktori penyimpanan ada (agar statfs/storage-info tidak error di awal)
if (!fs.existsSync(ABSOLUTE_HDD_DIR)) {
    fs.mkdirSync(ABSOLUTE_HDD_DIR, { recursive: true });
}

// Sanitasi username: buang separator path & pola '..' agar tidak bisa traversal
function sanitizeUsername(name) {
    return String(name || '')
        .replace(/[\\/]/g, '')
        .replace(/\.\./g, '.')
        .toLowerCase()
        .trim();
}

// Sanitasi nama file: buang separator path
function sanitizeFileName(name) {
    const s = String(name || '').replace(/[\\/]/g, '').trim();
    if (!s) return null;
    return s.replace(/\.\./g, '.');
}

const ALLOWED_CATEGORIES = ['photos', 'videos', 'documents'];
function sanitizeCategory(cat) {
    if (cat === 'home' || ALLOWED_CATEGORIES.includes(cat)) return cat;
    return 'documents';
}

// 🛡️ MIDDLEWARE AUTH
const authMiddleware = (req, res, next) => {
    const username = sanitizeUsername(req.headers.user || (req.body && req.body.username) || req.query.username || 'admin');
    const password = (req.headers.pass || (req.body && req.body.password) || req.query.pass || '').trim();
    try {
        const usersData = JSON.parse(fs.readFileSync(USERS_DB_FILE, 'utf8'));
        const foundUser = usersData.find(u => u.username.toLowerCase() === username && u.password === password);
        if (foundUser || (username === 'admin' && password === 'admin')) {
            req.authenticatedUser = username;
            next();
        } else {
            res.status(401).json({ success: false, message: "Akses Ditolak: Login Salah" });
        }
    } catch (e) { res.status(500).send("Error Auth"); }
};

// ⚙️ ENGINE STORAGE
const storageConfiguration = multer.diskStorage({
    destination: (req, file, cb) => {
        const username = sanitizeUsername(req.headers.user || (req.body && req.body.username) || req.query.username || 'admin');
        let uploadPath = path.join(ABSOLUTE_HDD_DIR, username);
        const ext = path.extname(file.originalname).toLowerCase();
        const photoExts = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
        const videoExts = ['.mp4', '.mkv', '.mov', '.avi'];
        if (photoExts.includes(ext)) uploadPath = path.join(uploadPath, 'photos');
        else if (videoExts.includes(ext)) uploadPath = path.join(uploadPath, 'videos');
        else uploadPath = path.join(uploadPath, 'documents');
        if (!fs.existsSync(uploadPath)) fs.mkdirSync(uploadPath, { recursive: true });
        req.uploadDir = uploadPath;
        cb(null, uploadPath);
    },
    filename: (req, file, cb) => {
        const baseName = sanitizeFileName(file.originalname) || 'file';
        const ext = path.extname(baseName);
        const base = path.basename(baseName, ext);
        const dir = req.uploadDir || path.join(ABSOLUTE_HDD_DIR, 'admin', 'documents');
        let finalName = baseName;
        let counter = 1;
        while (fs.existsSync(path.join(dir, finalName))) {
            finalName = `${base} (${counter})${ext}`;
            counter++;
        }
        cb(null, finalName);
    }
});
const uploadEngine = multer({
    storage: storageConfiguration,
    limits: { fileSize: 10 * 1024 * 1024 * 1024 } // 10GB max
});

// ============================
// 🖼️ STORAGE INFO & STATS
// ============================

// Format bytes ke GB
function formatGB(bytes) {
    return (bytes / (1024 * 1024 * 1024)).toFixed(1);
}

function getStorageStats() {
    try {
        if (typeof fs.statfsSync === 'function') {
            const stats = fs.statfsSync(ABSOLUTE_HDD_DIR);
            const total = Number(stats.bsize) * Number(stats.blocks);
            const free = Number(stats.bsize) * Number(stats.bfree);
            const used = total - free;
            const percentage = parseFloat(((used / total) * 100).toFixed(1));
            return { total, used, free, percentage, totalSpace: formatGB(total), usedSpace: formatGB(used), freeSpace: formatGB(free) };
        } else {
            const { execSync } = require('child_process');
            const stdout = execSync(`df -B1 "${ABSOLUTE_HDD_DIR}"`, { encoding: 'utf8', timeout: 5000 });
            const lines = stdout.trim().split('\n');
            const parts = lines[lines.length - 1].split(/\s+/);
            if (parts.length >= 4) {
                const total = parseFloat(parts[1]) || 0;
                const used = parseFloat(parts[2]) || 0;
                const free = parseFloat(parts[3]) || 0;
                const percentage = total > 0 ? parseFloat(((used / total) * 100).toFixed(1)) : 0;
                return { total, used, free, percentage, totalSpace: formatGB(total), usedSpace: formatGB(used), freeSpace: formatGB(free) };
            }
            throw new Error('df parse error');
        }
    } catch (e) {
        return { total: 0, used: 0, free: 0, percentage: 0, totalSpace: "0", usedSpace: "0", freeSpace: "0" };
    }
}

// Endpoint untuk frontend (format sesuai index.html)
app.get('/api/auth/storage', authMiddleware, (req, res) => {
    const stats = getStorageStats();
    res.json({ success: true, status: "success", percentage: stats.percentage, usedSpace: stats.usedSpace, totalSpace: stats.totalSpace });
});

// ============================
// 📱 ANDROID APP ENDPOINTS
// ============================

app.get('/api/media/check-connection', authMiddleware, (req, res) => {
    res.status(200).json({ status: "Connected", user: req.authenticatedUser });
});

app.get('/api/media/storage-info', authMiddleware, (req, res) => {
    const stats = getStorageStats();
    res.json({ total: stats.total, used: stats.used, free: stats.free, percent: stats.percentage, unit: "bytes" });
});

// ============================
// 📂 MEDIA LIST (Timeline)
// ============================

app.get('/api/media/list', authMiddleware, (req, res) => {
    let username = req.authenticatedUser;
    const targetUser = req.query.targetUser;
    if (targetUser && username === 'admin') {
        username = sanitizeUsername(targetUser);
    }
    const category = sanitizeCategory(req.query.category || 'home');
    const userDir = path.join(ABSOLUTE_HDD_DIR, username);

    if (!fs.existsSync(userDir)) {
        return res.json({ timeline: [] });
    }

    let scanDirs = [];
    if (category === 'home') {
        scanDirs = ALLOWED_CATEGORIES.map(d => ({ name: d, path: path.join(userDir, d) }));
    } else {
        scanDirs = [{ name: category, path: path.join(userDir, category) }];
    }

    const timeline = [];
    const fileTypes = { photos: 'photo', videos: 'video', documents: 'document' };

    scanDirs.forEach(dir => {
        if (!fs.existsSync(dir.path)) return;
        try {
            const files = fs.readdirSync(dir.path);
            const items = files
                .filter(f => fs.statSync(path.join(dir.path, f)).isFile())
                .map(f => {
                    const stat = fs.statSync(path.join(dir.path, f));
                    const type = fileTypes[dir.name] || 'document';
                    return {
                        name: f,
                        src: `/stream/${username}/${dir.name}/${encodeURIComponent(f)}`,
                        url: `/stream/${username}/${dir.name}/${encodeURIComponent(f)}`,
                        type: type,
                        size: stat.size,
                        lastModified: stat.mtime
                    };
                })
                .sort((a, b) => b.lastModified - a.lastModified);

            if (items.length > 0) {
                const label = dir.name.charAt(0).toUpperCase() + dir.name.slice(1);
                timeline.push({ title: label, items });
            }
        } catch (e) {
            // skip folder jika error baca
        }
    });

    res.json({ success: true, timeline });
});

// ============================
// 🚚 STREAM FILE
// ============================

app.get('/stream/:username/*path', (req, res) => {
    const username = sanitizeUsername(req.params.username);
    const rawPath = Array.isArray(req.params.path) ? req.params.path.join('/') : req.params.path;
    const fileRelPath = String(rawPath || '').replace(/\\/g, '/');
    if (!fileRelPath || fileRelPath.split('/').includes('..')) {
        return res.status(400).send('Invalid path');
    }
    const userRoot = path.join(ABSOLUTE_HDD_DIR, username);
    const fullFilePath = path.join(userRoot, fileRelPath);
    if (!fullFilePath.startsWith(userRoot + path.sep)) {
        return res.status(403).send('Forbidden');
    }
    if (!fs.existsSync(fullFilePath) || !fs.statSync(fullFilePath).isFile()) {
        return res.status(404).send('File not found');
    }
    res.sendFile(fullFilePath);
});

// ============================
// ⬆️ UPLOAD
// ============================

app.post('/api/media/upload', authMiddleware, (req, res) => {
    uploadEngine.single('file')(req, res, (err) => {
        if (err) {
            console.error(`[UPLOAD ERROR] ${err.message}`);
            if (err.code === 'LIMIT_FILE_SIZE') {
                return res.status(413).json({ success: false, message: "File terlalu besar. Maksimal 10GB." });
            }
            return res.status(500).json({ success: false, message: `Upload gagal: ${err.message}` });
        }
        if (!req.file) {
            return res.status(400).json({ success: false, message: "Tidak ada file yang diterima" });
        }
        console.log(`[UPLOAD OK] ${req.file.originalname} (${(req.file.size / 1024 / 1024).toFixed(2)} MB) -> ${req.file.destination} oleh ${req.authenticatedUser}`);
        res.status(200).json({ success: true, message: "Berhasil Backup ke HDD" });
    });
});

// ============================
// ❌ DELETE MULTIPLE
// ============================

app.post('/api/media/delete-multiple', authMiddleware, (req, res) => {
    let username = req.authenticatedUser;
    const targetUser = req.query.targetUser || req.body.targetUser;
    if (targetUser && username === 'admin') {
        username = sanitizeUsername(targetUser);
    }
    const files = (req.body.files || []).map(f => sanitizeFileName(f)).filter(Boolean);
    let deletedCount = 0;

    files.forEach(file => {
        // Cari di semua subfolder
        ALLOWED_CATEGORIES.forEach(sub => {
            const fullPath = path.join(ABSOLUTE_HDD_DIR, username, sub, file);
            if (fs.existsSync(fullPath)) {
                try {
                    fs.unlinkSync(fullPath);
                    deletedCount++;
                } catch (e) { /* skip */ }
            }
        });
    });

    res.json({ success: true, deleted: deletedCount, message: `${deletedCount} file dihapus` });
});

// ============================
// 📋 COPY MULTIPLE
// ============================

app.post('/api/media/copy-multiple', authMiddleware, (req, res) => {
    let username = req.authenticatedUser;
    const targetUser = req.query.targetUser || req.body.targetUser;
    if (targetUser && username === 'admin') {
        username = sanitizeUsername(targetUser);
    }
    const files = (req.body.files || []).map(f => sanitizeFileName(f)).filter(Boolean);
    const targetCategory = sanitizeCategory(req.body.targetCategory);
    const targetDir = path.join(ABSOLUTE_HDD_DIR, username, targetCategory);

    if (!fs.existsSync(targetDir)) fs.mkdirSync(targetDir, { recursive: true });

    let copiedCount = 0;
    files.forEach(file => {
        // Cari file di semua folder
        ALLOWED_CATEGORIES.forEach(sub => {
            const srcPath = path.join(ABSOLUTE_HDD_DIR, username, sub, file);
            if (fs.existsSync(srcPath) && sub !== targetCategory) {
                const destPath = path.join(targetDir, file);
                if (!fs.existsSync(destPath)) {
                    try {
                        fs.copyFileSync(srcPath, destPath);
                        copiedCount++;
                    } catch (e) { /* skip */ }
                }
            }
        });
    });

    res.json({ success: true, copied: copiedCount });
});

// ============================
// ✏️ RENAME FILE
// ============================

app.post('/api/media/rename', authMiddleware, (req, res) => {
    let username = req.authenticatedUser;
    const targetUser = req.query.targetUser || req.body.targetUser;
    if (targetUser && username === 'admin') {
        username = sanitizeUsername(targetUser);
    }
    const oldName = sanitizeFileName(req.body.oldName);
    const newName = sanitizeFileName(req.body.newName);
    if (!oldName || !newName) {
        return res.status(400).json({ success: false, message: "oldName dan newName wajib diisi" });
    }
    if (oldName === newName) {
        return res.json({ success: true, message: "Nama file tidak berubah" });
    }
    let renamed = false;
    let conflict = false;
    for (const sub of ALLOWED_CATEGORIES) {
        const oldPath = path.join(ABSOLUTE_HDD_DIR, username, sub, oldName);
        const newPath = path.join(ABSOLUTE_HDD_DIR, username, sub, newName);
        if (!fs.existsSync(oldPath)) continue;
        if (fs.existsSync(newPath)) {
            conflict = true;
            break;
        }
        try {
            fs.renameSync(oldPath, newPath);
            renamed = true;
        } catch (e) { console.log(`RENAME error: ${e.message}`); }
    }
    if (conflict) {
        return res.status(409).json({ success: false, message: "Nama baru sudah digunakan" });
    }
    if (renamed) {
        res.json({ success: true, message: "File berhasil diubah" });
    } else {
        res.status(404).json({ success: false, message: "File tidak ditemukan" });
    }
});

// ============================
// ✂️ MOVE (CUT + PASTE)
// ============================

app.post('/api/media/move', authMiddleware, (req, res) => {
    let username = req.authenticatedUser;
    const targetUser = req.query.targetUser || req.body.targetUser;
    if (targetUser && username === 'admin') {
        username = sanitizeUsername(targetUser);
    }
    const files = (req.body.files || []).map(f => sanitizeFileName(f)).filter(Boolean);
    const targetCategory = sanitizeCategory(req.body.targetCategory);
    if (!files.length || !targetCategory) {
        return res.status(400).json({ success: false, message: "files dan targetCategory wajib diisi" });
    }
    const targetDir = path.join(ABSOLUTE_HDD_DIR, username, targetCategory);
    if (!fs.existsSync(targetDir)) fs.mkdirSync(targetDir, { recursive: true });
    let movedCount = 0;
    files.forEach(file => {
        ALLOWED_CATEGORIES.forEach(sub => {
            const srcPath = path.join(ABSOLUTE_HDD_DIR, username, sub, file);
            if (fs.existsSync(srcPath) && sub !== targetCategory) {
                const destPath = path.join(targetDir, file);
                try {
                    if (!fs.existsSync(destPath)) {
                        fs.copyFileSync(srcPath, destPath);
                        fs.unlinkSync(srcPath);
                        movedCount++;
                    }
                } catch (e) { /* skip */ }
            }
        });
    });
    res.json({ success: true, moved: movedCount });
});

// ============================
// 🔐 AUTH ENDPOINTS
// ============================

app.post('/api/auth/login', (req, res) => {
    const username = (req.body.username || '').toLowerCase().trim();
    const password = (req.body.password || '').trim();
    try {
        const usersData = JSON.parse(fs.readFileSync(USERS_DB_FILE, 'utf8'));
        const foundUser = usersData.find(u => u.username.toLowerCase() === username && u.password === password);
        if (foundUser) {
            res.status(200).json({ success: true, username: foundUser.username });
        } else {
            res.status(401).json({ success: false, message: "Username / Password salah" });
        }
    } catch (e) {
        res.status(500).json({ success: false, message: "Terjadi kesalahan pada server" });
    }
});

app.post('/api/auth/register', (req, res) => {
    const username = sanitizeUsername(req.body.username);
    const password = (req.body.password || '').trim();
    if (!username || !password) {
        return res.status(400).json({ success: false, message: "Username dan password wajib diisi" });
    }
    try {
        const usersData = JSON.parse(fs.readFileSync(USERS_DB_FILE, 'utf8'));
        const exists = usersData.find(u => u.username.toLowerCase() === username);
        if (exists) {
            return res.status(409).json({ success: false, message: "Username sudah digunakan" });
        }
        usersData.push({ username, password });
        fs.writeFileSync(USERS_DB_FILE, JSON.stringify(usersData, null, 4));
        res.status(201).json({ success: true, message: "User berhasil dibuat" });
    } catch (e) {
        res.status(500).json({ success: false, message: "Terjadi kesalahan pada server" });
    }
});

// ============================
// 👑 SUPER ADMIN — USER MANAGEMENT
// ============================

const adminMiddleware = (req, res, next) => {
    if (req.authenticatedUser !== 'admin') {
        return res.status(403).json({ success: false, message: "Hanya admin yang bisa akses ini" });
    }
    next();
};

app.get('/api/admin/users', authMiddleware, adminMiddleware, (req, res) => {
    try {
        const usersData = JSON.parse(fs.readFileSync(USERS_DB_FILE, 'utf8'));
        const safe = usersData.map(u => ({ username: u.username, password: u.password || '' }));
        res.json({ success: true, users: safe });
    } catch (e) {
        res.status(500).json({ success: false, message: "Gagal membaca data user" });
    }
});

app.put('/api/admin/users/:username', authMiddleware, adminMiddleware, (req, res) => {
    const target = sanitizeUsername(req.params.username);
    const newPassword = (req.body.password || '').trim();
    if (!newPassword) {
        return res.status(400).json({ success: false, message: "Password baru wajib diisi" });
    }
    try {
        let usersData = JSON.parse(fs.readFileSync(USERS_DB_FILE, 'utf8'));
        const idx = usersData.findIndex(u => u.username.toLowerCase() === target);
        if (idx === -1) {
            return res.status(404).json({ success: false, message: "User tidak ditemukan" });
        }
        usersData[idx].password = newPassword;
        fs.writeFileSync(USERS_DB_FILE, JSON.stringify(usersData, null, 4));
        res.json({ success: true, message: `Password ${target} berhasil diubah` });
    } catch (e) {
        res.status(500).json({ success: false, message: "Gagal menyimpan" });
    }
});

app.delete('/api/admin/users/:username', authMiddleware, adminMiddleware, (req, res) => {
    const target = sanitizeUsername(req.params.username);
    if (target === 'admin') {
        return res.status(403).json({ success: false, message: "Tidak bisa menghapus user admin" });
    }
    try {
        let usersData = JSON.parse(fs.readFileSync(USERS_DB_FILE, 'utf8'));
        const filtered = usersData.filter(u => u.username.toLowerCase() !== target);
        if (filtered.length === usersData.length) {
            return res.status(404).json({ success: false, message: "User tidak ditemukan" });
        }
        fs.writeFileSync(USERS_DB_FILE, JSON.stringify(filtered, null, 4));
        // Hapus semua folder milik user
        const userDir = path.join(ABSOLUTE_HDD_DIR, target);
        if (fs.existsSync(userDir)) {
            fs.rmSync(userDir, { recursive: true, force: true });
        }
        res.json({ success: true, message: `User ${target} dan seluruh datanya berhasil dihapus` });
    } catch (e) {
        res.status(500).json({ success: false, message: "Gagal menyimpan" });
    }
});

app.listen(PORT, () => {
    console.log(`Server Cloud Secure berjalan di http://localhost:${PORT}`);
    console.log(`Penyimpanan Utama: ${ABSOLUTE_HDD_DIR}`);
});
