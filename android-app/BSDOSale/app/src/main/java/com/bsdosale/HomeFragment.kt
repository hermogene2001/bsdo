package com.bsdosale

import android.os.Bundle
import android.view.View
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView

class HomeFragment : BaseFragment() {
    
    private lateinit var recyclerViewFeaturedProducts: RecyclerView
    private lateinit var recyclerViewLiveStreams: RecyclerView
    
    override fun getLayoutId(): Int {
        return R.layout.fragment_home
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        initViews(view)
        setupRecyclerViews()
    }
    
    private fun initViews(view: View) {
        recyclerViewFeaturedProducts = view.findViewById(R.id.recyclerViewFeaturedProducts)
        recyclerViewLiveStreams = view.findViewById(R.id.recyclerViewLiveStreams)
    }
    
    private fun setupRecyclerViews() {
        // Setup featured products recycler view
        recyclerViewFeaturedProducts.layoutManager = LinearLayoutManager(
            context, 
            LinearLayoutManager.HORIZONTAL, 
            false
        )
        // TODO: Set adapter for featured products
        
        // Setup live streams recycler view
        recyclerViewLiveStreams.layoutManager = LinearLayoutManager(
            context,
            LinearLayoutManager.HORIZONTAL,
            false
        )
        // TODO: Set adapter for live streams
    }
}