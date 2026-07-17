package com.csrainbow.galerycloud.worker

import android.content.Context
import androidx.work.*
import java.util.concurrent.TimeUnit

class SyncManager(private val context: Context) {

    fun startAutoUpload() {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()

        val uploadRequest = PeriodicWorkRequestBuilder<UploadWorker>(
            1, TimeUnit.HOURS
        )
            .setConstraints(constraints)
            .addTag("auto_upload")
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            "auto_upload_work",
            ExistingPeriodicWorkPolicy.KEEP,
            uploadRequest
        )
    }

    fun triggerOneTimeUpload() {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()

        val uploadRequest = OneTimeWorkRequestBuilder<UploadWorker>()
            .setConstraints(constraints)
            .addTag("one_time_upload")
            .build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            "one_time_upload_work",
            ExistingWorkPolicy.REPLACE,
            uploadRequest
        )
    }

    fun cancelAllSync() {
        WorkManager.getInstance(context).cancelAllWorkByTag("auto_upload")
        WorkManager.getInstance(context).cancelUniqueWork("auto_upload_work")
        WorkManager.getInstance(context).cancelUniqueWork("one_time_upload_work")
    }
}
