package com.bsdosale

import android.os.Bundle
import android.util.Log
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.ImageView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bsdosale.adapters.ChatMessageAdapter
import com.bsdosale.models.ChatMessage
import com.bsdosale.utils.WebSocketClient
import org.json.JSONObject

class WatchStreamActivity : AppCompatActivity(), WebSocketClient.WebSocketListener {
    
    private lateinit var imageViewStream: ImageView
    private lateinit var textViewTitle: TextView
    private lateinit var textViewDescription: TextView
    private lateinit var textViewViewerCount: TextView
    private lateinit var recyclerViewChat: RecyclerView
    private lateinit var editTextMessage: EditText
    private lateinit var buttonSend: Button
    private lateinit var chatAdapter: ChatMessageAdapter
    private lateinit var webSocketClient: WebSocketClient
    
    private val chatMessages = mutableListOf<ChatMessage>()
    private var userId = 1 // In real app, get from session
    private var streamId = 1 // In real app, get from intent
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_watch_stream)
        
        initViews()
        setupChatRecyclerView()
        setClickListeners()
        
        // Get stream data from intent (in real app)
        val streamTitle = intent.getStringExtra("stream_title") ?: "Live Stream"
        val streamDescription = intent.getStringExtra("stream_description") ?: "Live stream description"
        
        // Set stream data
        textViewTitle.text = streamTitle
        textViewDescription.text = streamDescription
        textViewViewerCount.text = "15 viewers"
        
        // Initialize WebSocket client
        webSocketClient = WebSocketClient(this, this)
        
        // Connect to WebSocket server (in real app, use actual server URL)
        val wsUrl = "ws://localhost:8080/chat/stream/$streamId"
        webSocketClient.connect(wsUrl)
    }
    
    private fun initViews() {
        imageViewStream = findViewById(R.id.imageViewStream)
        textViewTitle = findViewById(R.id.textViewTitle)
        textViewDescription = findViewById(R.id.textViewDescription)
        textViewViewerCount = findViewById(R.id.textViewViewerCount)
        recyclerViewChat = findViewById(R.id.recyclerViewChat)
        editTextMessage = findViewById(R.id.editTextMessage)
        buttonSend = findViewById(R.id.buttonSend)
    }
    
    private fun setupChatRecyclerView() {
        recyclerViewChat.layoutManager = LinearLayoutManager(this)
        chatAdapter = ChatMessageAdapter(chatMessages)
        recyclerViewChat.adapter = chatAdapter
    }
    
    private fun setClickListeners() {
        buttonSend.setOnClickListener {
            sendMessage()
        }
    }
    
    private fun sendMessage() {
        val message = editTextMessage.text.toString().trim()
        if (message.isNotEmpty()) {
            // Send message via WebSocket
            val messageJson = JSONObject().apply {
                put("type", "chat_message")
                put("stream_id", streamId)
                put("user_id", userId)
                put("message", message)
                put("timestamp", System.currentTimeMillis())
            }
            
            webSocketClient.sendMessage(messageJson)
            editTextMessage.text.clear()
        }
    }
    
    override fun onConnected() {
        runOnUiThread {
            Toast.makeText(this, "Connected to chat", Toast.LENGTH_SHORT).show()
        }
    }
    
    override fun onDisconnected() {
        runOnUiThread {
            Toast.makeText(this, "Disconnected from chat", Toast.LENGTH_SHORT).show()
        }
    }
    
    override fun onMessageReceived(message: JSONObject) {
        runOnUiThread {
            try {
                val type = message.getString("type")
                
                when (type) {
                    "chat_message" -> {
                        val chatMessage = ChatMessage(
                            message.getInt("id"),
                            message.getInt("user_id"),
                            message.getString("message"),
                            message.getBoolean("is_seller"),
                            message.getString("timestamp")
                        )
                        
                        chatMessages.add(chatMessage)
                        chatAdapter.notifyItemInserted(chatMessages.size - 1)
                        recyclerViewChat.scrollToPosition(chatMessages.size - 1)
                    }
                    "viewer_count" -> {
                        val count = message.getInt("count")
                        textViewViewerCount.text = "$count viewers"
                    }
                }
            } catch (e: Exception) {
                Log.e("WatchStreamActivity", "Error processing message", e)
            }
        }
    }
    
    override fun onError(error: String) {
        runOnUiThread {
            Toast.makeText(this, "Chat error: $error", Toast.LENGTH_LONG).show()
        }
    }
    
    override fun onDestroy() {
        super.onDestroy()
        webSocketClient.disconnect()
    }
}