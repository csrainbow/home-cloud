package com.csrainbow.galerycloud.ui.viewmodel

import android.app.Application
import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.widget.Toast
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.csrainbow.galerycloud.data.local.AppDatabase
import com.csrainbow.galerycloud.data.local.ServerSettings
import com.csrainbow.galerycloud.data.local.SettingsManager
import com.csrainbow.galerycloud.data.local.SyncStatusEntity
import com.csrainbow.galerycloud.data.remote.GalleryApiService
import com.csrainbow.galerycloud.data.repository.MediaRepository
import com.csrainbow.galerycloud.domain.MediaItem
import com.csrainbow.galerycloud.domain.SyncStatus
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.flow.collectLatest
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.withContext

enum class GalleryTab {
    PHOTOS, ALBUMS, MEMORIES
}

class GalleryViewModel(application: Application) : AndroidViewModel(application) {
    private val repository: MediaRepository
    private val apiService = GalleryApiService()
    private val settingsManager = SettingsManager(application)
    private val db = AppDatabase.getDatabase(application)
    private val syncStatusDao = db.syncStatusDao()

    private val _currentTab = MutableStateFlow(GalleryTab.PHOTOS)
    val currentTab: StateFlow<GalleryTab> = _currentTab

    private val _mediaItems = MutableStateFlow<Map<String, List<MediaItem>>>(emptyMap())
    val mediaItems: StateFlow<Map<String, List<MediaItem>>> = _mediaItems

    private val _albums = MutableStateFlow<Map<String, List<MediaItem>>>(emptyMap())
    val albums: StateFlow<Map<String, List<MediaItem>>> = _albums

    private val _memories = MutableStateFlow<List<MediaItem>>(emptyList())
    val memories: StateFlow<List<MediaItem>> = _memories

    private val _isSelectionMode = MutableStateFlow(false)
    val isSelectionMode: StateFlow<Boolean> = _isSelectionMode

    private val _selectedIds = MutableStateFlow<Set<Long>>(emptySet())
    val selectedIds: StateFlow<Set<Long>> = _selectedIds

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading

    private val _isUploading = MutableStateFlow(false)
    val isUploading: StateFlow<Boolean> = _isUploading

    private val _uploadProgress = MutableStateFlow("")
    val uploadProgress: StateFlow<String> = _uploadProgress

    private val _pendingDeleteIntent = MutableStateFlow<android.content.IntentSender?>(null)
    val pendingDeleteIntent: StateFlow<android.content.IntentSender?> = _pendingDeleteIntent

    init {
        repository = MediaRepository(getApplication(), db.syncStatusDao())
        observeMedia()
    }

    private fun observeMedia() {
        viewModelScope.launch {
            _isLoading.value = true
            repository.getMediaFlow().collectLatest { groupedItems ->
                _mediaItems.value = groupedItems
                val allItems = groupedItems.values.flatten()
                _albums.value = allItems.groupBy { it.albumName }
                _memories.value = allItems.shuffled().take(10)
                _isLoading.value = false
            }
        }
    }

    fun setTab(tab: GalleryTab) {
        _currentTab.value = tab
        if (_isSelectionMode.value) clearSelection()
    }

    fun toggleSelection(id: Long) {
        _isSelectionMode.value = true
        _selectedIds.update { current ->
            if (current.contains(id)) {
                val next = current - id
                if (next.isEmpty()) _isSelectionMode.value = false
                next
            } else {
                current + id
            }
        }
    }

    fun enterSelectionMode(id: Long) {
        _isSelectionMode.value = true
        _selectedIds.value = setOf(id)
    }

    fun clearSelection() {
        _isSelectionMode.value = false
        _selectedIds.value = emptySet()
    }

    private fun isNetworkAvailable(): Boolean {
        val cm = getApplication<Application>().getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager ?: return false
        val network = cm.activeNetwork ?: return false
        val caps = cm.getNetworkCapabilities(network) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private suspend fun checkServer(baseUrl: String, username: String, password: String): Boolean {
        return withContext(Dispatchers.IO) {
            apiService.testConnection(baseUrl, username, password)
        }
    }

    private suspend fun uploadItem(baseUrl: String, settings: ServerSettings, item: MediaItem, contentResolver: android.content.ContentResolver): Boolean {
        return try {
            val fileSize = withContext(Dispatchers.IO) {
                contentResolver.openAssetFileDescriptor(item.uri, "r")?.use { it.length } ?: -1L
            }
            withContext(Dispatchers.IO) {
                val inputStream = contentResolver.openInputStream(item.uri) ?: return@withContext false
                try {
                    apiService.uploadFileStreaming(
                        baseUrl, settings.username, settings.password,
                        item.name, fileSize, inputStream
                    )
                } finally {
                    inputStream.close()
                }
            }
        } catch (e: Exception) {
            false
        }
    }

    fun uploadSelectedNow() {
        viewModelScope.launch {
            if (!isNetworkAvailable()) {
                Toast.makeText(getApplication(), "No internet connection", Toast.LENGTH_LONG).show()
                return@launch
            }
            val settings = settingsManager.serverSettings.first()
            if (settings.ip.isEmpty() || settings.port.isEmpty()) {
                Toast.makeText(getApplication(), "Configure server in Settings first", Toast.LENGTH_SHORT).show()
                return@launch
            }
            val allItems = _mediaItems.value.values.flatten()
            val selectedItems = allItems.filter { _selectedIds.value.contains(it.id) }
            if (selectedItems.isEmpty()) return@launch

            val baseUrl = "http://${settings.ip}:${settings.port}"
            if (!checkServer(baseUrl, settings.username, settings.password)) {
                Toast.makeText(getApplication(), "Can't connect to server. Check Settings.", Toast.LENGTH_LONG).show()
                return@launch
            }

            _isUploading.value = true
            _uploadProgress.value = "Uploading..."
            var successCount = 0
            val total = selectedItems.size
            val contentResolver = getApplication<Application>().contentResolver
            val results = mutableListOf<SyncStatusEntity>()

            for ((idx, item) in selectedItems.withIndex()) {
                val ok = uploadItem(baseUrl, settings, item, contentResolver)
                if (ok) {
                    results.add(SyncStatusEntity(item.id, "SYNCED"))
                    successCount++
                } else {
                    results.add(SyncStatusEntity(item.id, "FAILED"))
                }
            }

            syncStatusDao.insertAll(results)
            _uploadProgress.value = ""
            Toast.makeText(getApplication(), "$successCount/$total uploaded", Toast.LENGTH_SHORT).show()
            clearSelection()
            _isUploading.value = false
        }
    }

    fun uploadAllUnsynced() {
        viewModelScope.launch {
            if (!isNetworkAvailable()) {
                Toast.makeText(getApplication(), "No internet connection", Toast.LENGTH_LONG).show()
                return@launch
            }
            val settings = settingsManager.serverSettings.first()
            if (settings.ip.isEmpty() || settings.port.isEmpty()) {
                Toast.makeText(getApplication(), "Configure server in Settings first", Toast.LENGTH_SHORT).show()
                return@launch
            }
            val allItems = _mediaItems.value.values.flatten()
            val unsynced = allItems.filter { it.syncStatus != SyncStatus.SYNCED }
            if (unsynced.isEmpty()) {
                Toast.makeText(getApplication(), "Semua file sudah tersimpan", Toast.LENGTH_SHORT).show()
                return@launch
            }

            val baseUrl = "http://${settings.ip}:${settings.port}"
            if (!checkServer(baseUrl, settings.username, settings.password)) {
                Toast.makeText(getApplication(), "Can't connect to server. Check Settings.", Toast.LENGTH_LONG).show()
                return@launch
            }

            _isUploading.value = true
            _uploadProgress.value = "Uploading..."
            var successCount = 0
            val total = unsynced.size
            val contentResolver = getApplication<Application>().contentResolver
            val results = mutableListOf<SyncStatusEntity>()

            for ((idx, item) in unsynced.withIndex()) {
                val ok = uploadItem(baseUrl, settings, item, contentResolver)
                if (ok) {
                    results.add(SyncStatusEntity(item.id, "SYNCED"))
                    successCount++
                } else {
                    results.add(SyncStatusEntity(item.id, "FAILED"))
                }
            }

            syncStatusDao.insertAll(results)
            _uploadProgress.value = ""
            Toast.makeText(getApplication(), "$successCount/$total tersimpan", Toast.LENGTH_SHORT).show()
            _isUploading.value = false
        }
    }

    fun deleteMedia(item: MediaItem) {
        viewModelScope.launch {
            val intentSender = repository.deleteMedia(listOf(item))
            if (intentSender != null) {
                _pendingDeleteIntent.value = intentSender
            }
        }
    }

    fun deleteSelected() {
        viewModelScope.launch {
            val allItemsList = _mediaItems.value.values.flatten()
            val selected = allItemsList.filter { _selectedIds.value.contains(it.id) }
            val intentSender = repository.deleteMedia(selected)
            if (intentSender != null) {
                _pendingDeleteIntent.value = intentSender
            }
        }
    }

    fun clearPendingDelete() {
        _pendingDeleteIntent.value = null
    }

    fun copyMediaToFolder(item: MediaItem, uri: android.net.Uri) {
        viewModelScope.launch {
            _isLoading.value = true
            repository.copyMediaToUri(listOf(item), uri)
            _isLoading.value = false
        }
    }

    fun moveMediaToFolder(item: MediaItem, uri: android.net.Uri) {
        viewModelScope.launch {
            _isLoading.value = true
            val intentSender = repository.moveMediaToUri(listOf(item), uri)
            if (intentSender != null) {
                _pendingDeleteIntent.value = intentSender
            }
            _isLoading.value = false
        }
    }

    fun copySelectedToFolder(uri: android.net.Uri) {
        viewModelScope.launch {
            _isLoading.value = true
            val allItemsList = _mediaItems.value.values.flatten()
            val selected = allItemsList.filter { _selectedIds.value.contains(it.id) }
            repository.copyMediaToUri(selected, uri)
            clearSelection()
            _isLoading.value = false
        }
    }

    fun moveSelectedToFolder(uri: android.net.Uri) {
        viewModelScope.launch {
            _isLoading.value = true
            val allItemsList = _mediaItems.value.values.flatten()
            val selected = allItemsList.filter { _selectedIds.value.contains(it.id) }
            val intentSender = repository.moveMediaToUri(selected, uri)
            if (intentSender != null) {
                _pendingDeleteIntent.value = intentSender
            }
            clearSelection()
            _isLoading.value = false
        }
    }

    fun loadMedia() {
        observeMedia()
    }
}
