package com.csrainbow.galerycloud.ui.screens

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Cloud
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.csrainbow.galerycloud.data.local.ServerSettings
import com.csrainbow.galerycloud.ui.viewmodel.SettingsViewModel

import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    viewModel: SettingsViewModel = viewModel(),
    onBack: () -> Unit
) {
    val settings by viewModel.serverSettings.collectAsState()
    val isConnected by viewModel.isConnected.collectAsState()
    val storageInfo by viewModel.storageInfo.collectAsState()

    var ip by remember(settings) { mutableStateOf(settings.ip) }
    var port by remember(settings) { mutableStateOf(settings.port) }
    var username by remember(settings) { mutableStateOf(settings.username) }
    var password by remember(settings) { mutableStateOf(settings.password) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Settings") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .padding(padding)
                .padding(16.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Text("Server Configuration", style = MaterialTheme.typography.titleLarge)

            OutlinedTextField(
                value = ip,
                onValueChange = { ip = it },
                label = { Text("Server IP") },
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = port,
                onValueChange = { port = it },
                label = { Text("Port") },
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = username,
                onValueChange = { username = it },
                label = { Text("Username") },
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = password,
                onValueChange = { password = it },
                label = { Text("Password") },
                modifier = Modifier.fillMaxWidth(),
                visualTransformation = PasswordVisualTransformation(),
                singleLine = true
            )

            if (isConnected == true) {
                Button(
                    onClick = {
                        viewModel.logout()
                        onBack()
                    },
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error)
                ) {
                    Text("Logout")
                }
            } else {
                Button(
                    onClick = {
                        viewModel.saveAndCheck(ServerSettings(ip, port, username, password))
                    },
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text("Save & Connect")
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Connection Status
            Row(verticalAlignment = Alignment.CenterVertically) {
                val icon = if (isConnected == true) Icons.Default.Cloud else Icons.Default.CloudOff
                val tint = if (isConnected == true) Color.Green else Color.Red
                Icon(icon, contentDescription = null, tint = tint)
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = when (isConnected) {
                        true -> "Connected"
                        false -> "Disconnected"
                        null -> "Checking..."
                    },
                    color = tint,
                    fontWeight = FontWeight.Bold
                )
            }

            // Storage Info
            storageInfo?.let { info ->
                Text("Storage Capacity", style = MaterialTheme.typography.titleMedium)
                
                val progress = info.used.toFloat() / info.total.toFloat()
                val animatedProgress by animateFloatAsState(targetValue = progress)

                Column {
                    LinearProgressIndicator(
                        progress = { animatedProgress },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(12.dp)
                            .clip(RoundedCornerShape(6.dp)),
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(formatBytes(info.used) + " Used")
                        Text(formatBytes(info.total) + " Total")
                    }
                }
            }
        }
    }
}

fun formatBytes(bytes: Long): String {
    if (bytes <= 0) return "0 B"
    val units = arrayOf("B", "KB", "MB", "GB", "TB")
    val digitGroups = (Math.log10(bytes.toDouble()) / Math.log10(1024.0)).toInt()
    return String.format(Locale.getDefault(), "%.2f %s", bytes / Math.pow(1024.0, digitGroups.toDouble()), units[digitGroups])
}
