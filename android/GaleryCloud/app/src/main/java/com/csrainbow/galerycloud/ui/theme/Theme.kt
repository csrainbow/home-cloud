package com.csrainbow.galerycloud.ui.theme

import android.app.Activity
import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext

private val LightColorScheme = lightColorScheme(
    primary = GoogleBlue,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFD2E3FC),
    onPrimaryContainer = Color(0xFF041E49),
    secondary = GoogleGreen,
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFCEEAD6),
    onSecondaryContainer = Color(0xFF0D652D),
    tertiary = GoogleYellow,
    onTertiary = Color(0xFF3C4043),
    tertiaryContainer = Color(0xFFFDE7A2),
    onTertiaryContainer = Color(0xFF5F4B00),
    error = GoogleRed,
    onError = Color.White,
    errorContainer = Color(0xFFFCE8E6),
    onErrorContainer = Color(0xFF5F2120),
    background = GridBackground,
    onBackground = Color(0xFF202124),
    surface = GoogleSurface,
    onSurface = Color(0xFF202124),
    surfaceVariant = CardBackground,
    onSurfaceVariant = Color(0xFF5F6368),
    outline = GoogleGreyLight,
    outlineVariant = Color(0xFFDADCE0)
)

private val DarkColorScheme = darkColorScheme(
    primary = GoogleBlueDark,
    onPrimary = Color(0xFF003D8B),
    primaryContainer = Color(0xFF004A9F),
    onPrimaryContainer = Color(0xFFD2E3FC),
    secondary = GoogleGreen,
    onSecondary = Color(0xFF003D1A),
    secondaryContainer = Color(0xFF006E2E),
    onSecondaryContainer = Color(0xFFCEEAD6),
    tertiary = GoogleYellow,
    onTertiary = Color(0xFF3C3000),
    tertiaryContainer = Color(0xFF5F4B00),
    onTertiaryContainer = Color(0xFFFDE7A2),
    error = GoogleRed,
    onError = Color(0xFF5F2120),
    errorContainer = Color(0xFF8C1D18),
    onErrorContainer = Color(0xFFFCE8E6),
    background = GridBackgroundDark,
    onBackground = Color(0xFFE8EAED),
    surface = GoogleSurfaceDark,
    onSurface = Color(0xFFE8EAED),
    surfaceVariant = CardBackgroundDark,
    onSurfaceVariant = Color(0xFF9AA0A6),
    outline = GoogleGreyDark,
    outlineVariant = Color(0xFF3C4043)
)

@Composable
fun GaleryCloudTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    dynamicColor: Boolean = true,
    content: @Composable () -> Unit
) {
    val colorScheme = when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            val context = LocalContext.current
            if (darkTheme) {
                val scheme = androidx.compose.material3.dynamicDarkColorScheme(context)
                scheme.copy(
                    primary = GoogleBlueDark,
                    secondary = GoogleGreen,
                    tertiary = GoogleYellow
                )
            } else {
                val scheme = androidx.compose.material3.dynamicLightColorScheme(context)
                scheme.copy(
                    primary = GoogleBlue,
                    secondary = GoogleGreen,
                    tertiary = GoogleYellow
                )
            }
        }
        darkTheme -> DarkColorScheme
        else -> LightColorScheme
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        content = content
    )
}
