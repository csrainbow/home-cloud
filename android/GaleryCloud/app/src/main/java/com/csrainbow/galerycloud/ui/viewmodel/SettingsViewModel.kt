package com.csrainbow.galerycloud.ui.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.csrainbow.galerycloud.data.local.ServerSettings
import com.csrainbow.galerycloud.data.local.SettingsManager
import com.csrainbow.galerycloud.data.remote.GalleryApiService
import com.csrainbow.galerycloud.data.remote.StorageInfo
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch

import android.util.Log
import android.widget.Toast

import com.csrainbow.galerycloud.worker.SyncManager

class SettingsViewModel(application: Application) : AndroidViewModel(application) {
    private val context = application.applicationContext
    private val settingsManager = SettingsManager(application)
    private val apiService = GalleryApiService()
    private val syncManager = SyncManager(application)

    val serverSettings: StateFlow<ServerSettings> = settingsManager.serverSettings
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), ServerSettings())

    private val _storageInfo = MutableStateFlow<StorageInfo?>(null)
    val storageInfo: StateFlow<StorageInfo?> = _storageInfo

    private val _isConnected = MutableStateFlow<Boolean?>(null)
    val isConnected: StateFlow<Boolean?> = _isConnected

    init {
        viewModelScope.launch {
            serverSettings.collect { settings ->
                if (settings.ip.isNotEmpty() && settings.port.isNotEmpty()) {
                    checkConnection(settings = settings, isManual = false)
                }
            }
        }
    }

    fun saveAndCheck(settings: ServerSettings) {
        Log.d("SettingsVM", "Saving and checking: ${settings.ip}:${settings.port}")
        viewModelScope.launch {
            settingsManager.saveSettings(settings)
            checkConnection(settings = settings, isManual = true)
        }
    }

    fun checkConnection(settings: ServerSettings? = null, isManual: Boolean = true) {
        viewModelScope.launch {
            val s = settings ?: serverSettings.value
            if (s.ip.isEmpty() || s.port.isEmpty()) {
                Log.d("SettingsVM", "Settings incomplete, skipping connection check")
                return@launch
            }
            val baseUrl = "http://${s.ip}:${s.port}"
            val connected = apiService.testConnection(baseUrl, s.username, s.password)
            Log.d("SettingsVM", "Connected: $connected")
            _isConnected.value = connected
            if (connected) {
                try {
                    _storageInfo.value = apiService.getStorageInfo(baseUrl, s.username, s.password)
                    if (isManual) {
                        Toast.makeText(context, "Connected successfully!", Toast.LENGTH_SHORT).show()
                        syncManager.triggerOneTimeUpload()
                    }
                } catch (e: Exception) {
                    Log.e("SettingsVM", "Failed to fetch storage info", e)
                }
            } else {
                _storageInfo.value = null
                if (isManual) {
                    Toast.makeText(context, "Connection failed. Check IP/Port and Server.", Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    fun logout() {
        Log.d("SettingsVM", "Logging out, stopping sync...")
        viewModelScope.launch {
            syncManager.cancelAllSync()
            settingsManager.clearSettings()
            _isConnected.value = false
            _storageInfo.value = null
            Toast.makeText(context, "Disconnected & credentials cleared", Toast.LENGTH_SHORT).show()
        }
    }
}
