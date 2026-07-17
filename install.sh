#!/bin/bash
set -e

REPO="https://github.com/csrainbow/home-cloud.git"
INSTALL_DIR="/root/home-cloud-server"
HDD_DIR="/media/devmon/sda1-ata-WDC_WD5000LPVX-2/home-cloud-media"

echo "====================================="
echo "  HOME CLOUD - One Click Install"
echo "====================================="

# 1. Install Node.js jika belum ada
if ! command -v node &>/dev/null; then
    echo "[1/6] Menginstall Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
else
    echo "[1/6] Node.js sudah terinstall ($(node -v))"
fi

# 2. Install PM2
if ! command -v pm2 &>/dev/null; then
    echo "[2/6] Menginstall PM2..."
    npm install -g pm2
else
    echo "[2/6] PM2 sudah terinstall"
fi

# 3. Clone / update repo
if [ -d "$INSTALL_DIR/.git" ]; then
    echo "[3/6] Mengupdate repo..."
    cd "$INSTALL_DIR" && git pull
else
    echo "[3/6] Mengclone repo..."
    rm -rf "$INSTALL_DIR"
    git clone "$REPO" "$INSTALL_DIR"
fi

cd "$INSTALL_DIR"

# 4. Install dependency npm
echo "[4/6] Menginstall dependency..."
npm init -y --silent 2>/dev/null
npm install express cors multer dotenv

# 5. Buat users.json jika belum ada
if [ ! -f "$INSTALL_DIR/users.json" ]; then
    echo '[{"username":"admin","password":"admin"}]' > "$INSTALL_DIR/users.json"
    echo "[5/6] users.json dibuat (admin:admin)"
else
    echo "[5/6] users.json sudah ada"
fi

# 6. Buat direktori HDD
echo "[6/6] Membuat direktori penyimpanan..."
mkdir -p "$HDD_DIR"

# Start / restart PM2
echo "-------------------------------------"
echo "Menjalankan server dengan PM2..."
pm2 delete home-cloud 2>/dev/null || true
pm2 start "$INSTALL_DIR/server/server.js" --name home-cloud
pm2 save
pm2 startup systemd -u root --hp /root 2>/dev/null || true

echo "====================================="
echo "  INSTALLASI SELESAI!"
echo "  Server: http://$(hostname -I | awk '{print $1}'):3000"
echo "  Login: admin / admin"
echo "====================================="
