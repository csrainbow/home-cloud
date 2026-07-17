package com.csrainbow.galerycloud.ui.viewmodel

import android.app.Application
import android.widget.Toast
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.csrainbow.galerycloud.data.local.AppDatabase
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

    private val _uploadProgress = MutableStateFlow<Pair<Int, Int>?>(null) // current, total
    val uploadProgress: StateFlow<Pair<Int, Int>?> = _uploadProgress

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

    fun uploadAllUnsynced() {
        viewModelScope.launch {
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

            _isUploading.value = true
            _uploadProgress.value = Pair(0, unsynced.size)
            val baseUrl = "http://${settings.ip}:${settings.port}"
            var current = 0

            for (item in unsynced) {
                try {
                    val bytes = withContext(Dispatchers.IO) {
                        getApplication<Application>().contentResolver.openInputStream(item.uri)?.use { it.readBytes() }
                    }
                    if (bytes != null) {
                        val ok = apiService.uploadFile(baseUrl, settings.username, settings.password, item.name, bytes)
                        if (ok) {
                            syncStatusDao.insertSyncStatus(SyncStatusEntity(item.id, "SYNCED"))
                        }
                    }
                } catch (e: Exception) {
                    syncStatusDao.insertSyncStatus(SyncStatusEntity(item.id, "FAILED"))
                }
                current++
                _uploadProgress.value = Pair(current, unsynced.size)
            }

            Toast.makeText(getApplication(), "Upload selesai", Toast.LENGTH_SHORT).show()
            _uploadProgress.value = null
            _isUploading.value = false
        }
    }

    fun uploadSelectedNow() {
        viewModelScope.launch {
            val settings = settingsManager.serverSettings.first()
            if (settings.ip.isEmpty() || settings.port.isEmpty()) {
                Toast.makeText(getApplication(), "Configure server in Settings first", Toast.LENGTH_SHORT).show()
                return@launch
            }
            val allItems = _mediaItems.value.values.flatten()
            val selectedItems = allItems.filter { _selectedIds.value.contains(it.id) }
            if (selectedItems.isEmpty()) return@launch

            _isUploading.value = true
            _uploadProgress.value = "Preparing..."
            val baseUrl = "http://${settings.ip}:${settings.port}"
            var successCount = 0
            val total = selectedItems.size

            for ((idx, item) in selectedItems.withIndex()) {
                _uploadProgress.value = "Uploading ${idx+1} of $total"
                try {
                    syncStatusDao.insertSyncStatus(SyncStatusEntity(item.id, "SYNCING"))
                    val bytes = withContext(Dispatchers.IO) {
                        getApplication<Application>().contentResolver.openInputStream(item.uri)?.use { it.readBytes() }
                    }
                    if (bytes != null) {
                        val ok = apiService.uploadFile(baseUrl, settings.username, settings.password, item.name, bytes)
                        if (ok) {
                            syncStatusDao.insertSyncStatus(SyncStatusEntity(item.id, "SYNCED"))
                            successCount++
                        }
                    }
                } catch (e: Exception) {
                    syncStatusDao.insertSyncStatus(SyncStatusEntity(item.id, "FAILED"))
                }
            }

            _uploadProgress.value = ""
            Toast.makeText(getApplication(), "$successCount/$total uploaded", Toast.LENGTH_SHORT).show()
            clearSelection()
            _isUploading.value = false
        }
    }

    fun uploadAllUnsynced() {
        viewModelScope.launch {
            val settings = settingsManager.serverSettings.first()
            if (settings.ip.isEmpty() || settings.port.isEmpty()) {
                Toast.makeText(getApplication(), "Configure server in Settings first", Toast.LENGTH_SHORT).show()
                return@launch
            }
            val allItems = _mediaItems.value.values.flatten()
            // Filter items that are NOT already synced
            val unsynced = allItems.filter { it.syncStatus != SyncStatus.SYNCED }
            if (unsynced.isEmpty()) {
                Toast.makeText(getApplication(), "Semua file sudah tersimpan", Toast.LENGTH_SHORT).show()
                return@launch
            }

            _isUploading.value = true
            _uploadProgress.value = "Preparing..."
            val baseUrl = "http://${settings.ip}:${settings.port}"
            var successCount = 0
            val total = unsynced.size

            for ((idx, item) in unsynced.withIndex()) {
                _uploadProgress.value = "Uploading ${idx+1} of $total"
                try {
                    syncStatusDao.insertSyncStatus(SyncStatusEntity(item.id, "SYNCING"))
                    val bytes = withContext(Dispatchers.IO) {
                        getApplication<Application>().contentResolver.openInputStream(item.uri)?.use { it.readBytes() }
                    }
                    if (bytes != null) {
                        val ok = apiService.uploadFile(baseUrl, settings.username, settings.password, item.name, bytes)
                        if (ok) {
                            syncStatusDao.insertSyncStatus(SyncStatusEntity(item.id, "SYNCED"))
                            successCount++
                        }
                    }
                } catch (e: Exception) {
                    syncStatusDao.insertSyncStatus(SyncStatusEntity(item.id, "FAILED"))
                }
            }

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
