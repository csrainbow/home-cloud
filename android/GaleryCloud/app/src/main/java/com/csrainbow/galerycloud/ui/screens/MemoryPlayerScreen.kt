package com.csrainbow.galerycloud.ui.screens

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.csrainbow.galerycloud.domain.MediaItem
import com.csrainbow.galerycloud.ui.viewmodel.GalleryViewModel
import kotlinx.coroutines.delay

@Composable
fun MemoryPlayerScreen(
    viewModel: GalleryViewModel = viewModel(),
    onBack: () -> Unit
) {
    val memories by viewModel.memories.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    var currentIndex by remember { mutableIntStateOf(0) }
    var isPaused by remember { mutableStateOf(false) }
    
    // Use a derived state to determine if we should show the content
    val currentItem = remember(memories, currentIndex) { memories.getOrNull(currentIndex) }

    if (memories.isEmpty()) {
        Box(modifier = Modifier.fillMaxSize().background(Color.Black), contentAlignment = Alignment.Center) {
            if (isLoading) {
                CircularProgressIndicator(color = Color.White)
            } else {
                Text("No memories available", color = Color.White)
                // Add a small delay before going back if not loading
                LaunchedEffect(Unit) {
                    delay(2000)
                    onBack()
                }
            }
        }
        return
    }

    if (currentItem == null) {
        onBack()
        return
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.Black)
            .pointerInput(Unit) {
                detectTapGestures(
                    onPress = {
                        isPaused = true
                        tryAwaitRelease()
                        isPaused = false
                    },
                    onTap = { offset ->
                        if (offset.x < size.width / 3) {
                            if (currentIndex > 0) currentIndex--
                        } else {
                            if (currentIndex < memories.size - 1) currentIndex++
                            else onBack()
                        }
                    }
                )
            }
    ) {
        // Animated Content for Transitions
        AnimatedContent(
            targetState = currentItem,
            transitionSpec = {
                fadeIn(animationSpec = tween(1000)) togetherWith 
                fadeOut(animationSpec = tween(1000))
            },
            label = "MemoryTransition"
        ) { item ->
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                if (item.isVideo) {
                    VideoPlayer(uri = item.uri)
                } else {
                    KenBurnsImage(item = item)
                }
            }
        }

        // Progress Indicators (Top)
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(horizontal = 8.dp, vertical = 16.dp),
            horizontalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            memories.forEachIndexed { index, _ ->
                val progress = remember { Animatable(0f) }
                
                LaunchedEffect(currentIndex, isPaused) {
                    if (index < currentIndex) {
                        progress.snapTo(1f)
                    } else if (index == currentIndex) {
                        if (!isPaused) {
                            progress.animateTo(
                                targetValue = 1f,
                                animationSpec = tween(durationMillis = 5000, easing = LinearEasing)
                            )
                            if (currentIndex < memories.size - 1) currentIndex++
                            else onBack()
                        }
                    } else {
                        progress.snapTo(0f)
                    }
                }

                LinearProgressIndicator(
                    progress = { progress.value },
                    modifier = Modifier
                        .weight(1f)
                        .height(2.dp)
                        .clip(RoundedCornerShape(1.dp)),
                    color = Color.White,
                    trackColor = Color.White.copy(alpha = 0.3f)
                )
            }
        }

        // Close Button
        IconButton(
            onClick = onBack,
            modifier = Modifier
                .align(Alignment.TopEnd)
                .statusBarsPadding()
                .padding(top = 24.dp, end = 8.dp)
        ) {
            Icon(Icons.Default.Close, contentDescription = "Close", tint = Color.White)
        }
        
        // Date Label
        Text(
            text = currentItem.name,
            modifier = Modifier
                .align(Alignment.BottomStart)
                .navigationBarsPadding()
                .padding(16.dp),
            color = Color.White,
            style = MaterialTheme.typography.titleMedium
        )
    }
}

@Composable
fun KenBurnsImage(item: MediaItem) {
    val infiniteTransition = rememberInfiniteTransition(label = "KenBurns")
    
    val scale by infiniteTransition.animateFloat(
        initialValue = 1f,
        targetValue = 1.2f,
        animationSpec = infiniteRepeatable(
            animation = tween(10000, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "Scale"
    )
    
    val translationX by infiniteTransition.animateFloat(
        initialValue = -20f,
        targetValue = 20f,
        animationSpec = infiniteRepeatable(
            animation = tween(15000, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "TranslationX"
    )

    AsyncImage(
        model = item.uri,
        contentDescription = null,
        modifier = Modifier
            .fillMaxSize()
            .graphicsLayer(
                scaleX = scale,
                scaleY = scale,
                translationX = translationX
            ),
        contentScale = ContentScale.Crop
    )
}
