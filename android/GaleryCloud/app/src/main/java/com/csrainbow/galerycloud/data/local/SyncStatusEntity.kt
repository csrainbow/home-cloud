package com.csrainbow.galerycloud.data.local

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "sync_status")
data class SyncStatusEntity(
    @PrimaryKey val mediaId: Long,
    val status: String // "SYNCED", "NOT_SYNCED"
)
