package com.bsdosale.adapters

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.bsdosale.R
import com.bsdosale.models.Product
import com.bumptech.glide.Glide

class ProductAdapter(
    private val products: List<Product>,
    private val onItemClick: (Product) -> Unit
) : RecyclerView.Adapter<ProductAdapter.ProductViewHolder>() {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ProductViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_product, parent, false)
        return ProductViewHolder(view)
    }

    override fun onBindViewHolder(holder: ProductViewHolder, position: Int) {
        holder.bind(products[position])
    }

    override fun getItemCount(): Int = products.size

    inner class ProductViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val imageView: ImageView = itemView.findViewById(R.id.imageViewProduct)
        private val textViewName: TextView = itemView.findViewById(R.id.textViewProductName)
        private val textViewPrice: TextView = itemView.findViewById(R.id.textViewProductPrice)
        private val textViewRentalPrice: TextView = itemView.findViewById(R.id.textViewRentalPrice)
        private val textViewType: TextView = itemView.findViewById(R.id.textViewProductType)
        private val textViewStock: TextView = itemView.findViewById(R.id.textViewStock)

        fun bind(product: Product) {
            // Load image
            Glide.with(itemView.context)
                .load(product.imageUrl)
                .placeholder(R.drawable.ic_app_logo)
                .into(imageView)

            textViewName.text = product.name
            
            // Show price based on product type
            if (product.productType == "rental" && product.rentalPrice != null) {
                textViewPrice.text = "$${String.format("%.2f", product.rentalPrice)}/day"
                textViewRentalPrice.visibility = View.VISIBLE
                textViewRentalPrice.text = "Buy: $${String.format("%.2f", product.price)}"
            } else {
                textViewPrice.text = "$${String.format("%.2f", product.price)}"
                textViewRentalPrice.visibility = View.GONE
            }
            
            // Set product type badge
            when (product.productType) {
                "rental" -> {
                    textViewType.text = "RENTAL"
                    textViewType.setBackgroundResource(R.drawable.rental_badge)
                }
                else -> {
                    textViewType.text = "BUY"
                    textViewType.setBackgroundResource(R.drawable.buy_badge)
                }
            }
            
            textViewStock.text = "Stock: ${product.stock}"

            // Set click listener
            itemView.setOnClickListener {
                onItemClick(product)
            }
        }
    }
}