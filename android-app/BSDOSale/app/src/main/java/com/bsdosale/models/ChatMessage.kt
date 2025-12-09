package com.bsdosale.models

data class ChatMessage(
    val id: Int,
    val userId: Int,
    val message: String,
    val isSeller: Boolean,
    val timestamp: String
)