package com.csrainbow.galerycloud.ui.screens

import android.app.WallpaperManager
import android.content.Intent
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.IntentSenderRequest
import androidx.activity.result.contract.ActivityResultContracts
import android.widget.Toast
import androidx.annotation.OptIn
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.media3.common.MediaItem as ExoMediaItem
import androidx.media3.common.util.UnstableApi
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.ui.PlayerView
import coil.compose.AsyncImage
import com.csrainbow.galerycloud.domain.MediaItem
import com.csrainbow.galerycloud.ui.viewmodel.GalleryViewModel
import java.text.SimpleDateFormat
import java.util.*

@OptIn(UnstableApi::class)
@Composable
fun MediaViewerScreen(
    mediaId: Long,
    viewModel: GalleryViewModel = viewModel(),
    onBack: () -> Unit
) {
    val context = LocalContext.current
    val mediaItems by viewModel.mediaItems.collectAsState()
    val allItems = remember(mediaItems) { mediaItems.values.flatten() }
    
    val initialPage = remember(allItems) { 
        allItems.indexOfFirst { it.id == mediaId }.coerceAtLeast(0) 
    }
    
    val pagerState = rememberPagerState(initialPage = initialPage, pageCount = { allItems.size })
    val pendingDelete by viewModel.pendingDeleteIntent.collectAsState()

    // SAF Launchers
    val copyLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocumentTree()) { uri ->
        val current = allItems.getOrNull(pagerState.currentPage)
        if (current != null && uri != null) {
            viewModel.copyMediaToFolder(current, uri)
            Toast.makeText(context, "File copied", Toast.LENGTH_SHORT).show()
        }
    }
    val moveLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocumentTree()) { uri ->
        val current = allItems.getOrNull(pagerState.currentPage)
        if (current != null && uri != null) {
            viewModel.moveMediaToFolder(current, uri)
            Toast.makeText(context, "File moved", Toast.LENGTH_SHORT).show()
        }
    }

    // Delete Permission Launcher
    val deleteLauncher = rememberLauncherForActivityResult(ActivityResultContracts.StartIntentSenderForResult()) { result ->
        if (result.resultCode == android.app.Activity.RESULT_OK) {
            viewModel.loadMedia()
        }
        viewModel.clearPendingDelete()
    }

    LaunchedEffect(pendingDelete) {
        pendingDelete?.let {
            deleteLauncher.launch(IntentSenderRequest.Builder(it).build())
        }
    }
    
    var showDeleteDialog by remember { mutableStateOf(false) }
    var showInfoDialog by remember { mutableStateOf(false) }
    var showMoreMenu by remember { mutableStateOf(false) }

    if (allItems.isEmpty()) {
        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            Text("No media found", color = Color.White)
        }
        return
    }

    val currentItem = allItems.getOrNull(pagerState.currentPage) ?: return

    Box(modifier = Modifier.fillMaxSize().background(Color.Black)) {
        HorizontalPager(
            state = pagerState,
            modifier = Modifier.fillMaxSize(),
            pageSpacing = 16.dp
        ) { page ->
            val item = allItems[page]
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                if (item.isVideo) {
                    VideoPlayer(uri = item.uri)
                } else {
                    AsyncImage(
                        model = item.uri,
                        contentDescription = item.name,
                        modifier = Modifier.fillMaxSize(),
                        contentScale = ContentScale.Fit
                    )
                }
            }
        }

        // Top Back Button (Fixed Position)
        Box(
            modifier = Modifier
                .statusBarsPadding()
                .padding(16.dp)
                .align(Alignment.TopStart)
        ) {
            Surface(
                onClick = onBack,
                shape = CircleShape,
                color = Color.Black.copy(alpha = 0.4f),
                contentColor = Color.White
            ) {
                Icon(
                    Icons.Default.ArrowBack,
                    contentDescription = "Back",
                    modifier = Modifier.padding(12.dp)
                )
            }
        }

        // Bottom Floating Capsule Menu
        Box(
            modifier = Modifier
                .navigationBarsPadding()
                .padding(bottom = 48.dp) // Lifted from bottom
                .align(Alignment.BottomCenter)
        ) {
            Surface(
                shape = RoundedCornerShape(50),
                color = Color.Black.copy(alpha = 0.7f), // Semi-transparent different from background
                contentColor = Color.White,
                tonalElevation = 8.dp,
                shadowElevation = 8.dp
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 20.dp, vertical = 4.dp),
                    horizontalArrangement = Arrangement.spacedBy(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    IconButton(onClick = { shareMedia(context, currentItem) }) {
                        Icon(Icons.Default.Share, "Share")
                    }
                    IconButton(onClick = { editMedia(context, currentItem) }) {
                        Icon(Icons.Default.Edit, "Edit")
                    }
                    IconButton(onClick = { showDeleteDialog = true }) {
                        Icon(Icons.Default.Delete, "Delete")
                    }
                    Box {
                        IconButton(onClick = { showMoreMenu = true }) {
                            Icon(Icons.Default.MoreVert, "More")
                        }
                        DropdownMenu(
                            expanded = showMoreMenu,
                            onDismissRequest = { showMoreMenu = false }
                        ) {
                            DropdownMenuItem(
                                text = { Text("Copy to...") },
                                leadingIcon = { Icon(Icons.Default.ContentCopy, null) },
                                onClick = { 
                                    showMoreMenu = false
                                    copyLauncher.launch(null)
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("Move to...") },
                                leadingIcon = { Icon(Icons.Default.DriveFileMove, null) },
                                onClick = { 
                                    showMoreMenu = false
                                    moveLauncher.launch(null)
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("Set as Wallpaper") },
                                leadingIcon = { Icon(Icons.Default.Wallpaper, null) },
                                onClick = { 
                                    showMoreMenu = false
                                    setAsWallpaper(context, currentItem)
                                }
                            )
                            DropdownMenuItem(
                                text = { Text("Detail Information") },
                                leadingIcon = { Icon(Icons.Default.Info, null) },
                                onClick = { 
                                    showMoreMenu = false
                                    showInfoDialog = true 
                                }
                            )
                        }
                    }
                }
            }
        }
    }

    // Dialogs
    if (showDeleteDialog) {
        AlertDialog(
            onDismissRequest = { showDeleteDialog = false },
            title = { Text("Delete from this device?") },
            text = { Text("This will permanently remove the item from your phone. Files already synced to the cloud will remain safe in your account.") },
            confirmButton = {
                TextButton(onClick = {
                    showDeleteDialog = false
                    viewModel.deleteMedia(currentItem)
                    if (allItems.size <= 1) onBack()
                }) {
                    Text("Delete", color = Color.Red)
                }
            },
            dismissButton = {
                TextButton(onClick = { showDeleteDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }

    if (showInfoDialog) {
        MediaInfoDialog(item = currentItem, onDismiss = { showInfoDialog = false })
    }
}

@Composable
fun MediaInfoDialog(item: MediaItem, onDismiss: () -> Unit) {
    val dateFormat = SimpleDateFormat("EEEE, dd MMM yyyy HH:mm", Locale.getDefault())
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Detail Information") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                InfoRow("Name", item.name)
                InfoRow("Date", dateFormat.format(Date(item.dateAdded * 1000)))
                InfoRow("Type", item.mimeType)
                InfoRow("Size", formatFileSize(item.size))
                InfoRow("Path", item.uri.toString())
            }
        },
        confirmButton = {
            Button(onClick = onDismiss) { Text("Close") }
        }
    )
}

@Composable
fun InfoRow(label: String, value: String) {
    Column {
        Text(label, fontWeight = FontWeight.Bold, fontSize = 12.sp, color = Color.Gray)
        Text(value, fontSize = 14.sp)
    }
}

private fun shareMedia(context: android.content.Context, item: MediaItem) {
    val intent = Intent(Intent.ACTION_SEND).apply {
        type = item.mimeType
        putExtra(Intent.EXTRA_STREAM, item.uri)
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }
    context.startActivity(Intent.createChooser(intent, "Share Media"))
}

private fun editMedia(context: android.content.Context, item: MediaItem) {
    try {
        val intent = Intent(Intent.ACTION_EDIT).apply {
            setDataAndType(item.uri, item.mimeType)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(intent)
    } catch (e: Exception) {
        Toast.makeText(context, "No editor available", Toast.LENGTH_SHORT).show()
    }
}

private fun setAsWallpaper(context: android.content.Context, item: MediaItem) {
    try {
        val intent = Intent(Intent.ACTION_ATTACH_DATA).apply {
            setDataAndType(item.uri, item.mimeType)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            putExtra("mimeType", item.mimeType)
        }
        context.startActivity(Intent.createChooser(intent, "Set as Wallpaper"))
    } catch (e: Exception) {
        Toast.makeText(context, "Could not set wallpaper", Toast.LENGTH_SHORT).show()
    }
}

private fun formatFileSize(bytes: Long): String {
    if (bytes <= 0) return "0 B"
    val units = arrayOf("B", "KB", "MB", "GB", "TB")
    val digitGroups = (Math.log10(bytes.toDouble()) / Math.log10(1024.0)).toInt()
    return String.format(Locale.getDefault(), "%.2f %s", bytes / Math.pow(1024.0, digitGroups.toDouble()), units[digitGroups])
}

@OptIn(UnstableApi::class)
@Composable
fun VideoPlayer(uri: Uri) {
    val context = LocalContext.current
    val exoPlayer = remember {
        ExoPlayer.Builder(context).build().apply {
            setMediaItem(ExoMediaItem.fromUri(uri))
            prepare()
            playWhenReady = true
        }
    }

    DisposableEffect(exoPlayer) {
        onDispose {
            exoPlayer.release()
        }
    }

    AndroidView(
        factory = {
            PlayerView(context).apply {
                player = exoPlayer
            }
        },
        modifier = Modifier.fillMaxSize()
    )
}
