package com.csrainbow.galerycloud.data.repository

import android.app.RecoverableSecurityException
import android.content.ContentValues
import android.content.ContentUris
import android.content.Context
import android.content.IntentSender
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.provider.MediaStore
import androidx.documentfile.provider.DocumentFile
import com.csrainbow.galerycloud.data.local.SyncStatusDao
import com.csrainbow.galerycloud.domain.MediaItem
import com.csrainbow.galerycloud.domain.SyncStatus
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream
import java.text.SimpleDateFormat
import java.util.*

import kotlinx.coroutines.flow.*

private const val VIDEO_ID_OFFSET = 1_000_000_000_000L

class MediaRepository(
    private val context: Context,
    private val syncStatusDao: SyncStatusDao
) {

    fun getMediaFlow(): Flow<Map<String, List<MediaItem>>> = flow {
        // Initial fetch of media from MediaStore
        val mediaFromStore = fetchMediaStoreItems()
        emit(mediaFromStore)
    }.combine(syncStatusDao.getAllSyncStatusFlow()) { mediaItems, syncEntities ->
        val syncMap = syncEntities.associateBy { it.mediaId }
        val dateFormat = SimpleDateFormat("MMMM dd, yyyy", Locale.getDefault())
        
        mediaItems.map { item ->
            val statusStr = syncMap[item.id]?.status ?: "NOT_SYNCED"
            val status = try {
                SyncStatus.valueOf(statusStr)
            } catch (e: Exception) {
                SyncStatus.NOT_SYNCED
            }
            item.copy(syncStatus = status)
        }
        .groupBy { dateFormat.format(Date(it.dateAdded * 1000)) }
    }.flowOn(Dispatchers.IO)

    private suspend fun fetchMediaStoreItems(): List<MediaItem> = withContext(Dispatchers.IO) {
        val mediaList = mutableListOf<MediaItem>()
        val projection = arrayOf(
            MediaStore.MediaColumns._ID,
            MediaStore.MediaColumns.DISPLAY_NAME,
            MediaStore.MediaColumns.DATE_ADDED,
            MediaStore.MediaColumns.SIZE,
            MediaStore.MediaColumns.MIME_TYPE,
            MediaStore.MediaColumns.BUCKET_DISPLAY_NAME,
            MediaStore.Video.Media.DURATION
        )
        val sortOrder = "${MediaStore.MediaColumns.DATE_ADDED} DESC"

        listOf(
            MediaStore.Images.Media.EXTERNAL_CONTENT_URI,
            MediaStore.Video.Media.EXTERNAL_CONTENT_URI
        ).forEach { uri ->
            context.contentResolver.query(uri, projection, null, null, sortOrder)?.use { cursor ->
                val idColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns._ID)
                val nameColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.DISPLAY_NAME)
                val dateColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.DATE_ADDED)
                val sizeColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.SIZE)
                val mimeColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.MIME_TYPE)
                val bucketColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.BUCKET_DISPLAY_NAME)
                val durationColumn = cursor.getColumnIndex(MediaStore.Video.Media.DURATION)

                while (cursor.moveToNext()) {
                    val originalId = cursor.getLong(idColumn)
                    val id = if (uri == MediaStore.Video.Media.EXTERNAL_CONTENT_URI) originalId + VIDEO_ID_OFFSET else originalId
                    val name = cursor.getString(nameColumn)
                    val date = cursor.getLong(dateColumn)
                    val size = cursor.getLong(sizeColumn)
                    val mime = cursor.getString(mimeColumn)
                    val album = cursor.getString(bucketColumn) ?: "Unknown"
                    val duration = if (durationColumn != -1) cursor.getLong(durationColumn) else null
                    val contentUri = ContentUris.withAppendedId(uri, originalId)
                    mediaList.add(
                        MediaItem(id, contentUri, name, date, size, mime, album, duration, mime.startsWith("video"))
                    )
                }
            }
        }
        mediaList.sortByDescending { it.dateAdded }
        mediaList
    }

    suspend fun fetchMediaGroupedByDate(): Map<String, List<MediaItem>> = withContext(Dispatchers.IO) {
        val mediaList = mutableListOf<MediaItem>()
        val syncStatuses = syncStatusDao.getAllSyncStatus().associateBy { it.mediaId }

        val projection = arrayOf(
            MediaStore.MediaColumns._ID,
            MediaStore.MediaColumns.DISPLAY_NAME,
            MediaStore.MediaColumns.DATE_ADDED,
            MediaStore.MediaColumns.SIZE,
            MediaStore.MediaColumns.MIME_TYPE,
            MediaStore.MediaColumns.BUCKET_DISPLAY_NAME,
            MediaStore.Video.Media.DURATION
        )

        val sortOrder = "${MediaStore.MediaColumns.DATE_ADDED} DESC"

        val query = { uri: android.net.Uri ->
            context.contentResolver.query(
                uri,
                projection,
                null,
                null,
                sortOrder
            )
        }

        listOf(
            MediaStore.Images.Media.EXTERNAL_CONTENT_URI,
            MediaStore.Video.Media.EXTERNAL_CONTENT_URI
        ).forEach { uri ->
            query(uri)?.use { cursor ->
                val idColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns._ID)
                val nameColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.DISPLAY_NAME)
                val dateColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.DATE_ADDED)
                val sizeColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.SIZE)
                val mimeColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.MIME_TYPE)
                val bucketColumn = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.BUCKET_DISPLAY_NAME)
                val durationColumn = cursor.getColumnIndex(MediaStore.Video.Media.DURATION)

                while (cursor.moveToNext()) {
                    val originalId = cursor.getLong(idColumn)
                    val id = if (uri == MediaStore.Video.Media.EXTERNAL_CONTENT_URI) originalId + VIDEO_ID_OFFSET else originalId
                    val name = cursor.getString(nameColumn)
                    val date = cursor.getLong(dateColumn)
                    val size = cursor.getLong(sizeColumn)
                    val mime = cursor.getString(mimeColumn)
                    val album = cursor.getString(bucketColumn) ?: "Unknown"
                    val duration = if (durationColumn != -1) cursor.getLong(durationColumn) else null
                    val contentUri = ContentUris.withAppendedId(uri, originalId)
                    val isVideo = mime.startsWith("video")

                    val statusStr = syncStatuses[id]?.status ?: "NOT_SYNCED"
                    val status = SyncStatus.valueOf(statusStr)

                    mediaList.add(
                        MediaItem(id, contentUri, name, date, size, mime, album, duration, isVideo, status)
                    )
                }
            }
        }

        mediaList.sortByDescending { it.dateAdded }

        val dateFormat = SimpleDateFormat("MMMM dd, yyyy", Locale.getDefault())
        mediaList.groupBy { dateFormat.format(Date(it.dateAdded * 1000)) }
    }

    suspend fun deleteMedia(items: List<MediaItem>): IntentSender? = withContext(Dispatchers.IO) {
        if (items.isEmpty()) return@withContext null

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                // Android 11+ way
                val uris = items.map { it.uri }
                return@withContext MediaStore.createDeleteRequest(context.contentResolver, uris).intentSender
            } else {
                // Android 10 way (Single item at a time often required for security exception)
                items.forEach { item ->
                    try {
                        context.contentResolver.delete(item.uri, null, null)
                        syncStatusDao.deleteSyncStatus(item.id)
                    } catch (securityException: SecurityException) {
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q && securityException is RecoverableSecurityException) {
                            return@withContext securityException.userAction.actionIntent.intentSender
                        } else throw securityException
                    }
                }
            }
            null
        } catch (e: Exception) {
            null
        }
    }

    suspend fun copyMediaToUri(items: List<MediaItem>, targetDirUri: Uri): Boolean = withContext(Dispatchers.IO) {
        val root = DocumentFile.fromTreeUri(context, targetDirUri) ?: return@withContext false
        var success = true
        
        items.forEach { item ->
            try {
                val newFile = root.createFile(item.mimeType, item.name) ?: return@withContext false
                context.contentResolver.openInputStream(item.uri)?.use { input ->
                    context.contentResolver.openOutputStream(newFile.uri)?.use { output ->
                        input.copyTo(output)
                    }
                }
            } catch (e: Exception) {
                success = false
            }
        }
        success
    }

    suspend fun moveMediaToUri(items: List<MediaItem>, targetDirUri: Uri): IntentSender? {
        val copied = copyMediaToUri(items, targetDirUri)
        return if (copied) {
            deleteMedia(items)
        } else {
            null
        }
    }
}
