Install:
curl -fsSL https://raw.githubusercontent.com/csrainbow/home-cloud/main/install.sh | bash

Uninstall (tanya mode full/server-only):
curl -fsSL https://raw.githubusercontent.com/csrainbow/home-cloud/main/uninstall.sh | bash

Atau setelah install, jalankan langsung:
bash /root/uninstall-home-cloud.sh

Perintah Restart Server :
pm2 restart home-cloud

Atau kalau dari PC Windows via plink:
plink -ssh -pw S@idah182 root@192.168.2.162 "pm2 restart home-cloud"
