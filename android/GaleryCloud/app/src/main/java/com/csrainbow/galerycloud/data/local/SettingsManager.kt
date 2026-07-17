package com.csrainbow.galerycloud.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "settings")

class SettingsManager(private val context: Context) {

    companion object {
        val IP_KEY = stringPreferencesKey("server_ip")
        val PORT_KEY = stringPreferencesKey("server_port")
        val USERNAME_KEY = stringPreferencesKey("username")
        val PASSWORD_KEY = stringPreferencesKey("password")
    }

    val serverSettings: Flow<ServerSettings> = context.dataStore.data.map { preferences ->
        ServerSettings(
            ip = preferences[IP_KEY] ?: "",
            port = preferences[PORT_KEY] ?: "",
            username = preferences[USERNAME_KEY] ?: "",
            password = preferences[PASSWORD_KEY] ?: ""
        )
    }

    suspend fun saveSettings(settings: ServerSettings) {
        context.dataStore.edit { preferences ->
            preferences[IP_KEY] = settings.ip
            preferences[PORT_KEY] = settings.port
            preferences[USERNAME_KEY] = settings.username
            preferences[PASSWORD_KEY] = settings.password
        }
    }

    suspend fun clearSettings() {
        context.dataStore.edit { it.clear() }
    }
}
