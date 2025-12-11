package com.bsdosale.adapters

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.bsdosale.R
import com.bsdosale.models.LiveStream
import com.bumptech.glide.Glide

class LiveStreamAdapter(
    private val liveStreams: List<LiveStream>,
    private val onItemClick: (LiveStream) -> Unit
) : RecyclerView.Adapter<LiveStreamAdapter.LiveStreamViewHolder>() {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): LiveStreamViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_live_stream, parent, false)
        return LiveStreamViewHolder(view)
    }

    override fun onBindViewHolder(holder: LiveStreamViewHolder, position: Int) {
        holder.bind(liveStreams[position])
    }

    override fun getItemCount(): Int = liveStreams.size

    inner class LiveStreamViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val imageViewThumbnail: ImageView = itemView.findViewById(R.id.imageViewThumbnail)
        private val textViewTitle: TextView = itemView.findViewById(R.id.textViewTitle)
        private val textViewDescription: TextView = itemView.findViewById(R.id.textViewDescription)
        private val textViewStatus: TextView = itemView.findViewById(R.id.textViewStatus)
        private val textViewViewerCount: TextView = itemView.findViewById(R.id.textViewViewerCount)
        private val textViewScheduledTime: TextView = itemView.findViewById(R.id.textViewScheduledTime)

        fun bind(liveStream: LiveStream) {
            // Load thumbnail
            Glide.with(itemView.context)
                .load(liveStream.thumbnailUrl)
                .placeholder(R.drawable.ic_app_logo)
                .into(imageViewThumbnail)

            textViewTitle.text = liveStream.title
            textViewDescription.text = liveStream.description
            
            // Set status and viewer count
            if (liveStream.isLive) {
                textViewStatus.text = "LIVE"
                textViewStatus.setBackgroundResource(R.drawable.live_badge)
                textViewStatus.visibility = View.VISIBLE
                textViewViewerCount.text = "${liveStream.viewerCount} viewers"
                textViewViewerCount.visibility = View.VISIBLE
                textViewScheduledTime.visibility = View.GONE
            } else {
                textViewStatus.visibility = View.GONE
                textViewViewerCount.visibility = View.GONE
                textViewScheduledTime.visibility = View.VISIBLE
                
                if (liveStream.scheduledAt != null) {
                    textViewScheduledTime.text = "Scheduled for ${liveStream.scheduledAt}"
                } else {
                    textViewScheduledTime.text = "Scheduled"
                }
            }

            // Set click listener
            itemView.setOnClickListener {
                onItemClick(liveStream)
            }
        }
    }
}