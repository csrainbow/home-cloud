param([switch]$Uninstall)
$dst = "$env:LOCALAPPDATA\CloudSyncUploader"
$desktop = [Environment]::GetFolderPath("Desktop")
$startMenu = "$env:APPDATA\Microsoft\Windows\Start Menu\Programs\Cloud Sync Uploader"

if ($Uninstall) {
    Write-Host "Uninstalling Cloud Sync Uploader..."
    if (Test-Path $dst) { Remove-Item -Recurse -Force $dst; Write-Host "Removed: $dst" }
    Remove-Item "$desktop\Cloud Sync Uploader.lnk" -ErrorAction SilentlyContinue
    if (Test-Path $startMenu) { Remove-Item -Recurse -Force $startMenu -ErrorAction SilentlyContinue }
    Write-Host "Uninstall complete."
    return
}

Write-Host "Installing Cloud Sync Uploader..."
if (!(Test-Path $dst)) { New-Item -ItemType Directory -Path $dst -Force | Out-Null }

$src = Split-Path -Parent $MyInvocation.MyCommand.Path
Copy-Item "$src\CloudSyncApp.exe" "$dst\" -Force
Copy-Item "$src\config.json" "$dst\" -Force
Copy-Item "$src\app.ico" "$dst\" -Force
Copy-Item "$src\start.bat" "$dst\" -Force

$WshShell = New-Object -ComObject WScript.Shell
$shortcut = $WshShell.CreateShortcut("$desktop\Cloud Sync Uploader.lnk")
$shortcut.TargetPath = "$dst\start.bat"
$shortcut.WorkingDirectory = "$dst"
$shortcut.Description = "Cloud Sync Uploader"
$shortcut.IconLocation = "$dst\app.ico"
$shortcut.Save()

if (!(Test-Path $startMenu)) { New-Item -ItemType Directory -Path $startMenu -Force | Out-Null }
$shortcut2 = $WshShell.CreateShortcut("$startMenu\Cloud Sync Uploader.lnk")
$shortcut2.TargetPath = "$dst\start.bat"
$shortcut2.WorkingDirectory = "$dst"
$shortcut2.IconLocation = "$dst\app.ico"
$shortcut2.Save()

$WshShell = $null
Write-Host "Installed to: $dst"
Write-Host "Desktop shortcut created."
Write-Host "Run '$dst\start.bat' to start."
