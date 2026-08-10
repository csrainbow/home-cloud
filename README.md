# Home Cloud

Aplikasi backup media pribadi (foto, video, dokumen) ke HDD server rumah (STB) di jaringan LAN, dengan tampilan web, aplikasi Android, dan uploader Windows.

## Fitur

- **Web UI** (port `3000`) — lihat, unggah, unduh, rename, pindah, salin, dan hapus media per pengguna dengan halaman timeline (Photos / Videos / Documents).
- **Halaman Admin** (`/admin.html`) — kelola pengguna (tambah / ubah password / hapus), lihat pemakaian storage.
- **Autentikasi** — login berbasis header/basic untuk API, halaman login untuk web.
- **Upload otomatis dari HP** — aplikasi Android sinkron otomatis (WorkManager) + upload manual per item/sekali klik.
- **Uploader Windows** — aplikasi `CloudSyncApp` (C# WinForms + `sync.js`) untuk mengupload folder dari PC.
- **Penyimpanan terpusat** — seluruh file tersimpan di HDD eksternal, dipilah per pengguna: `photos/`, `videos/`, `documents/`.
- **DNS lokal** — alias `homecloud.lan` (dnsmasq) menunjuk ke server di LAN.

## Struktur Proyek

```
home-cloud-server/
├── server/            # Backend Node.js (Express 5)
│   └── server.js      # Semua endpoint API + streaming file
├── public/            # Frontend web (index.html, login.html, admin.html)
├── android/
│   └── GaleryCloud/   # Aplikasi Android (Kotlin + Jetpack Compose)
├── cloud-sync/        # Uploader Windows (C# WinForms + sync.js)
├── data/              # Data pendukung
├── install.sh         # One-click install server
└── uninstall.sh       # Hapus server (mode full / server-only)
```

## Instalasi Server (STB / Linux ARM64)

### Cara 1 — One-Click Install

```bash
curl -fsSL https://raw.githubusercontent.com/csrainbow/home-cloud/main/install.sh | bash
```

Script akan:

1. Memasang **Node.js 20** dan **PM2** jika belum ada.
2. Clone repo ke `/root/home-cloud-server`.
3. Menanyakan lokasi penyimpanan (default: `/media/devmon/sda1-ata-WDC_WD5000LPVX-2/home-cloud-media`).
4. Menyesuaikan path penyimpanan di `server.js` dan `.env`.
5. Install dependency npm (`express`, `cors`, `multer`, `dotenv`).
6. Membuat `server/users.json` default (`admin` / `admin`).
7. Membuat direktori penyimpanan, lalu menjalankan server dengan PM2 (auto-start saat boot).

### Cara 2 — Manual

```bash
git clone https://github.com/csrainbow/home-cloud.git /root/home-cloud-server
cd /root/home-cloud-server
npm install
pm2 start server/server.js --name home-cloud
pm2 save
pm2 startup systemd -u root --hp /root
```

### DNS lokal (opsional)

Agar `http://homecloud.lan:3000` bisa dipakai dari HP/PC:

```bash
cat > /etc/dnsmasq.d/homecloud.conf <<EOF
port=5353
bind-interfaces
interface=wlan0
domain-needed
bogus-priv
no-resolv
server=1.1.1.1
server=8.8.8.8
address=/homecloud.lan/192.168.2.162
EOF
systemctl enable dnsmasq && systemctl start dnsmasq
```

Ganti `192.168.2.162` dengan IP server Anda.

## Konfigurasi

File `.env` (di `/root/home-cloud-server/.env`):

```env
PORT=3000
UPLOAD_DIR=/media/devmon/sda1-ata-WDC_WD5000LPVX-2/home-cloud-media
JWT_SECRET=supersecretcloudkey123
```

Akun pengguna disimpan di `server/users.json` (jangan di-commit ke repo):

```json
[
  { "username": "admin", "password": "admin" },
  { "username": "nur ismani", "password": "ganti-password" }
]
```

## Penggunaan

- **Web** — buka `http://<ip-server>:3000`, login dengan akun yang terdaftar.
- **Admin** — buka `http://<ip-server>:3000/admin.html`, login `admin` untuk mengelola pengguna.
- **Android** — install aplikasi *GaleryCloud* (unduh APK dari `http://<ip-server>:3000/GaleryCloud.apk`), atur di Settings: IP server, port `3000`, username & password, lalu tekan Save (otomatis tes koneksi). Foto/video yang belum tersinkron akan diupload otomatis. Ikon sinkron berputar saat upload berjalan (termasuk upload otomatis di background), dan galeri otomatis menyegarkan isinya saat kembali ke layar galeri.
- **Windows** — jalankan `CloudSyncApp.exe` (folder `cloud-sync`), atau `node sync.js` untuk upload folder ke server.

## Build APK Android

```bash
cd android/GaleryCloud
# pastikan local.properties menunjuk ke SDK: sdk.dir=C:\\Users\\<user>\\AppData\\Local\\Android\\Sdk
./gradlew assembleRelease
# hasil: app/build/outputs/apk/release/app-release.apk
# salin ke public/ agar bisa diunduh: cp app/build/outputs/apk/release/app-release.apk ../../public/GaleryCloud.apk
```

## Perintah Operasional

```bash
pm2 restart home-cloud        # restart server
pm2 logs home-cloud           # lihat log (upload, error)
pm2 list                      # status proses
```

```powershell
# Dari PC Windows via plink
plink -ssh -pw <password> root@192.168.2.162 "pm2 restart home-cloud"
```

## API Ringkasan

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/media/check-connection` | Tes koneksi & autentikasi |
| GET | `/api/media/storage-info` | Info storage (bytes) |
| GET | `/api/auth/storage` | Info storage untuk web (GB + persen) |
| GET | `/api/media/list?category=home` | Daftar media (timeline) |
| POST | `/api/media/upload` | Upload file (multipart) |
| POST | `/api/media/rename` | Rename file |
| POST | `/api/media/move` / `copy-multiple` / `delete-multiple` | Pindah / salin / hapus |
| POST | `/api/auth/login` / `register` | Login / daftar pengguna |
| GET/PUT/DELETE | `/api/admin/users` | Kelola pengguna (admin) |
| GET | `/stream/:username/*` | Streaming file (foto/video/dokumen) |

Autentikasi API memakai header: `user: <username>` dan `pass: <password>`.

## Uninstall

```bash
curl -fsSL https://raw.githubusercontent.com/csrainbow/home-cloud/main/uninstall.sh | bash
```

Pilih mode:
1. **Full uninstall** — hapus server + seluruh file penyimpanan (HATI-HATI!).
2. **Uninstall server only** — hapus server saja, file penyimpanan tetap aman.