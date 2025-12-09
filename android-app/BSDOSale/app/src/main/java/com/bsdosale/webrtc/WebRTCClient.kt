package com.bsdosale.webrtc

import android.content.Context
import org.webrtc.*

class WebRTCClient(private val context: Context, private val listener: WebRTCListener) {
    
    private var peerConnectionFactory: PeerConnectionFactory? = null
    private var videoSource: VideoSource? = null
    private var localVideoTrack: VideoTrack? = null
    private var audioSource: AudioSource? = null
    private var localAudioTrack: AudioTrack? = null
    private var surfaceTextureHelper: SurfaceTextureHelper? = null
    private var cameraCapturer: CameraVideoCapturer? = null
    
    private val rootEglBase: EglBase = EglBase.create()
    
    init {
        initializePeerConnectionFactory()
    }
    
    private fun initializePeerConnectionFactory() {
        val options = PeerConnectionFactory.Options()
        val defaultVideoEncoderFactory = DefaultVideoEncoderFactory(
            rootEglBase.eglBaseContext, true, true
        )
        val defaultVideoDecoderFactory = DefaultVideoDecoderFactory(rootEglBase.eglBaseContext)
        
        peerConnectionFactory = PeerConnectionFactory.builder()
            .setOptions(options)
            .setVideoEncoderFactory(defaultVideoEncoderFactory)
            .setVideoDecoderFactory(defaultVideoDecoderFactory)
            .createPeerConnectionFactory()
    }
    
    fun initializeSurfaceView(surfaceView: SurfaceViewRenderer) {
        surfaceView.init(rootEglBase.eglBaseContext, null)
        surfaceView.setEnableHardwareScaler(true)
        surfaceView.setMirror(true)
    }
    
    fun startLocalVideoCapture(localVideoOutput: SurfaceViewRenderer) {
        val videoCapturer = createVideoCapturer() ?: return
        
        val surfaceTextureHelper = SurfaceTextureHelper.create(
            Thread.currentThread().name, rootEglBase.eglBaseContext
        )
        this.surfaceTextureHelper = surfaceTextureHelper
        
        videoSource = peerConnectionFactory?.createVideoSource(false)
        videoCapturer.initialize(
            surfaceTextureHelper, context, videoSource?.capturerObserver
        )
        
        val videoConstraints = MediaConstraints().apply {
            mandatory.add(MediaConstraints.KeyValuePair("minWidth", "1280"))
            mandatory.add(MediaConstraints.KeyValuePair("minHeight", "720"))
            mandatory.add(MediaConstraints.KeyValuePair("maxWidth", "1920"))
            mandatory.add(MediaConstraints.KeyValuePair("maxHeight", "1080"))
        }
        
        videoCapturer.startCapture(1280, 720, 30)
        
        localVideoTrack = peerConnectionFactory?.createVideoTrack(
            "local_video_track", videoSource
        )
        localVideoTrack?.addSink(localVideoOutput)
        
        // Create audio track
        createAudioTrack()
    }
    
    private fun createAudioTrack() {
        audioSource = peerConnectionFactory?.createAudioSource(MediaConstraints())
        localAudioTrack = peerConnectionFactory?.createAudioTrack(
            "local_audio_track", audioSource
        )
    }
    
    private fun createVideoCapturer(): CameraVideoCapturer? {
        val cameraEnumerator = if (Camera2Enumerator.isSupported(context)) {
            Camera2Enumerator(context)
        } else {
            Camera1Enumerator(true)
        }
        
        val deviceNames = cameraEnumerator.deviceNames
        for (deviceName in deviceNames) {
            if (cameraEnumerator.isFrontFacing(deviceName)) {
                cameraCapturer = cameraEnumerator.createCapturer(deviceName, null)
                return cameraCapturer
            }
        }
        
        // Front camera not found, try any camera
        for (deviceName in deviceNames) {
            cameraCapturer = cameraEnumerator.createCapturer(deviceName, null)
            return cameraCapturer
        }
        
        return null
    }
    
    fun stopLocalVideoCapture() {
        cameraCapturer?.stopCapture()
        cameraCapturer?.dispose()
        localVideoTrack?.removeSink(null)
        videoSource?.dispose()
        surfaceTextureHelper?.dispose()
        localAudioTrack?.dispose()
        audioSource?.dispose()
    }
    
    fun release() {
        stopLocalVideoCapture()
        peerConnectionFactory?.dispose()
        rootEglBase.release()
    }
    
    interface WebRTCListener {
        fun onWebRTCReady()
        fun onWebRTCError(error: String)
    }
}