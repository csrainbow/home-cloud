package com.csrainbow.galerycloud.worker

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.csrainbow.galerycloud.data.local.AppDatabase
import com.csrainbow.galerycloud.data.local.SyncStatusEntity
import com.csrainbow.galerycloud.data.repository.MediaRepository
import kotlinx.coroutines.flow.first

import com.csrainbow.galerycloud.data.local.SettingsManager
import com.csrainbow.galerycloud.data.remote.GalleryApiService
import kotlinx.coroutines.flow.first

import android.util.Log

class UploadWorker(
    context: Context,
    params: WorkerParameters
) : CoroutineWorker(context, params) {

    private val apiService = GalleryApiService()
    private val settingsManager = SettingsManager(context)

    override suspend fun doWork(): Result {
        Log.d("UploadWorker", "Starting sync job...")
        val db = AppDatabase.getDatabase(applicationContext)
        val dao = db.syncStatusDao()
        val repository = MediaRepository(applicationContext, dao)
        val settings = settingsManager.serverSettings.first()

        if (settings.ip.isEmpty() || settings.port.isEmpty()) {
            Log.d("UploadWorker", "Settings incomplete, skipping sync.")
            return Result.failure()
        }
        val baseUrl = "http://${settings.ip}:${settings.port}"
        
        try {
            val mediaGroups = repository.fetchMediaGroupedByDate()
            val allMedia = mediaGroups.values.flatten()
            
            val unsyncedItems = allMedia.filter { item ->
                dao.getSyncStatus(item.id)?.status != "SYNCED"
            }

            Log.d("UploadWorker", "Found ${unsyncedItems.size} unsynced items.")
            var hasFailures = false

            unsyncedItems.forEach { item ->
                try {
                    Log.d("UploadWorker", "Uploading: ${item.name}")
                    val inputStream = applicationContext.contentResolver.openInputStream(item.uri)
                    val bytes = inputStream?.readBytes()
                    if (bytes != null) {
                        val success = apiService.uploadFile(
                            baseUrl, 
                            settings.username, 
                            settings.password, 
                            item.name, 
                            bytes
                        )
                        if (success) {
                            Log.d("UploadWorker", "Uploaded successfully: ${item.name}")
                            dao.insertSyncStatus(SyncStatusEntity(item.id, "SYNCED"))
                        } else {
                            Log.e("UploadWorker", "Upload failed for: ${item.name}")
                            hasFailures = true
                        }
                    }
                    inputStream?.close()
                } catch (e: Exception) {
                    Log.e("UploadWorker", "Error uploading ${item.name}", e)
                    hasFailures = true
                }
            }
            
            Log.d("UploadWorker", "Sync job finished. Failures: $hasFailures")
            return if (hasFailures) Result.retry() else Result.success()
        } catch (e: Exception) {
            Log.e("UploadWorker", "Critical worker failure", e)
            return Result.retry()
        }
    }
}
