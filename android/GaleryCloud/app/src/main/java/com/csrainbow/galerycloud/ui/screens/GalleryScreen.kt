package com.csrainbow.galerycloud.ui.screens

import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.IntentSenderRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.combinedClickable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.draw.clip
import com.csrainbow.galerycloud.ui.viewmodel.GalleryTab
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.animation.core.*
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import coil.request.ImageRequest
import coil.decode.VideoFrameDecoder
import coil.request.videoFrameMicros
import com.csrainbow.galerycloud.domain.MediaItem
import com.csrainbow.galerycloud.domain.SyncStatus
import com.csrainbow.galerycloud.ui.viewmodel.GalleryViewModel

@OptIn(ExperimentalFoundationApi::class, ExperimentalMaterial3Api::class)
@Composable
fun GalleryScreen(
    viewModel: GalleryViewModel = viewModel(),
    onNavigateToSettings: () -> Unit,
    onNavigateToViewer: (Long) -> Unit,
    onNavigateToMemoryPlayer: () -> Unit
) {
    val mediaItems by viewModel.mediaItems.collectAsState()
    val albums by viewModel.albums.collectAsState()
    val memories by viewModel.memories.collectAsState()
    val currentTab by viewModel.currentTab.collectAsState()
    val isSelectionMode by viewModel.isSelectionMode.collectAsState()
    val selectedIds by viewModel.selectedIds.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    val isUploading by viewModel.isUploading.collectAsState()
    val isUploadingLarge by viewModel.isUploadingLarge.collectAsState()
    val pendingDelete by viewModel.pendingDeleteIntent.collectAsState()
    val context = LocalContext.current

    val copyLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocumentTree()) { uri ->
        uri?.let { viewModel.copySelectedToFolder(it) }
    }
    val moveLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocumentTree()) { uri ->
        uri?.let { viewModel.moveSelectedToFolder(it) }
    }
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

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        text = if (isSelectionMode) "${selectedIds.size} Selected" else "GaleryCloud",
                        fontWeight = FontWeight.Medium,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                },
                actions = {
                    if (isSelectionMode) {
                        IconButton(onClick = { viewModel.clearSelection() }) {
                            Icon(Icons.Default.Close, contentDescription = "Close")
                        }
                    } else {
                        Surface(
                            shape = RoundedCornerShape(20.dp),
                            color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.7f)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 2.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                val infiniteTransition = rememberInfiniteTransition(label = "cloudSpin")
                                val rotation by infiniteTransition.animateFloat(
                                    initialValue = 0f,
                                    targetValue = 360f,
                                    animationSpec = infiniteRepeatable(
                                        animation = tween(1500, easing = LinearEasing),
                                        repeatMode = RepeatMode.Restart
                                    ),
                                    label = "cloudRotation"
                                )
                                val largeSpinTransition = rememberInfiniteTransition(label = "largeSpin")
                                val largeRotation by largeSpinTransition.animateFloat(
                                    initialValue = 0f,
                                    targetValue = 360f,
                                    animationSpec = infiniteRepeatable(
                                        animation = tween(1000, easing = LinearEasing),
                                        repeatMode = RepeatMode.Restart
                                    ),
                                    label = "largeRotation"
                                )
                                Box {
                                    IconButton(
                                        onClick = { viewModel.uploadAllUnsynced() },
                                        modifier = Modifier.size(32.dp),
                                        enabled = !isUploading
                                    ) {
                                        Icon(
                                            if (isUploading) Icons.Default.Sync else Icons.Default.CloudUpload,
                                            contentDescription = "Sync all",
                                            modifier = Modifier.size(20.dp)
                                                .rotate(if (isUploading) rotation else 0f),
                                            tint = if (isUploadingLarge) MaterialTheme.colorScheme.tertiary
                                                   else if (isUploading) MaterialTheme.colorScheme.primary
                                                   else MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                    }
                                    if (isUploadingLarge) {
                                        CircularProgressIndicator(
                                            modifier = Modifier.size(32.dp),
                                            strokeWidth = 2.dp,
                                            color = MaterialTheme.colorScheme.tertiary
                                        )
                                    }
                                }
                                IconButton(onClick = onNavigateToSettings) {
                                    Icon(Icons.Default.Settings, contentDescription = "Settings",
                                        modifier = Modifier.size(20.dp))
                                }
                                IconButton(onClick = { viewModel.loadMedia() }) {
                                    Icon(Icons.Default.Refresh, contentDescription = "Refresh",
                                        modifier = Modifier.size(20.dp))
                                }
                            }
                        }
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color.Transparent,
                    titleContentColor = MaterialTheme.colorScheme.onSurface
                )
            )
        },
        bottomBar = {
            Surface(
                shape = RoundedCornerShape(28.dp),
                color = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.85f),
                shadowElevation = 8.dp,
                modifier = Modifier
                    .padding(horizontal = 48.dp)
                    .navigationBarsPadding()
                    .padding(bottom = 4.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth().height(56.dp),
                    horizontalArrangement = Arrangement.SpaceEvenly,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier.size(if (currentTab == GalleryTab.PHOTOS) 52.dp else 44.dp)
                            .clickable { viewModel.setTab(GalleryTab.PHOTOS) },
                        verticalArrangement = Arrangement.Center
                    ) {
                        Icon(Icons.Default.Photo, contentDescription = "Photos",
                            modifier = Modifier.size(20.dp),
                            tint = if (currentTab == GalleryTab.PHOTOS)
                                   MaterialTheme.colorScheme.primary
                                   else MaterialTheme.colorScheme.onSurfaceVariant)
                        if (currentTab == GalleryTab.PHOTOS) {
                            Text("Photos", style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.primary)
                        }
                    }
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier.size(if (currentTab == GalleryTab.ALBUMS) 52.dp else 44.dp)
                            .clickable { viewModel.setTab(GalleryTab.ALBUMS) },
                        verticalArrangement = Arrangement.Center
                    ) {
                        Icon(Icons.Default.Collections, contentDescription = "Albums",
                            modifier = Modifier.size(20.dp),
                            tint = if (currentTab == GalleryTab.ALBUMS)
                                   MaterialTheme.colorScheme.primary
                                   else MaterialTheme.colorScheme.onSurfaceVariant)
                        if (currentTab == GalleryTab.ALBUMS) {
                            Text("Albums", style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.primary)
                        }
                    }
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        modifier = Modifier.size(if (currentTab == GalleryTab.MEMORIES) 52.dp else 44.dp)
                            .clickable { viewModel.setTab(GalleryTab.MEMORIES) },
                        verticalArrangement = Arrangement.Center
                    ) {
                        Icon(Icons.Default.AutoAwesome, contentDescription = "Memories",
                            modifier = Modifier.size(20.dp),
                            tint = if (currentTab == GalleryTab.MEMORIES)
                                   MaterialTheme.colorScheme.primary
                                   else MaterialTheme.colorScheme.onSurfaceVariant)
                        if (currentTab == GalleryTab.MEMORIES) {
                            Text("Memories", style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.primary)
                        }
                    }
                }
            }
        },
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            if (isLoading) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = MaterialTheme.colorScheme.primary)
                }
            } else {
                when (currentTab) {
                    GalleryTab.PHOTOS -> {
                        PhotosGrid(
                            mediaItems = mediaItems,
                            isSelectionMode = isSelectionMode,
                            selectedIds = selectedIds,
                            onItemClick = { item ->
                                if (isSelectionMode) viewModel.toggleSelection(item.id)
                                else onNavigateToViewer(item.id)
                            },
                            onItemLongClick = { item ->
                                viewModel.enterSelectionMode(item.id)
                            }
                        )
                    }
                    GalleryTab.ALBUMS -> {
                        AlbumsGrid(
                            albums = albums,
                            onAlbumClick = { albumName ->
                                viewModel.setTab(GalleryTab.PHOTOS)
                                android.widget.Toast.makeText(context, albumName, android.widget.Toast.LENGTH_SHORT).show()
                            }
                        )
                    }
                    GalleryTab.MEMORIES -> {
                        MemoriesView(
                            memories = memories,
                            onMemoryClick = onNavigateToMemoryPlayer
                        )
                    }
                }
            }

            // Selection Capsule Menu with Upload Now
            if (isSelectionMode) {
                var showMoreMenu by remember { mutableStateOf(false) }

                Box(
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .padding(bottom = 32.dp)
                ) {
                    Surface(
                        shape = RoundedCornerShape(32.dp),
                        color = MaterialTheme.colorScheme.surface,
                        contentColor = MaterialTheme.colorScheme.onSurface,
                        tonalElevation = 8.dp,
                        shadowElevation = 12.dp
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 4.dp, vertical = 4.dp),
                            horizontalArrangement = Arrangement.spacedBy(0.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            IconButton(
                                onClick = { viewModel.uploadSelectedNow() },
                                enabled = !isUploading,
                                modifier = Modifier.size(40.dp)
                            ) {
                                if (isUploading) {
                                    CircularProgressIndicator(
                                        modifier = Modifier.size(20.dp),
                                        strokeWidth = 2.dp,
                                        color = MaterialTheme.colorScheme.primary
                                    )
                                } else {
                                    Icon(Icons.Default.CloudUpload, "Upload Now",
                                        modifier = Modifier.size(20.dp),
                                        tint = MaterialTheme.colorScheme.primary)
                                }
                            }
                            IconButton(
                                onClick = { viewModel.deleteSelected() },
                                modifier = Modifier.size(40.dp)
                            ) {
                                Icon(Icons.Default.Delete, "Delete",
                                    modifier = Modifier.size(20.dp),
                                    tint = MaterialTheme.colorScheme.error)
                            }
                            Box {
                                IconButton(
                                    onClick = { showMoreMenu = true },
                                    modifier = Modifier.size(40.dp)
                                ) {
                                    Icon(Icons.Default.MoreVert, "More",
                                        modifier = Modifier.size(20.dp))
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
                                            android.widget.Toast.makeText(context, "Select a single item for wallpaper", android.widget.Toast.LENGTH_SHORT).show()
                                        }
                                    )
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun PhotosGrid(
    mediaItems: Map<String, List<MediaItem>>,
    isSelectionMode: Boolean,
    selectedIds: Set<Long>,
    onItemClick: (MediaItem) -> Unit,
    onItemLongClick: (MediaItem) -> Unit
) {
    LazyVerticalGrid(
        columns = GridCells.Adaptive(120.dp),
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(4.dp),
        verticalArrangement = Arrangement.spacedBy(1.dp),
        horizontalArrangement = Arrangement.spacedBy(1.dp)
    ) {
        mediaItems.forEach { (date, items) ->
            item(span = { GridItemSpan(maxLineSpan) }) {
                Text(
                    text = date,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Medium,
                    color = MaterialTheme.colorScheme.onSurface,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 12.dp, vertical = 10.dp)
                )
            }
            items(items, key = { it.id }) { item ->
                MediaThumbnail(
                    item = item,
                    isSelected = selectedIds.contains(item.id),
                    isSelectionMode = isSelectionMode,
                    onClick = { onItemClick(item) },
                    onLongClick = { onItemLongClick(item) }
                )
            }
        }
    }
}

@Composable
fun AlbumsGrid(albums: Map<String, List<MediaItem>>, onAlbumClick: (String) -> Unit) {
    LazyVerticalGrid(
        columns = GridCells.Fixed(2),
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        albums.forEach { (name, items) ->
            item {
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    tonalElevation = 2.dp,
                    shadowElevation = 2.dp,
                    modifier = Modifier.clickable { onAlbumClick(name) }
                ) {
                    Column {
                        Box(
                            modifier = Modifier
                                .aspectRatio(1f)
                                .clip(RoundedCornerShape(12.dp))
                        ) {
                            val firstItem = items.firstOrNull()
                            AsyncImage(
                                model = ImageRequest.Builder(LocalContext.current)
                                    .data(firstItem?.uri)
                                    .crossfade(true)
                                    .apply {
                                        if (firstItem?.isVideo == true) {
                                            videoFrameMicros(15_000_000)
                                        }
                                    }
                                    .build(),
                                contentDescription = name,
                                modifier = Modifier.fillMaxSize(),
                                contentScale = ContentScale.Crop
                            )
                            Box(
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(6.dp)
                                    .background(
                                        Color.Black.copy(alpha = 0.5f),
                                        CircleShape
                                    )
                                    .padding(horizontal = 8.dp, vertical = 2.dp)
                            ) {
                                Text(
                                    text = "${items.size}",
                                    color = Color.White,
                                    style = MaterialTheme.typography.labelSmall,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                        Text(
                            text = name,
                            style = MaterialTheme.typography.bodyMedium,
                            fontWeight = FontWeight.Medium,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 6.dp),
                            maxLines = 1
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun MemoriesView(
    memories: List<MediaItem>,
    onMemoryClick: () -> Unit
) {
    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Text(
            "Your Flashbacks",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold
        )
        Spacer(modifier = Modifier.height(16.dp))
        LazyVerticalGrid(
            columns = GridCells.Fixed(2),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(memories) { item ->
                Surface(
                    shape = RoundedCornerShape(16.dp),
                    tonalElevation = 4.dp,
                    shadowElevation = 4.dp
                ) {
                    Box(
                        modifier = Modifier
                            .aspectRatio(0.7f)
                            .clickable { onMemoryClick() }
                    ) {
                        AsyncImage(
                            model = item.uri,
                            contentDescription = null,
                            modifier = Modifier.fillMaxSize(),
                            contentScale = ContentScale.Crop
                        )
                        Box(
                            modifier = Modifier
                                .fillMaxSize()
                                .background(Color.Black.copy(alpha = 0.15f))
                        )
                        Text(
                            text = "Memory from ${SimpleDateFormat("MMM yyyy", Locale.getDefault()).format(Date(item.dateAdded * 1000))}",
                            modifier = Modifier
                                .align(Alignment.BottomStart)
                                .padding(12.dp),
                            color = Color.White,
                            style = MaterialTheme.typography.bodySmall,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun MediaThumbnail(
    item: MediaItem,
    isSelected: Boolean,
    isSelectionMode: Boolean,
    onClick: () -> Unit,
    onLongClick: () -> Unit
) {
    Box(
        modifier = Modifier
            .aspectRatio(1f)
            .combinedClickable(
                onClick = onClick,
                onLongClick = onLongClick
            )
    ) {
        AsyncImage(
            model = ImageRequest.Builder(LocalContext.current)
                .data(item.uri)
                .crossfade(true)
                .apply {
                    if (item.isVideo) {
                        videoFrameMicros(15_000_000)
                    }
                }
                .build(),
            contentDescription = item.name,
            modifier = Modifier.fillMaxSize(),
            contentScale = ContentScale.Crop
        )

        if (isSelectionMode) {
            if (isSelected) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.15f))
                )
            }
            Box(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(6.dp)
                    .size(24.dp)
                    .background(
                        if (isSelected) MaterialTheme.colorScheme.primary
                        else Color.Black.copy(alpha = 0.3f),
                        CircleShape
                    ),
                contentAlignment = Alignment.Center
            ) {
                if (isSelected) {
                    Icon(
                        Icons.Default.Check,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp),
                        tint = Color.White
                    )
                }
            }
        }

        if (item.isVideo) {
            Surface(
                modifier = Modifier
                    .align(Alignment.Center)
                    .size(32.dp),
                shape = CircleShape,
                color = Color.Black.copy(alpha = 0.5f),
                contentColor = Color.White
            ) {
                Icon(
                    Icons.Default.PlayArrow,
                    contentDescription = null,
                    modifier = Modifier.padding(6.dp)
                )
            }
        }

        val rotateDegree = if (item.syncStatus == SyncStatus.SYNCING) {
            val infiniteTransition = rememberInfiniteTransition(label = "syncSpin")
            infiniteTransition.animateFloat(
                initialValue = 0f,
                targetValue = 360f,
                animationSpec = infiniteRepeatable(
                    animation = tween(1200, easing = LinearEasing),
                    repeatMode = RepeatMode.Restart
                ),
                label = "syncRotation"
            ).value
        } else 0f

        val syncIcon = when (item.syncStatus) {
            SyncStatus.SYNCING -> Icons.Default.Sync
            SyncStatus.FAILED -> Icons.Default.Error
            SyncStatus.SYNCED -> Icons.Default.CheckCircle
            else -> Icons.Default.CloudQueue
        }
        val syncTint = when (item.syncStatus) {
            SyncStatus.SYNCED -> Color(0xFF34A853)
            SyncStatus.SYNCING -> Color(0xFF1A73E8)
            SyncStatus.FAILED -> Color(0xFFEA4335)
            else -> Color(0xFF9AA0A6)
        }

        Surface(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(6.dp)
                .size(22.dp),
            shape = CircleShape,
            color = Color.Black.copy(alpha = 0.4f)
        ) {
            Icon(
                syncIcon,
                contentDescription = null,
                modifier = Modifier
                    .padding(3.dp)
                    .rotate(rotateDegree),
                tint = syncTint
            )
        }
    }
}
