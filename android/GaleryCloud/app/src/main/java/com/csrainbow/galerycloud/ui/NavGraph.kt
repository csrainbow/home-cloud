package com.csrainbow.galerycloud.ui

import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.csrainbow.galerycloud.ui.screens.GalleryScreen
import com.csrainbow.galerycloud.ui.screens.MediaViewerScreen
import com.csrainbow.galerycloud.ui.screens.SettingsScreen

sealed class Screen(val route: String) {
    object Gallery : Screen("gallery")
    object Settings : Screen("settings")
    object MediaViewer : Screen("viewer/{mediaId}") {
        fun createRoute(mediaId: Long) = "viewer/$mediaId"
    }
    object MemoryPlayer : Screen("memory_player")
}

@Composable
fun GaleryNavGraph(navController: NavHostController) {
    // Scope ViewModel to the NavGraph to share instance between screens
    val viewModel: com.csrainbow.galerycloud.ui.viewmodel.GalleryViewModel = androidx.lifecycle.viewmodel.compose.viewModel()

    NavHost(navController = navController, startDestination = Screen.Gallery.route) {
        composable(Screen.Gallery.route) {
            GalleryScreen(
                viewModel = viewModel,
                onNavigateToSettings = { navController.navigate(Screen.Settings.route) },
                onNavigateToViewer = { id -> navController.navigate(Screen.MediaViewer.createRoute(id)) },
                onNavigateToMemoryPlayer = { navController.navigate(Screen.MemoryPlayer.route) }
            )
        }
        composable(Screen.Settings.route) {
            SettingsScreen(onBack = { navController.popBackStack() })
        }
        composable(Screen.MemoryPlayer.route) {
            com.csrainbow.galerycloud.ui.screens.MemoryPlayerScreen(
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }
        composable(
            route = Screen.MediaViewer.route,
            arguments = listOf(navArgument("mediaId") { type = NavType.LongType })
        ) { backStackEntry ->
            val mediaId = backStackEntry.arguments?.getLong("mediaId") ?: return@composable
            MediaViewerScreen(
                mediaId = mediaId,
                viewModel = viewModel,
                onBack = { navController.popBackStack() }
            )
        }
    }
}
