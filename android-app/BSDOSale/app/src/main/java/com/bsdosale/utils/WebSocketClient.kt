package com.bsdosale.utils

import android.content.Context
import android.util.Log
import okhttp3.*
import okio.ByteString
import org.json.JSONObject
import java.util.concurrent.TimeUnit
import com.bsdosale.services.NotificationService

class WebSocketClient(private val listener: WebSocketListener, private val context: Context) {
    
    private var webSocket: WebSocket? = null
    private val client = OkHttpClient.Builder()
        .readTimeout(0, TimeUnit.MILLISECONDS)
        .build()
    private val notificationService = NotificationService(context)
    
    private val TAG = "WebSocketClient"
    
    fun connect(url: String) {
        val request = Request.Builder()
            .url(url)
            .build()
            
        webSocket = client.newWebSocket(request, object : okhttp3.WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                Log.d(TAG, "WebSocket connection opened")
                listener.onConnected()
            }
            
            override fun onMessage(webSocket: WebSocket, text: String) {
                Log.d(TAG, "Received message: $text")
                try {
                    val json = JSONObject(text)
                    handleNotification(json)
                    listener.onMessageReceived(json)
                } catch (e: Exception) {
                    Log.e(TAG, "Error parsing message", e)
                }
            }
            
            override fun onMessage(webSocket: WebSocket, bytes: ByteString) {
                Log.d(TAG, "Received bytes message")
            }
            
            override fun onClosing(webSocket: WebSocket, code: Int, reason: String) {
                Log.d(TAG, "WebSocket closing: $code, $reason")
                webSocket.close(1000, null)
                listener.onDisconnected()
            }
            
            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                Log.e(TAG, "WebSocket error", t)
                listener.onError(t.message ?: "Unknown error")
            }
        })
    }
    
    private fun handleNotification(json: JSONObject) {
        try {
            val type = json.getString("type")
            
            when (type) {
                "live_stream_started" -> {
                    val streamTitle = json.getString("stream_title")
                    val sellerName = json.getString("seller_name")
                    notificationService.showLiveStreamNotification(streamTitle, sellerName)
                }
                "new_message" -> {
                    val senderName = json.getString("sender_name")
                    val message = json.getString("message")
                    notificationService.showMessageNotification(senderName, message)
                }
                "order_update" -> {
                    val orderId = json.getString("order_id")
                    val status = json.getString("status")
                    notificationService.showOrderNotification(orderId, status)
                }
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error handling notification", e)
        }
    }
    
    fun sendMessage(message: String) {
        webSocket?.send(message)
    }
    
    fun sendMessage(json: JSONObject) {
        webSocket?.send(json.toString())
    }
    
    fun disconnect() {
        webSocket?.close(1000, "Goodbye")
    }
    
    interface WebSocketListener {
        fun onConnected()
        fun onDisconnected()
        fun onMessageReceived(message: JSONObject)
        fun onError(error: String)
    }
}