# Implementation Plan - Fix Connection Issues

The user is experiencing a `java.net.ConnectException: Failed to connect to /10.0.2.2:3000`. This error indicates that the Android app cannot establish a network connection to the server running on the host machine. Additionally, the current API endpoint paths in the app do not match the paths defined in the provided `server.js`.

## User Review Required

> [!IMPORTANT]
> **Server Status**: The `ConnectException` typically occurs because the server is not running or the port is unreachable. Please ensure that:
> 1. You have started the server by running `node server.js` in your terminal.
> 2. The server output confirms it is running on port 3000.
> 3. If you are using an Android Emulator, `10.0.2.2` is the correct IP to access your host machine's localhost. If you are using a physical device, you must use your computer's local IP address (e.g., `192.168.1.x`).

## Proposed Changes

### [Data Layer]

#### [MODIFY] [GalleryApiService.kt](file:///C:/Users/Ratu Sikumbang/AndroidStudioProjects/GaleryCloud/app/src/main/java/com/csrainbow/galerycloud/data/remote/GalleryApiService.kt)
- Correct the endpoint paths to match `server.js` (remove `/api/media/` prefix).
- Improve error logging to provide more context when a connection fails.

## Verification Plan

### Manual Verification
- Start the server: `node server.js`.
- Run the Android app.
- Go to **Settings**.
- Enter the Server IP (`10.0.2.2` for emulator) and Port (`3000`).
- Enter the default credentials (Username: `admin`, Password: `password`).
- Tap **Save & Connect**.
- Verify that the status changes to **Connected** and storage info is displayed.
