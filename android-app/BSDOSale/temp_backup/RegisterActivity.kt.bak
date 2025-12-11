package com.bsdosale

import android.content.Intent
import android.os.Bundle
import android.widget.*
import androidx.appcompat.app.AppCompatActivity

class RegisterActivity : AppCompatActivity() {
    
    private lateinit var etFirstName: EditText
    private lateinit var etLastName: EditText
    private lateinit var etEmail: EditText
    private lateinit var etPhone: EditText
    private lateinit var etStoreName: EditText
    private lateinit var spinnerBusinessType: Spinner
    private lateinit var etReferralCode: EditText
    private lateinit var etPassword: EditText
    private lateinit var etConfirmPassword: EditText
    private lateinit var radioGroupRole: RadioGroup
    private lateinit var btnRegister: Button
    private lateinit var tvLogin: TextView
    
    private val businessTypes = arrayOf(
        "Select Business Type",
        "Retail",
        "Wholesale",
        "Manufacturer",
        "Service Provider"
    )
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_register)
        
        initViews()
        setupSpinner()
        setClickListeners()
    }
    
    private fun initViews() {
        etFirstName = findViewById(R.id.etFirstName)
        etLastName = findViewById(R.id.etLastName)
        etEmail = findViewById(R.id.etEmail)
        etPhone = findViewById(R.id.etPhone)
        etStoreName = findViewById(R.id.etStoreName)
        spinnerBusinessType = findViewById(R.id.spinnerBusinessType)
        etReferralCode = findViewById(R.id.etReferralCode)
        etPassword = findViewById(R.id.etPassword)
        etConfirmPassword = findViewById(R.id.etConfirmPassword)
        radioGroupRole = findViewById(R.id.radioGroupRole)
        btnRegister = findViewById(R.id.btnRegister)
        tvLogin = findViewById(R.id.tvLogin)
    }
    
    private fun setupSpinner() {
        val adapter = ArrayAdapter(this, android.R.layout.simple_spinner_item, businessTypes)
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        spinnerBusinessType.adapter = adapter
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
    
    private fun register() {
        val firstName = etFirstName.text.toString().trim()
        val lastName = etLastName.text.toString().trim()
        val email = etEmail.text.toString().trim()
        val phone = etPhone.text.toString().trim()
        val storeName = etStoreName.text.toString().trim()
        val businessType = spinnerBusinessType.selectedItem.toString()
        val referralCode = etReferralCode.text.toString().trim()
        val password = etPassword.text.toString().trim()
        val confirmPassword = etConfirmPassword.text.toString().trim()
        val selectedRoleId = radioGroupRole.checkedRadioButtonId
        
        // Validate inputs
        if (firstName.isEmpty()) {
            etFirstName.error = "First name is required"
            etFirstName.requestFocus()
            return
        }
        
        if (lastName.isEmpty()) {
            etLastName.error = "Last name is required"
            etLastName.requestFocus()
            return
        }
        
        if (email.isEmpty()) {
            etEmail.error = "Email is required"
            etEmail.requestFocus()
            return
        }
        
        if (phone.isEmpty()) {
            etPhone.error = "Phone is required"
            etPhone.requestFocus()
            return
        }
        
        if (selectedRoleId == -1) {
            Toast.makeText(this, "Please select account type", Toast.LENGTH_SHORT).show()
            return
        }
        
        val role = when (selectedRoleId) {
            R.id.radioClient -> "client"
            R.id.radioSeller -> "seller"
            else -> ""
        }
        
        if (role == "seller") {
            if (storeName.isEmpty()) {
                etStoreName.error = "Store name is required for sellers"
                etStoreName.requestFocus()
                return
            }
            
            if (businessType == "Select Business Type") {
                Toast.makeText(this, "Please select business type", Toast.LENGTH_SHORT).show()
                return
            }
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
        
        // TODO: Implement actual registration logic with API call
        // For now, we'll just simulate a successful registration
        Toast.makeText(this, "Registration successful", Toast.LENGTH_SHORT).show()
        
        // Navigate to LoginActivity
        startActivity(Intent(this, LoginActivity::class.java))
        finish()
    }
}