package com.bsdosale

import android.Manifest
import android.content.pm.PackageManager
import android.os.Bundle
import android.view.View
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import org.webrtc.SurfaceViewRenderer
import com.bsdosale.webrtc.WebRTCClient

class LiveStreamActivity : AppCompatActivity(), WebRTCClient.WebRTCListener {
    
    private lateinit var surfaceViewRenderer: SurfaceViewRenderer
    private lateinit var buttonStartStream: Button
    private lateinit var buttonEndStream: Button
    private lateinit var editTextTitle: EditText
    private lateinit var editTextDescription: EditText
    private lateinit var progressBar: ProgressBar
    
    private lateinit var webRTCClient: WebRTCClient
    
    private val PERMISSIONS = arrayOf(
        Manifest.permission.CAMERA,
        Manifest.permission.RECORD_AUDIO
    )
    
    private val PERMISSION_REQUEST_CODE = 1001
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_live_stream)
        
        initViews()
        setClickListeners()
        
        webRTCClient = WebRTCClient(this, this)
        
        if (checkPermissions()) {
            initializeWebRTC()
        } else {
            requestPermissions()
        }
    }
    
    private fun initViews() {
        surfaceViewRenderer = findViewById(R.id.surfaceViewRenderer)
        buttonStartStream = findViewById(R.id.buttonStartStream)
        buttonEndStream = findViewById(R.id.buttonEndStream)
        editTextTitle = findViewById(R.id.editTextTitle)
        editTextDescription = findViewById(R.id.editTextDescription)
        progressBar = findViewById(R.id.progressBar)
    }
    
    private fun setClickListeners() {
        buttonStartStream.setOnClickListener {
            startStream()
        }
        
        buttonEndStream.setOnClickListener {
            endStream()
        }
    }
    
    private fun checkPermissions(): Boolean {
        return PERMISSIONS.all {
            ContextCompat.checkSelfPermission(this, it) == PackageManager.PERMISSION_GRANTED
        }
    }
    
    private fun requestPermissions() {
        ActivityCompat.requestPermissions(this, PERMISSIONS, PERMISSION_REQUEST_CODE)
    }
    
    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        
        if (requestCode == PERMISSION_REQUEST_CODE) {
            if (grantResults.isNotEmpty() && grantResults.all { it == PackageManager.PERMISSION_GRANTED }) {
                initializeWebRTC()
            } else {
                Toast.makeText(this, "Permissions are required for live streaming", Toast.LENGTH_LONG).show()
                finish()
            }
        }
    }
    
    private fun initializeWebRTC() {
        webRTCClient.initializeSurfaceView(surfaceViewRenderer)
        webRTCClient.startLocalVideoCapture(surfaceViewRenderer)
    }
    
    private fun startStream() {
        val title = editTextTitle.text.toString().trim()
        val description = editTextDescription.text.toString().trim()
        
        if (title.isEmpty()) {
            editTextTitle.error = "Title is required"
            editTextTitle.requestFocus()
            return
        }
        
        // Hide start button and show progress
        buttonStartStream.visibility = View.GONE
        progressBar.visibility = View.VISIBLE
        
        // In a real app, you would:
        // 1. Send stream info to server
        // 2. Get stream key/URL from server
        // 3. Start WebRTC connection to streaming server
        
        // For demo purposes, we'll just simulate starting
        buttonStartStream.visibility = View.GONE
        buttonEndStream.visibility = View.VISIBLE
        progressBar.visibility = View.GONE
        
        Toast.makeText(this, "Stream started successfully", Toast.LENGTH_SHORT).show()
    }
    
    private fun endStream() {
        // In a real app, you would:
        // 1. Notify server to end stream
        // 2. Close WebRTC connection
        
        // For demo purposes, we'll just simulate ending
        buttonEndStream.visibility = View.GONE
        buttonStartStream.visibility = View.VISIBLE
        
        Toast.makeText(this, "Stream ended", Toast.LENGTH_SHORT).show()
    }
    
    override fun onWebRTCReady() {
        runOnUiThread {
            // WebRTC is ready
        }
    }
    
    override fun onWebRTCError(error: String) {
        runOnUiThread {
            Toast.makeText(this, "WebRTC Error: $error", Toast.LENGTH_LONG).show()
        }
    }
    
    override fun onDestroy() {
        super.onDestroy()
        webRTCClient.stopLocalVideoCapture()
        webRTCClient.release()
        surfaceViewRenderer.release()
    }
}