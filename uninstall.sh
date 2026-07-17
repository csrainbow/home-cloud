#!/bin/bash
set -e

INSTALL_DIR="/root/home-cloud-server"
DEFAULT_HDD=$(grep "^const ABSOLUTE_HDD_DIR" "$INSTALL_DIR/server/server.js" 2>/dev/null | cut -d"'" -f2)

echo "====================================="
echo "  HOME CLOUD - Uninstall"
echo "====================================="
echo ""
echo "Pilih mode uninstall:"
echo "  1) Full uninstall  — hapus server + seluruh file penyimpanan"
echo "  2) Uninstall server — hapus server saja, penyimpanan tetap aman"
echo ""
read -p "Pilihan [1/2]: " MODE

case "$MODE" in
  2)
    echo ""
    echo "Menghapus server saja..."
    pm2 delete home-cloud 2>/dev/null || true
    pm2 save
    rm -rf "$INSTALL_DIR"
    echo ""
    echo "✅ Server berhasil dihapus. File penyimpanan tetap aman."
    echo "   Lokasi: ${DEFAULT_HDD:-}(tidak diketahui)"
    ;;
  *)
    echo ""
    echo "⚠️  FULL UNINSTALL — Semua file akan dihapus!"
    read -p "Ketik 'yakin' untuk melanjutkan: " CONFIRM
    if [ "$CONFIRM" != "yakin" ]; then
        echo "Dibatalkan."
        exit 1
    fi
    echo ""
    echo "Menghapus server dan penyimpanan..."
    pm2 delete home-cloud 2>/dev/null || true
    pm2 save
    rm -rf "$INSTALL_DIR"
    if [ -n "$DEFAULT_HDD" ] && [ -d "$DEFAULT_HDD" ]; then
        rm -rf "$DEFAULT_HDD"
        echo "   Penyimpanan dihapus: $DEFAULT_HDD"
    fi
    echo ""
    echo "✅ Full uninstall selesai."
    ;;
esac
