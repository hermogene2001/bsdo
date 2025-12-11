package com.bsdosale.models

data class LiveStream(
    val id: Int,
    val sellerId: Int,
    val title: String,
    val description: String,
    val categoryId: Int,
    val thumbnailUrl: String,
    val isLive: Boolean,
    val viewerCount: Int,
    val status: String, // "live", "scheduled", "ended"
    val scheduledAt: String? // ISO date string or null
)