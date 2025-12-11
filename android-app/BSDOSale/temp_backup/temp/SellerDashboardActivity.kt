package com.bsdosale

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

class SellerDashboardActivity : AppCompatActivity() {
    
    private lateinit var textViewWelcome: TextView
    private lateinit var buttonAddProduct: Button
    private lateinit var buttonMyProducts: Button
    private lateinit var buttonLiveStream: Button
    private lateinit var buttonOrders: Button
    private lateinit var buttonAnalytics: Button
    private lateinit var buttonProfile: Button
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_seller_dashboard)
        
        initViews()
        setClickListeners()
        
        // In a real app, you would get seller info from session
        val sellerName = "John Seller"
        textViewWelcome.text = "Welcome, $sellerName!"
    }
    
    private fun initViews() {
        textViewWelcome = findViewById(R.id.textViewWelcome)
        buttonAddProduct = findViewById(R.id.buttonAddProduct)
        buttonMyProducts = findViewById(R.id.buttonMyProducts)
        buttonLiveStream = findViewById(R.id.buttonLiveStream)
        buttonOrders = findViewById(R.id.buttonOrders)
        buttonAnalytics = findViewById(R.id.buttonAnalytics)
        buttonProfile = findViewById(R.id.buttonProfile)
    }
    
    private fun setClickListeners() {
        buttonAddProduct.setOnClickListener {
            // TODO: Navigate to add product screen
        }
        
        buttonMyProducts.setOnClickListener {
            // TODO: Navigate to my products screen
        }
        
        buttonLiveStream.setOnClickListener {
            startActivity(Intent(this, LiveStreamActivity::class.java))
        }
        
        buttonOrders.setOnClickListener {
            // TODO: Navigate to orders screen
        }
        
        buttonAnalytics.setOnClickListener {
            // TODO: Navigate to analytics screen
        }
        
        buttonProfile.setOnClickListener {
            // TODO: Navigate to profile screen
        }
    }
}