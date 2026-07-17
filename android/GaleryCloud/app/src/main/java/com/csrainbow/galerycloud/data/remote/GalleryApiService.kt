package com.csrainbow.galerycloud.data.remote

import android.util.Log
import io.ktor.client.*
import io.ktor.client.call.*
import io.ktor.client.plugins.*
import io.ktor.client.plugins.contentnegotiation.*
import io.ktor.client.request.*
import io.ktor.client.request.forms.*
import io.ktor.http.*
import io.ktor.serialization.kotlinx.json.*
import kotlinx.io.Buffer
import kotlinx.serialization.json.Json
import java.io.InputStream

class GalleryApiService {
    private val client = HttpClient {
        install(ContentNegotiation) {
            json(Json {
                ignoreUnknownKeys = true
            })
        }
        install(HttpTimeout) {
            requestTimeoutMillis = 120_000
            connectTimeoutMillis = 10_000
            socketTimeoutMillis = 120_000
        }
    }

    suspend fun getStorageInfo(baseUrl: String, user: String, pass: String): StorageInfo {
        Log.d("GalleryApi", "Fetching storage info from $baseUrl")
        return client.get("$baseUrl/api/media/storage-info") {
            header("user", user)
            header("pass", pass)
        }.body()
    }

    suspend fun testConnection(baseUrl: String, user: String, pass: String): Boolean {
        Log.d("GalleryApi", "Testing connection to $baseUrl")
        return try {
            val response = client.get("$baseUrl/api/media/check-connection") {
                header("user", user)
                header("pass", pass)
            }
            Log.d("GalleryApi", "Connection response status: ${response.status}")
            response.status.value == 200
        } catch (e: Exception) {
            Log.e("GalleryApi", "Connection failed: ${e.message}", e)
            false
        }
    }

    suspend fun uploadFile(baseUrl: String, user: String, pass: String, fileName: String, fileData: ByteArray): Boolean {
        return try {
            val response = client.post("$baseUrl/api/media/upload") {
                header("user", user)
                header("pass", pass)
                setBody(MultiPartFormDataContent(
                    formData {
                        append("file", fileData, Headers.build {
                            append(HttpHeaders.ContentDisposition, "filename=\"$fileName\"")
                        })
                    }
                ))
            }
            response.status.value == 200
        } catch (e: Exception) {
            false
        }
    }

    suspend fun uploadFileStreaming(baseUrl: String, user: String, pass: String, fileName: String, fileSize: Long, inputStream: InputStream): Boolean {
        return try {
            val response = client.post("$baseUrl/api/media/upload") {
                header("user", user)
                header("pass", pass)
                setBody(MultiPartFormDataContent(
                    formData {
                        append("file", filename = fileName, size = if (fileSize > 0) fileSize else null) {
                            val buf = Buffer()
                            val chunk = ByteArray(8192)
                            var bytesRead: Int
                            while (inputStream.read(chunk).also { bytesRead = it } != -1) {
                                buf.write(chunk, 0, bytesRead)
                                this.write(buf, bytesRead.toLong())
                            }
                        }
                    }
                ))
            }
            response.status.value == 200
        } catch (e: Exception) {
            false
        }
    }
}
