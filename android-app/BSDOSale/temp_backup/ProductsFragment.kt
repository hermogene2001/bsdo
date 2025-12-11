package com.bsdosale

import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.ImageButton
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bsdosale.adapters.ProductAdapter
import com.bsdosale.models.Product

class ProductsFragment : BaseFragment() {
    
    private lateinit var recyclerViewProducts: RecyclerView
    private lateinit var etSearch: EditText
    private lateinit var btnFilter: ImageButton
    private lateinit var btnAllProducts: Button
    private lateinit var btnRegularProducts: Button
    private lateinit var btnRentalProducts: Button
    private lateinit var productAdapter: ProductAdapter
    
    // Sample data - in real app this would come from API
    private val allProducts = listOf(
        Product(1, "Smartphone", "Latest smartphone with advanced features", 599.99, null, "https://example.com/image1.jpg", "regular", 10, 1, "Electronics Store", 1, "Electronics"),
        Product(2, "Laptop", "High-performance laptop for work and gaming", 1299.99, null, "https://example.com/image2.jpg", "regular", 5, 2, "Tech Store", 1, "Electronics"),
        Product(3, "Camera", "Professional DSLR camera with lens", 899.99, 29.99, "https://example.com/image3.jpg", "rental", 3, 3, "Photo Gear", 1, "Electronics"),
        Product(4, "Drone", "Quadcopter drone with HD camera", 499.99, 49.99, "https://example.com/image4.jpg", "rental", 2, 1, "Electronics Store", 1, "Electronics"),
        Product(5, "Construction Tools Set", "Complete set of construction tools for professionals", 299.99, 19.99, "https://example.com/image5.jpg", "rental", 5, 4, "Tool Masters", 2, "Tools"),
        Product(6, "Party Sound System", "High-quality sound system for events and parties", 799.99, 59.99, "https://example.com/image6.jpg", "rental", 2, 5, "Audio Experts", 3, "Audio")
    )
    
    private var filteredProducts = allProducts
    
    override fun getLayoutId(): Int {
        return R.layout.fragment_products
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        initViews(view)
        setupRecyclerView()
        setClickListeners()
    }
    
    private fun initViews(view: View) {
        recyclerViewProducts = view.findViewById(R.id.recyclerViewProducts)
        etSearch = view.findViewById(R.id.etSearch)
        btnFilter = view.findViewById(R.id.btnFilter)
        btnAllProducts = view.findViewById(R.id.btnAllProducts)
        btnRegularProducts = view.findViewById(R.id.btnRegularProducts)
        btnRentalProducts = view.findViewById(R.id.btnRentalProducts)
    }
    
    private fun setupRecyclerView() {
        // Setup products recycler view with grid layout
        recyclerViewProducts.layoutManager = GridLayoutManager(context, 2)
        productAdapter = ProductAdapter(filteredProducts) { product ->
            // Handle product click
            // TODO: Navigate to product detail screen
        }
        recyclerViewProducts.adapter = productAdapter
    }
    
    private fun filterProducts(type: String) {
        filteredProducts = when (type) {
            "regular" -> allProducts.filter { it.productType == "regular" }
            "rental" -> allProducts.filter { it.productType == "rental" }
            else -> allProducts
        }
        
        productAdapter = ProductAdapter(filteredProducts) { product ->
            // Handle product click
            // TODO: Navigate to product detail screen
        }
        recyclerViewProducts.adapter = productAdapter
    }
    
    private fun setClickListeners() {
        btnFilter.setOnClickListener {
            // Handle filter click
            // TODO: Show filter dialog
        }
        
        btnAllProducts.setOnClickListener {
            // Show all products
            btnAllProducts.isSelected = true
            btnRegularProducts.isSelected = false
            btnRentalProducts.isSelected = false
            filterProducts("all")
        }
        
        btnRegularProducts.setOnClickListener {
            // Show regular products
            btnAllProducts.isSelected = false
            btnRegularProducts.isSelected = true
            btnRentalProducts.isSelected = false
            filterProducts("regular")
        }
        
        btnRentalProducts.setOnClickListener {
            // Show rental products
            btnAllProducts.isSelected = false
            btnRegularProducts.isSelected = false
            btnRentalProducts.isSelected = true
            filterProducts("rental")
        }
        
        etSearch.setOnEditorActionListener { _, _, _ ->
            // Handle search
            val query = etSearch.text.toString().trim()
            if (query.isNotEmpty()) {
                filteredProducts = allProducts.filter { 
                    it.name.contains(query, ignoreCase = true) || 
                    it.description.contains(query, ignoreCase = true) ||
                    it.sellerName.contains(query, ignoreCase = true)
                }
                productAdapter = ProductAdapter(filteredProducts) { product ->
                    // Handle product click
                    // TODO: Navigate to product detail screen
                }
                recyclerViewProducts.adapter = productAdapter
            } else {
                filterProducts("all")
            }
            true
        }
    }
}