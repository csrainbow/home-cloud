package com.csrainbow.galerycloud.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query

import kotlinx.coroutines.flow.Flow

@Dao
interface SyncStatusDao {
    @Query("SELECT * FROM sync_status")
    fun getAllSyncStatusFlow(): Flow<List<SyncStatusEntity>>

    @Query("SELECT * FROM sync_status")
    suspend fun getAllSyncStatus(): List<SyncStatusEntity>

    @Query("SELECT * FROM sync_status WHERE mediaId = :mediaId")
    suspend fun getSyncStatus(mediaId: Long): SyncStatusEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertSyncStatus(syncStatus: SyncStatusEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(syncStatuses: List<SyncStatusEntity>)

    @Query("DELETE FROM sync_status WHERE mediaId = :mediaId")
    suspend fun deleteSyncStatus(mediaId: Long)
}
