# Implementation Plan - Automated Self-Test via Emulator

This plan outlines the steps I will take to perform a comprehensive "self-test" of the GaleryCloud app using the integrated emulator and ADB tools.

## Testing Objectives

1.  **UI/UX Verification**: Ensure all menus, floating capsules, and grids are rendered correctly.
2.  **Data Integrity**: Verify MediaStore fetching and grouping by date.
3.  **Cloud Connectivity**: Test the server settings logic and connection status reporting.
4.  **Functional Workflow**: Test single-view, swiping, mass selection, and system-level deletion prompts.
5.  **Memory Player**: Verify the "Stories" playback and Ken Burns animations.

## Proposed Testing Steps

### Phase 1: Environment & Deployment
- Deploy the latest version of the app to the emulator (`emulator-5554`).
- Grant `READ_MEDIA_IMAGES` and `READ_MEDIA_VIDEO` permissions via ADB to ensure immediate data access.

### Phase 2: Gallery & Navigation Test
- Launch the app.
- Take a screenshot of the **Photos** grid (verify date headers and cloud icons).
- Tap **Albums** tab via ADB coordinate. Capture screenshot.
- Tap **Memories** tab via ADB coordinate. Capture screenshot.

### Phase 3: Settings & Connection Test
- Navigate to **Settings**.
- Input mock connection details:
    - IP: `10.0.2.2` (Host computer)
    - Port: `3000`
- Click **Save & Connect**.
- Monitor `Logcat` for `GalleryApi` responses.
- Capture a screenshot of the **Connection Status** and **Storage Bar**.

### Phase 4: Media Interaction Test
- Go back to Gallery and tap the first photo.
- Capture full-screen viewer.
- Simulate a **Swipe Left** gesture. Capture the next item.
- Open **Detail Information** menu. Verify metadata in screenshot.

### Phase 5: Selection & System Integration
- Perform a **Long Press** on a gallery item.
- Select 2 items.
- Click **Delete**. Capture the **System Permission Dialog** (Android 10+ prompt).
- Click **Copy to...**. Verify the **System File Picker** (SAF) opens.

### Phase 6: Memory Player Test
- Navigate to **Memories**.
- Click a Flashback card.
- Verify playback start via Logcat and screenshot (check for Progress Bar).

## Reporting
I will provide a final report with screenshots and log excerpts for each failed or successful step.

## Verification Plan
- Successful completion of all phases without app crashes.
- Visual confirmation of the "Bubble Capsule" menu and "Cloud Icons" in screenshots.
