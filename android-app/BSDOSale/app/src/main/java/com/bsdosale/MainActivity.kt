package com.bsdosale

import android.os.Bundle
import android.view.View
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.ImageView
import android.widget.ProgressBar
import androidx.appcompat.app.AppCompatActivity

class MainActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        
        val webView = findViewById<WebView>(R.id.webView)
        val logoImage = findViewById<ImageView>(R.id.logoImage)
        val progressBar = findViewById<ProgressBar>(R.id.progressBar)
        
        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                // Hide logo and progress bar when page is loaded
                logoImage.visibility = View.GONE
                progressBar.visibility = View.GONE
                // Show the WebView
                webView.visibility = View.VISIBLE
            }
            
            override fun onPageStarted(view: WebView?, url: String?, favicon: android.graphics.Bitmap?) {
                super.onPageStarted(view, url, favicon)
                // Show logo and progress bar when page starts loading
                logoImage.visibility = View.VISIBLE
                progressBar.visibility = View.VISIBLE
                // Hide the WebView
                webView.visibility = View.GONE
            }
        }
        
        webView.loadUrl("https://bsdosale.com/")
    }
    
    override fun onBackPressed() {
        val webView = findViewById<WebView>(R.id.webView)
        if (webView.canGoBack()) {
            webView.goBack()
        } else {
            super.onBackPressed()
        }
    }
}