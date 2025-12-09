package com.bsdosale.models

data class Product(
    val id: Int,
    val name: String,
    val description: String,
    val price: Double,
    val rentalPrice: Double?, // For rental products
    val imageUrl: String,
    val productType: String, // "regular" or "rental"
    val stock: Int,
    val sellerId: Int,
    val sellerName: String,
    val categoryId: Int,
    val categoryName: String
)