package com.bsdosale

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.google.android.material.textfield.TextInputEditText

class AddProductActivity : AppCompatActivity() {
    
    private lateinit var imageViewProduct: ImageView
    private lateinit var buttonSelectImage: Button
    private lateinit var editTextProductName: TextInputEditText
    private lateinit var editTextProductDescription: TextInputEditText
    private lateinit var editTextProductPrice: TextInputEditText
    private lateinit var editTextProductQuantity: TextInputEditText
    private lateinit var checkBoxRental: CheckBox
    private lateinit var layoutRentalOptions: LinearLayout
    private lateinit var editTextDailyRate: TextInputEditText
    private lateinit var editTextWeeklyRate: TextInputEditText
    private lateinit var buttonSaveProduct: Button
    
    private val PERMISSION_REQUEST_CODE = 1002
    private val PICK_IMAGE_REQUEST = 1003
    
    private var selectedImageUri: Uri? = null
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_add_product)
        
        initViews()
        setClickListeners()
    }
    
    private fun initViews() {
        imageViewProduct = findViewById(R.id.imageViewProduct)
        buttonSelectImage = findViewById(R.id.buttonSelectImage)
        editTextProductName = findViewById(R.id.editTextProductName)
        editTextProductDescription = findViewById(R.id.editTextProductDescription)
        editTextProductPrice = findViewById(R.id.editTextProductPrice)
        editTextProductQuantity = findViewById(R.id.editTextProductQuantity)
        checkBoxRental = findViewById(R.id.checkBoxRental)
        layoutRentalOptions = findViewById(R.id.layoutRentalOptions)
        editTextDailyRate = findViewById(R.id.editTextDailyRate)
        editTextWeeklyRate = findViewById(R.id.editTextWeeklyRate)
        buttonSaveProduct = findViewById(R.id.buttonSaveProduct)
    }
    
    private fun setClickListeners() {
        buttonSelectImage.setOnClickListener {
            selectImage()
        }
        
        checkBoxRental.setOnCheckedChangeListener { _, isChecked ->
            layoutRentalOptions.visibility = if (isChecked) View.VISIBLE else View.GONE
        }
        
        buttonSaveProduct.setOnClickListener {
            saveProduct()
        }
    }
    
    private fun selectImage() {
        if (checkPermissions()) {
            openImagePicker()
        } else {
            requestPermissions()
        }
    }
    
    private fun checkPermissions(): Boolean {
        return ContextCompat.checkSelfPermission(
            this, 
            Manifest.permission.READ_EXTERNAL_STORAGE
        ) == PackageManager.PERMISSION_GRANTED
    }
    
    private fun requestPermissions() {
        ActivityCompat.requestPermissions(
            this,
            arrayOf(Manifest.permission.READ_EXTERNAL_STORAGE),
            PERMISSION_REQUEST_CODE
        )
    }
    
    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        
        if (requestCode == PERMISSION_REQUEST_CODE) {
            if (grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                openImagePicker()
            } else {
                Toast.makeText(this, "Permission required to select images", Toast.LENGTH_LONG).show()
            }
        }
    }
    
    private fun openImagePicker() {
        val intent = Intent(Intent.ACTION_PICK)
        intent.type = "image/*"
        startActivityForResult(intent, PICK_IMAGE_REQUEST)
    }
    
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        
        if (requestCode == PICK_IMAGE_REQUEST && resultCode == RESULT_OK && data != null) {
            selectedImageUri = data.data
            imageViewProduct.setImageURI(selectedImageUri)
        }
    }
    
    private fun saveProduct() {
        val name = editTextProductName.text.toString().trim()
        val description = editTextProductDescription.text.toString().trim()
        val priceStr = editTextProductPrice.text.toString().trim()
        val quantityStr = editTextProductQuantity.text.toString().trim()
        val isRental = checkBoxRental.isChecked
        
        // Validate inputs
        if (name.isEmpty()) {
            editTextProductName.error = "Product name is required"
            editTextProductName.requestFocus()
            return
        }
        
        if (description.isEmpty()) {
            editTextProductDescription.error = "Product description is required"
            editTextProductDescription.requestFocus()
            return
        }
        
        if (priceStr.isEmpty()) {
            editTextProductPrice.error = "Price is required"
            editTextProductPrice.requestFocus()
            return
        }
        
        val price = try {
            priceStr.toDouble()
        } catch (e: NumberFormatException) {
            editTextProductPrice.error = "Invalid price"
            editTextProductPrice.requestFocus()
            return
        }
        
        if (isRental) {
            val dailyRateStr = editTextDailyRate.text.toString().trim()
            val weeklyRateStr = editTextWeeklyRate.text.toString().trim()
            
            if (dailyRateStr.isEmpty() && weeklyRateStr.isEmpty()) {
                editTextDailyRate.error = "At least one rate is required for rental products"
                editTextDailyRate.requestFocus()
                return
            }
            
            if (dailyRateStr.isNotEmpty()) {
                try {
                    dailyRateStr.toDouble()
                } catch (e: NumberFormatException) {
                    editTextDailyRate.error = "Invalid daily rate"
                    editTextDailyRate.requestFocus()
                    return
                }
            }
            
            if (weeklyRateStr.isNotEmpty()) {
                try {
                    weeklyRateStr.toDouble()
                } catch (e: NumberFormatException) {
                    editTextWeeklyRate.error = "Invalid weekly rate"
                    editTextWeeklyRate.requestFocus()
                    return
                }
            }
        }
        
        if (quantityStr.isEmpty()) {
            editTextProductQuantity.error = "Quantity is required"
            editTextProductQuantity.requestFocus()
            return
        }
        
        val quantity = try {
            quantityStr.toInt()
        } catch (e: NumberFormatException) {
            editTextProductQuantity.error = "Invalid quantity"
            editTextProductQuantity.requestFocus()
            return
        }
        
        // In a real app, you would:
        // 1. Upload image to server
        // 2. Send product data to server
        // 3. Handle response
        
        Toast.makeText(this, "Product saved successfully", Toast.LENGTH_SHORT).show()
        finish()
    }
}