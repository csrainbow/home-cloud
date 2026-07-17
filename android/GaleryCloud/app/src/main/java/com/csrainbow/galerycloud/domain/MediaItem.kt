package com.csrainbow.galerycloud.domain

import android.net.Uri

data class MediaItem(
    val id: Long,
    val uri: Uri,
    val name: String,
    val dateAdded: Long,
    val size: Long,
    val mimeType: String,
    val albumName: String = "",
    val duration: Long? = null, // For videos
    val isVideo: Boolean = false,
    val syncStatus: SyncStatus = SyncStatus.NOT_SYNCED
)

enum class SyncStatus {
    NOT_SYNCED, SYNCED, SYNCING, FAILED
}
