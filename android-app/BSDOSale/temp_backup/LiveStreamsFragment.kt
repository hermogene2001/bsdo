package com.bsdosale

import android.os.Bundle
import android.view.View
import android.widget.Button
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.bsdosale.adapters.LiveStreamAdapter
import com.bsdosale.models.LiveStream

class LiveStreamsFragment : BaseFragment() {
    
    private lateinit var recyclerViewLiveStreams: RecyclerView
    private lateinit var btnAllStreams: Button
    private lateinit var btnLiveStreams: Button
    private lateinit var btnUpcomingStreams: Button
    private lateinit var liveStreamAdapter: LiveStreamAdapter
    
    // Sample data - in real app this would come from API
    private val liveStreams = listOf(
        LiveStream(1, 1, "Electronics Showcase", "Live demonstration of latest electronics", 1, "https://example.com/stream1.jpg", true, 15, "live", null),
        LiveStream(2, 2, "Fashion Live Sale", "Exclusive fashion items with live discounts", 2, "https://example.com/stream2.jpg", true, 22, "live", null),
        LiveStream(3, 3, "Home & Garden Tips", "Live tips for home improvement", 3, "https://example.com/stream3.jpg", false, 0, "scheduled", "2023-06-15 14:00:00"),
        LiveStream(4, 1, "Cooking Demonstration", "Learn to cook traditional dishes", 4, "https://example.com/stream4.jpg", false, 0, "scheduled", "2023-06-16 18:00:00")
    )
    
    override fun getLayoutId(): Int {
        return R.layout.fragment_live_streams
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        initViews(view)
        setupRecyclerView()
        setClickListeners()
    }
    
    private fun initViews(view: View) {
        recyclerViewLiveStreams = view.findViewById(R.id.recyclerViewLiveStreams)
        btnAllStreams = view.findViewById(R.id.btnAllStreams)
        btnLiveStreams = view.findViewById(R.id.btnLiveStreams)
        btnUpcomingStreams = view.findViewById(R.id.btnUpcomingStreams)
    }
    
    private fun setupRecyclerView() {
        // Setup live streams recycler view
        recyclerViewLiveStreams.layoutManager = LinearLayoutManager(context)
        liveStreamAdapter = LiveStreamAdapter(liveStreams) { liveStream ->
            // Handle live stream click
            // TODO: Navigate to watch stream screen
        }
        recyclerViewLiveStreams.adapter = liveStreamAdapter
    }
    
    private fun setClickListeners() {
        btnAllStreams.setOnClickListener {
            // Show all streams
            btnAllStreams.isSelected = true
            btnLiveStreams.isSelected = false
            btnUpcomingStreams.isSelected = false
        }
        
        btnLiveStreams.setOnClickListener {
            // Show live streams only
            btnAllStreams.isSelected = false
            btnLiveStreams.isSelected = true
            btnUpcomingStreams.isSelected = false
        }
        
        btnUpcomingStreams.setOnClickListener {
            // Show upcoming streams only
            btnAllStreams.isSelected = false
            btnLiveStreams.isSelected = false
            btnUpcomingStreams.isSelected = true
        }
    }
}