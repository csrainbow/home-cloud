package com.csrainbow.galerycloud.data.remote

import kotlinx.serialization.Serializable

@Serializable
data class StorageInfo(
    val total: Long,
    val used: Long,
    val free: Long,
    val unit: String
)
