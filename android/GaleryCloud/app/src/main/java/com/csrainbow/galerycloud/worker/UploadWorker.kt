package com.csrainbow.galerycloud.worker

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.csrainbow.galerycloud.data.local.AppDatabase
import com.csrainbow.galerycloud.data.local.SyncStatusEntity
import com.csrainbow.galerycloud.data.repository.MediaRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.withContext

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
        if (!isNetworkAvailable()) {
            Log.d("UploadWorker", "No network, skipping sync.")
            return Result.retry()
        }

        val db = AppDatabase.getDatabase(applicationContext)
        val dao = db.syncStatusDao()
        val repository = MediaRepository(applicationContext, dao)
        val settings = settingsManager.serverSettings.first()

        if (settings.ip.isEmpty() || settings.port.isEmpty()) {
            Log.d("UploadWorker", "Settings incomplete, skipping sync.")
            return Result.failure()
        }
        val baseUrl = "http://${settings.ip}:${settings.port}"

        if (!checkServer(baseUrl, settings.username, settings.password)) {
            Log.d("UploadWorker", "Server unreachable, skipping sync.")
            return Result.retry()
        }

        return try {
            val mediaGroups = repository.fetchMediaGroupedByDate()
            val allMedia = mediaGroups.values.flatten()

            val unsyncedItems = allMedia.filter { item ->
                dao.getSyncStatus(item.id)?.status != "SYNCED"
            }

            Log.d("UploadWorker", "Found ${unsyncedItems.size} unsynced items.")
            val results = mutableListOf<SyncStatusEntity>()
            var hasFailures = false

            for (item in unsyncedItems) {
                if (!isNetworkAvailable()) {
                    Log.d("UploadWorker", "Network lost, stopping sync.")
                    hasFailures = true
                    break
                }
                Log.d("UploadWorker", "Uploading: ${item.name}")
                try {
                    val ok = withContext(Dispatchers.IO) {
                        val inputStream = applicationContext.contentResolver.openInputStream(item.uri) ?: return@withContext false
                        try {
                            val fileSize = applicationContext.contentResolver
                                .openAssetFileDescriptor(item.uri, "r")?.use { it.length } ?: -1L
                            apiService.uploadFileStreaming(
                                baseUrl, settings.username, settings.password,
                                item.name, fileSize, inputStream
                            )
                        } finally {
                            inputStream.close()
                        }
                    }
                    if (ok) {
                        Log.d("UploadWorker", "Uploaded successfully: ${item.name}")
                        results.add(SyncStatusEntity(item.id, "SYNCED"))
                    } else {
                        Log.e("UploadWorker", "Upload failed for: ${item.name}")
                        results.add(SyncStatusEntity(item.id, "FAILED"))
                        hasFailures = true
                    }
                } catch (e: Exception) {
                    Log.e("UploadWorker", "Error uploading ${item.name}", e)
                    results.add(SyncStatusEntity(item.id, "FAILED"))
                    hasFailures = true
                }
            }

            dao.insertAll(results)
            Log.d("UploadWorker", "Sync job finished. Failures: $hasFailures")
            if (hasFailures) Result.retry() else Result.success()
        } catch (e: Exception) {
            Log.e("UploadWorker", "Critical worker failure", e)
            Result.retry()
        }
    }

    private fun isNetworkAvailable(): Boolean {
        val cm = applicationContext.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager ?: return false
        val network = cm.activeNetwork ?: return false
        val caps = cm.getNetworkCapabilities(network) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private suspend fun checkServer(baseUrl: String, username: String, password: String): Boolean {
        return withContext(Dispatchers.IO) {
            apiService.testConnection(baseUrl, username, password)
        }
    }
}
