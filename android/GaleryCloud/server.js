const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const multer = require('multer');
const disk = require('diskusage');
const os = require('os');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());

// 🔐 DIREKTORI HDD EKSTERNAL (Sesuaikan dengan jalur Anda)
const ABSOLUTE_HDD_DIR = '/media/devmon/sda1-ata-WDC_WD5000LPVX-2/home-cloud-media';
const USERS_DB_FILE = path.join(__dirname, 'users.json');

// Pastikan direktori HDD ada, jika tidak gunakan folder lokal 'uploads' sebagai fallback
const TARGET_DIR = fs.existsSync(ABSOLUTE_HDD_DIR) ? ABSOLUTE_HDD_DIR : path.join(__dirname, 'uploads');
if (!fs.existsSync(TARGET_DIR)) {
    fs.mkdirSync(TARGET_DIR, { recursive: true });
}

// Failsafe: Pastikan file database user mandiri selalu tercipta
if (!fs.existsSync(USERS_DB_FILE)) {
    const defaultData = [{ username: "admin", password: "admin" }];
    fs.writeFileSync(USERS_DB_FILE, JSON.stringify(defaultData, null, 4));
}

// 🛡️ MIDDLEWARE AUTH (Mendukung Header dari Android App)
const authMiddleware = (req, res, next) => {
    const username = (req.headers.user || req.body.username || req.query.username || 'admin').toLowerCase().trim();
    const password = (req.headers.pass || req.body.password || '').trim();

    try {
        const usersData = JSON.parse(fs.readFileSync(USERS_DB_FILE, 'utf8'));
        const foundUser = usersData.find(u => u.username.toLowerCase() === username && u.password === password);

        if (foundUser || (username === 'admin' && password === 'admin')) {
            req.authenticatedUser = username;
            next();
        } else {
            console.log(`Unauthorized attempt: ${username}`);
            res.status(401).json({ success: false, message: "Akses Ditolak: Login Salah" });
        }
    } catch (e) {
        res.status(500).send("Error Auth System");
    }
};

// ⚙️ ENGINE STORAGE CONFIGURATION (Otomatis sortir ke photos/videos)
const storageConfiguration = multer.diskStorage({
    destination: (req, file, cb) => {
        const username = (req.headers.user || req.body.username || 'admin').toLowerCase().trim();
        let uploadPath = path.join(TARGET_DIR, username);

        const ext = path.extname(file.originalname).toLowerCase();
        const photoExts = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.bmp'];
        const videoExts = ['.mp4', '.mkv', '.mov', '.avi', '.flv', '.wmv', '.3gp'];

        if (photoExts.includes(ext)) {
            uploadPath = path.join(uploadPath, 'photos');
        } else if (videoExts.includes(ext)) {
            uploadPath = path.join(uploadPath, 'videos');
        } else {
            uploadPath = path.join(uploadPath, 'documents');
        }

        if (!fs.existsSync(uploadPath)) {
            fs.mkdirSync(uploadPath, { recursive: true });
        }
        cb(null, uploadPath);
    },
    filename: (req, file, cb) => {
        cb(null, `${Date.now()}-${file.originalname.replace(/\s+/g, '_')}`);
    }
});
const uploadEngine = multer({ storage: storageConfiguration });

// ==========================================
// API ENDPOINTS UNTUK ANDROID APP
// ==========================================

// 1. Cek Koneksi
app.get('/api/media/check-connection', authMiddleware, (req, res) => {
    console.log(`Connection check from user: ${req.authenticatedUser}`);
    res.status(200).json({
        status: "Connected",
        user: req.authenticatedUser,
        serverTime: new Date().toISOString()
    });
});

// 2. Kapasitas Penyimpanan (Akurat)
app.get('/api/media/storage-info', authMiddleware, async (req, res) => {
    try {
        const pathToCheck = os.platform() === 'win32' ? 'C:' : TARGET_DIR;
        const info = await disk.check(pathToCheck);

        const total = info.total;
        const free = info.available;
        const used = total - free;

        res.json({
            total: total,
            used: used,
            free: free,
            percent: parseFloat(((used / total) * 100).toFixed(1)),
            unit: 'bytes'
        });
    } catch (err) {
        res.status(500).send("Gagal membaca kapasitas penyimpanan");
    }
});

// 3. Upload Berkas (Single File)
app.post('/api/media/upload', authMiddleware, uploadEngine.single('file'), (req, res) => {
    if (!req.file) return res.status(400).send("Tidak ada file yang diunggah.");
    console.log(`File uploaded to: ${req.file.path}`);
    res.status(200).json({ success: true, message: "Berkas berhasil dicadangkan." });
});

// 4. List Media (Untuk tampilan di aplikasi jika diperlukan nantinya)
app.get('/api/media/list', authMiddleware, (req, res) => {
    // Implementasi list logic Anda di sini jika ingin sync dua arah
    res.json({ success: true, data: [] });
});

// ==========================================
// START SERVER
// ==========================================
app.listen(PORT, '0.0.0.0', () => {
    console.log(`------------------------------------------`);
    console.log(`GaleryCloud Server berjalan di http://0.0.0.0:${PORT}`);
    console.log(`Menunggu koneksi dari aplikasi Android...`);
    console.log(`Direktori Media: ${TARGET_DIR}`);
    console.log(`------------------------------------------`);
});
