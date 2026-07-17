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
        // Automatically check connection whenever settings change to a valid state
        viewModelScope.launch {
            serverSettings.collect { settings ->
                if (settings.ip.isNotEmpty() && settings.port.isNotEmpty()) {
                    checkConnection(isManual = false)
                }
            }
        }
    }

    fun saveSettings(settings: ServerSettings) {
        Log.d("SettingsVM", "Saving settings: ${settings.ip}:${settings.port}")
        viewModelScope.launch {
            settingsManager.saveSettings(settings)
            // checkConnection is triggered by the collector in init
        }
    }

    fun checkConnection(isManual: Boolean = true) {
        viewModelScope.launch {
            val settings = serverSettings.value
            Log.d("SettingsVM", "Checking connection for ${settings.ip}:${settings.port}")
            if (settings.ip.isNotEmpty() && settings.port.isNotEmpty()) {
                val baseUrl = "http://${settings.ip}:${settings.port}"
                val connected = apiService.testConnection(baseUrl, settings.username, settings.password)
                Log.d("SettingsVM", "Connected: $connected")
                _isConnected.value = connected
                if (connected) {
                    try {
                        _storageInfo.value = apiService.getStorageInfo(baseUrl, settings.username, settings.password)
                        if (isManual) {
                            Toast.makeText(context, "Connected! Starting sync...", Toast.LENGTH_SHORT).show()
                            syncManager.triggerOneTimeUpload()
                            syncManager.startAutoUpload()
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
            } else {
                Log.d("SettingsVM", "Settings incomplete, skipping connection check")
            }
        }
    }

    fun logout() {
        Log.d("SettingsVM", "Logging out, stopping sync...")
        viewModelScope.launch {
            syncManager.cancelAllSync()
            _isConnected.value = false
            _storageInfo.value = null
            Toast.makeText(context, "Disconnected & sync stopped", Toast.LENGTH_SHORT).show()
        }
    }
}
