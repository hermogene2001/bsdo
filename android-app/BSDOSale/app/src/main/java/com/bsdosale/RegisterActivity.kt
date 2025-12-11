package com.bsdosale

import android.content.Intent
import android.os.Bundle
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.textfield.TextInputEditText

class RegisterActivity : AppCompatActivity() {
    
    private lateinit var rgRole: RadioGroup
    private lateinit var rbClient: RadioButton
    private lateinit var rbSeller: RadioButton
    private lateinit var tilClientName: com.google.android.material.textfield.TextInputLayout
    private lateinit var etClientName: TextInputEditText
    private lateinit var tilSellerName: com.google.android.material.textfield.TextInputLayout
    private lateinit var etSellerName: TextInputEditText
    private lateinit var tilSellerCode: com.google.android.material.textfield.TextInputLayout
    private lateinit var etSellerCode: TextInputEditText
    private lateinit var etEmail: TextInputEditText
    private lateinit var etPassword: TextInputEditText
    private lateinit var etConfirmPassword: TextInputEditText
    private lateinit var btnRegister: Button
    private lateinit var tvLogin: TextView
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_register)
        
        initViews()
        setClickListeners()
        setupRoleSelection()
    }
    
    private fun initViews() {
        rgRole = findViewById(R.id.rgRole)
        rbClient = findViewById(R.id.rbClient)
        rbSeller = findViewById(R.id.rbSeller)
        tilClientName = findViewById(R.id.tilClientName)
        etClientName = findViewById(R.id.etClientName)
        tilSellerName = findViewById(R.id.tilSellerName)
        etSellerName = findViewById(R.id.etSellerName)
        tilSellerCode = findViewById(R.id.tilSellerCode)
        etSellerCode = findViewById(R.id.etSellerCode)
        etEmail = findViewById(R.id.etEmail)
        etPassword = findViewById(R.id.etPassword)
        etConfirmPassword = findViewById(R.id.etConfirmPassword)
        btnRegister = findViewById(R.id.btnRegister)
        tvLogin = findViewById(R.id.tvLogin)
    }
    
    private fun setClickListeners() {
        btnRegister.setOnClickListener {
            register()
        }
        
        tvLogin.setOnClickListener {
            startActivity(Intent(this, LoginActivity::class.java))
            finish()
        }
    }
    
    private fun setupRoleSelection() {
        rgRole.setOnCheckedChangeListener { _, checkedId ->
            when (checkedId) {
                R.id.rbClient -> {
                    tilClientName.visibility = android.view.View.VISIBLE
                    tilSellerName.visibility = android.view.View.GONE
                    tilSellerCode.visibility = android.view.View.GONE
                }
                R.id.rbSeller -> {
                    tilClientName.visibility = android.view.View.GONE
                    tilSellerName.visibility = android.view.View.VISIBLE
                    tilSellerCode.visibility = android.view.View.VISIBLE
                }
            }
        }
        
        // Initially hide seller fields
        tilSellerName.visibility = android.view.View.GONE
        tilSellerCode.visibility = android.view.View.GONE
    }
    
    private fun register() {
        val selectedRoleId = rgRole.checkedRadioButtonId
        
        // Validate inputs
        if (selectedRoleId == -1) {
            Toast.makeText(this, "Please select account type", Toast.LENGTH_SHORT).show()
            return
        }
        
        val email = etEmail.text.toString().trim()
        val password = etPassword.text.toString().trim()
        val confirmPassword = etConfirmPassword.text.toString().trim()
        
        if (email.isEmpty()) {
            etEmail.error = "Email is required"
            etEmail.requestFocus()
            return
        }
        
        if (password.isEmpty()) {
            etPassword.error = "Password is required"
            etPassword.requestFocus()
            return
        }
        
        if (password.length < 8) {
            etPassword.error = "Password must be at least 8 characters"
            etPassword.requestFocus()
            return
        }
        
        if (confirmPassword != password) {
            etConfirmPassword.error = "Passwords do not match"
            etConfirmPassword.requestFocus()
            return
        }
        
        when (selectedRoleId) {
            R.id.rbClient -> {
                val clientName = etClientName.text.toString().trim()
                if (clientName.isEmpty()) {
                    etClientName.error = "Name is required"
                    etClientName.requestFocus()
                    return
                }
            }
            R.id.rbSeller -> {
                val sellerName = etSellerName.text.toString().trim()
                val sellerCode = etSellerCode.text.toString().trim()
                
                if (sellerName.isEmpty()) {
                    etSellerName.error = "Business name is required"
                    etSellerName.requestFocus()
                    return
                }
                
                if (sellerCode.isEmpty()) {
                    etSellerCode.error = "Seller code is required"
                    etSellerCode.requestFocus()
                    return
                }
            }
        }
        
        // TODO: Implement actual registration logic with API call
        // For now, we'll just simulate a successful registration
        Toast.makeText(this, "Registration successful", Toast.LENGTH_SHORT).show()
        
        // Navigate to LoginActivity
        startActivity(Intent(this, LoginActivity::class.java))
        finish()
    }
}