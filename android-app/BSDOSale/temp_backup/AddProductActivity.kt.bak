package com.bsdosale

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.widget.*
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat

class AddProductActivity : AppCompatActivity() {
    
    private lateinit var imageViewProduct: ImageView
    private lateinit var buttonSelectImage: Button
    private lateinit var editTextName: EditText
    private lateinit var editTextDescription: EditText
    private lateinit var editTextPrice: EditText
    private lateinit var editTextRentalPrice: EditText
    private lateinit var editTextStock: EditText
    private lateinit var spinnerCategory: Spinner
    private lateinit var radioGroupProductType: RadioGroup
    private lateinit var checkBoxRental: CheckBox
    private lateinit var buttonSave: Button
    private lateinit var buttonCancel: Button
    
    private val PERMISSION_REQUEST_CODE = 1002
    private val PICK_IMAGE_REQUEST = 1003
    
    private var selectedImageUri: Uri? = null
    
    private val categories = arrayOf(
        "Electronics",
        "Fashion",
        "Home & Garden",
        "Sports & Outdoors",
        "Books",
        "Toys & Games",
        "Health & Beauty",
        "Automotive",
        "Other"
    )
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_add_product)
        
        initViews()
        setupSpinner()
        setClickListeners()
    }
    
    private fun initViews() {
        imageViewProduct = findViewById(R.id.imageViewProduct)
        buttonSelectImage = findViewById(R.id.buttonSelectImage)
        editTextName = findViewById(R.id.editTextName)
        editTextDescription = findViewById(R.id.editTextDescription)
        editTextPrice = findViewById(R.id.editTextPrice)
        editTextRentalPrice = findViewById(R.id.editTextRentalPrice)
        editTextStock = findViewById(R.id.editTextStock)
        spinnerCategory = findViewById(R.id.spinnerCategory)
        radioGroupProductType = findViewById(R.id.radioGroupProductType)
        checkBoxRental = findViewById(R.id.checkBoxRental)
        buttonSave = findViewById(R.id.buttonSave)
        buttonCancel = findViewById(R.id.buttonCancel)
    }
    
    private fun setupSpinner() {
        val adapter = ArrayAdapter(this, android.R.layout.simple_spinner_item, categories)
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item)
        spinnerCategory.adapter = adapter
    }
    
    private fun setClickListeners() {
        buttonSelectImage.setOnClickListener {
            selectImage()
        }
        
        checkBoxRental.setOnCheckedChangeListener { _, isChecked ->
            editTextRentalPrice.isEnabled = isChecked
        }
        
        buttonSave.setOnClickListener {
            saveProduct()
        }
        
        buttonCancel.setOnClickListener {
            finish()
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
        val name = editTextName.text.toString().trim()
        val description = editTextDescription.text.toString().trim()
        val priceStr = editTextPrice.text.toString().trim()
        val rentalPriceStr = editTextRentalPrice.text.toString().trim()
        val stockStr = editTextStock.text.toString().trim()
        val category = spinnerCategory.selectedItem.toString()
        val isRental = checkBoxRental.isChecked
        
        // Validate inputs
        if (name.isEmpty()) {
            editTextName.error = "Product name is required"
            editTextName.requestFocus()
            return
        }
        
        if (description.isEmpty()) {
            editTextDescription.error = "Product description is required"
            editTextDescription.requestFocus()
            return
        }
        
        if (priceStr.isEmpty()) {
            editTextPrice.error = "Price is required"
            editTextPrice.requestFocus()
            return
        }
        
        val price = try {
            priceStr.toDouble()
        } catch (e: NumberFormatException) {
            editTextPrice.error = "Invalid price"
            editTextPrice.requestFocus()
            return
        }
        
        var rentalPrice: Double? = null
        if (isRental) {
            if (rentalPriceStr.isEmpty()) {
                editTextRentalPrice.error = "Rental price is required for rental products"
                editTextRentalPrice.requestFocus()
                return
            }
            
            rentalPrice = try {
                rentalPriceStr.toDouble()
            } catch (e: NumberFormatException) {
                editTextRentalPrice.error = "Invalid rental price"
                editTextRentalPrice.requestFocus()
                return
            }
        }
        
        if (stockStr.isEmpty()) {
            editTextStock.error = "Stock quantity is required"
            editTextStock.requestFocus()
            return
        }
        
        val stock = try {
            stockStr.toInt()
        } catch (e: NumberFormatException) {
            editTextStock.error = "Invalid stock quantity"
            editTextStock.requestFocus()
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