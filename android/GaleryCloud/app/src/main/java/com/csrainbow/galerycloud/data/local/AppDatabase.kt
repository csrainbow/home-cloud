package com.csrainbow.galerycloud.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase

@Database(entities = [SyncStatusEntity::class], version = 1)
abstract class AppDatabase : RoomDatabase() {
    abstract fun syncStatusDao(): SyncStatusDao

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "galery_cloud_db"
                ).build()
                INSTANCE = instance
                instance
            }
        }
    }
}
